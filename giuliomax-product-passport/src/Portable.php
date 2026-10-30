<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Portable
{
    public static function export(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Abilita l’estensione PHP zip per creare archivi portabili.');
        }
        global $wpdb;
        $archive = ['format' => 'gmpp-portable','version' => 1,'created_at' => gmdate('c'),'origin' => home_url('/'),'settings' => Store::settings(),'passports' => $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', Store::table('passports')), ARRAY_A),'revisions' => $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', Store::table('revisions')), ARRAY_A),'documents' => $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', Store::table('documents')), ARRAY_A),'versions' => []];
        $tmp = wp_tempnam('gmpp-export');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $checks = [];
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', Store::table('document_versions')), ARRAY_A) as $v) {
            $name = 'documents/' . $v['id'] . '.bin';
            $zip->addFromString($name, $v['content']);
            $checks[$name] = hash('sha256', $v['content']);
            unset($v['content']);
            $v['entry'] = $name;
            $archive['versions'][] = $v;
        }
        $data = Store::json($archive);
        $zip->addFromString('data.json', $data);
        $checks['data.json'] = hash('sha256', $data);
        $zip->addFromString('manifest.json', Store::json(['format' => 1,'sha256' => $checks]));
        $zip->close();
        return $tmp;
    }
    public static function restore(string $base64, bool $confirm): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Abilita l’estensione PHP zip.');
        }
        $bytes = base64_decode($base64, true);
        if (!$bytes || strlen($bytes) > 50 * 1024 * 1024) {
            throw new \InvalidArgumentException('Archivio non valido o superiore a 50 MB.');
        }
        $tmp = wp_tempnam('gmpp-restore');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive();
        $opened = false;
        try {
            if ($zip->open($tmp) !== true) {
                throw new \InvalidArgumentException('ZIP non valido.');
            }
            $opened = true;
            $total = 0;
            if ($zip->numFiles > 5000) {
                throw new \InvalidArgumentException('Troppi elementi nell’archivio.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i);
                $total += $s['size'];
                if (!preg_match('~^(manifest\.json|data\.json|documents/[0-9]+\.bin)$~D', $s['name']) || $s['size'] > 20 * 1024 * 1024 || $total > 100 * 1024 * 1024) {
                    throw new \InvalidArgumentException('Archivio non sicuro: percorsi o dimensioni non validi.');
                }
            }
            $manifest = Store::decode($zip->getFromName('manifest.json') ?: '');
            if (($manifest['format'] ?? 0) !== 1 || empty($manifest['sha256']['data.json'])) {
                throw new \InvalidArgumentException('Manifest non valido.');
            }
            foreach ($manifest['sha256'] as $path => $digest) {
                $content = $zip->getFromName($path);
                if ($content === false || !hash_equals($digest, hash('sha256', $content))) {
                    throw new \InvalidArgumentException('Integrità non verificata: ' . $path);
                }
            }
            $data = Store::decode($zip->getFromName('data.json'));
            if (($data['format'] ?? '') !== 'gmpp-portable' || ($data['version'] ?? 0) !== 1) {
                throw new \InvalidArgumentException('Versione del formato non supportata.');
            }
            if (!$confirm) {
                return ['preview' => true,'passports' => count($data['passports']),'documents' => count($data['documents']),'revisions' => count($data['revisions']),'origin' => $data['origin'],'message' => 'Le identità esistenti vengono conservate senza sovrascrittura; i nuovi dati vengono importati.'];
            }
            global $wpdb;
            Store::begin();
            try {
                $docs = [];
                $versions = [];
                $ids = [];
                $new = [];
                $new_docs = [];
                foreach ($data['documents'] as $d) {
                    if (!wp_is_uuid($d['uuid'])) {
                        throw new \InvalidArgumentException('Identità documento non valida.');
                    }
                    $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE uuid=%s', Store::table('documents'), $d['uuid']));
                    $docs[$d['id']] = $existing ? (int)$existing : Store::insert('documents', ['uuid' => $d['uuid'],'title' => sanitize_text_field($d['title']),'owner' => get_current_user_id()]);
                    if (!$existing) {
                        $new_docs[$d['id']] = true;
                    }
                }
                foreach ($data['versions'] as $v) {
                    $content = $zip->getFromName($v['entry']);
                    if (!isset($manifest['sha256'][$v['entry']]) || $content === false || !hash_equals($v['digest'], hash('sha256', $content))) {
                        throw new \InvalidArgumentException('Documento non verificato.');
                    }
                    $meta = Store::decode($v['metadata']);
                    $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE document_id=%d AND digest=%s AND metadata=%s', Store::table('document_versions'), $docs[$v['document_id']], $v['digest'], $v['metadata']));
                    $document = Store::one('documents', $docs[$v['document_id']]);
                    $old_current = $document['current_version'];
                    $created = $existing ? Documents::version((int)$existing) : Documents::store_bytes($content, $meta['filename'], array_replace($meta, ['document_id' => $docs[$v['document_id']]]))['document'];
                    if (!isset($new_docs[$v['document_id']])) {
                        $wpdb->update(Store::table('documents'), ['current_version' => $old_current,'title' => $document['title']], ['id' => $docs[$v['document_id']]]);
                    }
                    $versions[$v['id']] = $created['version_id'];
                }
                foreach ($data['documents'] as $d) {
                    if (isset($new_docs[$d['id']], $versions[$d['current_version']])) {
                        $wpdb->update(Store::table('documents'), ['current_version' => $versions[$d['current_version']]], ['id' => $docs[$d['id']]]);
                    }
                }
                foreach ($data['passports'] as $p) {
                    $existing = Store::uuid($p['uuid']);
                    if ($existing) {
                        $ids[$p['id']] = (int)$existing['id'];
                        continue;
                    }
                    $draft = Passport::clean(Store::decode($p['data']));
                    if (array_key_exists('documents', $draft)) {
                        $draft['documents'] = array_values(array_filter(array_map(static fn($id)=>$docs[$id] ?? 0, $draft['documents'])));
                    } unset($draft['compatible']);
                    // Export order is parent-first because relationships can only refer to older records.
                    $created = Passport::create(['data' => $draft,'kind' => $p['kind'],'parent_id' => $ids[$p['parent_id']] ?? 0], $p['uuid']);
                    $ids[$p['id']] = (int)$created['id'];
                    $new[$p['id']] = $p;
                }
                foreach ($new as $old => $p) {
                    $draft = Store::decode($p['data']);
                    $created = Passport::get($ids[$old]);
                    $compatible = array_values(array_filter(array_map(static fn($id)=>$ids[$id] ?? 0, $draft['compatible'] ?? [])));
                    $saved = Passport::save($ids[$old], array_key_exists('compatible', $draft) ? ['compatible' => $compatible] : [], (int)$created['lock_version']);
                    foreach (['_company_snapshot','_compatible_snapshot'] as $internal) {
                        if (isset($draft[$internal]) && is_array($draft[$internal])) {
                            if ($internal === '_company_snapshot') {
                                $saved['data'][$internal] = array_intersect_key($draft[$internal], array_flip(['company','email','phone','address','role','logo','accent']));
                            } else {
                                $saved['data'][$internal] = array_map(static fn($part)=>['name' => sanitize_text_field($part['name'] ?? ''),'url' => esc_url_raw($part['url'] ?? '', ['http','https'])], $draft[$internal]);
                            }
                        }
                    }
                    $wpdb->update(Store::table('passports'), ['data' => Store::json($saved['data'])], ['id' => $ids[$old]]);
                    if (!empty($draft['_document_pins'])) {
                        $pins = [];
                        foreach ($draft['_document_pins'] as $doc => $version) {
                            if (isset($docs[$doc], $versions[$version])) {
                                $pins[$docs[$doc]] = $versions[$version];
                            }
                        }
                        $saved['data']['_document_pins'] = $pins;
                        $wpdb->update(Store::table('passports'), ['data' => Store::json($saved['data'])], ['id' => $ids[$old]]);
                    }
                }
                foreach ($data['revisions'] as $r) {
                    if (!isset($new[$r['passport_id']])) {
                        continue;
                    }
                    $snap = Store::decode($r['snapshot']);
                    $clean = Passport::clean($snap['data']);
                    unset($clean['responsible'], $clean['quality']);
                    $clean['documents'] = [];
                    foreach ($snap['data']['documents'] ?? [] as $d) {
                        if (isset($versions[$d['version_id']])) {
                            $v = Documents::version($versions[$d['version_id']]);
                            if ($v['visibility'] === 'public') {
                                $clean['documents'][] = $v;
                            }
                        }
                    }
                    $clean['compatible'] = [];
                    foreach ($snap['data']['compatible'] ?? [] as $part) {
                        $clean['compatible'][] = ['name' => sanitize_text_field($part['name'] ?? ''),'url' => esc_url_raw($part['url'] ?? '', ['http','https'])];
                    }
                    $company = [];
                    foreach (['company','email','phone','address','role','accent','logo'] as $k) {
                        $company[$k] = in_array($k, ['logo'], true) ? esc_url_raw($snap['company'][$k] ?? '', ['http','https']) : sanitize_text_field($snap['company'][$k] ?? '');
                    }
                    $snapshot = ['format' => 1,'uuid' => $new[$r['passport_id']]['uuid'],'kind' => $new[$r['passport_id']]['kind'],'data' => $clean,'company' => $company,'published_at' => sanitize_text_field($snap['published_at'] ?? ''),'mode' => 'voluntary'];
                    $json = Store::json($snapshot);
                    $rid = Store::insert('revisions', ['passport_id' => $ids[$r['passport_id']],'snapshot' => $json,'digest' => hash('sha256', $json),'author' => get_current_user_id(),'reason' => 'Importazione revisione: ' . sanitize_text_field($r['reason']),'created_at' => sanitize_text_field($r['created_at'])]);
                    if ((int)$new[$r['passport_id']]['published_revision'] === (int)$r['id']) {
                        $wpdb->update(Store::table('passports'), ['published_revision' => $rid,'state' => in_array($new[$r['passport_id']]['state'], ['published','archived'], true) ? $new[$r['passport_id']]['state'] : 'published'], ['id' => $ids[$r['passport_id']]]);
                    }
                }
                Store::commit();
                return ['imported' => count($new),'preserved' => count($data['passports']) - count($new),'message' => 'Importazione completata. Controlla dominio, documenti e contatti prima di distribuire i QR.'];
            } catch (\Throwable $e) {
                Store::rollback();
                throw $e;
            }
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($tmp);
        }
    }
}

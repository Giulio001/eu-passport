<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Passport
{
    public const TEXT = ['name','description','sku','gtin','batch','serial','family','materials','care','safety','end_of_life','spares','support','notice','external_id','method','environmental_value','unit','source','data_date','responsible'];
    public const URL = ['image','support_url','original_url','successor_url'];
    public const TRANSLATABLE = ['name','description','materials','care','safety','end_of_life','spares','support','notice'];
    public static function clean(array $input): array
    {
        $out = [];
        foreach (self::TEXT as $key) {
            if (isset($input[$key])) {
                $out[$key] = sanitize_textarea_field(mb_substr((string)$input[$key], 0, 20000));
            }
        }
        foreach (['name' => 255,'sku' => 190,'serial' => 190,'batch' => 190] as $field => $limit) {
            if (isset($out[$field]) && mb_strlen($out[$field]) > $limit) {
                throw new \InvalidArgumentException(esc_html((Admin::labels()[$field] ?? $field) . ': usa al massimo ' . $limit . ' caratteri.'));
            }
        }
        foreach (self::URL as $key) {
            if (isset($input[$key])) {
                $out[$key] = esc_url_raw((string)$input[$key], ['https','http']);
            }
        }
        if (array_key_exists('design', $input)) {
            $out['design'] = is_array($input['design']) ? Designs::clean($input['design']) : null;
        }
        if (isset($input['language'])) {
            $out['language'] = self::language($input['language']);
        }
        if (isset($input['template'])) {
            $out['template'] = in_array($input['template'], ['generic','furniture','textile'], true) ? $input['template'] : 'generic';
        }
        if (isset($input['documents'])) {
            $out['documents'] = array_values(array_unique(array_filter(array_map('absint', array_slice((array)$input['documents'], 0, 500)))));
        }
        if (isset($input['compatible'])) {
            $out['compatible'] = array_values(array_unique(array_filter(array_map('absint', array_slice((array)$input['compatible'], 0, 500)))));
        }
        if (isset($input['attributes'])) {
            $out['attributes'] = [];
            foreach (array_slice((array)$input['attributes'], 0, 50, true) as $name => $value) {
                $out['attributes'][sanitize_text_field($name)] = sanitize_text_field((string)$value);
            }
        }
        if (isset($input['translations'])) {
            $out['translations'] = [];
            foreach (array_slice((array)$input['translations'], 0, 30, true) as $lang => $values) {
                $safe = [];
                foreach (self::TRANSLATABLE as $key) {
                    if (isset($values[$key])) {
                        $safe[$key] = sanitize_textarea_field(mb_substr((string)$values[$key], 0, 20000));
                    }
                }
                $out['translations'][self::language($lang)] = $safe;
            }
        }
        if (isset($input['quality'])) {
            $out['quality'] = [];
            foreach ((array)$input['quality'] as $field => $entry) {
                if (!in_array($field, array_merge(self::TEXT, self::URL), true) || !is_array($entry)) {
                    continue;
                }
                $state = in_array($entry['state'] ?? '', ['missing','present','review','na','internal'], true) ? $entry['state'] : 'review';
                if ($state === 'internal' && !current_user_can('gmpp_review')) {
                    throw new \DomainException('Solo un revisore può dichiarare un dato verificato internamente.');
                }
                $reason = sanitize_textarea_field($entry['reason'] ?? '');
                if ($state === 'na' && $reason === '') {
                    throw new \InvalidArgumentException('Indica il motivo del dato non applicabile.');
                }
                $out['quality'][$field] = ['state' => $state,'reason' => $reason,'source' => sanitize_text_field($entry['source'] ?? ''),'date' => sanitize_text_field($entry['date'] ?? ''),'responsible' => sanitize_text_field($entry['responsible'] ?? ''),'evidence' => absint($entry['evidence'] ?? 0)];
            }
        }
        return $out;
    }
    public static function language($lang): string
    {
        return preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', (string)$lang) ? (string)$lang : 'it';
    }
    private static function identity(array $data, string $kind, int $parent, string $uuid): string
    {
        $scope = $kind === 'model' ? 'catalog' : $kind . ':' . $parent;
        $value = $kind === 'item' ? ($data['serial'] ?? '') : ($kind === 'batch' ? ($data['batch'] ?? '') : ($data['sku'] ?? ''));
        return hash('sha256', $scope . ':' . ($value !== '' ? mb_strtolower(trim($value)) : $uuid));
    }
    public static function create(array $input, ?string $uuid = null): array
    {
        $kind = in_array($input['kind'] ?? '', ['model','variant','batch','item'], true) ? $input['kind'] : 'model';
        $parent = absint($input['parent_id'] ?? 0);
        self::validate_parent($kind, $parent);
        $raw = $input['data'] ?? $input;
        $inherited = $parent ? self::get($parent)['effective'] : [];
        $profile = Profiles::get((string)($raw['template'] ?? $inherited['template'] ?? Store::settings()['sector']));
        $data = self::clean(array_replace($parent ? [] : ($profile['defaults'] ?? []), $raw));
        $data['template'] = $profile['id'];
        $uuid = $uuid ?: wp_generate_uuid4();
        if (!wp_is_uuid($uuid)) {
            throw new \InvalidArgumentException('Identificatore non valido.');
        }
        $title = $data['name'] ?? (($inherited['name'] ?? '') . (!empty($data['serial']) ? ' · ' . $data['serial'] : (!empty($data['batch']) ? ' · ' . $data['batch'] : '')));
        $id = Store::insert('passports', ['uuid' => $uuid,'identity_key' => self::identity($data, $kind, $parent, $uuid),'title' => mb_substr($title, 0, 255), 'sku' => $data['sku'] ?? '', 'kind' => $kind, 'parent_id' => $parent, 'wc_id' => !empty($input['wc_id']) ? absint($input['wc_id']) : null,'owner' => get_current_user_id(),'data' => Store::json($data),'updated_at' => current_time('mysql', true)]);
        Store::event($id, 'created');
        return self::get($id);
    }
    private static function validate_parent(string $kind, int $parent): void
    {
        if ($kind === 'model' && $parent) {
            throw new \InvalidArgumentException('Un modello non può ereditare da un altro passaporto.');
        }
        if ($kind !== 'model') {
            $row = Store::one('passports', $parent);
            $allowed = ['variant' => ['model'],'batch' => ['model','variant'],'item' => ['model','variant','batch']];
            if (!$row || !Store::can($row) || !in_array($row['kind'], $allowed[$kind], true)) {
                throw new \InvalidArgumentException('Seleziona un genitore accessibile del tipo corretto.');
            }
        }
    }
    public static function get(int $id): array
    {
        $row = Store::one('passports', $id);
        if (!$row) {
            throw new \OutOfBoundsException('Passaporto non trovato.');
        }
        $row['data'] = Store::decode($row['data']);
        $row['effective'] = self::effective($row);
        $row['url'] = self::url($row['uuid']);
        $row['checks'] = self::checks($row['effective']);
        return $row;
    }
    public static function effective(array $row, int $depth = 0): array
    {
        if ($depth > 4) {
            throw new \RuntimeException('Relazione di eredità non valida.');
        }
        $data = is_array($row['data']) ? $row['data'] : Store::decode($row['data']);
        if ((int)$row['parent_id']) {
            $parent = Store::one('passports', (int)$row['parent_id']);
            if ($parent) {
                $data = array_replace_recursive(self::effective($parent, $depth + 1), $data);
            }
        }
        // Document arrays replace rather than merge by numeric index.
        $own = is_array($row['data']) ? $row['data'] : Store::decode($row['data']);
        foreach (['documents','compatible'] as $key) {
            if (array_key_exists($key, $own)) {
                $data[$key] = $own[$key];
            }
        }
        return $data + ['language' => Store::settings()['language'],'template' => 'generic'];
    }
    public static function save(int $id, array $input, int $version): array
    {
        global $wpdb;
        $row = self::get($id);
        if (!Store::can($row)) {
            throw new \DomainException('Non puoi modificare questo passaporto.');
        }
        $incoming = self::clean($input['data'] ?? $input);
        $data = array_replace($row['data'], $incoming);
        foreach (self::TEXT as $field) {
            if (array_key_exists($field, $incoming) && $incoming[$field] !== ($row['effective'][$field] ?? '') && ($row['effective']['quality'][$field]['state'] ?? '') === 'internal' && !isset($incoming['quality'][$field])) {
                $data['quality'][$field] = array_replace($row['effective']['quality'][$field], ['state' => 'review']);
            }
        }
        if (isset(($input['data'] ?? $input)['documents']) && ($data['documents'] ?? []) !== ($row['data']['documents'] ?? [])) {
            unset($data['_document_pins']);
        }
        if (isset(($input['data'] ?? $input)['compatible'])) {
            unset($data['_compatible_snapshot']);
        }
        foreach ((array)($input['reset'] ?? []) as $key) {
            unset($data[sanitize_key($key)]);
        }
        $result = $wpdb->query($wpdb->prepare('UPDATE %i SET data=%s,title=%s,sku=%s,identity_key=%s,lock_version=lock_version+1,updated_at=%s WHERE id=%d AND lock_version=%d', Store::table('passports'), Store::json($data), $data['name'] ?? $row['title'], $data['sku'] ?? '', self::identity($data, $row['kind'], (int)$row['parent_id'], $row['uuid']), current_time('mysql', true), $id, $version));
        if (!$result) {
            throw new \RuntimeException('Conflitto: la scheda è stata modificata oppure il riferimento è già utilizzato. Ricarica prima di salvare.');
        }
        Store::event($id, 'draft_saved', sanitize_text_field($input['reason'] ?? ''));
        return self::get($id);
    }
    public static function checks(array $data): array
    {
        $issues = [];
        if (empty($data['name'])) {
            $issues[] = ['field' => 'name','message' => 'Inserisci il nome del prodotto.','blocking' => true];
        }
        $profile = Profiles::get($data['template'] ?? 'generic');
        foreach ($profile['required'] as $key) {
            if ($key !== 'name' && (!isset($data[$key]) || $data[$key] === '')) {
                $issues[] = ['field' => $key,'message' => 'Completa il dato richiesto dal tuo modello: ' . (Admin::labels()[$key] ?? $key) . '.','blocking' => true];
            }
        }
        if (empty(Store::settings()['company'])) {
            $issues[] = ['field' => 'company','message' => 'Inserisci il nome dell’azienda nelle impostazioni.','blocking' => true];
        }
        if (empty($data['care'])) {
            $issues[] = ['field' => 'care','message' => 'Aggiungi le istruzioni di cura, se disponibili.','blocking' => false];
        }
        $manual = false;
        foreach ($data['documents'] ?? [] as $id) {
            $doc = !empty($data['_document_pins'][$id]) ? Documents::version((int)$data['_document_pins'][$id]) : Documents::current((int)$id);
            if ($doc && $doc['type'] === 'manual' && $doc['language'] === ($data['language'] ?? 'it') && $doc['visibility'] === 'public') {
                $manual = true;
            }
        }
        if (!$manual) {
            $issues[] = ['field' => 'documents','message' => 'Manca un manuale nella lingua principale della scheda.','blocking' => false];
        }
        return $issues;
    }
    public static function snapshot(array $row): array
    {
        $data = $row['effective'];
        $docs = [];
        foreach ($data['documents'] ?? [] as $id) {
            $doc = !empty($data['_document_pins'][$id]) ? Documents::version((int)$data['_document_pins'][$id]) : Documents::current((int)$id);
            if ($doc && (int)$doc['document_id'] === (int)$id && $doc['visibility'] === 'public') {
                $docs[] = $doc;
            }
        }
        $company = $data['_company_snapshot'] ?? null;
        $restored_compatible = $data['_compatible_snapshot'] ?? null;
        unset($data['responsible'], $data['quality'], $data['compatible'], $data['_document_pins'], $data['_company_snapshot'], $data['_compatible_snapshot']);
        $compatible = [];
        foreach ($row['effective']['compatible'] ?? [] as $id) {
            $other = Store::one('passports', (int)$id);
            if ($other && $other['published_revision']) {
                $rev = Store::one('revisions', (int)$other['published_revision']);
                $snap = Store::decode($rev['snapshot']);
                $compatible[] = ['name' => $snap['data']['name'] ?? '', 'url' => self::url($other['uuid'])];
            }
        }
        $data['documents'] = $docs;
        $data['compatible'] = $restored_compatible ?? $compatible;
        $s = Store::settings();
        $profile = Profiles::get($data['template'] ?? 'generic');
        return ['format' => 1,'uuid' => $row['uuid'],'kind' => $row['kind'],'data' => $data,'company' => $company ?? array_intersect_key($s, array_flip(['company','email','phone','address','role','logo','accent'])),'profile' => ['id' => $profile['id'],'version' => $profile['version'],'state' => 'voluntary'],'published_at' => gmdate('c'),'mode' => 'voluntary'];
    }
    public static function publish(int $id, int $version, string $reason): array
    {
        global $wpdb;
        Store::begin();
        try {
            $row = self::get($id);
            if (!Store::can($row, 'gmpp_publish')) {
                throw new \DomainException('Permesso di pubblicazione richiesto.');
            }
            if (!trim($reason)) {
                throw new \InvalidArgumentException('Indica una motivazione per la revisione.');
            }
            foreach ($row['checks'] as $check) {
                if ($check['blocking']) {
                    throw new \InvalidArgumentException($check['message']);
                }
            }
            $json = Store::json(self::snapshot($row));
            $rev = Store::insert('revisions', ['passport_id' => $id,'snapshot' => $json,'digest' => hash('sha256', $json),'author' => get_current_user_id(),'reason' => sanitize_textarea_field($reason),'created_at' => current_time('mysql', true)]);
            $ok = $wpdb->query($wpdb->prepare('UPDATE %i SET published_revision=%d,state=\'published\',lock_version=lock_version+1 WHERE id=%d AND lock_version=%d', Store::table('passports'), $rev, $id, $version));
            if (!$ok) {
                throw new \RuntimeException('Conflitto: ricarica la scheda prima di pubblicare.');
            }
            Store::event($id, 'published', $reason);
            Store::commit();
        } catch (\Throwable $e) {
            Store::rollback();
            throw $e;
        }
        return self::get($id);
    }
    public static function archive(int $id, int $version): array
    {
        $existing = self::get($id);
        if (!Store::can($existing, 'gmpp_publish')) {
            throw new \DomainException('Permesso di pubblicazione richiesto.');
        }
        Store::begin();
        try {
            $notice = trim(($existing['effective']['notice'] ?? '') . "\n\n" . __('Prodotto fuori produzione. Le informazioni di assistenza restano disponibili.', 'giuliomax-product-passport'));
            $row = self::save($id, ['notice' => $notice], $version);
            $row = self::publish($id, (int)$row['lock_version'], 'Archiviazione commerciale');
            global $wpdb;
            if ($wpdb->update(Store::table('passports'), ['state' => 'archived'], ['id' => $id,'lock_version' => $row['lock_version']]) !== 1) {
                throw new \RuntimeException('Impossibile confermare l’archiviazione.');
            }
            Store::commit();
        } catch (\Throwable $e) {
            Store::rollback();
            throw $e;
        }
        return self::get($id);
    }
    public static function restore(int $id, int $revision, int $version): array
    {
        $rev = Store::one('revisions', $revision);
        if (!$rev || (int)$rev['passport_id'] !== $id) {
            throw new \InvalidArgumentException('Revisione non valida.');
        }
        Store::begin();
        try {
            $snap = Store::decode($rev['snapshot']);
            $data = $snap['data'];
            $data['design'] = $data['design'] ?? null;
            $data['documents'] = array_column($data['documents'], 'document_id');
            $data['compatible'] = [];
        // A restore is a tracked draft; publication must be explicitly reviewed again.
            $row = self::save($id, ['data' => $data,'reason' => 'Ripristino revisione ' . $revision], $version);
            $draft = $row['data'];
            $draft['_document_pins'] = array_column($snap['data']['documents'], 'version_id', 'document_id');
            $draft['_company_snapshot'] = $snap['company'];
            $draft['_compatible_snapshot'] = $snap['data']['compatible'] ?? [];
            global $wpdb;
            if ($wpdb->update(Store::table('passports'), ['data' => Store::json($draft)], ['id' => $id,'lock_version' => $row['lock_version']]) === false) {
                throw new \RuntimeException('Ripristino non confermato.');
            }
            Store::event($id, 'restored', 'Revisione ' . $revision . '; dati aziendali e versioni dei documenti conservati.');
            Store::commit();
            return self::get($id);
        } catch (\Throwable $e) {
            Store::rollback();
            throw $e;
        }
    }
    public static function url(string $uuid): string
    {
        return add_query_arg('gmpp', $uuid, home_url('/'));
    }
    public static function published(string $uuid): ?array
    {
        $row = Store::uuid($uuid);
        if (!$row || !$row['published_revision']) {
            return null;
        }
        $rev = Store::one('revisions', (int)$row['published_revision']);
        $snap = Store::decode($rev['snapshot']);
        $snap['revision'] = (int)$rev['id'];
        return $snap;
    }
}

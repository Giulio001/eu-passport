<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Jobs
{
    public static function csv(string $csv, array $map, string $mode, string $key, bool $clear): array
    {
        if (strlen($csv) > 12 * 1024 * 1024 || !mb_check_encoding($csv, 'UTF-8')) {
            throw new \InvalidArgumentException('Usa CSV UTF-8 fino a 12 MB.');
        }
        if (!in_array($mode, ['create','update','upsert'], true) || !in_array($key, ['sku','uuid'], true)) {
            throw new \InvalidArgumentException('Scegli modalità e chiave di riconciliazione.');
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $first = strtok($csv, "\r\n") ?: '';
        $delimiter = ',';
        $count = 0;
        foreach ([',',';',"\t"] as $candidate) {
            if (count(str_getcsv($first, $candidate, '"', '')) > $count) {
                $count = count(str_getcsv($first, $candidate, '"', ''));
                $delimiter = $candidate;
            }
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!$headers) {
            throw new \InvalidArgumentException('CSV senza intestazioni.');
        }
        if (count(array_unique($headers)) !== count($headers)) {
            fclose($stream);
            throw new \InvalidArgumentException('Le intestazioni CSV devono essere univoche.');
        }
        $map = $map ?: array_combine($headers, $headers);
        $allowed = array_merge(Passport::TEXT, Passport::URL, ['language','template','uuid','kind','parent_uuid','documents']);
        $rows = [];
        $seen = [];
        $line = 1;
        while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            ++$line;
            if ($values === [null]) {
                continue;
            }
            $row = [];
            $error = '';
            foreach ($headers as $index => $header) {
                $field = $map[$header] ?? '';
                if (in_array($field, $allowed, true)) {
                    $v = $values[$index] ?? '';
                    if ($v !== '' || $clear) {
                        $row[$field] = $v;
                    }
                }
            }
            if (!isset($row[$key]) || trim((string)$row[$key]) === '') {
                $error = 'Chiave ' . $key . ' mancante.';
            }
            $identity = mb_strtolower(trim($row[$key] ?? ''));
            if (isset($seen[$identity])) {
                $error = 'Chiave ripetuta nel file.';
            } $seen[$identity] = true;
            $existing = null;
            try {
                if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                    $existing = self::find($key, $row[$key]);
                }
                Passport::clean($row);
                if (count($values) !== count($headers)) {
                    throw new \InvalidArgumentException('Numero di colonne diverso dall’intestazione.');
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
            if ($existing && !Store::can($existing)) {
                $error = 'Passaporto non accessibile.';
            }
            if ($mode === 'create' && $existing) {
                $error = 'La scheda esiste già.';
            }
            if ($mode === 'update' && !$existing) {
                $error = 'Scheda da aggiornare non trovata.';
            }
            if (isset($row['documents'])) {
                $row['documents'] = array_filter(array_map('absint', explode('|', $row['documents'])));
            }
            $rows[] = ['line' => $line,'data' => $row,'id' => $existing ? (int)$existing['id'] : 0,'version' => $existing ? (int)$existing['lock_version'] : 0,'error' => $error];
            if (count($rows) > 20000) {
                throw new \InvalidArgumentException('Massimo 20.000 righe per file.');
            }
        }
        fclose($stream);
        return ['headers' => $headers,'delimiter' => $delimiter,'rows' => $rows,'mode' => $mode,'key' => $key,'clear' => $clear];
    }
    private static function find(string $key, string $value): ?array
    {
        if ($key === 'uuid') {
            return Store::uuid($value);
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Store::table('passports') . ' WHERE sku=%s LIMIT 2', $value), ARRAY_A);
        if (count($rows) > 1) {
            throw new \InvalidArgumentException('SKU ambiguo: usa UUID come chiave di riconciliazione.');
        }
        return $rows[0] ?? null;
    }
    public static function create(string $kind, array $payload, int $total): array
    {
        $id = Store::insert('jobs', ['kind' => $kind,'state' => 'queued','owner' => get_current_user_id(),'total' => $total,'payload' => Store::json($payload),'report' => '[]','updated_at' => current_time('mysql', true)]);
        self::schedule();
        return Store::one('jobs', $id);
    }
    private static function schedule(): void
    {
        if (function_exists('as_schedule_single_action')) {
            if (!as_has_scheduled_action('gmpp_jobs_continue', [], 'gmpp')) {
                as_schedule_single_action(time() + 5, 'gmpp_jobs_continue', [], 'gmpp');
            }
        } elseif (!wp_next_scheduled('gmpp_jobs_continue')) {
            wp_schedule_single_event(time() + 5, 'gmpp_jobs_continue');
        }
    }
    public static function tick(): void
    {
        global $wpdb;
        $table = Store::table('jobs');
        $wpdb->query($wpdb->prepare("UPDATE $table SET state='queued',lease_token='' WHERE state='running' AND updated_at<%s", gmdate('Y-m-d H:i:s', time() - 300)));
        $ids = $wpdb->get_col("SELECT id FROM $table WHERE state='queued' ORDER BY id LIMIT 3");
        $previous = get_current_user_id();
        try {
            foreach ($ids as $id) {
                $lease = wp_generate_uuid4();
                if (!$wpdb->query($wpdb->prepare("UPDATE $table SET state='running',lease_token=%s,updated_at=%s WHERE id=%d AND state='queued'", $lease, gmdate('Y-m-d H:i:s'), $id))) {
                    continue;
                }
                // Read progress only after acquiring the lease. A queued record may
                // have progressed since the initial candidate query.
                $job = Store::one('jobs', (int)$id);
                wp_set_current_user((int)$job['owner']);
                $payload = Store::decode($job['payload']);
                $report = Store::decode($job['report']);
                $cursor = (int)$job['cursor'];
                $end = min((int)$job['total'], $cursor + 50);
                for (; $cursor < $end; ++$cursor) {
                    Store::begin();
                    try {
                        // This write locks the job while the row and cursor commit.
                        $wpdb->query($wpdb->prepare("UPDATE $table SET updated_at=%s WHERE id=%d AND lease_token=%s AND state='running'", gmdate('Y-m-d H:i:s'), $id, $lease));
                        $live = Store::one('jobs', (int)$id);
                        if (!$live || $live['lease_token'] !== $lease || $live['state'] !== 'running' || (int)$live['cursor'] !== $cursor) {
                            throw new \RuntimeException('Operazione acquisita da un altro processo.');
                        }
                        $wpdb->query('SAVEPOINT gmpp_row');
                        try {
                            if (!current_user_can('gmpp_edit')) {
                                throw new \DomainException('Permessi revocati.');
                            }
                            $item = $payload['rows'][$cursor];
                            if (!empty($item['error'])) {
                                throw new \InvalidArgumentException($item['error']);
                            }
                            if ($job['kind'] === 'csv') {
                                $data = $item['data'];
                                if ($item['id']) {
                                    Passport::save($item['id'], ['data' => $data], $item['version']);
                                } else {
                                    if (self::find($payload['key'], $data[$payload['key']])) {
                                        throw new \RuntimeException('La chiave è stata creata dopo l’anteprima: rivedi l’importazione.');
                                    }
                                    $parent = !empty($data['parent_uuid']) ? Store::uuid($data['parent_uuid']) : null;
                                    Passport::create(['data' => $data,'kind' => $data['kind'] ?? 'model','parent_id' => $parent['id'] ?? 0], $data['uuid'] ?? null);
                                }
                            } elseif ($job['kind'] === 'serials') {
                                Passport::create(['data' => ['serial' => $item['serial']],'kind' => 'item','parent_id' => $payload['parent_id']]);
                            } elseif ($job['kind'] === 'bulk') {
                                if ($payload['action'] === 'publish') {
                                    Passport::publish($item['id'], $item['version'], $payload['reason']);
                                } elseif ($payload['action'] === 'archive') {
                                    Passport::archive($item['id'], $item['version']);
                                } elseif ($payload['action'] === 'review') {
                                    $row = Passport::get($item['id']);
                                    if (!Store::can($row, 'gmpp_review')) {
                                        throw new \DomainException('Permesso di revisione richiesto.');
                                    }
                                    Passport::save($item['id'], ['reason' => 'Revisione documento condiviso'], $item['version']);
                                }
                            } else {
                                throw new \InvalidArgumentException('Tipo di operazione non supportato.');
                            }
                            $entry = ['row' => $item['line'] ?? $cursor + 1,'ok' => true];
                        } catch (\Throwable $e) {
                            $wpdb->query('ROLLBACK TO SAVEPOINT gmpp_row');
                            $entry = ['row' => $item['line'] ?? $cursor + 1,'ok' => false,'error' => $e->getMessage()];
                        }
                        $report[] = $entry;
                        $ok = $wpdb->update($table, ['cursor' => $cursor + 1,'report' => Store::json($report),'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id,'lease_token' => $lease,'state' => 'running']);
                        if ($ok !== 1) {
                            throw new \RuntimeException('Avanzamento non salvato: il lotto verrà ripreso.');
                        }
                        Store::commit();
                    } catch (\Throwable $e) {
                        Store::rollback();
                        // Keep already committed progress and allow a safe retry.
                        $wpdb->update($table, ['state' => 'queued','lease_token' => ''], ['id' => $id,'lease_token' => $lease]);
                        break;
                    }
                }
                $wpdb->update($table, ['state' => $cursor >= (int)$job['total'] ? 'done' : 'queued','lease_token' => ''], ['id' => $id,'lease_token' => $lease,'state' => 'running']);
            }
        } finally {
            wp_set_current_user($previous);
            if ($wpdb->get_var("SELECT id FROM $table WHERE state IN ('queued','running') LIMIT 1")) {
                self::schedule();
            }
        }
    }
    public static function csv_cell(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'" . $value : $value;
    }
}

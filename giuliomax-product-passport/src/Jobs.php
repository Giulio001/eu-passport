<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Jobs
{
    public static function csv(string $csv, array $map, string $mode, string $key, bool $clear): array
    {
        if (strlen($csv) > 12 * 1024 * 1024 || !mb_check_encoding($csv, 'UTF-8')) {
            throw new \InvalidArgumentException(__('Use a UTF-8 CSV file up to 12 MB.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        if (!in_array($mode, ['create','update','upsert'], true) || !in_array($key, ['sku','uuid'], true)) {
            throw new \InvalidArgumentException(__('Select an import mode and reconciliation key.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
        $stream = fopen('php://temp', 'w+'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory CSV stream; no filesystem path is opened.
        fwrite($stream, $csv); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory CSV stream; no filesystem path is opened.
        rewind($stream);
        $headers = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!$headers) {
            throw new \InvalidArgumentException(__('CSV headers are missing.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        if (count(array_unique($headers)) !== count($headers)) {
            fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory CSV stream; no filesystem path is opened.
            throw new \InvalidArgumentException(__('CSV headers must be unique.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
                $error = __('Key ', 'giuliomax-product-passport') . $key . __(' missing.', 'giuliomax-product-passport');
            }
            $identity = mb_strtolower(trim($row[$key] ?? ''));
            if (isset($seen[$identity])) {
                $error = __('Duplicate key in the file.', 'giuliomax-product-passport');
            } $seen[$identity] = true;
            $existing = null;
            try {
                if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                    $existing = self::find($key, $row[$key]);
                }
                Passport::clean($row);
                if (count($values) !== count($headers)) {
                    throw new \InvalidArgumentException(__('The column count does not match the header.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
            if ($existing && !Store::can($existing)) {
                $error = __('Passport is not accessible.', 'giuliomax-product-passport');
            }
            if ($mode === 'create' && $existing) {
                $error = __('The sheet already exists.', 'giuliomax-product-passport');
            }
            if ($mode === 'update' && !$existing) {
                $error = __('The sheet to update was not found.', 'giuliomax-product-passport');
            }
            if (isset($row['documents'])) {
                $row['documents'] = array_filter(array_map('absint', explode('|', $row['documents'])));
            }
            $rows[] = ['line' => $line,'data' => $row,'id' => $existing ? (int)$existing['id'] : 0,'version' => $existing ? (int)$existing['lock_version'] : 0,'error' => $error];
            if (count($rows) > 20000) {
                throw new \InvalidArgumentException(__('A file can contain up to 20,000 rows.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
            }
        }
        fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory CSV stream; no filesystem path is opened.
        return ['headers' => $headers,'delimiter' => $delimiter,'rows' => $rows,'mode' => $mode,'key' => $key,'clear' => $clear];
    }
    private static function find(string $key, string $value): ?array
    {
        if ($key === 'uuid') {
            return Store::uuid($value);
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE sku=%s LIMIT 2', Store::table('passports'), $value), ARRAY_A);
        if (count($rows) > 1) {
            throw new \InvalidArgumentException(__('Ambiguous SKU: use UUID as the reconciliation key.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
            // Only pending actions count: the currently executing action must
            // not suppress scheduling the next batch.
            if (!as_get_scheduled_actions(['hook' => 'gmpp_jobs_continue','args' => [],'group' => 'gmpp','status' => 'pending','per_page' => 1], 'ids')) {
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
        $wpdb->query($wpdb->prepare("UPDATE %i SET state='queued',lease_token='' WHERE state='running' AND updated_at<%s", $table, gmdate('Y-m-d H:i:s', time() - 300)));
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM %i WHERE state='queued' ORDER BY id LIMIT 3", $table));
        $previous = get_current_user_id();
        try {
            foreach ($ids as $id) {
                $lease = wp_generate_uuid4();
                if (!$wpdb->query($wpdb->prepare("UPDATE %i SET state='running',lease_token=%s,updated_at=%s WHERE id=%d AND state='queued'", $table, $lease, gmdate('Y-m-d H:i:s'), $id))) {
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
                        $wpdb->query($wpdb->prepare("UPDATE %i SET updated_at=%s WHERE id=%d AND lease_token=%s AND state='running'", $table, gmdate('Y-m-d H:i:s'), $id, $lease));
                        $live = Store::one('jobs', (int)$id);
                        if (!$live || $live['lease_token'] !== $lease || $live['state'] !== 'running' || (int)$live['cursor'] !== $cursor) {
                            throw new \RuntimeException(__('Another worker acquired this job.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
                        }
                        $wpdb->query('SAVEPOINT gmpp_row');
                        try {
                            if (!current_user_can('gmpp_edit')) {
                                throw new \DomainException(__('Permissions revoked.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
                                        throw new \RuntimeException(__('The key was created after preview: review the import again.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
                                        throw new \DomainException(__('Review permission is required.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
                                    }
                                    Passport::save($item['id'], ['reason' => __('Shared document review', 'giuliomax-product-passport')], $item['version']);
                                }
                            } else {
                                throw new \InvalidArgumentException(__('Unsupported job type.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
                            }
                            $entry = ['row' => $item['line'] ?? $cursor + 1,'ok' => true];
                        } catch (\Throwable $e) {
                            $wpdb->query('ROLLBACK TO SAVEPOINT gmpp_row');
                            $entry = ['row' => $item['line'] ?? $cursor + 1,'ok' => false,'error' => $e->getMessage()];
                        }
                        $report[] = $entry;
                        $ok = $wpdb->update($table, ['cursor' => $cursor + 1,'report' => Store::json($report),'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id,'lease_token' => $lease,'state' => 'running']);
                        if ($ok !== 1) {
                            throw new \RuntimeException(__('Progress not saved: this batch will be resumed.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM %i WHERE state IN ('queued','running') LIMIT 1", $table))) {
                self::schedule();
            }
        }
    }
    public static function csv_cell(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'" . $value : $value;
    }
}

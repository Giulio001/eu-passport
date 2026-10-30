<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Store
{
    private static int $depth = 0;
    public static function begin(): void
    {
        global $wpdb;
        $level = self::$depth;
        $result = $level === 0 ? $wpdb->query('START TRANSACTION') : $wpdb->query($wpdb->prepare('SAVEPOINT %i', 'gmpp_tx_' . $level));
        if ($result === false) {
            throw new \RuntimeException(__('The database could not start a safe save. Try again.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        self::$depth++;
    }
    public static function commit(): void
    {
        global $wpdb;
        $level = self::$depth - 1;
        if ($level < 0) {
            throw new \LogicException(__('No open transaction.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        $result = $level === 0 ? $wpdb->query('COMMIT') : $wpdb->query($wpdb->prepare('RELEASE SAVEPOINT %i', 'gmpp_tx_' . $level));
        if ($result === false) {
            throw new \RuntimeException(__('The database did not confirm the save. Reload before trying again.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        self::$depth--;
    }
    public static function rollback(): void
    {
        global $wpdb;
        if (self::$depth > 0) {
            $level = --self::$depth;
            if ($level === 0) {
                $wpdb->query('ROLLBACK');
            } else {
                $wpdb->query($wpdb->prepare('ROLLBACK TO SAVEPOINT %i', 'gmpp_tx_' . $level));
            }
        }
    }
    public static function table(string $name): string
    {
        global $wpdb;
        if (!in_array($name, ['passports',__('revisions', 'giuliomax-product-passport'),'documents','document_versions','requests','jobs','events'], true)) {
            throw new \InvalidArgumentException(__('Unsupported table.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        return $wpdb->prefix . 'gmpp_' . $name;
    }
    public static function one(string $table, int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', self::table($table), $id), ARRAY_A);
        return $row ?: null;
    }
    public static function uuid(string $uuid): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE uuid=%s', self::table('passports'), $uuid), ARRAY_A) ?: null;
    }
    public static function insert(string $table, array $data): int
    {
        global $wpdb;
        $old = $wpdb->suppress_errors(true);
        $ok = $wpdb->insert(self::table($table), $data);
        $wpdb->suppress_errors($old);
        if (!$ok) {
            throw new \RuntimeException(esc_html__('Unable to save: duplicate identifier or invalid data.', 'giuliomax-product-passport'));
        } return (int)$wpdb->insert_id;
    }
    public static function event(int $id, string $action, string $detail = ''): void
    {
        self::insert('events', ['passport_id' => $id,'author' => get_current_user_id(),'action' => $action,'detail' => sanitize_textarea_field($detail),'created_at' => current_time('mysql', true)]);
    }
    public static function decode(?string $json): array
    {
        $value = json_decode($json ?: '{}', true);
        return is_array($value) ? $value : [];
    }
    public static function json(array $data): string
    {
        return (string) wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    public static function can(array $row, string $cap = 'gmpp_edit'): bool
    {
        return current_user_can($cap) && (current_user_can('gmpp_edit_others') || (int)$row['owner'] === get_current_user_id());
    }
    public static function settings(): array
    {
        return (array)get_option('gmpp_settings', []) + ['company' => '','email' => '','phone' => '','address' => '','role' => 'manufacturer','logo' => '','accent' => '#135f54','language' => 'en','sector' => 'generic','mode' => 'standalone','label_width' => 70,'label_height' => 45,'label_margin' => 8,'email_enabled' => false];
    }
    public static function is_clone(): bool
    {
        return wp_get_environment_type() !== 'production' || untrailingslashit((string)get_option('gmpp_origin')) !== untrailingslashit(home_url('/'));
    }
}

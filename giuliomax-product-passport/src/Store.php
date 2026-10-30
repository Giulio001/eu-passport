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
        if ($wpdb->query($level === 0 ? 'START TRANSACTION' : 'SAVEPOINT gmpp_tx_' . $level) === false) {
            throw new \RuntimeException('Il database non ha potuto avviare un salvataggio sicuro. Riprova.');
        }
        self::$depth++;
    }
    public static function commit(): void
    {
        global $wpdb;
        $level = self::$depth - 1;
        if ($level < 0 || $wpdb->query($level === 0 ? 'COMMIT' : 'RELEASE SAVEPOINT gmpp_tx_' . $level) === false) {
            throw new \RuntimeException('Il database non ha confermato il salvataggio. Ricarica prima di riprovare.');
        }
        self::$depth--;
    }
    public static function rollback(): void
    {
        global $wpdb;
        if (self::$depth > 0) {
            $level = --self::$depth;
            $wpdb->query($level === 0 ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT gmpp_tx_' . $level);
        }
    }
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'gmpp_' . $name;
    }
    public static function one(string $table, int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table($table) . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }
    public static function uuid(string $uuid): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('passports') . ' WHERE uuid=%s', $uuid), ARRAY_A) ?: null;
    }
    public static function insert(string $table, array $data): int
    {
        global $wpdb;
        $old = $wpdb->suppress_errors(true);
        $ok = $wpdb->insert(self::table($table), $data);
        $wpdb->suppress_errors($old);
        if (!$ok) {
            throw new \RuntimeException(__('Impossibile salvare: identificatore duplicato o dati non validi.', 'giuliomax-product-passport'));
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
        return (array)get_option('gmpp_settings', []) + ['company' => '','email' => '','phone' => '','address' => '','role' => 'manufacturer','logo' => '','accent' => '#135f54','ui_language' => 'it','language' => 'it','sector' => 'generic','mode' => 'standalone','label_width' => 70,'label_height' => 45,'label_margin' => 8,'email_enabled' => false];
    }
    public static function is_clone(): bool
    {
        return wp_get_environment_type() !== 'production' || untrailingslashit((string)get_option('gmpp_origin')) !== untrailingslashit(home_url('/'));
    }
}

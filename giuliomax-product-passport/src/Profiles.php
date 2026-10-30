<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Profiles
{
    public static function all(): array
    {
        $saved = (array)get_option('gmpp_profiles', []);
        $out = [];
        foreach (Connections::profiles() as $profile) {
            $out[] = array_replace($profile, $saved[$profile['id']] ?? []);
        }
        return $out;
    }
    public static function get(string $id): array
    {
        foreach (self::all() as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        } return self::all()[0];
    }
    public static function save(string $id, array $input): array
    {
        if (!in_array($id, ['generic','furniture','textile'], true)) {
            throw new \InvalidArgumentException('Modello non disponibile.');
        }
        $current = self::get($id);
        if (($input['version'] ?? '') !== $current['version']) {
            throw new \RuntimeException('Il modello è stato aggiornato. Ricarica prima di salvare.');
        }
        $required = array_values(array_unique(array_merge(['name'], array_intersect(Passport::TEXT, (array)($input['required'] ?? [])))));
        $defaults = array_intersect_key(Passport::clean((array)($input['defaults'] ?? [])), array_flip(['materials','care','safety','end_of_life','spares','support']));
        $saved = (array)get_option('gmpp_profiles', []);
        $saved[$id] = ['version' => (string)((int)$current['version'] + 1),'required' => $required,'defaults' => $defaults,'updated_at' => gmdate('c')];
        update_option('gmpp_profiles', $saved, false);
        global $wpdb;
        $like = '%' . $wpdb->esc_like('"template":"' . $id . '"') . '%';
        $affected = $wpdb->get_results($wpdb->prepare('SELECT id,title,lock_version FROM ' . Store::table('passports') . ' WHERE data LIKE %s', $like), ARRAY_A);
        return ['profile' => self::get($id),'affected' => $affected,'previous' => $current,'message' => 'Modello aggiornato. Le revisioni pubblicate conservano i dati precedenti; verifica le nuove richieste prima di ripubblicare.'];
    }
}

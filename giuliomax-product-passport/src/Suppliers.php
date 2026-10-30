<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Suppliers
{
    public const FIELDS = ['materials','care','safety','end_of_life','spares','method','environmental_value','unit','source','data_date'];
    public static function create(int $passport, array $fields, int $days = 7): array
    {
        $row = Passport::get($passport);
        if (!Store::can($row, 'gmpp_review')) {
            throw new \DomainException('Permesso di revisione richiesto.');
        }
        $fields = array_values(array_intersect(self::FIELDS, array_map('sanitize_key', $fields)));
        if (!$fields) {
            throw new \InvalidArgumentException('Scegli almeno un campo da richiedere.');
        }
        $token = bin2hex(random_bytes(32));
        $id = Store::insert('requests', ['token_hash' => hash('sha256', $token),'passport_id' => $passport,'fields' => Store::json($fields),'proposal' => '{}','state' => 'open','expires_at' => gmdate('Y-m-d H:i:s', time() + max(1, min(30, $days)) * DAY_IN_SECONDS),'created_at' => current_time('mysql', true)]);
        Store::event($passport, 'supplier_requested', implode(', ', $fields));
        return ['id' => $id,'url' => add_query_arg('gmpp_supplier', $token, home_url('/'))];
    }
    public static function by_token(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Store::table('requests') . " WHERE token_hash=%s AND state='open' AND expires_at>%s", hash('sha256', $token), gmdate('Y-m-d H:i:s')), ARRAY_A) ?: null;
    }
    public static function respond(string $token, array $values): void
    {
        $row = self::by_token($token);
        if (!$row) {
            throw new \InvalidArgumentException('Collegamento scaduto, revocato o già utilizzato.');
        }
        $key = 'gmpp_rate_' . substr(hash('sha256', $token), 0, 32);
        if ((int)get_transient($key) >= 5) {
            throw new \RuntimeException('Attendi prima di riprovare.');
        }
        set_transient($key, (int)get_transient($key) + 1, HOUR_IN_SECONDS);
        $values = array_intersect_key(Passport::clean($values), array_flip(Store::decode($row['fields'])));
        global $wpdb;
        $ok = $wpdb->query($wpdb->prepare('UPDATE ' . Store::table('requests') . " SET proposal=%s,state='answered' WHERE id=%d AND state='open'", Store::json($values), (int)$row['id']));
        if (!$ok) {
            throw new \RuntimeException('Risposta già acquisita.');
        }
        Store::event((int)$row['passport_id'], 'supplier_answered');
    }
    public static function review(int $id, bool $accept, int $version): array
    {
        $request = Store::one('requests', $id);
        if (!$request) {
            throw new \OutOfBoundsException('Richiesta non trovata.');
        }
        $passport = Passport::get((int)$request['passport_id']);
        if (!Store::can($passport, 'gmpp_review')) {
            throw new \DomainException('Permesso di revisione richiesto.');
        }
        if ($request['state'] !== 'answered') {
            throw new \InvalidArgumentException('La proposta è già stata gestita o non è ancora disponibile.');
        }
        if ($accept) {
            $passport = Passport::save((int)$passport['id'], ['data' => Store::decode($request['proposal']),'reason' => 'Proposta fornitore ' . $id], $version);
        }
        global $wpdb;
        $wpdb->update(Store::table('requests'), ['state' => $accept ? 'accepted' : 'rejected'], ['id' => $id,'state' => 'answered']);
        Store::event((int)$passport['id'], $accept ? 'supplier_accepted' : 'supplier_rejected');
        return $passport;
    }
    public static function email(int $id, string $recipient, string $url): bool
    {
        if (!Store::settings()['email_enabled'] || Store::is_clone()) {
            throw new \DomainException('Abilita esplicitamente l’email aziendale su un sito di produzione verificato.');
        }
        if (!is_email($recipient)) {
            throw new \InvalidArgumentException('Email non valida.');
        }
        $token = (string)wp_parse_url($url, PHP_URL_QUERY);
        parse_str($token, $args);
        $req = self::by_token($args['gmpp_supplier'] ?? '');
        if (!$req || (int)$req['id'] !== $id) {
            throw new \InvalidArgumentException('Collegamento della richiesta non valido.');
        }
        return wp_mail($recipient, __('Richiesta informazioni prodotto', 'giuliomax-product-passport'), "Completa le informazioni richieste entro la scadenza:\n" . add_query_arg('gmpp_supplier', $args['gmpp_supplier'], home_url('/')));
    }
}

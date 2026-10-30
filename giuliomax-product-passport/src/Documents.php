<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Documents
{
    public const MAX_BYTES = 10485760;
    public static function current(int $id): ?array
    {
        $doc = Store::one('documents', $id);
        return $doc ? self::version((int)$doc['current_version']) : null;
    }
    public static function version(int $id): ?array
    {
        global $wpdb;
        $v = $wpdb->get_row($wpdb->prepare('SELECT id,document_id,metadata,digest,created_at FROM ' . Store::table('document_versions') . ' WHERE id=%d', $id), ARRAY_A);
        if (!$v) {
            return null;
        }
        return ['version_id' => (int)$v['id'],'document_id' => (int)$v['document_id']] + Store::decode($v['metadata']) + ['digest' => $v['digest'],'created_at' => $v['created_at']];
    }
    public static function upload(array $file, array $input): array
    {
        if (!current_user_can('gmpp_edit') || !current_user_can('upload_files')) {
            throw new \DomainException('Non puoi caricare documenti.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Caricamento non valido.');
        }
        return self::store_bytes((string)file_get_contents($file['tmp_name']), $file['name'], $input);
    }
    public static function store_bytes(string $bytes, string $name, array $input): array
    {
        $name = sanitize_file_name(wp_basename($name));
        if (strlen($bytes) > self::MAX_BYTES || !$bytes) {
            throw new \InvalidArgumentException('Usa un file non vuoto di massimo 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf' => 'application/pdf','png' => 'image/png','jpg' => 'image/jpeg','jpeg' => 'image/jpeg','txt' => 'text/plain'];
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            throw new \InvalidArgumentException('Formato non valido: usa PDF, PNG, JPG o testo semplice con estensione e contenuto coerenti.');
        }
        if ($mime === 'application/pdf' && !str_starts_with($bytes, '%PDF-')) {
            throw new \InvalidArgumentException('PDF non valido.');
        }
        if (($input['visibility'] ?? '') === 'private' && !current_user_can('gmpp_review')) {
            throw new \DomainException('Solo un revisore può caricare documenti riservati.');
        }
        $id = absint($input['document_id'] ?? 0);
        $existing = $id ? Store::one('documents', $id) : null;
        $expected = absint($input['expected_version'] ?? ($existing['current_version'] ?? 0));
        if ($id && !Store::one('documents', $id)) {
            throw new \InvalidArgumentException('Documento non trovato.');
        }
        if ($id && !current_user_can('gmpp_review')) {
            throw new \DomainException('Un revisore deve aggiornare i documenti condivisi.');
        }
        $title = sanitize_text_field($input['title'] ?? $name);
        if ($title === '' || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Inserisci un titolo del documento di massimo 255 caratteri.');
        }
        Store::begin();
        try {
            if (!$id) {
                $id = Store::insert('documents', ['uuid' => wp_generate_uuid4(),'title' => $title,'owner' => get_current_user_id()]);
            }
            $metadata = ['title' => $title,'filename' => $name,'mime' => $mime,'size' => strlen($bytes),'type' => in_array($input['type'] ?? '', ['manual','declaration','evidence','guide'], true) ? $input['type'] : 'manual','language' => Passport::language($input['language'] ?? 'it'),'version' => sanitize_text_field($input['version'] ?? '1'),'date' => sanitize_text_field($input['date'] ?? gmdate('Y-m-d')),'expires' => sanitize_text_field($input['expires'] ?? ''),'source' => sanitize_text_field($input['source'] ?? ''),'visibility' => ($input['visibility'] ?? '') === 'private' ? 'private' : 'public'];
            $v = Store::insert('document_versions', ['document_id' => $id,'metadata' => Store::json($metadata),'storage_path' => 'database','content' => $bytes,'digest' => hash('sha256', $bytes),'created_at' => current_time('mysql', true)]);
            global $wpdb;
            if ($wpdb->update(Store::table('documents'), ['current_version' => $v,'title' => $title], ['id' => $id,'current_version' => $expected]) !== 1) {
                throw new \RuntimeException('Il documento è stato aggiornato da un altro utente. Ricarica prima di caricare una nuova versione.');
            }
            Store::commit();
        } catch (\Throwable $e) {
            Store::rollback();
            throw $e;
        }
        return ['document' => self::version($v),'affected' => self::affected($id)];
    }
    public static function affected(int $document): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT id,title,data,parent_id FROM ' . Store::table('passports'), ARRAY_A);
        $out = [];
        // Resolve inheritance so a shared model document also reports affected items.
        foreach ($rows as $row) {
            $data = Passport::effective($row);
            if (in_array($document, array_map('intval', $data['documents'] ?? []), true)) {
                $out[] = ['id' => (int)$row['id'],'title' => $row['title']];
            }
        }
        return $out;
    }
    public static function is_public(int $version): bool
    {
        $v = self::version($version);
        if (!$v || $v['visibility'] !== 'public') {
            return false;
        }
        global $wpdb;
        $like = '%' . $wpdb->esc_like('"version_id":' . $version . ',') . '%';
        return (bool)$wpdb->get_var($wpdb->prepare('SELECT r.id FROM ' . Store::table('revisions') . ' r INNER JOIN ' . Store::table('passports') . ' p ON p.published_revision=r.id WHERE r.snapshot LIKE %s LIMIT 1', $like));
    }
    public static function can_read(int $id): bool
    {
        $version = self::version($id);
        if (!$version) {
            return false;
        }
        if (self::is_public($id) || current_user_can('gmpp_review')) {
            return true;
        }
        $document = Store::one('documents', (int)$version['document_id']);
        return $version['visibility'] === 'public' && current_user_can('gmpp_edit') && $document && (int)$document['owner'] === get_current_user_id();
    }
    public static function download(int $id): void
    {
        $v = self::version($id);
        if (!$v || !self::can_read($id)) {
            status_header(404);
            exit;
        }
        global $wpdb;
        $bytes = $wpdb->get_var($wpdb->prepare('SELECT content FROM ' . Store::table('document_versions') . ' WHERE id=%d', $id));
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Content-Type: ' . $v['mime']);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($v['filename']) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verified binary download.
        exit;
    }
    public static function url(int $id): string
    {
        return add_query_arg('gmpp_document', $id, home_url('/'));
    }
}

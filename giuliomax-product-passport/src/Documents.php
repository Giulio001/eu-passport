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
        $v = $wpdb->get_row($wpdb->prepare('SELECT id,document_id,metadata,digest,created_at FROM %i WHERE id=%d', Store::table('document_versions'), $id), ARRAY_A);
        if (!$v) {
            return null;
        }
        return ['version_id' => (int)$v['id'],'document_id' => (int)$v['document_id']] + Store::decode($v['metadata']) + ['digest' => $v['digest'],'created_at' => $v['created_at']];
    }
    public static function upload(array $file, array $input): array
    {
        if (!current_user_can('gmpp_edit') || !current_user_can('upload_files')) {
            throw new \DomainException(__('You cannot upload documents.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException(__('Invalid upload.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        return self::store_bytes((string)file_get_contents($file['tmp_name']), $file['name'], $input);
    }
    public static function store_bytes(string $bytes, string $name, array $input): array
    {
        $name = sanitize_file_name(wp_basename($name));
        if (strlen($bytes) > self::MAX_BYTES || !$bytes) {
            throw new \InvalidArgumentException(__('Choose a non-empty file up to 10 MB.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf' => 'application/pdf','png' => 'image/png','jpg' => 'image/jpeg','jpeg' => 'image/jpeg','txt' => 'text/plain'];
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            throw new \InvalidArgumentException(__('Invalid format: use PDF, PNG, JPG or plain text with matching extension and content.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        if ($mime === 'application/pdf' && !str_starts_with($bytes, '%PDF-')) {
            throw new \InvalidArgumentException(__('Invalid PDF.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        if (($input['visibility'] ?? '') === 'private' && !current_user_can('gmpp_review')) {
            throw new \DomainException(__('Only a reviewer can upload private documents.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        $id = absint($input['document_id'] ?? 0);
        $existing = $id ? Store::one('documents', $id) : null;
        $expected = absint($input['expected_version'] ?? ($existing['current_version'] ?? 0));
        if ($id && !Store::one('documents', $id)) {
            throw new \InvalidArgumentException(__('Document not found.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        if ($id && !current_user_can('gmpp_review')) {
            throw new \DomainException(__('A reviewer must update shared documents.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        $title = sanitize_text_field($input['title'] ?? $name);
        if ($title === '' || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException(__('Enter a document title up to 255 characters.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
                throw new \RuntimeException(__('Another user updated this document. Reload before uploading a new version.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,title,data,parent_id FROM %i', Store::table('passports')), ARRAY_A);
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
        return (bool)$wpdb->get_var($wpdb->prepare('SELECT r.id FROM %i r INNER JOIN %i p ON p.published_revision=r.id WHERE r.snapshot LIKE %s LIMIT 1', Store::table(__('revisions', 'giuliomax-product-passport')), Store::table('passports'), $like));
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
        $bytes = $wpdb->get_var($wpdb->prepare('SELECT content FROM %i WHERE id=%d', Store::table('document_versions'), $id));
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

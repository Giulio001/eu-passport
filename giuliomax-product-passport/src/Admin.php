<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Admin
{
    public function hooks(): void
    {
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'privacy']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        add_action('admin_post_gmpp_export', [$this,'export']);
        add_action('admin_post_gmpp_labels', [$this,'labels_page']);
    }
    public function privacy(): void
    {
        if (function_exists('wp_add_privacy_policy_content')) {
            wp_add_privacy_policy_content('Giuliomax Product Passport', '<p>' . esc_html__('Product sheets publish the company contacts and product information selected by the operator. Internal editorial records retain user IDs, timestamps and reasons. Supplier proposal links expire and are stored as hashes. Private documents stay in the database and require authorization. No telemetry or AI requests are sent. Supplier emails use the site mail transport only after explicit administrative enablement. External image URLs selected by an operator may disclose visitor IP addresses to the image host. Data is retained on uninstall. Define retention periods, restrict hosting and backup access, and avoid unnecessary personal information in public sheets.', 'giuliomax-product-passport') . '</p>');
        }
    }
    public function menu(): void
    {
        add_menu_page('Product Passport', 'Product Passport', 'gmpp_edit', 'gmpp', [$this,'page'], 'dashicons-id', 57);
        foreach (['gmpp' => __('Overview', 'giuliomax-product-passport'),'gmpp-passports' => __('Passports', 'giuliomax-product-passport'),'gmpp-documents' => __('Documents', 'giuliomax-product-passport'),'gmpp-designs' => __('Design & templates', 'giuliomax-product-passport'),'gmpp-settings' => __('Settings', 'giuliomax-product-passport')] as $slug => $label) {
            add_submenu_page('gmpp', $label, $label, $slug === 'gmpp-settings' ? 'gmpp_manage' : 'gmpp_edit', $slug, [$this,'page']);
        }
    }
    public static function labels(): array
    {
        return ['name' => __('Product name', 'giuliomax-product-passport'),'description' => __('Description', 'giuliomax-product-passport'),'sku' => __('Reference / SKU', 'giuliomax-product-passport'),'gtin' => __('GTIN (if available)', 'giuliomax-product-passport'),'batch' => __('Batch reference', 'giuliomax-product-passport'),'serial' => __('Serial number', 'giuliomax-product-passport'),'family' => __('Product family', 'giuliomax-product-passport'),'materials' => __('Materials and composition', 'giuliomax-product-passport'),'care' => __('Care and maintenance', 'giuliomax-product-passport'),'safety' => __('Safety and warnings', 'giuliomax-product-passport'),'end_of_life' => __('End of life and disposal', 'giuliomax-product-passport'),'spares' => __('Spare parts and compatibility', 'giuliomax-product-passport'),'support' => __('Support details and instructions', 'giuliomax-product-passport'),'notice' => __('Safety or discontinuation notice', 'giuliomax-product-passport'),'image' => __('Product image', 'giuliomax-product-passport'),'support_url' => __('Support page', 'giuliomax-product-passport'),'original_url' => __('Original manufacturer passport', 'giuliomax-product-passport'),'successor_url' => __('Successor product', 'giuliomax-product-passport'),'external_id' => __('External identifier (unverified)', 'giuliomax-product-passport'),'environmental_value' => __('Declared environmental value', 'giuliomax-product-passport'),'unit' => __('Unit', 'giuliomax-product-passport'),'method' => __('Measurement method', 'giuliomax-product-passport'),'source' => __('Source / evidence', 'giuliomax-product-passport'),'data_date' => __('Data date', 'giuliomax-product-passport'),'responsible' => __('Internal data owner', 'giuliomax-product-passport')];
    }
    public function assets(): void
    {
        if (!str_starts_with(sanitize_key(wp_unslash($_GET['page'] ?? '')), 'gmpp')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing and product selector; mutations use REST nonce and capabilities.
            return;
        }
        wp_enqueue_style('gmpp-admin', plugins_url('assets/style.css', GMPP_FILE), [], GMPP_VERSION);
        $designer = sanitize_key(wp_unslash($_GET['page'] ?? '')) === 'gmpp-designs'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset selection.
        if ($designer) {
            wp_enqueue_style('gmpp-designer', plugins_url('assets/designer.css', GMPP_FILE), [], GMPP_VERSION);
        }
        $media = current_user_can('upload_files');
        if ($media) {
            wp_enqueue_media();
        }
        wp_enqueue_script('gmpp-admin', plugins_url($designer ? 'assets/designer.js' : 'assets/admin.js', GMPP_FILE), $media ? ['wp-i18n','media-views'] : ['wp-i18n'], GMPP_VERSION, true);
        wp_set_script_translations('gmpp-admin', 'giuliomax-product-passport', GMPP_DIR . 'languages');
        wp_localize_script('gmpp-admin', 'GMPP', ['version' => GMPP_VERSION,'api' => rest_url('gmpp/v1/'),'nonce' => wp_create_nonce('wp_rest'),'preview_nonce' => wp_create_nonce('gmpp_preview'),'download_nonce' => wp_create_nonce('gmpp_download'),'admin' => admin_url('admin-post.php'),'home' => home_url('/'),'page' => sanitize_key(wp_unslash($_GET['page'] ?? 'gmpp')),'settings' => array_intersect_key(Store::settings(), array_flip(['company','language','sector','mode','accent'])),'labels' => self::labels(),'caps' => ['upload' => $media,'manage' => current_user_can('gmpp_manage'),'review' => current_user_can('gmpp_review'),'publish' => current_user_can('gmpp_publish')],'wc' => absint($_GET['wc'] ?? 0)]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing and product selector; all writes use REST nonce and capability checks.
    }
    public function page(): void
    {
        echo '<div class="wrap gmpp-app" id="gmpp-app"><div class="gmpp-loading" role="status">' . esc_html__('Loading passports…', 'giuliomax-product-passport') . '</div></div>';
    }
    public function export(): void
    {
        if (!current_user_can('gmpp_manage')) {
            wp_die(esc_html__('Access denied.', 'giuliomax-product-passport'), '', ['response' => 403]);
        }
        check_admin_referer('gmpp_download');
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        if (sanitize_key(wp_unslash($_GET['scope'] ?? '')) === 'public') {
            global $wpdb;
            $out = [];
            foreach ($wpdb->get_col($wpdb->prepare('SELECT uuid FROM %i WHERE published_revision>0', Store::table('passports'))) as $uuid) {
                $out[] = Passport::published($uuid);
            }
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="product-passports-public.json"');
            echo Store::json(['format' => 1,'passports' => $out]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON attachment, encoded by wp_json_encode; not HTML.
            exit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        try {
            $path = Portable::export();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="giuliomax-passports-' . gmdate('Y-m-d') . '.zip"');
            header('Content-Length: ' . filesize($path));
            readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream our locally generated temporary ZIP; filesystem credentials must not interrupt the authorized download.
            wp_delete_file($path);
            exit;
        } catch (\Throwable $e) {
            wp_die(esc_html($e->getMessage()));
        }
    }
    public function labels_page(): void
    {
        if (!current_user_can('gmpp_edit')) {
            wp_die(esc_html__('Access denied.', 'giuliomax-product-passport'), '', ['response' => 403]);
        }
        check_admin_referer('gmpp_download');
        nocache_headers();
        header('X-Robots-Tag: noindex');
        $ids = array_slice(array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_GET['ids'] ?? ''))))), 0, 1000);
        $s = Store::settings();
        echo ('<!doctype html><html lang="' . esc_attr(str_replace('_', '-', get_user_locale())) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__('Product labels', 'giuliomax-product-passport') . '</title><style>body{font:12px system-ui;margin:0}button{padding:12px;margin:12px}main{display:flex;flex-wrap:wrap;gap:0;padding:') . (int)$s['label_margin'] . 'mm}.label{box-sizing:border-box;width:' . (int)$s['label_width'] . 'mm;height:' . (int)$s['label_height'] . 'mm;border:1px dashed #bbb;display:flex;align-items:center;padding:3mm;gap:3mm;break-inside:avoid;overflow:hidden}.label svg{width:28mm;height:28mm;flex-shrink:0}.label span{overflow-wrap:anywhere}small{display:block;margin-top:4px}@media print{button,p{display:none}main{padding:0}.label{border-color:transparent}}@page{size:A4;margin:' . (int)$s['label_margin'] . ('mm}</style></head><body><button onclick="window.print()">' . esc_html__('Print / save PDF', 'giuliomax-product-passport') . '</button><p>' . esc_html__('Test a scan at the actual size before printing the full batch.', 'giuliomax-product-passport') . '</p><main>');
        foreach ($ids as $id) {
            try {
                $row = Passport::get($id);
                if (!Store::can($row)) {
                    continue;
                } $snap = Passport::published($row['uuid']);
                if (!$snap) {
                    continue;
                } echo '<div class="label">' . Qr::svg($row['url']) . '<span><strong>' . esc_html(mb_strimwidth($snap['data']['name'], 0, 90, '…')) . '</strong><small>' . esc_html($snap['data']['sku'] ?? $snap['data']['serial'] ?? '') . '</small></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Qr::svg emits only a fixed SVG element with integer coordinates.
            } catch (\Throwable $e) {
                continue;
            }
        } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is internally generated.
        echo '</main></body></html>';
        exit;
    }
}

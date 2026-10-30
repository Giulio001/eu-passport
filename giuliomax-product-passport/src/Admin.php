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
        foreach (['gmpp' => 'Panoramica','gmpp-passports' => 'Passaporti','gmpp-documents' => 'Documenti','gmpp-settings' => 'Impostazioni'] as $slug => $label) {
            add_submenu_page('gmpp', $label, $label, $slug === 'gmpp-settings' ? 'gmpp_manage' : 'gmpp_edit', $slug, [$this,'page']);
        }
    }
    public static function labels(): array
    {
        return ['name' => 'Nome del prodotto','description' => 'Descrizione','sku' => 'Riferimento / SKU','gtin' => 'GTIN (se disponibile)','batch' => 'Codice lotto','serial' => 'Numero di serie','family' => 'Famiglia di prodotti','materials' => 'Materiali e composizione','care' => 'Cura e manutenzione','safety' => 'Sicurezza e avvertenze','end_of_life' => 'Fine vita e smaltimento','spares' => 'Ricambi e compatibilità','support' => 'Contatti e istruzioni di assistenza','notice' => 'Avviso di sicurezza o fuori produzione','image' => 'Immagine prodotto','support_url' => 'Pagina assistenza','original_url' => 'Passaporto originale del produttore','successor_url' => 'Prodotto successore','external_id' => 'Identificatore esterno (non verificato)','environmental_value' => 'Valore ambientale dichiarato','unit' => 'Unità di misura','method' => 'Metodo di misurazione','source' => 'Fonte / evidenza','data_date' => 'Data del dato','responsible' => 'Responsabile interno del dato'];
    }
    public function assets(): void
    {
        if (!str_starts_with(sanitize_key(wp_unslash($_GET['page'] ?? '')), 'gmpp')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing and product selector; mutations use REST nonce and capabilities.
            return;
        }
        wp_enqueue_style('gmpp-admin', plugins_url('assets/style.css', GMPP_FILE), [], GMPP_VERSION);
        wp_enqueue_script('gmpp-admin', plugins_url('assets/admin.js', GMPP_FILE), ['wp-i18n'], GMPP_VERSION, true);
        wp_set_script_translations('gmpp-admin', 'giuliomax-product-passport', GMPP_DIR . 'languages');
        wp_localize_script('gmpp-admin', 'GMPP', ['version' => GMPP_VERSION,'ui_language' => Store::settings()['ui_language'], 'english' => Store::decode((string)file_get_contents(GMPP_DIR . 'languages/en.json')), 'api' => rest_url('gmpp/v1/'),'nonce' => wp_create_nonce('wp_rest'),'preview_nonce' => wp_create_nonce('gmpp_preview'),'download_nonce' => wp_create_nonce('gmpp_download'),'admin' => admin_url('admin-post.php'),'home' => home_url('/'),'page' => sanitize_key(wp_unslash($_GET['page'] ?? 'gmpp')),'settings' => array_intersect_key(Store::settings(), array_flip(['company','language','sector','mode','accent'])),'labels' => self::labels(),'caps' => ['manage' => current_user_can('gmpp_manage'),'review' => current_user_can('gmpp_review'),'publish' => current_user_can('gmpp_publish')],'wc' => absint($_GET['wc'] ?? 0)]);
    }
    public function page(): void
    {
        echo '<div class="wrap gmpp-app" id="gmpp-app"><div class="gmpp-loading" role="status">' . esc_html__('Caricamento dei passaporti…', 'giuliomax-product-passport') . '</div></div>';
    }
    public function export(): void
    {
        if (!current_user_can('gmpp_manage')) {
            wp_die('Accesso negato.', '', ['response' => 403]);
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
            wp_die('Accesso negato.', '', ['response' => 403]);
        }
        check_admin_referer('gmpp_download');
        nocache_headers();
        header('X-Robots-Tag: noindex');
        $ids = array_slice(array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_GET['ids'] ?? ''))))), 0, 1000);
        $s = Store::settings();
        echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Etichette prodotto</title><style>body{font:12px system-ui;margin:0}button{padding:12px;margin:12px}main{display:flex;flex-wrap:wrap;gap:0;padding:' . (int)$s['label_margin'] . 'mm}.label{box-sizing:border-box;width:' . (int)$s['label_width'] . 'mm;height:' . (int)$s['label_height'] . 'mm;border:1px dashed #bbb;display:flex;align-items:center;padding:3mm;gap:3mm;break-inside:avoid;overflow:hidden}.label svg{width:28mm;height:28mm;flex-shrink:0}.label span{overflow-wrap:anywhere}small{display:block;margin-top:4px}@media print{button,p{display:none}main{padding:0}.label{border-color:transparent}}@page{size:A4;margin:' . (int)$s['label_margin'] . 'mm}</style></head><body><button onclick="window.print()">Stampa / salva PDF</button><p>Controlla una scansione di prova alla dimensione effettiva prima di stampare l’intero lotto.</p><main>';
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

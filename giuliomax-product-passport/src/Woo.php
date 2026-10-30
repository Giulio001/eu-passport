<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Woo
{
    public function hooks(): void
    {
        add_action('woocommerce_single_product_summary', [$this,'link'], 35);
        add_filter('post_row_actions', [$this,'action'], 10, 2);
    }
    public static function products(string $search): array
    {
        if (!function_exists('wc_get_products')) {
            return ['available' => false,'products' => []];
        }
        if (!current_user_can('edit_products')) {
            throw new \DomainException('Permesso WooCommerce richiesto.');
        }
        $products = wc_get_products(['limit' => 30,'status' => ['publish','draft','private'],'s' => $search,'type' => ['simple','variable','variation']]);
        return ['available' => true,'products' => array_map(static fn($p)=>['id' => $p->get_id(),'name' => $p->get_name(),'sku' => $p->get_sku(),'type' => $p->get_type(),'children' => $p->is_type('variable') ? $p->get_children() : []], $products)];
    }
    public static function import(int $id): array
    {
        if (!function_exists('wc_get_product')) {
            throw new \RuntimeException('WooCommerce non è attivo.');
        }
        if (!current_user_can('edit_post', $id)) {
            throw new \DomainException('Prodotto WooCommerce non accessibile.');
        }
        $p = wc_get_product($id);
        if (!$p) {
            throw new \OutOfBoundsException('Prodotto non trovato.');
        }
        $data = ['name' => $p->get_name(),'description' => wp_strip_all_tags($p->get_description()),'sku' => $p->get_sku(),'image' => wp_get_attachment_url($p->get_image_id()) ?: ''];
        $attributes = [];
        foreach ($p->get_attributes() as $name => $attribute) {
            if ($attribute instanceof \WC_Product_Attribute) {
                $attributes[wc_attribute_label($attribute->get_name())] = $p->get_attribute($attribute->get_name());
            } else {
                $attributes[wc_attribute_label((string)$name)] = (string)$attribute;
            }
        }
        $data['attributes'] = $attributes;
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Store::table('passports') . ' WHERE wc_id=%d', $id));
        if ($existing) {
            $row = Passport::get((int)$existing);
            if (!Store::can($row)) {
                throw new \DomainException('Scheda non accessibile.');
            } return ['existing' => $row,'suggested' => $data,'message' => 'Confronta i dati commerciali: nessuna modifica viene applicata automaticamente.'];
        }
        $parent = 0;
        if ($p->is_type('variation')) {
            $result = self::import($p->get_parent_id());
            $parent = (int)($result['id'] ?? $result['existing']['id']);
        }
        return Passport::create(['data' => $data,'wc_id' => $id,'kind' => $parent ? 'variant' : 'model','parent_id' => $parent]);
    }
    public function link(): void
    {
        global $product,$wpdb;
        if (!$product || !is_callable([$product,'get_id'])) {
            return;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT uuid,published_revision FROM ' . Store::table('passports') . ' WHERE wc_id=%d', $product->get_id()), ARRAY_A);
        if ($row && $row['published_revision']) {
            echo '<p><a class="button" href="' . esc_url(Passport::url($row['uuid'])) . '">' . esc_html__('Informazioni e manuali del prodotto', 'giuliomax-product-passport') . '</a></p>';
        }
    }
    public function action(array $actions, \WP_Post $post): array
    {
        if ($post->post_type === 'product' && current_user_can('gmpp_edit')) {
            $actions['gmpp'] = '<a href="' . esc_url(admin_url('admin.php?page=gmpp-passports&wc=' . $post->ID)) . '">' . esc_html__('Crea o collega scheda digitale', 'giuliomax-product-passport') . '</a>';
        }
        return $actions;
    }
}

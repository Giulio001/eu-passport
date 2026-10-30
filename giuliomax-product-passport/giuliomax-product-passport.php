<?php

/**
 * Plugin Name: Giuliomax Product Passport
 * Description: Product information sheets, versioned documents and local QR codes, with optional WooCommerce integration.
 * Version: 1.1.1
 * Plugin URI: https://github.com/Giulio001/eu-passport
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Giuliomax
 * License: GPL-2.0-or-later
 * Text Domain: giuliomax-product-passport
 * Domain Path: /languages
 */

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
define('GMPP_FILE', __FILE__);
define('GMPP_DIR', __DIR__ . '/');
define('GMPP_VERSION', '1.1.1');
spl_autoload_register(static function ($class) {
    $prefix = __NAMESPACE__ . '\\';
    if (str_starts_with($class, $prefix)) {
        $path = GMPP_DIR . 'src/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
register_activation_hook(__FILE__, [Schema::class, 'activate']);
register_deactivation_hook(__FILE__, static function () {
    wp_clear_scheduled_hook('gmpp_jobs');
    wp_clear_scheduled_hook('gmpp_jobs_continue');
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('gmpp_jobs_continue', [], 'gmpp');
    }
    flush_rewrite_rules();
});
add_action('plugins_loaded', static function () {
    if (get_option('gmpp_schema') !== Schema::VERSION) {
        Schema::activate();
    }
    (new Admin())->hooks();
    (new PublicPage())->hooks();
    (new Api())->hooks();
    (new Woo())->hooks();
    add_action('gmpp_jobs', [Jobs::class, 'tick']);
    add_action('gmpp_jobs_continue', [Jobs::class, 'tick']);
    if (!wp_next_scheduled('gmpp_jobs')) {
        wp_schedule_event(time() + 60, 'hourly', 'gmpp_jobs');
    }
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gmpp jobs', static function () {
            Jobs::tick();
            \WP_CLI::success(__('Queue processed.', 'giuliomax-product-passport'));
        });
    }
});

add_action('init', [I18n::class, 'load'], 0);

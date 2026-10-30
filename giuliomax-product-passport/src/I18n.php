<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;

/** Standard gettext catalogues; public controls follow the requested content language. */
final class I18n
{
    public static function load(): void
    {
        // Register the bundled catalogue path for ZIP installs outside the directory too.
        load_plugin_textdomain('giuliomax-product-passport', false, dirname(plugin_basename(GMPP_FILE)) . '/languages');
        $roles = wp_roles();
        foreach (['gmpp_author' => __('Passport author', 'giuliomax-product-passport'),'gmpp_reviewer' => __('Passport reviewer', 'giuliomax-product-passport'),'gmpp_publisher' => __('Passport publisher', 'giuliomax-product-passport')] as $key => $label) {
            if (isset($roles->roles[$key])) {
                $roles->roles[$key]['name'] = $label;
                $roles->role_names[$key] = $label;
            }
        }
    }
    public static function with_language(string $language, callable $render): string
    {
        $locales = ['en' => 'en_US','it' => 'it_IT','fr' => 'fr_FR','de' => 'de_DE','es' => 'es_ES'];
        $locale = $locales[$language] ?? str_replace('-', '_', Passport::language($language));
        $catalogue = null;
        $paths = [WP_LANG_DIR . '/plugins/giuliomax-product-passport-' . $locale . '.mo', GMPP_DIR . 'languages/giuliomax-product-passport-' . $locale . '.mo'];
        require_once ABSPATH . WPINC . '/pomo/mo.php';
        foreach ($paths as $path) {
            if (is_readable($path)) {
                $catalogue = new \MO();
                if ($catalogue->import_from_file($path)) {
                    break;
                }
                $catalogue = null;
            }
        }
        // A product language need not have a corresponding WordPress core pack installed.
        $translate = static fn($translated, $original)=>$catalogue ? $catalogue->translate($original) : $original;
        add_filter('gettext_giuliomax-product-passport', $translate, 100, 2);
        try {
            return $render();
        } finally {
            remove_filter('gettext_giuliomax-product-passport', $translate, 100);
        }
    }
}

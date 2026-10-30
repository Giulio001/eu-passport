<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Schema
{
    public const VERSION = '2';
    public static function activate(): void
    {
        foreach (['mbstring','fileinfo','iconv','zlib','zip'] as $extension) {
            if (!extension_loaded($extension)) {
                wp_die(esc_html('Giuliomax Product Passport richiede l’estensione PHP ' . $extension . '. Chiedi al tuo hosting di abilitarla, poi riattiva il plugin.'));
            }
        }
        if (is_multisite()) {
            wp_die(esc_html('Questa versione supporta installazioni WordPress singole. Il multisito richiede una migrazione dedicata per ogni sito.'));
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'gmpp_';
        $sql = [
            "CREATE TABLE {$p}passports (
id bigint unsigned NOT NULL AUTO_INCREMENT,
uuid varchar(36) NOT NULL,
identity_key varchar(64) NOT NULL,
title varchar(255) NOT NULL DEFAULT '',
sku varchar(190) NOT NULL DEFAULT '',
kind varchar(16) NOT NULL DEFAULT 'model',
parent_id bigint unsigned NOT NULL DEFAULT 0,
wc_id bigint unsigned DEFAULT NULL,
owner bigint unsigned NOT NULL DEFAULT 0,
state varchar(16) NOT NULL DEFAULT 'draft',
lock_version bigint unsigned NOT NULL DEFAULT 1,
published_revision bigint unsigned NOT NULL DEFAULT 0,
data longtext NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY uuid (uuid),
UNIQUE KEY identity_key (identity_key),
UNIQUE KEY wc_id (wc_id),
KEY title (title(100)),
KEY sku (sku),
KEY state_id (state,id),
KEY parent_id (parent_id)
) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}revisions (
id bigint unsigned NOT NULL AUTO_INCREMENT,
passport_id bigint unsigned NOT NULL,
snapshot longtext NOT NULL,
digest varchar(64) NOT NULL,
author bigint unsigned NOT NULL,
reason text NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY passport_id (passport_id,id)
) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}documents (
id bigint unsigned NOT NULL AUTO_INCREMENT,
uuid varchar(36) NOT NULL,
title varchar(255) NOT NULL,
owner bigint unsigned NOT NULL DEFAULT 0,
current_version bigint unsigned NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
UNIQUE KEY uuid (uuid)
) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}document_versions (
id bigint unsigned NOT NULL AUTO_INCREMENT,
document_id bigint unsigned NOT NULL,
metadata longtext NOT NULL,
storage_path text NOT NULL,
content longblob NOT NULL,
digest varchar(64) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY document_id (document_id,id)
) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}requests (
id bigint unsigned NOT NULL AUTO_INCREMENT,
token_hash varchar(64) NOT NULL,
passport_id bigint unsigned NOT NULL,
fields text NOT NULL,
proposal longtext NOT NULL,
state varchar(16) NOT NULL,
expires_at datetime NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY token_hash (token_hash),
KEY state (state)
) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}jobs (
id bigint unsigned NOT NULL AUTO_INCREMENT,
kind varchar(32) NOT NULL,
state varchar(16) NOT NULL,
owner bigint unsigned NOT NULL,
lease_token varchar(36) NOT NULL DEFAULT '',
`cursor` bigint unsigned NOT NULL DEFAULT 0,
total bigint unsigned NOT NULL DEFAULT 0,
payload longtext NOT NULL,
report longtext NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY state (state)
) ENGINE=InnoDB $c;",
            "CREATE TABLE {$p}events (
id bigint unsigned NOT NULL AUTO_INCREMENT,
passport_id bigint unsigned NOT NULL,
author bigint unsigned NOT NULL,
action varchar(40) NOT NULL,
detail text NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY passport_id (passport_id,id)
) ENGINE=InnoDB $c;"
        ];
        foreach ($sql as $statement) {
            dbDelta($statement);
        }
        update_option('gmpp_schema', self::VERSION, false);
        add_option('gmpp_origin', home_url('/'), '', false);
        $caps = ['read' => true, 'gmpp_edit' => true, 'upload_files' => true];
        add_role('gmpp_author', 'Compilatore passaporti', $caps);
        add_role('gmpp_reviewer', 'Revisore passaporti', $caps + ['gmpp_edit_others' => true, 'gmpp_review' => true]);
        add_role('gmpp_publisher', 'Responsabile passaporti', $caps + ['gmpp_edit_others' => true, 'gmpp_review' => true, 'gmpp_publish' => true]);
        $admin = get_role('administrator');
        foreach (['gmpp_edit','gmpp_edit_others','gmpp_review','gmpp_publish','gmpp_manage'] as $cap) {
            if ($admin) {
                $admin->add_cap($cap);
            }
        }
    }
}

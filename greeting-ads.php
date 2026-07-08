<?php

/**
 * Plugin Name: Greeting Ads
 * Description: Plugin untuk mengimpor dan mengelola data CSV dengan fitur CRUD.
 * Version: 1.1.0
 * Author: Velocity Developer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Konstanta untuk nama tabel database
define('GREETING_ADS_TABLE', 'greeting_ads_data');
define('VD_WA_CLICKS_TABLE', 'vd_whatsapp_clicks');
define('VD_WA_CLICKS_SCHEMA_VERSION', '2.1.0');
define('VD_FORM_FUNNEL_TABLE', 'vd_form_funnel');
define('VD_FORM_FUNNEL_SCHEMA_VERSION', '1.0.0');

// Memuat file tambahan
require_once plugin_dir_path(__FILE__) . 'includes/import-csv.php';
require_once plugin_dir_path(__FILE__) . 'includes/crud-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/function.php';
require_once plugin_dir_path(__FILE__) . 'includes/lead-queue.php';
require_once plugin_dir_path(__FILE__) . 'includes/form-funnel.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/form-chat.php';
require_once plugin_dir_path(__FILE__) . 'includes/floating-whatsapp.php';
require_once plugin_dir_path(__FILE__) . 'includes/page-rekap.php';
require_once plugin_dir_path(__FILE__) . 'api/greeting.php';
require_once plugin_dir_path(__FILE__) . 'api/vdnet.php';
require_once plugin_dir_path(__FILE__) . 'includes/sync-api.php'; // API untuk sync data dari apivdcom
require_once plugin_dir_path(__FILE__) . 'includes/klien-kami.php';

// Hook untuk menambahkan menu admin
add_action('admin_menu', 'greeting_ads_add_menu');
function greeting_ads_add_menu()
{
    add_menu_page(
        'Greeting Ads',
        'Greeting Ads',
        'manage_options',
        'greeting-ads',
        'greeting_ads_admin_page',
        'dashicons-admin-generic',
        6
    );
}

// Halaman admin plugin
function greeting_ads_admin_page()
{
?>
    <div class="wrap">
        <h1>Greeting Ads</h1>
        <?php include plugin_dir_path(__FILE__) . 'includes/table-display.php'; ?>
    </div>
<?php
}

// Hook untuk membuat tabel database saat plugin diaktifkan
register_activation_hook(__FILE__, 'greeting_ads_create_table');
function greeting_ads_create_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        kata_kunci text NOT NULL,
        grup_iklan text NOT NULL,
        id_grup_iklan text NOT NULL,
        nomor_kata_kunci text NOT NULL,
        greeting text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Ensure WhatsApp clicks table is ready with latest schema.
    vd_maybe_upgrade_whatsapp_clicks_table(true);
    vd_maybe_upgrade_lead_queue_table(true);
    vd_maybe_upgrade_form_funnel_table(true);
}

// Hook untuk menghapus tabel saat plugin dihapus
register_uninstall_hook(__FILE__, 'greeting_ads_uninstall');
function greeting_ads_uninstall()
{
    global $wpdb;
    $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/**
 * Runtime migration for WhatsApp click logs table.
 * Runs once per schema version and is safe to call multiple times.
 */
function vd_maybe_upgrade_whatsapp_clicks_table($force = false)
{
    $installed = get_option('vd_wa_clicks_schema_version', '');
    if (!$force && $installed === VD_WA_CLICKS_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . VD_WA_CLICKS_TABLE;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id char(36) NULL,
        ip_address varchar(45) NOT NULL DEFAULT 'unknown',
        user_agent text NOT NULL,
        referer text NULL,
        greeting varchar(191) NULL,
        status varchar(20) NOT NULL DEFAULT 'success',
        retry_count smallint(5) unsigned NOT NULL DEFAULT 0,
        last_error text NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY event_id (event_id),
        KEY created_at (created_at),
        KEY status (status)
    ) $charset_collate;";

    dbDelta($sql);

    update_option('vd_wa_clicks_schema_version', VD_WA_CLICKS_SCHEMA_VERSION, false);
}

add_action('init', 'vd_maybe_upgrade_whatsapp_clicks_table', 5);

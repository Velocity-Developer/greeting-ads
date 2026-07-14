<?php

/**
 * AJAX Log - mencatat log proses AJAX untuk monitoring.
 *
 * Digunakan oleh vd_log_ajax() untuk menyimpan keberhasilan/kegagalan
 * setiap pemrosesan endpoint AJAX.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('VD_AJAX_LOG_TABLE')) {
    define('VD_AJAX_LOG_TABLE', 'vd_ajax_log');
}

if (!defined('VD_AJAX_LOG_SCHEMA_VERSION')) {
    define('VD_AJAX_LOG_SCHEMA_VERSION', '1.0.0');
}

add_action('init', 'vd_maybe_upgrade_ajax_log_table', 7);

/**
 * Membuat / meng-upgrade tabel vd_ajax_log.
 */
function vd_maybe_upgrade_ajax_log_table($force = false)
{
    $installed = get_option('vd_ajax_log_schema_version', '');
    if (!$force && $installed === VD_AJAX_LOG_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . VD_AJAX_LOG_TABLE;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        action varchar(100) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'success',
        message text NULL,
        request_data longtext NULL,
        ip_address varchar(45) NOT NULL DEFAULT 'unknown',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY action (action),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql);

    update_option('vd_ajax_log_schema_version', VD_AJAX_LOG_SCHEMA_VERSION, false);
}

/**
 * Mencatat log proses AJAX ke tabel vd_ajax_log.
 *
 * @param string $action       Nama action AJAX (contoh: 'rekap_chat_form').
 * @param string $status       'success' atau 'failed'.
 * @param string $message      Pesan deskriptif (opsional).
 * @param mixed  $request_data Data request yang relevan (array/string, opsional).
 * @return bool|int            false jika gagal, atau ID baris yang di-insert.
 */
function vd_log_ajax($action, $status = 'success', $message = '', $request_data = null)
{
    global $wpdb;

    $table_name = $wpdb->prefix . VD_AJAX_LOG_TABLE;

    // Pastikan tabel ada
    vd_maybe_upgrade_ajax_log_table();

    $action  = sanitize_text_field((string) $action);
    $status  = in_array($status, ['success', 'failed'], true) ? $status : 'success';
    $message = sanitize_text_field((string) $message);

    if ($request_data !== null) {
        if (is_array($request_data) || is_object($request_data)) {
            $request_data = wp_json_encode($request_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $request_data = sanitize_textarea_field((string) $request_data);
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';

    $result = $wpdb->insert(
        $table_name,
        [
            'action'       => $action,
            'status'       => $status,
            'message'      => $message ?: null,
            'request_data'  => $request_data ?: null,
            'ip_address'   => $ip,
            'created_at'   => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($result === false) {
        return false;
    }

    return (int) $wpdb->insert_id;
}

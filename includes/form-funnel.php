<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!defined('VD_FORM_FUNNEL_TABLE')) {
  define('VD_FORM_FUNNEL_TABLE', 'vd_form_funnel');
}

if (!defined('VD_FORM_FUNNEL_SCHEMA_VERSION')) {
  define('VD_FORM_FUNNEL_SCHEMA_VERSION', '1.0.0');
}

add_action('init', 'vd_maybe_upgrade_form_funnel_table', 6);

function vd_maybe_upgrade_form_funnel_table($force = false)
{
  $installed = get_option('vd_form_funnel_schema_version', '');
  if (!$force && $installed === VD_FORM_FUNNEL_SCHEMA_VERSION) {
    return;
  }

  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $table_name = $wpdb->prefix . VD_FORM_FUNNEL_TABLE;

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $sql = "CREATE TABLE $table_name (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id char(36) NOT NULL,
    session_key varchar(64) NOT NULL,
    event_type varchar(30) NOT NULL,
    page_url text NULL,
    referer text NULL,
    device varchar(20) NULL,
    greeting varchar(191) NULL,
    traffic_source varchar(50) NULL,
    label varchar(191) NULL,
    utm_content varchar(191) NULL,
    utm_medium varchar(191) NULL,
    ip_address varchar(45) NOT NULL DEFAULT 'unknown',
    user_agent text NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY event_id (event_id),
    KEY event_type (event_type),
    KEY session_key (session_key),
    KEY created_at (created_at)
  ) $charset_collate;";

  dbDelta($sql);

  update_option('vd_form_funnel_schema_version', VD_FORM_FUNNEL_SCHEMA_VERSION, false);
}

function vd_generate_form_funnel_event_id()
{
  if (function_exists('wp_generate_uuid4')) {
    return wp_generate_uuid4();
  }

  return uniqid('vdff_', true);
}

function vd_insert_form_funnel_event($payload)
{
  global $wpdb;

  $table_name = $wpdb->prefix . VD_FORM_FUNNEL_TABLE;

  $defaults = [
    'event_id' => vd_generate_form_funnel_event_id(),
    'session_key' => '',
    'event_type' => '',
    'page_url' => '',
    'referer' => '',
    'device' => '',
    'greeting' => '',
    'traffic_source' => '',
    'label' => '',
    'utm_content' => '',
    'utm_medium' => '',
    'ip_address' => 'unknown',
    'user_agent' => '',
    'created_at' => current_time('mysql'),
  ];

  $payload = wp_parse_args($payload, $defaults);

  $inserted = $wpdb->insert(
    $table_name,
    [
      'event_id' => $payload['event_id'],
      'session_key' => $payload['session_key'],
      'event_type' => $payload['event_type'],
      'page_url' => $payload['page_url'],
      'referer' => $payload['referer'],
      'device' => $payload['device'],
      'greeting' => $payload['greeting'],
      'traffic_source' => $payload['traffic_source'],
      'label' => $payload['label'],
      'utm_content' => $payload['utm_content'],
      'utm_medium' => $payload['utm_medium'],
      'ip_address' => $payload['ip_address'],
      'user_agent' => $payload['user_agent'],
      'created_at' => $payload['created_at'],
    ],
    [
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
      '%s',
    ]
  );

  if ($inserted === false) {
    return new WP_Error('db_insert_error', $wpdb->last_error ?: 'Gagal menyimpan event funnel.');
  }

  return [
    'id' => (int) $wpdb->insert_id,
    'event_id' => $payload['event_id'],
  ];
}

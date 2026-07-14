<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!defined('VD_LEAD_QUEUE_TABLE')) {
  define('VD_LEAD_QUEUE_TABLE', 'vd_lead_queue');
}

if (!defined('VD_LEAD_QUEUE_SCHEMA_VERSION')) {
  define('VD_LEAD_QUEUE_SCHEMA_VERSION', '1.0.0');
}

if (!defined('VD_LEAD_QUEUE_CRON_HOOK')) {
  define('VD_LEAD_QUEUE_CRON_HOOK', 'vd_process_lead_queue_cron');
}

add_action('init', 'vd_maybe_upgrade_lead_queue_table', 6);
add_action('wp_ajax_vd_process_lead_queue', 'vd_process_lead_queue_ajax');
add_action('wp_ajax_nopriv_vd_process_lead_queue', 'vd_process_lead_queue_ajax');
add_action(VD_LEAD_QUEUE_CRON_HOOK, 'vd_process_lead_queue_job', 10, 1);

function vd_maybe_upgrade_lead_queue_table($force = false)
{
  $installed = get_option('vd_lead_queue_schema_version', '');
  if (!$force && $installed === VD_LEAD_QUEUE_SCHEMA_VERSION) {
    return;
  }

  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $table_name = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $sql = "CREATE TABLE $table_name (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id char(36) NOT NULL,
    nama varchar(191) NOT NULL,
    no_whatsapp varchar(30) NOT NULL,
    jenis_website text NOT NULL,
    via varchar(20) NOT NULL DEFAULT '',
    utm_content varchar(191) NULL,
    utm_medium varchar(191) NULL,
    greeting varchar(191) NULL,
    gclid varchar(191) NULL,
    label varchar(191) NULL,
    ai_result varchar(20) NULL,
    wa_result varchar(20) NULL,
    process_status varchar(20) NOT NULL DEFAULT 'pending',
    retry_count smallint(5) unsigned NOT NULL DEFAULT 0,
    rekap_form_id bigint(20) unsigned NULL,
    telegram_status varchar(50) NULL,
    last_error text NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NULL,
    locked_at datetime NULL,
    processed_at datetime NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY event_id (event_id),
    KEY process_status (process_status),
    KEY retry_count (retry_count),
    KEY created_at (created_at),
    KEY rekap_form_id (rekap_form_id)
  ) $charset_collate;";

  dbDelta($sql);

  update_option('vd_lead_queue_schema_version', VD_LEAD_QUEUE_SCHEMA_VERSION, false);
}

function vd_get_lead_retry_delays()
{
  return [30, 120, 600, 1800];
}

function vd_prepare_lead_queue_payload($payload)
{
  $payload = wp_parse_args(
    is_array($payload) ? $payload : [],
    [
      'event_id' => '',
      'nama' => '',
      'no_whatsapp' => '',
      'jenis_website' => '',
      'via' => '',
      'utm_content' => '',
      'utm_medium' => '',
      'greeting' => '',
      'gclid' => '',
      'label' => '',
      'created_at' => current_time('mysql'),
    ]
  );

  $event_id = sanitize_text_field((string) $payload['event_id']);
  if ($event_id === '' && function_exists('wp_generate_uuid4')) {
    $event_id = wp_generate_uuid4();
  }

  return [
    'event_id' => $event_id,
    'nama' => sanitize_text_field((string) $payload['nama']),
    'no_whatsapp' => sanitize_text_field((string) $payload['no_whatsapp']),
    'jenis_website' => sanitize_textarea_field((string) $payload['jenis_website']),
    'via' => sanitize_text_field((string) $payload['via']),
    'utm_content' => sanitize_text_field((string) $payload['utm_content']),
    'utm_medium' => sanitize_text_field((string) $payload['utm_medium']),
    'greeting' => sanitize_text_field((string) $payload['greeting']),
    'gclid' => sanitize_text_field((string) $payload['gclid']),
    'label' => sanitize_text_field((string) $payload['label']),
    'created_at' => sanitize_text_field((string) $payload['created_at']),
  ];
}

function vd_insert_lead_queue($payload)
{
  global $wpdb;

  $payload = vd_prepare_lead_queue_payload($payload);
  $table_name = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;

  $result = $wpdb->insert(
    $table_name,
    [
      'event_id' => $payload['event_id'],
      'nama' => $payload['nama'],
      'no_whatsapp' => $payload['no_whatsapp'],
      'jenis_website' => $payload['jenis_website'],
      'via' => $payload['via'],
      'utm_content' => $payload['utm_content'],
      'utm_medium' => $payload['utm_medium'],
      'greeting' => $payload['greeting'],
      'gclid' => $payload['gclid'],
      'label' => $payload['label'],
      'process_status' => 'pending',
      'created_at' => $payload['created_at'],
      'updated_at' => $payload['created_at'],
    ],
    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
  );

  if ($result === false) {
    return new WP_Error('lead_queue_insert_failed', $wpdb->last_error ?: 'Gagal menyimpan queue lead.');
  }

  return [
    'queue_id' => (int) $wpdb->insert_id,
    'event_id' => $payload['event_id'],
  ];
}

function vd_schedule_lead_queue_job($queue_id, $delay_seconds = 60)
{
  $queue_id = (int) $queue_id;
  if ($queue_id <= 0) {
    return false;
  }

  $args = [$queue_id];
  if (wp_next_scheduled(VD_LEAD_QUEUE_CRON_HOOK, $args)) {
    return true;
  }

  $timestamp = time() + max(1, (int) $delay_seconds);

  return (bool) wp_schedule_single_event($timestamp, VD_LEAD_QUEUE_CRON_HOOK, $args);
}

function vd_requeue_lead_queue_job($queue_id, $force_status = 'retrying')
{
  global $wpdb;

  $queue_id = (int) $queue_id;
  if ($queue_id <= 0) {
    return false;
  }

  $allowed_statuses = ['pending', 'retrying', 'failed'];
  $force_status = in_array($force_status, $allowed_statuses, true) ? $force_status : 'retrying';
  $table_name = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;
  $now = current_time('mysql');

  $updated = $wpdb->update(
    $table_name,
    [
      'process_status' => $force_status,
      'last_error' => '',
      'updated_at' => $now,
      'locked_at' => null,
    ],
    ['id' => $queue_id],
    ['%s', '%s', '%s', '%s'],
    ['%d']
  );

  if ($updated === false) {
    return false;
  }

  vd_schedule_lead_queue_job($queue_id, 1);

  return vd_trigger_lead_queue_async($queue_id);
}

function vd_trigger_lead_queue_async($queue_id)
{
  $queue_id = (int) $queue_id;
  if ($queue_id <= 0) {
    return false;
  }

  $scheduled = vd_schedule_lead_queue_job($queue_id, 60);
  $nonce = wp_create_nonce('vd_process_lead_queue_' . $queue_id);

  $response = wp_remote_post(
    admin_url('admin-ajax.php'),
    [
      'timeout' => 0.01,
      'blocking' => false,
      'sslverify' => apply_filters('https_local_ssl_verify', false),
      'body' => [
        'action' => 'vd_process_lead_queue',
        'queue_id' => $queue_id,
        'nonce' => $nonce,
      ],
    ]
  );

  if (is_wp_error($response)) {
    return $scheduled;
  }

  return true;
}

function vd_process_lead_queue_ajax()
{
  $queue_id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
  if ($queue_id <= 0) {
    vd_log_ajax('vd_process_lead_queue', 'failed', 'Queue ID tidak valid');
    wp_send_json_error(['message' => 'Queue ID tidak valid.'], 400);
  }

  check_ajax_referer('vd_process_lead_queue_' . $queue_id, 'nonce');

  $processed = vd_process_lead_queue_job($queue_id);
  vd_log_ajax('vd_process_lead_queue', $processed ? 'success' : 'failed', 'Queue ID: ' . $queue_id);
  wp_send_json_success(['processed' => (bool) $processed]);
}

function vd_claim_lead_queue_job($queue_id)
{
  global $wpdb;

  $table_name = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;
  $now = current_time('mysql');

  $result = $wpdb->query(
    $wpdb->prepare(
      "UPDATE $table_name
      SET process_status = %s, locked_at = %s, updated_at = %s
      WHERE id = %d AND process_status IN ('pending', 'retrying')",
      'processing',
      $now,
      $now,
      $queue_id
    )
  );

  return $result === 1;
}

function vd_schedule_lead_queue_retry($queue_id, $current_retry_count, $error_message)
{
  global $wpdb;

  $queue_id = (int) $queue_id;
  if ($queue_id <= 0) {
    return false;
  }

  $delays = vd_get_lead_retry_delays();
  $next_retry = max(0, (int) $current_retry_count) + 1;
  $table_name = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;
  $now = current_time('mysql');
  $error_message = sanitize_text_field((string) $error_message);

  if ($next_retry <= count($delays)) {
    $retry_delay = $delays[$next_retry - 1];
    $scheduled_at = gmdate('Y-m-d H:i:s', time() + $retry_delay);

    $wpdb->update(
      $table_name,
      [
        'process_status' => 'retrying',
        'retry_count' => $next_retry,
        'last_error' => $error_message,
        'updated_at' => $now,
        'locked_at' => null,
      ],
      ['id' => $queue_id],
      ['%s', '%d', '%s', '%s', '%s'],
      ['%d']
    );

    $scheduled = vd_schedule_lead_queue_job($queue_id, $retry_delay);
    if ($scheduled) {
      return true;
    }

    $error_message = 'Gagal menjadwalkan retry queue lead pada ' . $scheduled_at;
  }

  $wpdb->update(
    $table_name,
    [
      'process_status' => 'failed',
      'retry_count' => $next_retry,
      'last_error' => $error_message,
      'updated_at' => $now,
      'locked_at' => null,
    ],
    ['id' => $queue_id],
    ['%s', '%d', '%s', '%s', '%s'],
    ['%d']
  );

  vd_notify_lead_queue_failure($queue_id, $error_message, $next_retry);

  return false;
}

function vd_is_telegram_send_success($status_message)
{
  if (!is_string($status_message)) {
    return false;
  }

  return stripos($status_message, 'berhasil') !== false;
}

function vd_send_lead_queue_telegram($queue_row, $ai_result)
{
  $nama = $queue_row->nama;
  $no_whatsapp = $queue_row->no_whatsapp;
  $jenis_website = $queue_row->jenis_website;
  $greeting = $queue_row->greeting;
  $label = $queue_row->label;
  $gclid = $queue_row->gclid;
  $sumber = ($greeting === 'v0') ? 'WA2' : 'WA ADS';
  $conversion_tracking = vd_evaluate_conversion_tracking($jenis_website);

  $message_text = "Ada Chat Baru dari: <b>{$nama}</b>\n"
    . "No. WhatsApp: <b>{$no_whatsapp}</b>\n"
    . "Jenis Web: <b>{$jenis_website}</b>\n"
    . "Greeting: <b>{$greeting}</b>\n"
    . "Sumber: <b>{$sumber}</b>\n"
    . "Status Konversi: <b>{$conversion_tracking['status_label']}</b>\n"
    . (!$conversion_tracking['should_track'] && $conversion_tracking['matched_keyword'] !== ''
      ? "Frasa Pemicu: <b>{$conversion_tracking['matched_keyword']}</b>\n"
      : '')
    . "label: <b>{$label}</b>\n\n"
    . "gclid: <b>{$gclid}</b>\n";

  $chat_ids = [
    '-944668693',
  ];

  $detail_status = kirim_telegram($message_text, $chat_ids);
  if (!vd_is_telegram_send_success($detail_status)) {
    return new WP_Error('telegram_send_failed', is_string($detail_status) ? $detail_status : 'Gagal mengirim notifikasi Telegram.');
  }

  if ($ai_result === 'dilarang') {
    $status_text = "<b>Gagal WA</b>\n";
    $status_result = kirim_telegram($status_text, $chat_ids);

    if (!vd_is_telegram_send_success($status_result)) {
      return new WP_Error('telegram_status_failed', is_string($status_result) ? $status_result : 'Gagal mengirim status Telegram.');
    }
  }

  return 'sent';
}

function vd_process_lead_queue_job($queue_id = 0)
{
  global $wpdb;

  if (function_exists('vd_maybe_upgrade_lead_queue_table')) {
    vd_maybe_upgrade_lead_queue_table();
  }

  $queue_id = (int) $queue_id;
  if ($queue_id <= 0) {
    return false;
  }

  $queue_table = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;
  $queue_row = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $queue_table WHERE id = %d LIMIT 1", $queue_id)
  );

  if (!$queue_row) {
    return false;
  }

  if ($queue_row->process_status === 'done') {
    return true;
  }

  if (!vd_claim_lead_queue_job($queue_id)) {
    $latest_row = $wpdb->get_row(
      $wpdb->prepare("SELECT process_status FROM $queue_table WHERE id = %d LIMIT 1", $queue_id)
    );

    return $latest_row && $latest_row->process_status === 'done';
  }

  $queue_row = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $queue_table WHERE id = %d LIMIT 1", $queue_id)
  );

  if (!$queue_row) {
    return false;
  }

  $ai_result = validasi_jenis_web($queue_row->jenis_website);
  $wa_result = validasi_no_wa($queue_row->no_whatsapp);
  $now = current_time('mysql');
  $rekap_form_id = (int) $queue_row->rekap_form_id;

  if ($rekap_form_id <= 0) {
    $inserted = $wpdb->insert(
      $wpdb->prefix . 'rekap_form',
      [
        'nama' => $queue_row->nama,
        'no_whatsapp' => $queue_row->no_whatsapp,
        'jenis_website' => $queue_row->jenis_website,
        'ai_result' => $ai_result,
        'via' => $queue_row->via,
        'utm_content' => $queue_row->utm_content,
        'utm_medium' => $queue_row->utm_medium,
        'greeting' => $queue_row->greeting,
        'gclid' => $queue_row->gclid ?: null,
        'label' => $queue_row->label ?: null,
        'created_at' => $queue_row->created_at,
      ]
    );

    if ($inserted === false) {
      $error_message = $wpdb->last_error ?: 'Gagal menyimpan lead ke rekap_form.';
      return vd_schedule_lead_queue_retry($queue_id, (int) $queue_row->retry_count, $error_message);
    }

    $rekap_form_id = (int) $wpdb->insert_id;

    $wpdb->update(
      $queue_table,
      [
        'rekap_form_id' => $rekap_form_id,
        'ai_result' => $ai_result,
        'wa_result' => $wa_result,
        'updated_at' => $now,
        'last_error' => '',
      ],
      ['id' => $queue_id],
      ['%d', '%s', '%s', '%s', '%s'],
      ['%d']
    );
  }

  $telegram_status = vd_send_lead_queue_telegram($queue_row, $ai_result);
  if (is_wp_error($telegram_status)) {
    return vd_schedule_lead_queue_retry($queue_id, (int) $queue_row->retry_count, $telegram_status->get_error_message());
  }

  $updated = $wpdb->update(
    $queue_table,
    [
      'ai_result' => $ai_result,
      'wa_result' => $wa_result,
      'process_status' => 'done',
      'telegram_status' => 'sent',
      'last_error' => '',
      'updated_at' => $now,
      'locked_at' => null,
      'processed_at' => $now,
    ],
    ['id' => $queue_id],
    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
    ['%d']
  );

  return $updated !== false;
}

function vd_notify_lead_queue_failure($queue_id, $error_message, $retry_count)
{
  global $wpdb;

  $queue_id = (int) $queue_id;
  if ($queue_id <= 0) {
    return;
  }

  $queue_table = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;
  $queue_row = $wpdb->get_row(
    $wpdb->prepare("SELECT id, event_id, nama, no_whatsapp, jenis_website FROM $queue_table WHERE id = %d LIMIT 1", $queue_id)
  );

  if (!$queue_row) {
    return;
  }

  $report_chat_ids = [
    '260162734',
    '785329499',
  ];

  $message = "Lead queue gagal diproses\n"
    . "Queue ID: <b>{$queue_row->id}</b>\n"
    . "Event ID: <b>{$queue_row->event_id}</b>\n"
    . "Nama: <b>{$queue_row->nama}</b>\n"
    . "No WA: <b>{$queue_row->no_whatsapp}</b>\n"
    . "Retry: <b>{$retry_count}</b>\n"
    . "Error: <b>" . esc_html($error_message) . "</b>\n";

  kirim_telegram($message, $report_chat_ids);
}

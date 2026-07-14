<?php



add_action('wp_ajax_get_high_intent_ips', 'get_high_intent_ips');
function get_high_intent_ips()
{
  check_ajax_referer('get_high_intent_ips_nonce');

  global $wpdb;
  $table_name = $wpdb->prefix . 'vd_whatsapp_clicks';

  $threshold = isset($_POST['threshold']) ? intval($_POST['threshold']) : 5;
  $greeting_filter_sql = " AND (greeting IS NULL OR greeting <> 'v0')";

  $results = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT ip_address, COUNT(*) as total_clicks, 
             MAX(created_at) as last_click,
             GROUP_CONCAT(DISTINCT greeting SEPARATOR ', ') as greetings
             FROM $table_name
             WHERE ip_address <> ''$greeting_filter_sql
             GROUP BY ip_address
             HAVING COUNT(*) >= %d
             ORDER BY total_clicks DESC
             LIMIT 100",
      $threshold
    )
  );

  if (empty($results)) {
    wp_send_json_success('<p>Tidak ada data High Intent saat ini.</p>');
  }

  $html = '<table class="widefat striped" style="text-align:left;">';
  $html .= '<thead><tr><th>IP Address</th><th>Total Klik</th><th>Terakhir Klik</th><th>Greeting</th><th>Aksi</th></tr></thead>';
  $html .= '<tbody>';

  foreach ($results as $row) {
    $ip = esc_html($row->ip_address);
    $html .= '<tr>';
    $html .= '<td><a href="admin.php?page=rekap-whatsapp-clicks&search_ip=' . $ip . '" target="_blank">' . $ip . '</a></td>';
    $html .= '<td>' . intval($row->total_clicks) . '</td>';
    $html .= '<td>' . esc_html($row->last_click) . '</td>';
    $html .= '<td>' . esc_html($row->greetings) . '</td>';
    $html .= '<td><button class="button button-small" onclick="copyToClipboard(\'' . $ip . '\', this)">Copy IP</button></td>';
    $html .= '</tr>';
  }

  $html .= '</tbody></table>';

  vd_log_ajax('get_high_intent_ips', 'success', 'Found ' . count($results) . ' IPs');
  wp_send_json_success($html);
}

add_action('wp_ajax_rekap_chat_form', 'rekap_chat_form');


add_action('wp_ajax_nopriv_rekap_chat_form', 'rekap_chat_form');

add_action('wp_ajax_vd_async_track_wa_click', 'vd_async_track_wa_click');

add_action('wp_ajax_nopriv_vd_async_track_wa_click', 'vd_async_track_wa_click');

add_action('wp_ajax_vd_track_form_funnel_event', 'vd_track_form_funnel_event');

add_action('wp_ajax_nopriv_vd_track_form_funnel_event', 'vd_track_form_funnel_event');



function rekap_chat_form()

{

  global $wpdb;

  $nama = sanitize_text_field($_POST['nama']);
  $no_whatsapp = sanitize_text_field($_POST['no_whatsapp']);
  $jenis_website = sanitize_text_field($_POST['jenis_website']);
  $via = sanitize_text_field($_POST['via']);

  // Ambil data tambahan dari cookie.
  $utm_content = sanitize_text_field($_COOKIE['utm_content'] ?? '');
  $utm_medium = sanitize_text_field($_COOKIE['utm_medium'] ?? '');
  $gclid = sanitize_text_field($_COOKIE['_gcl_aw'] ?? '');
  $label = sanitize_text_field($_COOKIE['label'] ?? '');

  $greeting = sanitize_text_field($_COOKIE['greeting'] ?? 'vx');
  $greeting = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? $greeting : 'v0';

  // AI saat ini memang nonaktif dari validasi_jenis_web(), jadi respons tetap ringan.
  $ai_result = validasi_jenis_web($jenis_website);
  $wa_result = validasi_no_wa($no_whatsapp);
  $conversion_tracking = vd_evaluate_conversion_tracking($jenis_website);

  $payload = [
    'nama' => $nama,
    'no_whatsapp' => $no_whatsapp,
    'jenis_website' => $jenis_website,
    'via' => $via,
    'utm_content' => $utm_content,
    'utm_medium' => $utm_medium,
    'greeting' => $greeting,
    'gclid' => $gclid,
    'label' => $label,
    'created_at' => current_time('mysql'),
  ];

  $queue_result = vd_insert_lead_queue($payload);
  if (!is_wp_error($queue_result)) {
    vd_trigger_lead_queue_async($queue_result['queue_id']);

    vd_log_ajax('rekap_chat_form', 'success', 'Lead masuk queue. Queue ID: ' . $queue_result['queue_id']);
    wp_send_json_success([
      'ai_result' => $ai_result,
      'wa_result' => $wa_result,
      'pesan' => 'Lead masuk ke queue.',
      'queue_id' => $queue_result['queue_id'],
      'process_mode' => 'queue',
      'should_track_conversion' => $conversion_tracking['should_track'],
      'conversion_tracking_status' => $conversion_tracking['status_label'],
    ]);
  }

  // Fallback darurat: kalau queue gagal, pakai alur sinkron lama agar klien tetap bisa lanjut ke WA.
  $sumber = ($greeting == 'v0') ? 'WA2' : 'WA ADS';
  $messageText = "Ada Chat Baru dari: <b>{$nama}</b>\n"
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

  $chatIds = [
    '-944668693'
  ];

  $pesan = kirim_telegram($messageText, $chatIds);

  $wpdb->insert(
    $wpdb->prefix . 'rekap_form',
    [
      'nama' => $nama,
      'no_whatsapp' => $no_whatsapp,
      'jenis_website' => $jenis_website,
      'ai_result' => $ai_result,
      'via' => $via,
      'utm_content' => $utm_content,
      'utm_medium' => $utm_medium,
      'greeting' => $greeting,
      'gclid' => $gclid ?: null,
      'label' => $label ?: null,
      'created_at' => current_time('mysql'),
    ]
  );

  if ($wpdb->last_error) {
    $id_reports = [
      '260162734',
      '785329499'
    ];

    $log_message = "MySQL Error: " . $wpdb->last_error . "\nQueue error: " . $queue_result->get_error_message();
    kirim_telegram($log_message, $id_reports);

    vd_log_ajax('rekap_chat_form', 'failed', 'Queue dan simpan sinkron sama-sama gagal');
    wp_send_json_error([
      'ai_result' => $ai_result,
      'wa_result' => $wa_result,
      'pesan' => 'Queue dan simpan sinkron sama-sama gagal.'
    ], 500);
  }

  vd_log_ajax('rekap_chat_form', 'success', 'Lead saved via sync fallback');
  wp_send_json_success([
    'ai_result' => $ai_result,
    'wa_result' => $wa_result,
    'pesan' => $pesan,
    'process_mode' => 'sync_fallback',
    'should_track_conversion' => $conversion_tracking['should_track'],
    'conversion_tracking_status' => $conversion_tracking['status_label'],
  ]);
}



function vd_async_track_wa_click()

{

  global $wpdb;



  // Ambil page_url lebih awal untuk validasi ads via parameter URL

  $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';

  if ($page_url === '' && isset($_SERVER['HTTP_REFERER'])) {

    $page_url = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
  }



  $nonce_valid = check_ajax_referer('vd_async_wa_click', 'nonce', false);

  // Hapus validasi ketat nonce/referer agar seperti GTM (terima semua request)

  // if (!$nonce_valid) { ... } logic dihapus



  $is_ads = get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads');



  // Fallback: Cek parameter URL pada page_url jika cookie/logic server gagal

  if (!$is_ads && $page_url) {

    $query_str = parse_url($page_url, PHP_URL_QUERY);

    if ($query_str) {

      parse_str($query_str, $query_params);

      if (

        isset($query_params['gclid']) ||

        isset($query_params['wbraid']) ||

        isset($query_params['gbraid']) ||

        (isset($query_params['utm_source']) && $query_params['utm_source'] === 'google' && (isset($query_params['utm_medium']) || isset($query_params['utm_content'])))

      ) {

        $is_ads = true;
      }
    }
  }



  $greeting = isset($_POST['greeting']) ? sanitize_text_field(wp_unslash($_POST['greeting'])) : '';

  $greeting = trim($greeting);



  if (!$is_ads) {

    $greeting = 'v0';
  } else {

    if ($greeting === '' || $greeting === 'v0') {

      $cookie_greeting = isset($_COOKIE['greeting']) ? sanitize_text_field(wp_unslash($_COOKIE['greeting'])) : '';

      $cookie_greeting = trim($cookie_greeting);

      $greeting = $cookie_greeting !== '' ? $cookie_greeting : 'vx';
    }
  }



  $event_id = isset($_POST['event_id']) ? sanitize_text_field(wp_unslash($_POST['event_id'])) : '';

  $event_id = trim($event_id);

  if ($event_id === '' && function_exists('vd_generate_whatsapp_event_id')) {

    $event_id = vd_generate_whatsapp_event_id();
  }



  $payload = [

    'event_id' => $event_id,

    'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',

    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown',

    'referer' => $page_url,

    'greeting' => $greeting,

    'created_at' => current_time('mysql'),

  ];



  if (empty($payload['ip_address'])) {

    $payload['ip_address'] = 'unknown';
  }



  if (empty($payload['user_agent'])) {

    $payload['user_agent'] = 'unknown';
  }



  if (function_exists('vd_maybe_upgrade_whatsapp_clicks_table')) {

    vd_maybe_upgrade_whatsapp_clicks_table();
  }



  $saved = function_exists('vd_upsert_whatsapp_click_status')

    ? vd_upsert_whatsapp_click_status($payload, 'success', 0, '')

    : false;



  if (!$saved) {

    $error_message = isset($wpdb) && isset($wpdb->last_error) && $wpdb->last_error ? $wpdb->last_error : 'Failed to save WA click log';



    $scheduled = function_exists('vd_schedule_whatsapp_click_retry')

      ? vd_schedule_whatsapp_click_retry($payload, 0, $error_message)

      : false;



    if (!$scheduled) {

      vd_log_ajax('vd_async_track_wa_click', 'failed', $error_message);
      wp_send_json_error(['logged' => false, 'message' => $error_message], 500);
    }
  }



  vd_log_ajax('vd_async_track_wa_click', 'success', 'Tracked', ['greeting' => $greeting]);
  wp_send_json_success(['logged' => true]);
}

function vd_track_form_funnel_event()
{
  $event_type = isset($_POST['event_type']) ? sanitize_text_field(wp_unslash($_POST['event_type'])) : '';
  $allowed_event_types = ['form_view', 'form_start', 'submit_enabled', 'submit_click'];

  if (!in_array($event_type, $allowed_event_types, true)) {
    vd_log_ajax('vd_track_form_funnel_event', 'failed', 'Event type tidak valid: ' . $event_type);
    wp_send_json_error(['message' => 'Event type tidak valid.'], 400);
  }

  $session_key = isset($_POST['session_key']) ? sanitize_text_field(wp_unslash($_POST['session_key'])) : '';
  if ($session_key === '') {
    $session_key = wp_generate_password(20, false, false);
  }

  $page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';
  $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
  $device = isset($_POST['device']) ? sanitize_text_field(wp_unslash($_POST['device'])) : '';

  $utm_content = sanitize_text_field($_COOKIE['utm_content'] ?? '');
  $utm_medium = sanitize_text_field($_COOKIE['utm_medium'] ?? '');
  $label = sanitize_text_field($_COOKIE['label'] ?? '');

  $greeting = sanitize_text_field($_COOKIE['greeting'] ?? 'vx');
  $greeting = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? $greeting : 'v0';
  $traffic_source = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? 'ads' : 'organik';

  $payload = [
    'event_id' => isset($_POST['event_id']) ? sanitize_text_field(wp_unslash($_POST['event_id'])) : vd_generate_form_funnel_event_id(),
    'session_key' => $session_key,
    'event_type' => $event_type,
    'page_url' => $page_url,
    'referer' => $referer,
    'device' => $device,
    'greeting' => $greeting,
    'traffic_source' => $traffic_source,
    'label' => $label,
    'utm_content' => $utm_content,
    'utm_medium' => $utm_medium,
    'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
    'created_at' => current_time('mysql'),
  ];

  vd_maybe_upgrade_form_funnel_table();
  $saved = vd_insert_form_funnel_event($payload);

  if (is_wp_error($saved)) {
    vd_log_ajax('vd_track_form_funnel_event', 'failed', $saved->get_error_message());
    wp_send_json_error(['message' => $saved->get_error_message()], 500);
  }

  vd_log_ajax('vd_track_form_funnel_event', 'success', 'Event tracked', ['event_type' => $event_type]);
  wp_send_json_success([
    'logged' => true,
    'event_id' => $saved['event_id'],
    'id' => $saved['id'],
  ]);
}



add_action('wp_ajax_cek_jenis_website_ai', 'cek_jenis_website_ai_handler');

function cek_jenis_website_ai_handler()

{

  check_ajax_referer('cek_jenis_website_ai_nonce');



  global $wpdb;

  $table = $wpdb->prefix . 'rekap_form';



  $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];



  if (empty($ids)) {

    vd_log_ajax('cek_jenis_website_ai', 'failed', 'Tidak ada ID yang dikirim');
    wp_send_json_error('Tidak ada ID yang dikirim.');

    return;
  }



  $results = $wpdb->get_results("SELECT id, jenis_website FROM $table WHERE id IN (" . implode(',', $ids) . ")");



  if (!$results) {

    vd_log_ajax('cek_jenis_website_ai', 'failed', 'Data tidak ditemukan');
    wp_send_json_error('Data tidak ditemukan.');

    return;
  }



  $responses = [];



  foreach ($results as $row) {

    $input = trim($row->jenis_website);



    $gptReply = validasi_jenis_web($input);



    // Update kolom ai_result

    $wpdb->update($table, ['ai_result' => $gptReply], ['id' => $row->id]);



    $statusLabel = match ($gptReply) {

      'valid'    => '✅',

      'dilarang' => '⚠️',

      default    => '',
    };

    $responses[] = "ID {$row->id}: <strong>{$statusLabel}</strong> ({$input})";
  }



  echo '<div class="updated"><ul><li>' . implode('</li><li>', $responses) . '</li></ul></div>';

  vd_log_ajax('cek_jenis_website_ai', 'success', 'Checked ' . count($ids) . ' items');
  wp_die();
}



add_action('wp_ajax_update_inline_status', 'update_inline_status_handler');

function update_inline_status_handler()

{

  check_ajax_referer('update_inline_status_nonce');



  global $wpdb;

  $table_name = $wpdb->prefix . 'rekap_form';



  $id = intval($_POST['id']);

  $status = sanitize_text_field($_POST['status']);



  if (empty($id)) {

    vd_log_ajax('update_inline_status', 'failed', 'ID tidak valid');
    wp_send_json_error('ID tidak valid.');

    return;
  }



  // Update status in database

  $result = $wpdb->update(

    $table_name,

    ['status' => $status],

    ['id' => $id],

    ['%s'],

    ['%d']

  );



  if ($result === false) {

    vd_log_ajax('update_inline_status', 'failed', 'Gagal update status ID ' . $id);
    wp_send_json_error('Gagal memperbarui status di database.');

    return;
  }



  vd_log_ajax('update_inline_status', 'success', 'Updated ID ' . $id . ' to ' . $status);
  wp_send_json_success(['status' => $status]);
}



if (!defined('VD_WA_CLICK_CRON_HOOK')) {

  define('VD_WA_CLICK_CRON_HOOK', 'vd_process_whatsapp_click_job');
}



if (!defined('VD_WA_CLICK_RETRY_DELAYS')) {

  define('VD_WA_CLICK_RETRY_DELAYS', '60,300,900,3600,21600');
}



function vd_get_whatsapp_retry_delays()

{

  $delays = array_map('intval', explode(',', VD_WA_CLICK_RETRY_DELAYS));

  $delays = array_values(array_filter($delays, function ($delay) {

    return $delay > 0;
  }));



  return empty($delays) ? [60, 300, 900, 3600, 21600] : $delays;
}



function vd_generate_whatsapp_event_id()

{

  if (function_exists('wp_generate_uuid4')) {

    return wp_generate_uuid4();
  }



  return uniqid('vdwa_', true);
}



function vd_normalize_whatsapp_click_payload($payload)

{

  $payload = is_array($payload) ? $payload : [];



  $normalized = [

    'event_id' => isset($payload['event_id']) ? sanitize_text_field($payload['event_id']) : '',

    'ip_address' => isset($payload['ip_address']) ? sanitize_text_field($payload['ip_address']) : 'unknown',

    'user_agent' => isset($payload['user_agent']) ? sanitize_text_field($payload['user_agent']) : 'unknown',

    'referer' => isset($payload['referer']) ? esc_url_raw($payload['referer']) : '',

    'greeting' => isset($payload['greeting']) ? sanitize_text_field((string) $payload['greeting']) : '',

    'created_at' => isset($payload['created_at']) ? sanitize_text_field($payload['created_at']) : current_time('mysql'),

    'attempt' => isset($payload['attempt']) ? max(0, intval($payload['attempt'])) : 0,

  ];



  if ($normalized['ip_address'] === '') {

    $normalized['ip_address'] = 'unknown';
  }



  if ($normalized['user_agent'] === '') {

    $normalized['user_agent'] = 'unknown';
  }



  return $normalized;
}



function vd_schedule_whatsapp_click_job($payload, $attempt = 0, $delay_seconds = 1)

{

  $payload = vd_normalize_whatsapp_click_payload($payload);

  if (empty($payload['event_id'])) {

    return false;
  }



  $payload['attempt'] = max(0, intval($attempt));

  $timestamp = time() + max(1, intval($delay_seconds));



  // Prevent accidental duplicate scheduling for the same exact payload.

  if (wp_next_scheduled(VD_WA_CLICK_CRON_HOOK, [$payload])) {

    return true;
  }



  return (bool) wp_schedule_single_event($timestamp, VD_WA_CLICK_CRON_HOOK, [$payload]);
}



function vd_upsert_whatsapp_click_status($payload, $status, $retry_count, $error_message = '')

{

  global $wpdb;



  $payload = vd_normalize_whatsapp_click_payload($payload);

  if (empty($payload['event_id'])) {

    return false;
  }



  $table_name = $wpdb->prefix . VD_WA_CLICKS_TABLE;

  $status = sanitize_text_field($status);

  $retry_count = max(0, intval($retry_count));

  $error_message = sanitize_text_field($error_message);



  $existing = $wpdb->get_row(

    $wpdb->prepare(

      "SELECT id, status FROM $table_name WHERE event_id = %s LIMIT 1",

      $payload['event_id']

    )

  );



  if ($existing && $existing->status === 'success' && $status !== 'success') {

    return true;
  }



  $data = [

    'event_id' => $payload['event_id'],

    'ip_address' => $payload['ip_address'],

    'user_agent' => $payload['user_agent'],

    'referer' => $payload['referer'],

    'greeting' => $payload['greeting'],

    'status' => $status,

    'retry_count' => $retry_count,

    'last_error' => $error_message,

    'created_at' => $payload['created_at'],

  ];



  if ($existing) {

    $update_data = [

      'greeting' => $payload['greeting'],

      'status' => $status,

      'retry_count' => $retry_count,

      'last_error' => $error_message,

    ];



    $result = $wpdb->update(

      $table_name,

      $update_data,

      ['event_id' => $payload['event_id']],

      ['%s', '%s', '%d', '%s'],

      ['%s']

    );



    return $result !== false;
  }



  $insert_result = $wpdb->insert(

    $table_name,

    $data,

    ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']

  );



  return $insert_result !== false;
}



function vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message)

{

  $payload = vd_normalize_whatsapp_click_payload($payload);

  $delays = vd_get_whatsapp_retry_delays();

  $error_message = sanitize_text_field($error_message);

  $next_attempt = max(0, intval($attempt)) + 1;



  // Attempt index starts from 0; retries use configured delays sequentially.

  if ($next_attempt <= count($delays)) {

    $retry_delay = $delays[$next_attempt - 1];

    vd_upsert_whatsapp_click_status($payload, 'pending', $next_attempt, $error_message);

    $scheduled = vd_schedule_whatsapp_click_job($payload, $next_attempt, $retry_delay);



    if (!$scheduled) {

      vd_upsert_whatsapp_click_status($payload, 'failed', $next_attempt, 'Failed to schedule retry job');
    }



    return $scheduled;
  }



  return vd_upsert_whatsapp_click_status($payload, 'failed', $next_attempt, $error_message);
}



add_action(VD_WA_CLICK_CRON_HOOK, 'vd_process_whatsapp_click_job', 10, 1);

function vd_process_whatsapp_click_job($payload = [])

{

  global $wpdb;



  if (function_exists('vd_maybe_upgrade_whatsapp_clicks_table')) {

    vd_maybe_upgrade_whatsapp_clicks_table();
  }



  $payload = vd_normalize_whatsapp_click_payload($payload);

  $attempt = max(0, intval($payload['attempt']));

  if (empty($payload['event_id'])) {

    return;
  }



  $table_name = $wpdb->prefix . VD_WA_CLICKS_TABLE;

  $existing = $wpdb->get_row(

    $wpdb->prepare(

      "SELECT id, status FROM $table_name WHERE event_id = %s LIMIT 1",

      $payload['event_id']

    )

  );



  if ($existing && $existing->status === 'success') {

    return;
  }



  if (!$existing) {

    $created_pending = vd_upsert_whatsapp_click_status($payload, 'pending', $attempt, '');

    if (!$created_pending) {

      $error_message = $wpdb->last_error ?: 'Failed to create pending WA click row';

      vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message);

      return;
    }
  } else {

    $marked_pending = vd_upsert_whatsapp_click_status($payload, 'pending', $attempt, '');

    if (!$marked_pending) {

      $error_message = $wpdb->last_error ?: 'Failed to mark pending WA click row';

      vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message);

      return;
    }
  }



  $mark_success = vd_upsert_whatsapp_click_status($payload, 'success', $attempt, '');

  if (!$mark_success) {

    $error_message = $wpdb->last_error ?: 'Failed to finalize WA click row';

    vd_schedule_whatsapp_click_retry($payload, $attempt, $error_message);
  }
}

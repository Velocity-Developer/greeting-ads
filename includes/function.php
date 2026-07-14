<?php

/**
 * Mengirim pesan ke beberapa chat_id Telegram.
 *
 * @param string $message Isi pesan yang ingin dikirim.
 * @param array $chatIds Daftar chat_id tujuan.
 * @return string Status pengiriman ('sukses' atau pesan error)
 */

function get_ads_logic()
{
  if (
    !is_admin() && (
      isset($_COOKIE['_gcl_aw']) ||
      isset($_COOKIE['greeting']) ||
      isset($_GET['gclid']) ||
      isset($_GET['wbraid']) ||
      isset($_GET['gbraid']) ||
      (isset($_GET['utm_source']) && $_GET['utm_source'] == 'google' &&  isset($_GET['utm_medium'])) ||
      (isset($_GET['utm_source']) && $_GET['utm_source'] == 'google' &&  isset($_GET['utm_content']))
    )
  ) {
    return true;
  }
}

function save_utm_cookies()
{

  // Pastikan berjalan di frontend dan parameter utama ada
  if (get_ads_logic()) {
    // Konfigurasi cookie
    $expiration = time() + 30 * DAY_IN_SECONDS; // 30 hari
    $path = '/';
    $domain = parse_url(get_site_url(), PHP_URL_HOST); // Ambil domain dari site URL
    $secure = is_ssl(); // Aktifkan secure flag jika HTTPS
    $httponly = true; // Cegah akses JavaScript

    setcookie('traffic', 'ads', [
      'expires' => $expiration,
      'path' => $path,
      'domain' => $domain,
      'secure' => $secure,
      'httponly' => $httponly,
      'samesite' => 'Lax'
    ]);

    // Sanitasi nilai parameter
    $utm_medium = isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : '';
    $utm_content = isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : '';

    if ($utm_medium && $utm_content) {
        // Ekstrak angka dari utm_medium menggunakan regex
        $utm_medium = trim($utm_medium);
        if (preg_match('/kwd-(\d+)/', $utm_medium, $matches)) {
          $utm_medium = $matches[1];
        } else {
          $utm_medium = preg_replace('/[^0-9]/', '', $utm_medium);
        }

        // Query database untuk mencocokkan utm_content dan nomor kata kunci
        global $wpdb;
        $table_name = $wpdb->prefix . 'greeting_ads_data';

        $query = $wpdb->prepare(
          "SELECT greeting FROM $table_name WHERE id_grup_iklan = '%s' AND nomor_kata_kunci = '%d'",
          $utm_content,
          $utm_medium
        );

        $result = $wpdb->get_var($query);
        // echo '<pre>' . print_r($result, true) . '</pre>';

        // Jika ada hasil yang cocok, simpan kolom greeting ke cookie
        if ($result) {
          $greeting = sanitize_text_field($result);

          // set utm_content ke cookie
          setcookie('utm_content', $utm_content, [
            'expires' => $expiration,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
          ]);

          // set utm_medium ke cookie
          setcookie('utm_medium', $utm_medium, [
            'expires' => $expiration,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
          ]);

          // Set cookie greeting
          setcookie('greeting', $greeting, [
            'expires' => $expiration,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            // Must be readable by client JS to build WA URL on first click without refresh.
            'httponly' => false,
            'samesite' => 'Lax'
          ]);
        }
    }

    // Sanitasi nilai parameter
    $label = isset($_GET['label']) ? sanitize_text_field($_GET['label']) : '';
    if ($label) {
      // Set cookie label
      setcookie('label', $label, [
        'expires' => $expiration,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
      ]);
    }
  }
}
add_action('init', 'save_utm_cookies');


function check_greeting_langsung()
{
  // Sanitasi nilai parameter
  $utm_medium = isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : '';
  $utm_content = isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : '';

  // Ekstrak angka dari utm_medium menggunakan regex
  $utm_medium = trim($utm_medium);
  if (preg_match('/kwd-(\d+)/', $utm_medium, $matches)) {
    $utm_medium = $matches[1];
  } else {
    $utm_medium = preg_replace('/[^0-9]/', '', $utm_medium);
  }

  // Query database untuk mencocokkan utm_content dan nomor kata kunci
  global $wpdb;
  $table_name = $wpdb->prefix . 'greeting_ads_data';

  $query = $wpdb->prepare(
    "SELECT greeting FROM $table_name WHERE id_grup_iklan = '%s' AND nomor_kata_kunci = '%d'",
    $utm_content,
    $utm_medium
  );

  $result = $wpdb->get_var($query);
  // echo '<pre>' . print_r($result, true) . '</pre>';

  // Jika ada hasil yang cocok, simpan kolom greeting ke cookie
  if ($result) {
    $greeting = sanitize_text_field($result);
    return $greeting;
  }
}
function kirim_telegram($message, array $chatIds)
{
  if (empty($chatIds)) {
    return 'Tidak ada chat ID tujuan.';
  }

  $botToken = TOKEN_TELEGRAM_BOT; // Pastikan ini didefinisikan di wp-config.php atau functions.php
  $url = "https://api.telegram.org/bot$botToken/sendMessage";

  $pesanStatus = '';

  foreach ($chatIds as $chatId) {
    $data = [
      'chat_id' => $chatId,
      'text' => $message,
      'parse_mode' => 'HTML', // Bisa juga 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => $data,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
      $pesanStatus = 'Curl error: ' . curl_error($ch);
    } else {
      $result = json_decode($response, true);
      if (!empty($result['ok'])) {
        $pesanStatus = 'Pesan berhasil dikirim!';
      } else {
        $pesanStatus = 'Gagal mengirim pesan: ' . ($result['description'] ?? 'Unknown error');
      }
    }
    curl_close($ch);
  }

  return $pesanStatus;
}

/**
 * Melakukan validasi jenis website menggunakan OpenAI GPT API.
 *
 * @param string $input Deskripsi jenis website.
 * @return string 'valid' atau 'dilarang' tergantung hasil analisis GPT.
 */
function validasi_jenis_web($input)
{
  // TOGGLE: Ubah ke true jika ingin mengaktifkan validasi AI kembali
  $gunakan_validasi_ai = false;

  if (!$gunakan_validasi_ai) {
    return 'valid';
  }

  $apiKey = get_option('openai_api_key');
  //skrip asli>> $prompt = get_option('prompt_jenis_web');
  // skrip toro>>
  $prompt = stripslashes(get_option('prompt_jenis_web'));

  if (empty($apiKey) || empty($prompt)) {
    return 'unknown'; // fallback kalau setting kosong
  }

  $payload = [
    'model' => 'gpt-5',
    'messages' => [
      ['role' => 'system', 'content' => $prompt],
      ['role' => 'user', 'content' => $input]
    ]
  ];

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
  ]);

  $response = curl_exec($ch);
  if (curl_errno($ch)) {
    curl_close($ch);
    return 'unknown'; // gagal koneksi
  }

  $result = json_decode($response, true);
  curl_close($ch);

  $gptReply = strtolower(trim($result['choices'][0]['message']['content'] ?? ''));

  return in_array($gptReply, ['valid', 'dilarang']) ? $gptReply : 'unknown';
}

// validasi nomor wa
function validasi_no_wa($no_wa)
{
  // harus diawali dengan 62 atau 08
  if (substr($no_wa, 0, 2) !== '62' && substr($no_wa, 0, 2) !== '08') {
    return 'invalid';
  }
  // cek panjang nomor antara 10 sampai 13
  if (strlen($no_wa) < 10 || strlen($no_wa) > 13) {
    return 'invalid';
  }
  // cek apakah hanya angka
  if (!is_numeric($no_wa)) {
    return 'invalid';
  }
  return 'valid';
}

function vd_sanitize_conversion_blacklist_enabled($value)
{
  return $value === '1' ? '1' : '0';
}

function vd_sanitize_conversion_blacklist_keywords($value)
{
  $value = is_string($value) ? wp_unslash($value) : '';
  $lines = preg_split('/\r\n|\r|\n/', $value);
  if (!is_array($lines)) {
    return '';
  }

  $sanitized = [];
  foreach ($lines as $line) {
    $line = sanitize_text_field($line);
    $line = preg_replace('/\s+/u', ' ', trim((string) $line));
    if ($line === '') {
      continue;
    }

    $sanitized[] = $line;
  }

  $sanitized = array_values(array_unique($sanitized));

  return implode("\n", $sanitized);
}

function vd_get_conversion_blacklist_enabled()
{
  return get_option('vd_conversion_blacklist_enabled', '0') === '1';
}

function vd_get_conversion_blacklist_keywords()
{
  $raw_value = get_option('vd_conversion_blacklist_keywords', '');
  $raw_value = is_string($raw_value) ? $raw_value : '';

  $lines = preg_split('/\r\n|\r|\n/', $raw_value);
  if (!is_array($lines)) {
    return [];
  }

  $keywords = [];
  foreach ($lines as $line) {
    $normalized_line = vd_normalize_conversion_blacklist_text($line);
    if ($normalized_line === '') {
      continue;
    }

    $keywords[] = $normalized_line;
  }

  return array_values(array_unique($keywords));
}

function vd_normalize_conversion_blacklist_text($text)
{
  $text = is_string($text) ? $text : '';
  $text = sanitize_text_field(wp_unslash($text));
  $text = preg_replace('/\s+/u', ' ', trim($text));

  if ($text === '') {
    return '';
  }

  if (function_exists('mb_strtolower')) {
    return mb_strtolower($text, 'UTF-8');
  }

  return strtolower($text);
}

function vd_text_contains_phrase($haystack, $needle)
{
  if ($haystack === '' || $needle === '') {
    return false;
  }

  if (function_exists('mb_stripos')) {
    return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
  }

  return stripos($haystack, $needle) !== false;
}

function vd_evaluate_conversion_tracking($jenis_website)
{
  $normalized_text = vd_normalize_conversion_blacklist_text($jenis_website);
  $blacklist_enabled = vd_get_conversion_blacklist_enabled();
  $blacklist_keywords = vd_get_conversion_blacklist_keywords();

  $result = [
    'should_track' => true,
    'status_label' => 'Dilacak',
    'matched_keyword' => '',
    'blacklist_enabled' => $blacklist_enabled,
  ];

  if (!$blacklist_enabled || $normalized_text === '' || empty($blacklist_keywords)) {
    return $result;
  }

  foreach ($blacklist_keywords as $keyword) {
    if (!vd_text_contains_phrase($normalized_text, $keyword)) {
      continue;
    }

    $result['should_track'] = false;
    $result['status_label'] = 'Tidak Dilacak';
    $result['matched_keyword'] = $keyword;
    return $result;
  }

  return $result;
}

<?php
// Fungsi untuk menambah data
function greeting_ads_add_data($data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
  return $wpdb->insert($table_name, $data);
}

// Fungsi untuk mengupdate data
function greeting_ads_update_data($id, $data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Hilangkan karakter escape (\) dari data
  $data = array_map(function ($value) {
    return stripslashes(sanitize_text_field($value));
  }, $data);

  // Update data di database
  return $wpdb->update(
    $table_name,
    $data,
    array('id' => $id),
    array('%s', '%s', '%s', '%s', '%s'), // Format data
    array('%d') // Format where
  );
}

// Fungsi untuk menghapus data
function greeting_ads_delete_data($id)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;
  return $wpdb->delete($table_name, array('id' => $id));
}

// Fungsi untuk bulk hapus data berdasarkan nomor kata kunci
function greeting_ads_bulk_delete_by_keyword_number($nomor_kata_kunci)
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  return $wpdb->delete(
    $table_name,
    array('nomor_kata_kunci' => $nomor_kata_kunci),
    array('%s')
  );
}


// ajax handler
add_action('wp_ajax_search_greeting', 'handle_search_greeting');

function handle_search_greeting()
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Ambil parameter dari request
  $id_grup_iklan = sanitize_text_field($_POST['id_grup_iklan']);
  $nomor_kata_kunci = sanitize_text_field($_POST['nomor_kata_kunci']);

  // Query database untuk mencari data
  $query = $wpdb->prepare(
    "SELECT greeting FROM $table_name WHERE id_grup_iklan = %s AND nomor_kata_kunci = %s",
    $id_grup_iklan,
    $nomor_kata_kunci
  );

  $result = $wpdb->get_row($query, ARRAY_A);

  if ($result) {
    vd_log_ajax('search_greeting', 'success', 'Found greeting');
    wp_send_json_success(['greeting' => $result['greeting']]);
  } else {
    vd_log_ajax('search_greeting', 'failed', 'Greeting not found');
    wp_send_json_error(['message' => 'Data tidak ditemukan.']);
  }

  wp_die(); // Penting untuk mengakhiri proses AJAX
}


add_action('wp_ajax_delete_greeting', 'handle_delete_greeting');

function handle_delete_greeting()
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Ambil ID dari permintaan AJAX
  $id = intval($_POST['id']);

  // Hapus data dari database
  $result = $wpdb->delete(
    $table_name,
    array('id' => $id),
    array('%d') // Format where
  );

  if ($result) {
    vd_log_ajax('delete_greeting', 'success', 'Deleted ID ' . $id);
    wp_send_json_success('Data berhasil dihapus.');
  } else {
    vd_log_ajax('delete_greeting', 'failed', 'Delete failed ID ' . $id);
    wp_send_json_error('Gagal menghapus data.');
  }
}

add_action('wp_ajax_bulk_delete_greeting_by_keyword', 'handle_bulk_delete_greeting_by_keyword');

function handle_bulk_delete_greeting_by_keyword()
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Ambil nomor kata kunci dari permintaan AJAX
  $nomor_kata_kunci_raw = isset($_POST['nomor_kata_kunci']) ? $_POST['nomor_kata_kunci'] : '';

  if (empty($nomor_kata_kunci_raw)) {
    vd_log_ajax('bulk_delete_greeting', 'failed', 'Nomor kata kunci tidak boleh kosong');
    wp_send_json_error('Nomor kata kunci tidak boleh kosong.');
    return;
  }

  // Parse JSON array of keyword numbers or handle single value
  $keyword_numbers = json_decode(stripslashes($nomor_kata_kunci_raw), true);

  // If JSON decode fails, treat as single keyword number
  if (json_last_error() !== JSON_ERROR_NONE) {
    $keyword_numbers = array($nomor_kata_kunci_raw);
  }

  if (!is_array($keyword_numbers) || empty($keyword_numbers)) {
    vd_log_ajax('bulk_delete_greeting', 'failed', 'Format nomor kata kunci tidak valid');
    wp_send_json_error('Format nomor kata kunci tidak valid. Data yang diterima: ' . $nomor_kata_kunci_raw);
    return;
  }

  // Sanitize each keyword number and remove any whitespace
  $keyword_numbers = array_map(function ($num) {
    return sanitize_text_field(trim($num));
  }, $keyword_numbers);
  $keyword_numbers = array_filter($keyword_numbers, function ($num) {
    return !empty($num);
  });

  if (empty($keyword_numbers)) {
    vd_log_ajax('bulk_delete_greeting', 'failed', 'Tidak ada nomor kata kunci yang valid');
    wp_send_json_error('Tidak ada nomor kata kunci yang valid.');
    return;
  }

  // Build IN clause for SQL query
  $placeholders = implode(',', array_fill(0, count($keyword_numbers), '%s'));

  // Hitung jumlah data yang akan dihapus terlebih dahulu
  $count_query = $wpdb->prepare(
    "SELECT COUNT(*) FROM $table_name WHERE nomor_kata_kunci IN ($placeholders)",
    ...$keyword_numbers
  );
  $count = $wpdb->get_var($count_query);

  if ($count == 0) {
    vd_log_ajax('bulk_delete_greeting', 'failed', 'Tidak ada data dengan nomor kata kunci tersebut');
    wp_send_json_error('Tidak ada data dengan nomor kata kunci tersebut.');
    return;
  }

  // Get detailed count per keyword for result message
  $summary_query = $wpdb->prepare(
    "SELECT nomor_kata_kunci, COUNT(*) as count FROM $table_name WHERE nomor_kata_kunci IN ($placeholders) GROUP BY nomor_kata_kunci",
    ...$keyword_numbers
  );
  $summary_results = $wpdb->get_results($summary_query, ARRAY_A);

  // Hapus data dari database
  $delete_query = $wpdb->prepare(
    "DELETE FROM $table_name WHERE nomor_kata_kunci IN ($placeholders)",
    ...$keyword_numbers
  );
  $result = $wpdb->query($delete_query);

  if ($result !== false) {
    $keyword_count = count($keyword_numbers);
    $summary_text = implode(', ', array_map(function ($item) {
      return $item['nomor_kata_kunci'] . ': ' . $item['count'] . ' data';
    }, $summary_results));

    vd_log_ajax('bulk_delete_greeting', 'success', 'Deleted ' . $count . ' items');
    wp_send_json_success("Berhasil menghapus $count data total dari $keyword_count nomor kata kunci. Detail: $summary_text");
  } else {
    vd_log_ajax('bulk_delete_greeting', 'failed', 'Gagal menghapus data');
    wp_send_json_error('Gagal menghapus data.');
  }
}

add_action('wp_ajax_preview_bulk_delete_greeting', 'handle_preview_bulk_delete_greeting');

function handle_preview_bulk_delete_greeting()
{
  global $wpdb;
  $table_name = $wpdb->prefix . GREETING_ADS_TABLE;

  // Ambil nomor kata kunci dari permintaan AJAX
  $nomor_kata_kunci_raw = isset($_POST['nomor_kata_kunci']) ? $_POST['nomor_kata_kunci'] : '';

  if (empty($nomor_kata_kunci_raw)) {
    vd_log_ajax('preview_bulk_delete', 'failed', 'Nomor kata kunci tidak boleh kosong');
    wp_send_json_error('Nomor kata kunci tidak boleh kosong.');
    return;
  }

  // Parse JSON array of keyword numbers or handle single value
  $keyword_numbers = json_decode(stripslashes($nomor_kata_kunci_raw), true);

  // If JSON decode fails, treat as single keyword number
  if (json_last_error() !== JSON_ERROR_NONE) {
    $keyword_numbers = array($nomor_kata_kunci_raw);
  }

  if (!is_array($keyword_numbers) || empty($keyword_numbers)) {
    vd_log_ajax('preview_bulk_delete', 'failed', 'Format nomor kata kunci tidak valid');
    wp_send_json_error('Format nomor kata kunci tidak valid. Data yang diterima: ' . $nomor_kata_kunci_raw);
    return;
  }

  // Sanitize each keyword number and remove any whitespace
  $keyword_numbers = array_map(function ($num) {
    return sanitize_text_field(trim($num));
  }, $keyword_numbers);
  $keyword_numbers = array_filter($keyword_numbers, function ($num) {
    return !empty($num);
  });

  if (empty($keyword_numbers)) {
    vd_log_ajax('preview_bulk_delete', 'failed', 'Tidak ada nomor kata kunci yang valid');
    wp_send_json_error('Tidak ada nomor kata kunci yang valid.');
    return;
  }

  // Build IN clause for SQL query
  $placeholders = implode(',', array_fill(0, count($keyword_numbers), '%s'));

  // Get summary with keyword details for each keyword number
  $summary_query = $wpdb->prepare(
    "SELECT nomor_kata_kunci, kata_kunci, COUNT(*) as count FROM $table_name WHERE nomor_kata_kunci IN ($placeholders) GROUP BY nomor_kata_kunci, kata_kunci ORDER BY nomor_kata_kunci",
    ...$keyword_numbers
  );
  $summary_results = $wpdb->get_results($summary_query, ARRAY_A);

  // Get preview items (max 20)
  $preview_query = $wpdb->prepare(
    "SELECT id, kata_kunci, grup_iklan, nomor_kata_kunci, greeting FROM $table_name WHERE nomor_kata_kunci IN ($placeholders) LIMIT 20",
    ...$keyword_numbers
  );
  $preview_items = $wpdb->get_results($preview_query, ARRAY_A);

  // Get total count
  $count_query = $wpdb->prepare(
    "SELECT COUNT(*) FROM $table_name WHERE nomor_kata_kunci IN ($placeholders)",
    ...$keyword_numbers
  );
  $total_count = $wpdb->get_var($count_query);

  if ($total_count == 0) {
    // Debug: Check what keyword numbers exist in database
    $all_keywords_query = "SELECT DISTINCT nomor_kata_kunci FROM $table_name LIMIT 10";
    $existing_keywords = $wpdb->get_col($all_keywords_query);

    // Also check if any of the numbers exist with partial matches
    $partial_matches = array();
    foreach ($keyword_numbers as $num) {
      $partial_query = $wpdb->prepare(
        "SELECT DISTINCT nomor_kata_kunci FROM $table_name WHERE nomor_kata_kunci LIKE %s LIMIT 3",
        '%' . $wpdb->esc_like($num) . '%'
      );
      $matches = $wpdb->get_col($partial_query);
      if (!empty($matches)) {
        $partial_matches[$num] = $matches;
      }
    }

    $error_msg = 'Tidak ada data dengan nomor kata kunci tersebut. Kata kunci yang dicari: ' . implode(', ', $keyword_numbers) . '. Contoh kata kunci yang ada di database: ' . implode(', ', $existing_keywords);

    if (!empty($partial_matches)) {
      $error_msg .= '. Kemungkinan kecocokan parsial: ';
      foreach ($partial_matches as $search => $matches) {
        $error_msg .= $search . ' -> ' . implode(', ', $matches) . '; ';
      }
    }

    vd_log_ajax('preview_bulk_delete', 'failed', $error_msg);
    wp_send_json_error($error_msg);
    return;
  }

  // Format summary for frontend
  $summary = array_map(function ($item) {
    return array(
      'keyword_number' => $item['nomor_kata_kunci'],
      'keyword' => $item['kata_kunci'],
      'count' => $item['count']
    );
  }, $summary_results);

  vd_log_ajax('preview_bulk_delete', 'success', 'Preview generated');
  wp_send_json_success(array(
    'items' => $preview_items,
    'count' => $total_count,
    'summary' => $summary
  ));
}

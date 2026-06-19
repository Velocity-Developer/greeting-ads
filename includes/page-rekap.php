<?php
add_action('admin_menu', 'velocity_add_admin_page');

function velocity_add_admin_page()
{
  add_menu_page(
    'Rekap Chat Form',
    'Rekap Chat',
    'manage_options',
    'rekap-chat-form',
    'velocity_render_admin_page',
    'dashicons-format-chat',
    25
  );

  add_submenu_page(
    'rekap-chat-form',
    'Klik WhatsApp',
    'Klik WhatsApp',
    'manage_options',
    'rekap-whatsapp-clicks',
    'velocity_render_whatsapp_clicks_page'
  );

  add_submenu_page(
    'rekap-chat-form',
    'Settings WhatsApp',
    'Settings',
    'manage_options',
    'rekap-chat-settings',
    'velocity_render_whatsapp_settings_page'
  );
}

add_action('admin_init', 'velocity_register_whatsapp_settings');

function velocity_register_whatsapp_settings()
{
  register_setting(
    'velocity_whatsapp_settings_group',
    'vd_whatsapp_url_template',
    [
      'sanitize_callback' => 'velocity_sanitize_whatsapp_url_template',
      'default' => vd_get_default_whatsapp_url_template(),
    ]
  );
}

function velocity_sanitize_whatsapp_url_template($template)
{
  $default_template = vd_get_default_whatsapp_url_template();

  if (!is_string($template)) {
    return $default_template;
  }

  $template = trim(wp_unslash($template));
  if ($template === '') {
    return $default_template;
  }

  return $template;
}

function velocity_render_whatsapp_settings_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $current_template = vd_get_whatsapp_url_template();
?>
  <div class="wrap">
    <h1>Settings WhatsApp</h1>
    <?php settings_errors('vd_whatsapp_url_template'); ?>
    <form method="post" action="options.php">
      <?php settings_fields('velocity_whatsapp_settings_group'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">
            <label for="vd_whatsapp_url_template">Template URL WhatsApp</label>
          </th>
          <td>
            <input
              type="text"
              id="vd_whatsapp_url_template"
              name="vd_whatsapp_url_template"
              class="regular-text"
              style="width: 100%; max-width: 720px;"
              value="<?php echo esc_attr($current_template); ?>">
            <p class="description">
              Bisa isi URL bebas seperti WhatsApp, Google Form, atau halaman lain.
            </p>
            <p class="description">
              Jika ingin dinamis, Anda bisa tetap pakai placeholder <code>{number}</code> dan <code>{message}</code>.
            </p>
            <p class="description">
              Contoh: <code><?php echo esc_html(vd_get_default_whatsapp_url_template()); ?></code> atau <code>https://docs.google.com/forms/d/e/xxxxx/viewform</code>
            </p>
          </td>
        </tr>
      </table>
      <?php submit_button('Simpan Settings'); ?>
    </form>
  </div>
<?php
}

// READ + FORM + CREATE + UPDATE + DELETE HANDLING
function velocity_render_admin_page()
{
  date_default_timezone_set('Asia/Jakarta');
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  // Handle Create/Update
  if (isset($_POST['nama']) && isset($_POST['no_whatsapp']) && isset($_POST['jenis_website'])) {
    check_admin_referer('velocity_crud_action');

    $data = [
      'nama' => sanitize_text_field($_POST['nama']),
      'no_whatsapp' => sanitize_text_field($_POST['no_whatsapp']),
      'jenis_website' => sanitize_text_field($_POST['jenis_website']),
      'via' => sanitize_text_field($_POST['via']),
      'utm_content' => sanitize_text_field($_POST['utm_content']),
      'utm_medium' => sanitize_text_field($_POST['utm_medium']),
      'greeting' => sanitize_text_field($_POST['greeting']),
      'status' => sanitize_text_field($_POST['status']),
    ];

    if (!empty($_POST['id'])) {
      $wpdb->update($table_name, $data, ['id' => intval($_POST['id'])]);
      echo '<div class="updated"><p>Data berhasil diperbarui.</p></div>';
    } else {
      $data['created_at'] = current_time('mysql');
      $wpdb->insert($table_name, $data);
      echo '<div class="updated"><p>Data berhasil ditambahkan.</p></div>';
    }
  }

  // Handle Delete (single)
  if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $wpdb->delete($table_name, ['id' => $id]);
    echo '<div class="updated"><p>Data berhasil dihapus.</p></div>';
  }

  // Handle Bulk Delete
  if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete' && !empty($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    $id_placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($id_placeholders)", ...$ids));
    echo '<div class="updated"><p>' . count($ids) . ' data berhasil dihapus.</p></div>';
  }

  // Load data untuk edit
  $edit_data = null;
  if (isset($_GET['edit'])) {
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['edit'])));
  }
  // Filter Setup
  $selected_greeting = isset($_GET['filter_greeting']) ? sanitize_text_field($_GET['filter_greeting']) : '';

  // Build WHERE clause for filtering
  $where_clause = "WHERE 1=1";
  if (!empty($selected_greeting)) {
    $where_clause .= $wpdb->prepare(" AND greeting LIKE %s", '%' . $wpdb->esc_like($selected_greeting) . '%');
  }

  // Pagination Setup
  $per_page = 40;
  $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
  $offset = ($current_page - 1) * $per_page;

  $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
  $total_pages = ceil($total_items / $per_page);

  $results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
  ));
?>
  <div class="wrap">
    <style>
      .status-cell {
        cursor: pointer;
        position: relative;
        min-width: 120px;
      }

      .status-cell:hover {
        background-color: #f9f9f9;
      }

      .status-select {
        width: 100%;
        max-width: 150px;
      }

      .status-cell span {
        display: inline-block;
        padding: 2px 4px;
        border-radius: 3px;
      }
    </style>

    <?php if ($edit_data): ?>
      <script>
        document.addEventListener("DOMContentLoaded", function() {
          openModal();
        });
      </script>
    <?php endif; ?>
    <div id="modalForm" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
      <div style="background:#fff; margin:5% auto; padding:20px; width:90%; max-width:700px; border-radius:8px; position:relative;">
        <button onclick="closeModal()" style="position:absolute; top:10px; right:10px;">✕</button>
        <h2><?php echo $edit_data ? 'Edit Data' : 'Tambah Data'; ?></h2>
        <form method="post">
          <?php wp_nonce_field('velocity_crud_action'); ?>
          <?php if ($edit_data): ?>
            <input type="hidden" name="id" value="<?php echo intval($edit_data->id); ?>">
          <?php endif; ?>
          <table class="form-table">
            <tr>
              <th><label for="nama">Nama</label></th>
              <td><input name="nama" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->nama ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="no_whatsapp">No WhatsApp</label></th>
              <td><input name="no_whatsapp" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->no_whatsapp ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="jenis_website">Jenis Website</label></th>
              <td><input name="jenis_website" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->jenis_website ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="via">Via</label></th>
              <td><input name="via" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->via ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="utm_content">UTM Content</label></th>
              <td><input name="utm_content" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->utm_content ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="utm_medium">UTM Medium</label></th>
              <td><input name="utm_medium" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->utm_medium ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="greeting">Greeting</label></th>
              <td><input name="greeting" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->greeting ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="gclid">GCLID</label></th>
              <td><input name="gclid" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->gclid ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="status">Status</label></th>
              <td>
                <select name="status" class="regular-text">
                  <option value="">Pilih Status</option>
                  <option value="sesuai" <?php echo (isset($edit_data->status) && $edit_data->status === 'sesuai') ? 'selected' : ''; ?>>Sesuai</option>
                  <option value="salah sambung" <?php echo (isset($edit_data->status) && $edit_data->status === 'salah sambung') ? 'selected' : ''; ?>>Salah Sambung</option>
                  <option value="tidak ada nomor" <?php echo (isset($edit_data->status) && $edit_data->status === 'tidak ada nomor') ? 'selected' : ''; ?>>Tidak Ada Nomor</option>
                </select>
              </td>
            </tr>
          </table>
          <?php submit_button($edit_data ? 'Update' : 'Tambah'); ?>
        </form>
      </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <div style="display: flex; gap: 10px; align-items: center;">
        <button type="button" class="button button-primary" onclick="openModal()">+ Tambah Data</button>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" style="display: inline;">
          <input type="hidden" name="action" value="velocity_export_to_excel">
          <button type="submit" class="button button-primary">
            <span style="margin-top: 5px;" class="dashicons dashicons-download"></span> Export Excel</button>
        </form>
      </div>

      <!-- Filter Greeting -->
      <form method="get" action="" style="display: flex; align-items: center; gap: 10px;">
        <input type="hidden" name="page" value="rekap-chat-form">
        <label for="filter_greeting" style="font-weight: bold;">Filter Greeting:</label>
        <input
          type="text"
          name="filter_greeting"
          id="filter_greeting"
          value="<?php echo htmlspecialchars($selected_greeting); ?>"
          placeholder="Contoh: v5008"
          style="padding: 5px; border: 1px solid #ccc; border-radius: 3px; width: 200px;">
        <button type="submit" class="button button-primary">Filter</button>
        <button type="button" class="button" onclick="clearFilter()">Clear</button>
      </form>
    </div>

    <?php if (!empty($selected_greeting)): ?>
      <div style="background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px; padding: 8px 12px; margin-bottom: 15px;">
        <strong>Filter Aktif:</strong>
        Greeting = <?php echo htmlspecialchars($selected_greeting); ?>
        <a href="?page=rekap-chat-form" style="float: right; color: #d63638;">✕ Hapus Filter</a>
        <div style="margin-top: 5px; font-size: 12px; color: #50575e;">
          Menampilkan <strong><?php echo $total_items; ?></strong> data
        </div>
      </div>
    <?php elseif ($total_items > 0): ?>
      <div style="margin-bottom: 10px; color: #50575e;">
        Menampilkan <strong><?php echo $total_items; ?></strong> data
      </div>
    <?php endif; ?>

    <form method="post">

      <div style="display: flex; justify-content:space-between;">
        <button type="submit" id="cek-jenis-website" class="button button-primary">Cek Jenis website</button>
        <?php
        submit_button('Hapus yang dipilih', 'delete', '', false);
        ?>
      </div>
      <br>
      <input type="hidden" name="bulk_action" value="delete">
      <table class="widefat fixed striped" style="margin: 10px 0;">
        <thead>
          <tr>
            <th style="text-align: center;width: 40px;"><input style="margin: 0;" type="checkbox" id="select_all"></th>
            <th>Nama</th>
            <th>No WhatsApp</th>
            <th>Jenis Website</th>
            <th>Perangkat</th>
            <th>UTM Content</th>
            <th>UTM Medium</th>
            <th>Greeting</th>
            <th>GCLID</th>
            <th>Hasil Check CS</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($results): foreach ($results as $row): ?>
              <tr>
                <td style="text-align: center;"><input type="checkbox" name="selected_ids[]" value="<?php echo intval($row->id); ?>"></td>
                <td><?php echo esc_html($row->nama); ?></td>
                <td><?php echo esc_html($row->no_whatsapp);
                    echo show_valid_wa_icon($row->no_whatsapp); ?></td>
                <td>
                  <?php echo esc_html($row->jenis_website); ?>
                  <span class="ai-result" data-id="<?php echo intval($row->id); ?>">
                    <?php echo format_ai_result($row->ai_result); ?>
                  </span>
                </td>
                <td><?php echo esc_html($row->via); ?></td>
                <td><?php echo esc_html($row->utm_content); ?></td>
                <td><?php echo esc_html($row->utm_medium); ?></td>
                <td><?php echo esc_html($row->greeting); ?></td>
                <td><?php echo esc_html($row->gclid); ?></td>
                <td>
                  <div class="status-cell" data-id="<?php echo intval($row->id); ?>">
                    <?php echo format_status($row->status); ?>
                    <select class="status-select" style="display:none;" data-id="<?php echo intval($row->id); ?>">
                      <option value="">Pilih Status</option>
                      <option value="sesuai" <?php echo (isset($row->status) && $row->status === 'sesuai') ? 'selected' : ''; ?>>Sesuai</option>
                      <option value="salah sambung" <?php echo (isset($row->status) && $row->status === 'salah sambung') ? 'selected' : ''; ?>>Salah Sambung</option>
                      <option value="tidak ada nomor" <?php echo (isset($row->status) && $row->status === 'tidak ada nomor') ? 'selected' : ''; ?>>Tidak Ada Nomor</option>
                    </select>
                  </div>
                </td>
                <td><?php echo esc_html($row->created_at); ?></td>
                <td>
                  <a href="?page=rekap-chat-form&edit=<?php echo intval($row->id); ?>">Edit</a> |
                  <a href="?page=rekap-chat-form&delete=<?php echo intval($row->id); ?>" onclick="return confirm('Hapus data ini?')">Hapus</a>
                </td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="11">
                <?php if (!empty($selected_greeting)): ?>
                  Tidak ada data dengan greeting "<strong><?php echo htmlspecialchars($selected_greeting); ?></strong>".
                  <br><a href="?page=rekap-chat-form">Tampilkan semua data</a>
                <?php else: ?>
                  Belum ada data.
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </form>

    <?php if ($total_pages > 1): ?>
      <div class="tablenav bottom">
        <div class="tablenav-pages">
          <?php
          $display_pages = [];
          // Always show first 5
          for ($i = 1; $i <= min(5, $total_pages); $i++) {
            $display_pages[] = $i;
          }
          // Always show last 3
          for ($i = max($total_pages - 2, 1); $i <= $total_pages; $i++) {
            $display_pages[] = $i;
          }
          // Show 2 before and after current page
          for ($i = max($current_page - 2, 1); $i <= min($current_page + 2, $total_pages); $i++) {
            $display_pages[] = $i;
          }
          $display_pages = array_unique($display_pages);
          sort($display_pages);

          $last = 0;
          for ($idx = 0; $idx < count($display_pages); $idx++) {
            $i = $display_pages[$idx];
            if ($last && $i > $last + 1) {
              echo '<span class="page-numbers dots" style="padding: 10px;">...</span>';
            }
            if ($i == $current_page) {
              echo '<span class="page-numbers current" style="padding: 10px;">' . $i . '</span>';
            } else {
              $pagination_url = '?page=rekap-chat-form&paged=' . $i;
              if (!empty($selected_greeting)) {
                $pagination_url .= '&filter_greeting=' . urlencode($selected_greeting);
              }
              echo '<a class="page-numbers" style="padding: 10px;" href="' . esc_url($pagination_url) . '">' . $i . '</a>';
            }
            $last = $i;
          }
          ?>
        </div>
      </div>
    <?php endif; ?>

    <script>
      function openModal() {
        document.getElementById('modalForm').style.display = 'block';
      }

      function closeModal() {
        document.getElementById('modalForm').style.display = 'none';
      }

      function clearFilter() {
        // Reset ke halaman utama tanpa filter
        window.location.href = '?page=rekap-chat-form';
      }
      document.getElementById('select_all').addEventListener('click', function() {
        let checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
      });
    </script>

    <script>
      jQuery(document).ready(function($) {
        $('#cek-jenis-website').on('click', function(e) {
          e.preventDefault();
          let $this = $(this);
          // loading spin icon
          $this.html('Memproses...');
          let selectedIds = [];
          $('input[name="selected_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
          });

          if (selectedIds.length === 0) {
            alert('Pilih setidaknya satu data!');
            return;
          }

          $('#ai-validation-result').html('Memproses...');

          $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
              action: 'cek_jenis_website_ai',
              ids: selectedIds,
              _wpnonce: '<?php echo wp_create_nonce("cek_jenis_website_ai_nonce"); ?>'
            },
            success: function(response) {
              $('#ai-validation-result').html(response);
              // ganti spiner kembali
              $this.html('Cek Jenis Website');

              if (response && typeof response === 'string') {
                // Ambil data ID dan hasil dari response HTML sederhana
                let parser = new DOMParser();
                let htmlDoc = parser.parseFromString(response, 'text/html');
                let items = htmlDoc.querySelectorAll('li');

                items.forEach(item => {
                  let match = item.innerText.match(/ID (\d+):\s+(✅|❌|⚠️|❓)/);
                  if (match) {
                    let id = match[1];
                    let icon = match[2];

                    let target = document.querySelector('span.ai-result[data-id="' + id + '"]');
                    if (target) {
                      target.innerHTML = icon;
                    }
                  }
                });
              }
            },
            error: function() {
              $('#ai-validation-result').html('<div class="error">Terjadi kesalahan saat memproses permintaan.</div>');
            }
          });
        });
      });
    </script>

    <script>
      jQuery(document).ready(function($) {
        // Inline edit for status - use event delegation for dynamic elements
        $(document).on('click', '.status-cell', function(e) {
          e.stopPropagation();
          var $cell = $(this);
          var $select = $cell.find('.status-select');
          var $display = $cell.find('span').eq(0); // First span for status display

          if ($select.is(':visible')) {
            return; // Already in edit mode
          }

          // Show select, hide display
          $select.show();
          if ($display.length) {
            $display.hide();
          }

          // Focus on select
          $select.focus();
        });

        // Handle status change - use event delegation for dynamic elements
        $(document).on('change', '.status-select', function() {
          var $select = $(this);
          var status = $select.val();
          var id = $select.data('id');
          var $cell = $select.closest('.status-cell');

          // Update via AJAX
          $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
              action: 'update_inline_status',
              id: id,
              status: status,
              _wpnonce: '<?php echo wp_create_nonce("update_inline_status_nonce"); ?>'
            },
            success: function(response) {
              if (response.success) {
                // Update display with new status and recreate the cell structure
                $cell.html(formatStatusDisplay(status, id));
                // Show success notification
                showNotification('Status berhasil diperbarui', 'success');
              } else {
                showNotification('Gagal memperbarui status', 'error');
                // Hide select, show original display
                $select.hide();
                $cell.find('span').show();
              }
            },
            error: function() {
              showNotification('Terjadi kesalahan saat memperbarui status', 'error');
              $select.hide();
              $cell.find('span').show();
            }
          });
        });

        // Hide select when clicking outside
        $(document).on('click', function(e) {
          if (!$(e.target).closest('.status-cell').length) {
            $('.status-select').hide();
            $('.status-cell span').show();
          }
        });

        // Helper function to format status display
        function formatStatusDisplay(status, id) {
          var statusHtml = '';
          switch (status) {
            case 'sesuai':
              statusHtml = '<span style="color: green;">✅ Sesuai</span>';
              break;
            case 'salah sambung':
              statusHtml = '<span style="color: orange;">🔄 Salah Sambung</span>';
              break;
            case 'tidak ada nomor':
              statusHtml = '<span style="color: red;">❌ Tidak Ada Nomor</span>';
              break;
            default:
              statusHtml = '<span style="color: gray;">❓</span>';
              break;
          }

          // Return complete cell HTML with both display and select
          var selectedSesuai = status === 'sesuai' ? 'selected' : '';
          var selectedSalah = status === 'salah sambung' ? 'selected' : '';
          var selectedTidak = status === 'tidak ada nomor' ? 'selected' : '';

          return '<div class="status-cell" data-id="' + id + '">' +
            statusHtml +
            '<select class="status-select" style="display:none;" data-id="' + id + '">' +
            '<option value="">Pilih Status</option>' +
            '<option value="sesuai" ' + selectedSesuai + '>Sesuai</option>' +
            '<option value="salah sambung" ' + selectedSalah + '>Salah Sambung</option>' +
            '<option value="tidak ada nomor" ' + selectedTidak + '>Tidak Ada Nomor</option>' +
            '</select>' +
            '</div>';
        }

        // Notification helper
        function showNotification(message, type) {
          var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
          $('.wrap h1').after(notification);
          setTimeout(function() {
            notification.fadeOut(function() {
              $(this).remove();
            });
          }, 3000);
        }
      });
    </script>

  </div>
<?php
}


function show_valid_wa_icon($number)
{
  // Bersihkan input dari spasi, titik, dash
  $number = preg_replace('/[\s\.\-]/', '', $number);
  // jika di awali 62
  $number = preg_replace('/^62/', '0', $number);
  // Validasi: harus mulai dengan 08 dan panjang 10–14 digit
  if (preg_match('/^08[0-9]{8,12}$/', $number)) {
    return '<span >✅</span>'; // HTML icon centang
  } else {
    return '<span >❌</span>'; // HTML icon x
  }

  return '';
}

function format_ai_result($status)
{
  $status = strtolower(trim($status));
  if ($status === 'valid') {
    return '<span class="ai-status valid" style="color:green;">✅</span>';
  } elseif ($status === 'dilarang') {
    return '<span class="ai-status dilarang" style="color:red;">⚠️</span>';
  } elseif ($status) {
    return '<span class="ai-status unknown" style="color:gray;">❓</span>';
  }
  return '';
}

function format_status($status)
{
  switch (strtolower(trim($status))) {
    case 'sesuai':
      return '<span style="color: green;">✅ Sesuai</span>';
    case 'salah sambung':
      return '<span style="color: orange;">🔄 Salah Sambung</span>';
    case 'tidak ada nomor':
      return '<span style="color: red;">❌ Tidak Ada Nomor</span>';
    default:
      return '<span style="color: gray;">❓</span>';
  }
}

function velocity_render_whatsapp_clicks_page()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'vd_whatsapp_clicks';

  $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

  $today = wp_date('Y-m-d');
  $yesterday = wp_date('Y-m-d', strtotime('-1 day', strtotime($today)));

  $from_date = isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : '';
  $to_date = isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : '';
  $show_v0 = isset($_GET['show_v0']) && sanitize_text_field($_GET['show_v0']) === '1';

  if (isset($_POST['vd_wa_bulk_action']) && $_POST['vd_wa_bulk_action'] === 'delete' && !empty($_POST['vd_selected_ids'])) {
    check_admin_referer('vd_wa_bulk_delete', 'vd_wa_bulk_nonce');

    $ids = array_map('intval', (array) $_POST['vd_selected_ids']);
    if (!empty($ids)) {
      $placeholders = implode(',', array_fill(0, count($ids), '%d'));
      $wpdb->query(
        $wpdb->prepare(
          "DELETE FROM $table_name WHERE id IN ($placeholders)",
          $ids
        )
      );

      echo '<div class="updated"><p>' . esc_html(count($ids)) . ' data klik WhatsApp berhasil dihapus.</p></div>';
    }
  }

?>
  <div class="wrap">
    <h1>Klik WhatsApp</h1>
    <?php if (!$table_exists): ?>
      <p>Belum ada data klik WhatsApp yang terekam.</p>
  </div>
<?php
      return;
    endif;

    $has_event_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'event_id'");
    $has_status = (bool) $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'status'");
    $has_retry_count = (bool) $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'retry_count'");
    $has_last_error = (bool) $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'last_error'");
    $has_greeting = (bool) $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'greeting'");

    $select_columns = "id,"
      . ($has_event_id ? " event_id," : " '' AS event_id,")
      . ($has_status ? " status," : " 'success' AS status,")
      . ($has_retry_count ? " retry_count," : " 0 AS retry_count,")
      . ($has_last_error ? " last_error," : " '' AS last_error,")
      . ($has_greeting ? " greeting," : " '' AS greeting,")
      . " ip_address, referer, user_agent, created_at";

    $greeting_filter_sql = $has_greeting ? " AND (greeting IS NULL OR greeting <> 'v0')" : '';

    $total_all = (int) $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE ip_address <> ''$greeting_filter_sql");

    $today_count = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE DATE(created_at) = %s AND ip_address <> ''$greeting_filter_sql",
        $today
      )
    );

    $yesterday_count = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE DATE(created_at) = %s AND ip_address <> ''$greeting_filter_sql",
        $yesterday
      )
    );

    $month_count = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE YEAR(created_at) = YEAR(%s) AND MONTH(created_at) = MONTH(%s) AND ip_address <> ''$greeting_filter_sql",
        $today,
        $today
      )
    );

    $per_page = 30;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $where = "WHERE 1=1";
    $where_params = [];

    if ($has_greeting && !$show_v0) {
      $where .= " AND (greeting IS NULL OR greeting <> 'v0')";
    }

    if (!empty($from_date)) {
      $where .= " AND DATE(created_at) >= %s";
      $where_params[] = $from_date;
    }

    if (!empty($to_date)) {
      $where .= " AND DATE(created_at) <= %s";
      $where_params[] = $to_date;
    }

    if (!empty($where_params)) {
      $total_items = (int) $wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*) FROM $table_name $where",
          $where_params
        )
      );
    } else {
      $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    }

    $total_pages = max(1, ceil($total_items / $per_page));

    if (!empty($where_params)) {
      $latest_clicks = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT $select_columns FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
          ...array_merge($where_params, [$per_page, $offset])
        )
      );
    } else {
      $latest_clicks = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT $select_columns FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
          $per_page,
          $offset
        )
      );
    }
?>

<style>
  .vd-summary-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin: 12px 0 24px;
  }

  .vd-summary-card {
    flex: 1 1 180px;
    background: #ffffff;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.06);
    border: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .vd-summary-label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  .vd-summary-value {
    font-size: 26px;
    font-weight: 700;
    color: #111827;
    line-height: 1.1;
  }

  .vd-summary-caption {
    font-size: 12px;
    color: #9ca3af;
  }

  @media (prefers-color-scheme: dark) {
    .vd-summary-card {
      background: #020617;
      border-color: #1f2937;
      box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.6), 0 4px 6px -4px rgba(15, 23, 42, 0.7);
    }

    .vd-summary-label {
      color: #9ca3af;
    }

    .vd-summary-value {
      color: #f9fafb;
    }

    .vd-summary-caption {
      color: #6b7280;
    }
  }
</style>

<div class="vd-summary-grid">
  <div class="vd-summary-card">
    <div class="vd-summary-label">Hari ini</div>
    <div class="vd-summary-value"><?php echo esc_html($today_count); ?></div>
    <div class="vd-summary-caption">Jumlah IP unik pada tanggal <?php echo esc_html($today); ?></div>
  </div>
  <div class="vd-summary-card">
    <div class="vd-summary-label">Kemarin</div>
    <div class="vd-summary-value"><?php echo esc_html($yesterday_count); ?></div>
    <div class="vd-summary-caption">Jumlah IP unik pada tanggal <?php echo esc_html($yesterday); ?></div>
  </div>
  <div class="vd-summary-card">
    <div class="vd-summary-label">Bulan ini</div>
    <div class="vd-summary-value"><?php echo esc_html($month_count); ?></div>
    <div class="vd-summary-caption">Total IP unik di bulan ini</div>
  </div>
  <div class="vd-summary-card">
    <div class="vd-summary-label">Total</div>
    <div class="vd-summary-value"><?php echo esc_html($total_all); ?></div>
    <div class="vd-summary-caption">Total semua IP unik yang terekam</div>
  </div>
</div>
<h2 style="margin-top: 30px;">Klik Terbaru</h2>

<form method="get" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
  <input type="hidden" name="page" value="rekap-whatsapp-clicks">
  <div>
    <label for="from_date">Dari tanggal</label><br>
    <input type="date" id="from_date" name="from_date" value="<?php echo esc_attr($from_date); ?>">
  </div>
  <div>
    <label for="to_date">Sampai tanggal</label><br>
    <input type="date" id="to_date" name="to_date" value="<?php echo esc_attr($to_date); ?>">
  </div>
  <div>
    <label for="show_v0" style="display:block;">&nbsp;</label>
    <label style="display:flex;gap:6px;align-items:center;">
      <input type="checkbox" id="show_v0" name="show_v0" value="1" <?php checked($show_v0, true); ?>>
      Tampilkan organik (v0)
    </label>
  </div>
  <div>
    <button type="submit" class="button button-primary">Filter</button>
    <a href="<?php echo esc_url(admin_url('admin.php?page=rekap-whatsapp-clicks')); ?>" class="button">Reset</a>
  </div>
</form>

<?php if ($latest_clicks): ?>
  <form method="post">
    <?php wp_nonce_field('vd_wa_bulk_delete', 'vd_wa_bulk_nonce'); ?>
    <input type="hidden" name="vd_wa_bulk_action" value="delete">
    <div style="margin-bottom: 10px;">
      <button type="submit" class="button button-secondary" onclick="return confirm('Hapus data yang dipilih?');">
        Hapus yang dipilih
      </button>
    </div>
    <style>
      .vd-click-table {
        table-layout: auto;
        --vd-greeting-col-width: 90px;
        --vd-keyword-col-width: 120px;
      }

      .vd-date-col,
      .vd-date-cell,
      .vd-ip-col,
      .vd-ip-cell {
        width: 10%;
        white-space: nowrap;
      }

      .vd-event-col,
      .vd-event-cell {
        width: 3%;
        white-space: nowrap;
      }

      .vd-keyword-col,
      .vd-keyword-cell {
        width: var(--vd-keyword-col-width);
        max-width: var(--vd-keyword-col-width);
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
      }

      .vd-greeting-col,
      .vd-greeting-cell {
        width: var(--vd-greeting-col-width);
        max-width: var(--vd-greeting-col-width);
        white-space: nowrap;
      }

      .vd-status-col,
      .vd-status-cell {
        width: 5%;
        white-space: nowrap;
      }

      .vd-retry-col,
      .vd-retry-cell {
        width: 3%;
        white-space: nowrap;
      }

      .vd-error-col,
      .vd-error-cell {
        width: 3%;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
      }

      .vd-status-chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .vd-status-success {
        background: #dcfce7;
        color: #166534;
      }

      .vd-status-pending {
        background: #fef3c7;
        color: #92400e;
      }

      .vd-status-failed {
        background: #fee2e2;
        color: #991b1b;
      }

      .vd-ua-cell {
        max-width: 460px;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
      }

      .vd-page-cell {
        max-width: 460px;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
      }

      .vd-ip-cell {
        max-width: 160px;
      }

      .vd-copy-wrap {
        display: flex;
        align-items: flex-start;
        gap: 6px;
      }

      .vd-copy-text {
        flex: 1;
        min-width: 0;
      }

      .vd-copy-btn {
        color: #2271b1;
        text-decoration: none;
        line-height: 1;
      }

      .vd-copy-icon {
        display: inline-flex;
        width: 16px;
        height: 16px;
      }

      .vd-copy-icon svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
      }

      .vd-copy-btn.is-copied {
        color: #15803d;
      }

      @media (max-width: 782px) {
        .vd-click-table {
          border: 0;
          background: transparent;
        }

        .vd-click-table thead {
          display: none;
        }

        .vd-click-table tbody {
          display: block;
        }

        .vd-click-table tbody tr {
          display: block;
          background: #fff;
          border: 1px solid #dcdcde;
          border-radius: 10px;
          margin-bottom: 12px;
          padding: 10px 12px;
        }

        .vd-click-table tbody td {
          display: grid;
          grid-template-columns: 92px 1fr;
          gap: 8px;
          border: 0;
          padding: 6px 0;
          width: 100% !important;
          max-width: none !important;
          white-space: normal !important;
          word-break: break-word;
          overflow-wrap: anywhere;
        }

        .vd-click-table tbody td::before {
          content: attr(data-label);
          font-weight: 700;
          color: #1f2937;
        }

        .vd-click-table .vd-select-cell {
          grid-template-columns: 92px auto;
          align-items: center;
        }

        .vd-click-table .vd-select-cell input[type="checkbox"] {
          margin: 0;
        }

        .vd-click-table .vd-copy-wrap {
          align-items: flex-start;
        }
      }
    </style>
    <table class="widefat fixed striped vd-click-table">
      <thead>
        <tr>
          <th style="width:40px;text-align:center;">
            <input type="checkbox" id="vd_select_all_clicks">
          </th>
          <th class="vd-date-col">Tanggal</th>
          <th class="vd-greeting-col">Greeting</th>
          <th class="vd-keyword-col">Kata Kunci</th>
          <th class="vd-event-col">Event</th>
          <th class="vd-status-col">Status</th>
          <th class="vd-retry-col">Retry</th>
          <th class="vd-error-col">Error</th>
          <th>Page</th>
          <th class="vd-ip-col">IP</th>
          <th>User Agent</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($latest_clicks as $click): ?>
          <?php
          $event_short = !empty($click->event_id) ? substr($click->event_id, 0, 4) : '-';
          $status_raw = !empty($click->status) ? strtolower(trim($click->status)) : '-';
          $status_class = 'vd-status-pending';
          if ($status_raw === 'success') {
            $status_class = 'vd-status-success';
          } elseif ($status_raw === 'failed') {
            $status_class = 'vd-status-failed';
          } elseif ($status_raw === '-' || $status_raw === '') {
            $status_class = 'vd-status-success';
            $status_raw = 'success';
          }
          $greeting_value = '-';
          if (isset($click->greeting) && trim((string) $click->greeting) !== '') {
            $greeting_value = sanitize_text_field((string) $click->greeting);
          }
          $keyword = '-';
          $referer_url = isset($click->referer) ? (string) $click->referer : '';
          if ($referer_url !== '') {
            $query_string = wp_parse_url($referer_url, PHP_URL_QUERY);
            if (is_string($query_string) && $query_string !== '') {
              $query_args = [];
              parse_str($query_string, $query_args);
              if (isset($query_args['utm_term']) && trim((string) $query_args['utm_term']) !== '') {
                $keyword = sanitize_text_field((string) $query_args['utm_term']);
              }
            }
          }
          $retry_count = isset($click->retry_count) && $click->retry_count !== null ? intval($click->retry_count) : 0;
          $last_error = isset($click->last_error) && trim((string) $click->last_error) !== '' ? (string) $click->last_error : '-';
          ?>
          <tr>
            <td class="vd-select-cell" data-label="Pilih">
              <input type="checkbox" name="vd_selected_ids[]" value="<?php echo intval($click->id); ?>">
            </td>
            <td class="vd-date-cell" data-label="Tanggal"><?php echo esc_html($click->created_at); ?></td>
            <td class="vd-greeting-cell" data-label="Greeting"><?php echo esc_html($greeting_value); ?></td>
            <td class="vd-keyword-cell" data-label="Kata Kunci"><?php echo esc_html($keyword); ?></td>
            <td class="vd-event-cell" data-label="Event"><?php echo esc_html($event_short); ?></td>
            <td class="vd-status-cell" data-label="Status">
              <span class="vd-status-chip <?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($status_raw); ?>
              </span>
            </td>
            <td class="vd-retry-cell" data-label="Retry"><?php echo esc_html($retry_count); ?></td>
            <td class="vd-error-cell" data-label="Error" title="<?php echo esc_attr($last_error); ?>">
              <?php echo esc_html($last_error); ?>
            </td>
            <td class="vd-page-cell" data-label="Page" title="<?php echo esc_attr($click->referer); ?>">
              <div class="vd-copy-wrap">
                <span class="vd-copy-text"><?php echo esc_html($click->referer); ?></span>
                <button
                  type="button"
                  class="button-link vd-copy-btn"
                  data-copy="<?php echo esc_attr($click->referer); ?>"
                  title="Copy Page">
                  <span class="vd-copy-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M8 8a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-1v-1h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-9a1 1 0 0 0-1 1v1H8V8z"></path>
                      <path d="M5 11a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9zm2-1a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H7z"></path>
                    </svg>
                  </span>
                </button>
              </div>
            </td>
            <td class="vd-ip-cell" data-label="IP" title="<?php echo esc_attr($click->ip_address); ?>">
              <div class="vd-copy-wrap">
                <span class="vd-copy-text"><?php echo esc_html($click->ip_address); ?></span>
                <button
                  type="button"
                  class="button-link vd-copy-btn"
                  data-copy="<?php echo esc_attr($click->ip_address); ?>"
                  title="Copy IP">
                  <span class="vd-copy-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M8 8a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-1v-1h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-9a1 1 0 0 0-1 1v1H8V8z"></path>
                      <path d="M5 11a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9zm2-1a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H7z"></path>
                    </svg>
                  </span>
                </button>
              </div>
            </td>
            <td class="vd-ua-cell" data-label="User Agent" title="<?php echo esc_attr($click->user_agent); ?>">
              <div class="vd-copy-wrap">
                <span class="vd-copy-text"><?php echo esc_html($click->user_agent); ?></span>
                <button
                  type="button"
                  class="button-link vd-copy-btn"
                  data-copy="<?php echo esc_attr($click->user_agent); ?>"
                  title="Copy User Agent">
                  <span class="vd-copy-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path d="M8 8a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-1v-1h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-9a1 1 0 0 0-1 1v1H8V8z"></path>
                      <path d="M5 11a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9zm2-1a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H7z"></path>
                    </svg>
                  </span>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>
  <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
      <div class="tablenav-pages">
        <?php
        $display_pages = [];
        for ($i = 1; $i <= min(5, $total_pages); $i++) {
          $display_pages[] = $i;
        }
        for ($i = max($total_pages - 2, 1); $i <= $total_pages; $i++) {
          $display_pages[] = $i;
        }
        for ($i = max($current_page - 2, 1); $i <= min($current_page + 2, $total_pages); $i++) {
          $display_pages[] = $i;
        }
        $display_pages = array_unique($display_pages);
        sort($display_pages);

        $base_args = ['page' => 'rekap-whatsapp-clicks'];
        if (!empty($from_date)) {
          $base_args['from_date'] = $from_date;
        }
        if (!empty($to_date)) {
          $base_args['to_date'] = $to_date;
        }
        if ($show_v0) {
          $base_args['show_v0'] = '1';
        }

        $last = 0;
        foreach ($display_pages as $i) {
          if ($last && $i > $last + 1) {
            echo '<span class="page-numbers dots" style="padding: 10px;">...</span>';
          }
          if ($i == $current_page) {
            echo '<span class="page-numbers current" style="padding: 10px;">' . $i . '</span>';
          } else {
            $pagination_url = add_query_arg(
              array_merge($base_args, ['paged' => $i]),
              admin_url('admin.php')
            );
            echo '<a class="page-numbers" style="padding: 10px;" href="' . esc_url($pagination_url) . '">' . $i . '</a>';
          }
          $last = $i;
        }
        ?>
      </div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div style="text-align: center; padding: 20px;width: 100%;">Belum ada klik yang terekam sesuai filter.</div>
<?php endif; ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    function copyTextToClipboard(text) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
      }

      return new Promise(function(resolve, reject) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
          var success = document.execCommand('copy');
          document.body.removeChild(textarea);
          if (success) {
            resolve();
          } else {
            reject();
          }
        } catch (error) {
          document.body.removeChild(textarea);
          reject(error);
        }
      });
    }

    var selectAll = document.getElementById('vd_select_all_clicks');
    if (selectAll) {
      selectAll.addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('input[name="vd_selected_ids[]"]');
        checkboxes.forEach(function(cb) {
          cb.checked = selectAll.checked;
        });
      });
    }

    var copyButtons = document.querySelectorAll('.vd-copy-btn');
    copyButtons.forEach(function(button) {
      button.addEventListener('click', function() {
        var text = button.getAttribute('data-copy') || '';
        if (!text) {
          return;
        }

        copyTextToClipboard(text).then(function() {
          button.classList.add('is-copied');
          button.setAttribute('title', 'Copied');
          setTimeout(function() {
            button.classList.remove('is-copied');
            button.setAttribute('title', 'Copy');
          }, 1200);
        });
      });
    });
  });
</script>
</div>
<?php
}

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
    'Lead Queue',
    'Lead Queue',
    'manage_options',
    'rekap-lead-queue',
    'velocity_render_lead_queue_page'
  );

  add_submenu_page(
    'rekap-chat-form',
    'Funnel Form',
    'Funnel Form',
    'manage_options',
    'rekap-form-funnel',
    'velocity_render_form_funnel_page'
  );

  add_submenu_page(
    'rekap-chat-form',
    'Blacklist Kata Konversi',
    'Blacklist Kata Konversi',
    'manage_options',
    'rekap-conversion-blacklist',
    'velocity_render_conversion_blacklist_page'
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

  register_setting(
    'vd_conversion_blacklist_settings_group',
    'vd_conversion_blacklist_enabled',
    [
      'sanitize_callback' => 'vd_sanitize_conversion_blacklist_enabled',
      'default' => '0',
    ]
  );

  register_setting(
    'vd_conversion_blacklist_settings_group',
    'vd_conversion_blacklist_keywords',
    [
      'sanitize_callback' => 'vd_sanitize_conversion_blacklist_keywords',
      'default' => '',
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

function velocity_render_conversion_blacklist_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $blacklist_enabled = vd_get_conversion_blacklist_enabled();
  $blacklist_keywords = (string) get_option('vd_conversion_blacklist_keywords', '');
?>
  <div class="wrap">
    <h1>Blacklist Kata Konversi</h1>
    <?php settings_errors('vd_conversion_blacklist_keywords'); ?>
    <form method="post" action="options.php">
      <?php settings_fields('vd_conversion_blacklist_settings_group'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">Aktifkan Blacklist Kata Konversi</th>
          <td>
            <label for="vd_conversion_blacklist_enabled">
              <input
                type="checkbox"
                id="vd_conversion_blacklist_enabled"
                name="vd_conversion_blacklist_enabled"
                value="1"
                <?php checked($blacklist_enabled); ?>>
              Jika aktif, lead yang mengandung frasa di daftar blacklist tetap masuk chat, tetapi konversi tidak dikirim.
            </label>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="vd_conversion_blacklist_keywords">Daftar Kata / Frasa Blacklist</label>
          </th>
          <td>
            <textarea
              id="vd_conversion_blacklist_keywords"
              name="vd_conversion_blacklist_keywords"
              rows="10"
              class="large-text code"
              style="max-width: 720px;"><?php echo esc_textarea($blacklist_keywords); ?></textarea>
            <p class="description">
              Isi satu kata atau frasa per baris. Baris kosong diabaikan. Spasi di tengah frasa tetap dipertahankan.
            </p>
            <p class="description">
              Contoh:
            </p>
            <pre style="background:#fff; padding:10px; border:1px solid #ccd0d4; max-width: 420px;">uang jajan
minta uang
pinjam uang</pre>
          </td>
        </tr>
      </table>
      <?php submit_button('Simpan Blacklist Kata Konversi'); ?>
    </form>
  </div>
<?php
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

function velocity_format_tanggal_indonesia($datetime_value)
{
  if (empty($datetime_value)) {
    return '-';
  }

  $timestamp = strtotime((string) $datetime_value);
  if (!$timestamp) {
    return esc_html((string) $datetime_value);
  }

  $bulan = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
  ];

  $hari = (int) date('j', $timestamp);
  $bulan_index = (int) date('n', $timestamp);
  $tahun = date('Y', $timestamp);

  return $hari . ' ' . $bulan[$bulan_index] . ' ' . $tahun;
}

function velocity_format_tanggal_waktu_indonesia($datetime_value)
{
  if (empty($datetime_value)) {
    return '-';
  }

  $timestamp = strtotime((string) $datetime_value);
  if (!$timestamp) {
    return esc_html((string) $datetime_value);
  }

  return velocity_format_tanggal_indonesia($datetime_value) . ' ' . date('H:i:s', $timestamp);
}

function velocity_render_whatsapp_clicks_page()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'vd_whatsapp_clicks';

  $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

  $today = wp_date('Y-m-d');
  $yesterday = wp_date('Y-m-d', strtotime('-1 day', strtotime($today)));
  $default_from_date = wp_date('Y-m-d', strtotime('-29 days', strtotime($today)));

  $from_date = isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : $default_from_date;
  $to_date = isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : $today;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = $default_from_date;
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = $today;
  }
  if ($from_date > $to_date) {
    [$from_date, $to_date] = [$to_date, $from_date];
  }
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

    $period_label = (isset($_GET['from_date']) || isset($_GET['to_date'])) ? 'Periode Filter' : '30 Hari Terakhir';
    $per_page = 30;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    $where = "WHERE ip_address <> ''";
    $where_params = [];

    if ($has_greeting && !$show_v0) {
      $where .= " AND (greeting IS NULL OR greeting <> 'v0')";
    }

    $where .= " AND DATE(created_at) >= %s AND DATE(created_at) <= %s";
    $where_params[] = $from_date;
    $where_params[] = $to_date;

    $period_unique_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT ip_address) FROM $table_name $where", $where_params));
    $period_total_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $where", $where_params));
    $daily_summary = $wpdb->get_results($wpdb->prepare("SELECT DATE(created_at) AS summary_date, COUNT(DISTINCT ip_address) AS unique_ips, COUNT(*) AS total_clicks FROM $table_name $where GROUP BY DATE(created_at) ORDER BY summary_date DESC", $where_params));
    $total_items = $period_total_count;
    $total_pages = max(1, ceil($total_items / $per_page));
    $latest_clicks = $wpdb->get_results($wpdb->prepare("SELECT $select_columns FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d", ...array_merge($where_params, [$per_page, $offset])));
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

<form method="get" style="margin: 18px 0 15px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
  <input type="hidden" name="page" value="rekap-whatsapp-clicks">
  <div><label for="from_date">Dari tanggal</label><br><input type="date" id="from_date" name="from_date" value="<?php echo esc_attr($from_date); ?>"></div>
  <div><label for="to_date">Sampai tanggal</label><br><input type="date" id="to_date" name="to_date" value="<?php echo esc_attr($to_date); ?>"></div>
  <div><label for="show_v0" style="display:block;">&nbsp;</label><label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" id="show_v0" name="show_v0" value="1" <?php checked($show_v0, true); ?>>Tampilkan organik (v0)</label></div>
  <div><button type="submit" class="button button-primary">Terapkan Filter</button> <a href="<?php echo esc_url(admin_url('admin.php?page=rekap-whatsapp-clicks')); ?>" class="button">Reset</a></div>
</form>
<div class="vd-summary-grid">
  <div class="vd-summary-card"><div class="vd-summary-label">Hari ini</div><div class="vd-summary-value"><?php echo esc_html($today_count); ?></div><div class="vd-summary-caption">IP unik pada <?php echo esc_html($today); ?></div></div>
  <div class="vd-summary-card"><div class="vd-summary-label">Kemarin</div><div class="vd-summary-value"><?php echo esc_html($yesterday_count); ?></div><div class="vd-summary-caption">IP unik pada <?php echo esc_html($yesterday); ?></div></div>
  <div class="vd-summary-card"><div class="vd-summary-label"><?php echo esc_html($period_label); ?></div><div class="vd-summary-value"><?php echo esc_html($period_unique_count); ?></div><div class="vd-summary-caption">IP unik <?php echo esc_html($from_date); ?> s/d <?php echo esc_html($to_date); ?></div></div>
  <div class="vd-summary-card"><div class="vd-summary-label">Klik di Periode</div><div class="vd-summary-value"><?php echo esc_html($period_total_count); ?></div><div class="vd-summary-caption">Total klik <?php echo esc_html($from_date); ?> s/d <?php echo esc_html($to_date); ?></div></div>
</div>
<h2 style="margin-top: 24px;">Summary Harian</h2>
<table class="widefat striped" style="max-width:720px;margin-bottom:24px;"><thead><tr><th>Tanggal</th><th>IP Unik</th><th>Total Klik</th></tr></thead><tbody><?php if ($daily_summary): foreach ($daily_summary as $row): ?><tr><td><?php echo esc_html($row->summary_date); ?></td><td><?php echo esc_html($row->unique_ips); ?></td><td><?php echo esc_html($row->total_clicks); ?></td></tr><?php endforeach; else: ?><tr><td colspan="3">Belum ada data untuk periode ini.</td></tr><?php endif; ?></tbody></table>
<h2 style="margin-top: 30px;">Detail Klik</h2>

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

function velocity_render_form_funnel_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  global $wpdb;
  $table_name = $wpdb->prefix . VD_FORM_FUNNEL_TABLE;
  $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

  $today = wp_date('Y-m-d');
  $default_from_date = wp_date('Y-m-d', strtotime('-29 days', strtotime($today)));

  $from_date = isset($_GET['from_date']) ? sanitize_text_field(wp_unslash($_GET['from_date'])) : $default_from_date;
  $to_date = isset($_GET['to_date']) ? sanitize_text_field(wp_unslash($_GET['to_date'])) : $today;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = $default_from_date;
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = $today;
  }
  if ($from_date > $to_date) {
    [$from_date, $to_date] = [$to_date, $from_date];
  }

  $selected_event = isset($_GET['event_type']) ? sanitize_text_field(wp_unslash($_GET['event_type'])) : '';
  $allowed_events = ['form_view', 'form_start', 'submit_enabled', 'submit_click'];
  if (!in_array($selected_event, $allowed_events, true)) {
    $selected_event = '';
  }

  $selected_traffic = isset($_GET['traffic_source']) ? sanitize_text_field(wp_unslash($_GET['traffic_source'])) : 'ads';
  $allowed_traffic = ['ads', 'organik', 'all'];
  if (!in_array($selected_traffic, $allowed_traffic, true)) {
    $selected_traffic = 'ads';
  }

  ?>
  <div class="wrap">
    <h1>Funnel Form</h1>
    <p>Tracking internal web untuk memantau tahapan <code>klik_wa_floating</code>, <code>form_view</code>, <code>form_start</code>, <code>submit_enabled</code>, dan <code>submit_click</code>.</p>
    <?php if (!$table_exists) : ?>
      <p>Belum ada data funnel form yang terekam.</p>
    </div>
    <?php
      return;
    endif;

    $where = 'WHERE DATE(created_at) >= %s AND DATE(created_at) <= %s';
    $params = [$from_date, $to_date];
    if ($selected_traffic !== 'all') {
      $where .= ' AND traffic_source = %s';
      $params[] = $selected_traffic;
    }
    if ($selected_event !== '') {
      $where .= ' AND event_type = %s';
      $params[] = $selected_event;
    }

    $summary_where = 'WHERE DATE(created_at) >= %s AND DATE(created_at) <= %s';
    $summary_params = [$from_date, $to_date];
    if ($selected_traffic !== 'all') {
      $summary_where .= ' AND traffic_source = %s';
      $summary_params[] = $selected_traffic;
    }

    $summary_rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT event_type, COUNT(DISTINCT ip_address) AS total FROM $table_name $summary_where GROUP BY event_type",
        ...$summary_params
      ),
      OBJECT_K
    );

    $summary_map = [];
    foreach ($allowed_events as $event_type) {
      $summary_map[$event_type] = isset($summary_rows[$event_type]) ? (int) $summary_rows[$event_type]->total : 0;
    }

    $wa_clicks_table = $wpdb->prefix . 'vd_whatsapp_clicks';
    $wa_clicks_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wa_clicks_table));
    $floating_click_total = 0;
    if ($wa_clicks_table_exists) {
      $wa_where = 'WHERE DATE(created_at) >= %s AND DATE(created_at) <= %s AND ip_address <> %s';
      $wa_params = [$from_date, $to_date, ''];
      if ($selected_traffic === 'ads') {
        $wa_where .= " AND greeting IS NOT NULL AND greeting <> '' AND greeting <> 'v0'";
      } elseif ($selected_traffic === 'organik') {
        $wa_where .= " AND greeting = 'v0'";
      }

      $floating_click_total = (int) $wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(DISTINCT ip_address) FROM $wa_clicks_table $wa_where",
          ...$wa_params
        )
      );
    }

    $daily_rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT DATE(created_at) AS summary_date,
                COUNT(DISTINCT CASE WHEN event_type = 'form_view' THEN ip_address END) AS form_view,
                COUNT(DISTINCT CASE WHEN event_type = 'form_start' THEN ip_address END) AS form_start,
                COUNT(DISTINCT CASE WHEN event_type = 'submit_enabled' THEN ip_address END) AS submit_enabled,
                COUNT(DISTINCT CASE WHEN event_type = 'submit_click' THEN ip_address END) AS submit_click
         FROM $table_name
         $summary_where
         GROUP BY DATE(created_at)
         ORDER BY summary_date DESC",
        ...$summary_params
      )
    );

    $daily_floating_clicks = [];
    if ($wa_clicks_table_exists) {
      $wa_daily_where = 'WHERE DATE(created_at) >= %s AND DATE(created_at) <= %s AND ip_address <> %s';
      $wa_daily_params = [$from_date, $to_date, ''];
      if ($selected_traffic === 'ads') {
        $wa_daily_where .= " AND greeting IS NOT NULL AND greeting <> '' AND greeting <> 'v0'";
      } elseif ($selected_traffic === 'organik') {
        $wa_daily_where .= " AND greeting = 'v0'";
      }

      $daily_floating_rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT DATE(created_at) AS summary_date, COUNT(DISTINCT ip_address) AS total_clicks
           FROM $wa_clicks_table
           $wa_daily_where
           GROUP BY DATE(created_at)",
          ...$wa_daily_params
        )
      );
      foreach ($daily_floating_rows as $daily_floating_row) {
        $daily_floating_clicks[$daily_floating_row->summary_date] = (int) $daily_floating_row->total_clicks;
      }
    }

    $daily_funnel_map = [];
    foreach ($daily_rows as $daily_row) {
      $daily_funnel_map[$daily_row->summary_date] = [
        'form_view' => (int) $daily_row->form_view,
        'form_start' => (int) $daily_row->form_start,
        'submit_enabled' => (int) $daily_row->submit_enabled,
        'submit_click' => (int) $daily_row->submit_click,
      ];
    }

    $daily_summary_dates = array_unique(array_merge(array_keys($daily_funnel_map), array_keys($daily_floating_clicks)));
    rsort($daily_summary_dates);

    $per_page = 30;
    $current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_items = (int) ($params
      ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $where", ...$params))
      : $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where"));
    $total_pages = max(1, (int) ceil($total_items / $per_page));

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, event_type, session_key, ip_address, page_url, referer, device, greeting, traffic_source, label, utm_content, utm_medium, created_at
         FROM $table_name
         $where
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        ...array_merge($params, [$per_page, $offset])
      )
    );

    $base_args = ['page' => 'rekap-form-funnel', 'from_date' => $from_date, 'to_date' => $to_date, 'traffic_source' => $selected_traffic];
    if ($selected_event !== '') {
      $base_args['event_type'] = $selected_event;
    }
    ?>

    <form method="get" style="margin: 18px 0 15px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
      <input type="hidden" name="page" value="rekap-form-funnel">
      <div><label for="from_date">Dari tanggal</label><br><input type="date" id="from_date" name="from_date" value="<?php echo esc_attr($from_date); ?>"></div>
      <div><label for="to_date">Sampai tanggal</label><br><input type="date" id="to_date" name="to_date" value="<?php echo esc_attr($to_date); ?>"></div>
      <div>
        <label for="traffic_source">Sumber Traffic</label><br>
        <select name="traffic_source" id="traffic_source">
          <option value="ads" <?php selected($selected_traffic, 'ads'); ?>>Ads</option>
          <option value="organik" <?php selected($selected_traffic, 'organik'); ?>>Organik</option>
          <option value="all" <?php selected($selected_traffic, 'all'); ?>>Semua</option>
        </select>
      </div>
      <div>
        <label for="event_type">Event</label><br>
        <select name="event_type" id="event_type">
          <option value="">Semua Event</option>
          <?php foreach ($allowed_events as $event_type) : ?>
            <option value="<?php echo esc_attr($event_type); ?>" <?php selected($selected_event, $event_type); ?>>
              <?php echo esc_html($event_type); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><button type="submit" class="button button-primary">Terapkan Filter</button> <a href="<?php echo esc_url(admin_url('admin.php?page=rekap-form-funnel')); ?>" class="button">Reset</a></div>
    </form>

    <p style="margin-top:-4px; color:#50575e;">
      Periode aktif: <strong><?php echo esc_html(velocity_format_tanggal_indonesia($from_date)); ?></strong> s/d <strong><?php echo esc_html(velocity_format_tanggal_indonesia($to_date)); ?></strong>
    </p>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin:16px 0 20px;">
      <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:12px 16px; min-width:140px;">
        <div style="font-size:12px; color:#50575e; text-transform:uppercase;">klik_wa_floating</div>
        <div style="font-size:24px; font-weight:600; line-height:1.2;"><?php echo intval($floating_click_total); ?></div>
      </div>
      <?php foreach ($summary_map as $event_type => $total) : ?>
        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:12px 16px; min-width:140px;">
          <div style="font-size:12px; color:#50575e; text-transform:uppercase;"><?php echo esc_html($event_type); ?></div>
          <div style="font-size:24px; font-weight:600; line-height:1.2;"><?php echo intval($total); ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <h2>Summary Harian</h2>
    <table class="widefat striped" style="max-width:860px; margin-bottom:24px;">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Klik WA Floating</th>
          <th>Form View</th>
          <th>Form Start</th>
          <th>Tombol Aktif</th>
          <th>Submit Click</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($daily_summary_dates) : ?>
          <?php foreach ($daily_summary_dates as $summary_date) : ?>
            <?php
            $daily_funnel = $daily_funnel_map[$summary_date] ?? [
              'form_view' => 0,
              'form_start' => 0,
              'submit_enabled' => 0,
              'submit_click' => 0,
            ];
            ?>
            <tr>
              <td><?php echo esc_html(velocity_format_tanggal_indonesia($summary_date)); ?></td>
              <td><?php echo isset($daily_floating_clicks[$summary_date]) ? intval($daily_floating_clicks[$summary_date]) : 0; ?></td>
              <td><?php echo intval($daily_funnel['form_view']); ?></td>
              <td><?php echo intval($daily_funnel['form_start']); ?></td>
              <td><?php echo intval($daily_funnel['submit_enabled']); ?></td>
              <td><?php echo intval($daily_funnel['submit_click']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr><td colspan="6">Belum ada data untuk periode ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <h2>Detail Event</h2>
    <table class="widefat striped" style="table-layout:fixed;">
      <thead>
        <tr>
          <th style="width:60px;">ID</th>
          <th style="width:140px;">Tanggal</th>
          <th style="width:120px;">Event</th>
          <th style="width:120px;">IP</th>
          <th style="width:140px;">Session</th>
          <th style="width:90px;">Device</th>
          <th style="width:80px;">Traffic</th>
          <th style="width:110px;">Greeting</th>
          <th style="width:120px;">Label</th>
          <th style="width:160px;">UTM</th>
          <th>Page</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows) : ?>
          <?php foreach ($rows as $row) : ?>
            <tr>
              <td><?php echo intval($row->id); ?></td>
              <td><?php echo esc_html(velocity_format_tanggal_waktu_indonesia($row->created_at)); ?></td>
              <td><strong><?php echo esc_html($row->event_type); ?></strong></td>
              <td><?php echo $row->ip_address ? esc_html($row->ip_address) : '-'; ?></td>
              <td style="word-break:break-all;"><?php echo esc_html($row->session_key); ?></td>
              <td><?php echo $row->device ? esc_html($row->device) : '-'; ?></td>
              <td><?php echo $row->traffic_source ? esc_html($row->traffic_source) : '-'; ?></td>
              <td><?php echo $row->greeting ? esc_html($row->greeting) : '-'; ?></td>
              <td><?php echo $row->label ? esc_html($row->label) : '-'; ?></td>
              <td><?php echo esc_html(trim(($row->utm_content ?: '-') . ' / ' . ($row->utm_medium ?: '-'), ' /')); ?></td>
              <td style="word-break:break-word;">
                <?php echo $row->page_url ? esc_html($row->page_url) : '-'; ?>
                <?php if (!empty($row->referer)) : ?>
                  <br><span style="color:#50575e; font-size:11px;"><?php echo esc_html($row->referer); ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr><td colspan="11" style="text-align:center; padding:18px;">Belum ada data detail sesuai filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
      <div class="tablenav bottom" style="margin-top:16px;">
        <div class="tablenav-pages">
          <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
            <?php $page_url = add_query_arg(array_merge($base_args, ['paged' => $i]), admin_url('admin.php')); ?>
            <?php if ($i === $current_page) : ?>
              <span class="page-numbers current" style="padding:10px;"><?php echo intval($i); ?></span>
            <?php else : ?>
              <a class="page-numbers" style="padding:10px;" href="<?php echo esc_url($page_url); ?>"><?php echo intval($i); ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php
}

function velocity_render_lead_queue_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  global $wpdb;
  $table_name = $wpdb->prefix . VD_LEAD_QUEUE_TABLE;

  if (
    isset($_GET['vd_queue_action'], $_GET['queue_id'], $_GET['_wpnonce']) &&
    $_GET['vd_queue_action'] === 'retry'
  ) {
    $queue_id = (int) $_GET['queue_id'];
    check_admin_referer('vd_retry_queue_' . $queue_id);

    $retried = vd_requeue_lead_queue_job($queue_id);
    if ($retried) {
      echo '<div class="notice notice-success is-dismissible"><p>Queue #' . intval($queue_id) . ' dijadwalkan ulang untuk diproses.</p></div>';
    } else {
      echo '<div class="notice notice-error is-dismissible"><p>Queue #' . intval($queue_id) . ' gagal dijadwalkan ulang.</p></div>';
    }
  }

  $selected_status = isset($_GET['queue_status']) ? sanitize_text_field(wp_unslash($_GET['queue_status'])) : '';
  $search = isset($_GET['queue_search']) ? sanitize_text_field(wp_unslash($_GET['queue_search'])) : '';

  $allowed_statuses = ['pending', 'processing', 'retrying', 'done', 'failed'];
  if (!in_array($selected_status, $allowed_statuses, true)) {
    $selected_status = '';
  }

  $where_clauses = ['1=1'];
  $query_params = [];

  if ($selected_status !== '') {
    $where_clauses[] = 'process_status = %s';
    $query_params[] = $selected_status;
  }

  if ($search !== '') {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where_clauses[] = '(event_id LIKE %s OR nama LIKE %s OR no_whatsapp LIKE %s OR jenis_website LIKE %s)';
    array_push($query_params, $like, $like, $like, $like);
  }

  $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

  $per_page = 30;
  $current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
  $offset = ($current_page - 1) * $per_page;

  $count_sql = "SELECT COUNT(*) FROM $table_name $where_sql";
  $total_items = $query_params
    ? (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$query_params))
    : (int) $wpdb->get_var($count_sql);
  $total_pages = max(1, (int) ceil($total_items / $per_page));

  $data_sql = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
  $data_params = $query_params;
  $data_params[] = $per_page;
  $data_params[] = $offset;
  $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$data_params));

  $summary_rows = $wpdb->get_results("SELECT process_status, COUNT(*) AS total FROM $table_name GROUP BY process_status", OBJECT_K);
  $summary_map = [];
  foreach ($allowed_statuses as $status) {
    $summary_map[$status] = isset($summary_rows[$status]) ? (int) $summary_rows[$status]->total : 0;
  }

  $base_args = ['page' => 'rekap-lead-queue'];
  if ($selected_status !== '') {
    $base_args['queue_status'] = $selected_status;
  }
  if ($search !== '') {
    $base_args['queue_search'] = $search;
  }
  ?>
  <div class="wrap">
    <h1>Lead Queue</h1>
    <p>Monitor proses lead yang masuk dulu ke queue sebelum diteruskan ke <code>rekap_form</code> dan Telegram.</p>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin:16px 0 20px;">
      <?php foreach ($summary_map as $status => $total) : ?>
        <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:12px 16px; min-width:120px;">
          <div style="font-size:12px; color:#50575e; text-transform:uppercase;"><?php echo esc_html($status); ?></div>
          <div style="font-size:24px; font-weight:600; line-height:1.2;"><?php echo intval($total); ?></div>
        </div>
      <?php endforeach; ?>
      <div style="background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:12px 16px; min-width:120px;">
        <div style="font-size:12px; color:#50575e; text-transform:uppercase;">total</div>
        <div style="font-size:24px; font-weight:600; line-height:1.2;"><?php echo intval($total_items); ?></div>
      </div>
    </div>

    <form method="get" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
      <input type="hidden" name="page" value="rekap-lead-queue">
      <div>
        <label for="queue_status" style="display:block; margin-bottom:4px;">Status</label>
        <select name="queue_status" id="queue_status">
          <option value="">Semua Status</option>
          <?php foreach ($allowed_statuses as $status) : ?>
            <option value="<?php echo esc_attr($status); ?>" <?php selected($selected_status, $status); ?>>
              <?php echo esc_html(ucfirst($status)); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="queue_search" style="display:block; margin-bottom:4px;">Cari</label>
        <input type="text" id="queue_search" name="queue_search" value="<?php echo esc_attr($search); ?>" placeholder="Nama / No WA / Event ID" style="min-width:260px;">
      </div>
      <div>
        <button type="submit" class="button button-primary">Terapkan Filter</button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=rekap-lead-queue')); ?>" class="button">Reset</a>
      </div>
    </form>

    <table class="widefat striped" style="table-layout:fixed;">
      <thead>
        <tr>
          <th style="width:60px;">ID</th>
          <th style="width:140px;">Waktu</th>
          <th style="width:160px;">Nama / WA</th>
          <th>Jenis Website</th>
          <th style="width:90px;">Status</th>
          <th style="width:70px;">Retry</th>
          <th style="width:90px;">Rekap ID</th>
          <th style="width:110px;">Telegram</th>
          <th style="width:120px;">Greeting</th>
          <th style="width:120px;">Via</th>
          <th style="width:220px;">Error</th>
          <th style="width:90px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows) : ?>
          <?php foreach ($rows as $row) : ?>
            <tr>
              <td><?php echo intval($row->id); ?></td>
              <td>
                <?php echo esc_html($row->created_at); ?>
                <?php if (!empty($row->processed_at)) : ?>
                  <br><span style="color:#50575e;">Selesai: <?php echo esc_html($row->processed_at); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?php echo esc_html($row->nama); ?></strong>
                <br><?php echo esc_html($row->no_whatsapp); ?>
                <br><span style="color:#50575e; font-size:11px;"><?php echo esc_html($row->event_id); ?></span>
              </td>
              <td><?php echo esc_html($row->jenis_website); ?></td>
              <td><strong><?php echo esc_html($row->process_status); ?></strong></td>
              <td><?php echo intval($row->retry_count); ?></td>
              <td><?php echo $row->rekap_form_id ? intval($row->rekap_form_id) : '-'; ?></td>
              <td><?php echo $row->telegram_status ? esc_html($row->telegram_status) : '-'; ?></td>
              <td>
                <?php echo $row->greeting ? esc_html($row->greeting) : '-'; ?>
                <?php if (!empty($row->label)) : ?>
                  <br><span style="color:#50575e;"><?php echo esc_html($row->label); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php echo $row->via ? esc_html($row->via) : '-'; ?>
                <?php if (!empty($row->utm_content) || !empty($row->utm_medium)) : ?>
                  <br><span style="color:#50575e; font-size:11px;"><?php echo esc_html(trim($row->utm_content . ' / ' . $row->utm_medium, ' /')); ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo $row->last_error ? esc_html($row->last_error) : '-'; ?></td>
              <td>
                <?php if (in_array($row->process_status, ['pending', 'retrying', 'failed'], true)) : ?>
                  <?php
                  $retry_url = wp_nonce_url(
                    add_query_arg(
                      array_merge($base_args, [
                        'vd_queue_action' => 'retry',
                        'queue_id' => (int) $row->id,
                        'paged' => $current_page,
                      ]),
                      admin_url('admin.php')
                    ),
                    'vd_retry_queue_' . (int) $row->id
                  );
                  ?>
                  <a class="button button-small" href="<?php echo esc_url($retry_url); ?>">Proses Ulang</a>
                <?php else : ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr>
            <td colspan="12" style="text-align:center; padding:18px;">Belum ada data lead queue sesuai filter.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
      <div class="tablenav" style="margin-top:16px;">
        <div class="tablenav-pages">
          <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
            <?php
            $page_url = add_query_arg(
              array_merge($base_args, ['paged' => $i]),
              admin_url('admin.php')
            );
            ?>
            <?php if ($i === $current_page) : ?>
              <span class="page-numbers current" style="padding:10px;"><?php echo intval($i); ?></span>
            <?php else : ?>
              <a class="page-numbers" style="padding:10px;" href="<?php echo esc_url($page_url); ?>"><?php echo intval($i); ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php
}

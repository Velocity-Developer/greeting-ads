<?php
function chat_form_new($atts)
{
  $atts = shortcode_atts([
    'redirect' => '', // default kosong
    'walength' => 10, // 0 jika tidak ingin set
    'weblength' => 10, // default 10
    'push' => 'mobile,pc', // default mobile,pc
  ], $atts, 'chat-form-new');

  ob_start();
  $wa_ads = '6285729319861';
  $wa_organik = '6285701216057';

  $telp_ads = '082136302531';
  $telp_organik = '085701216057';

  $nowhatsapp = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? $wa_ads : $wa_organik;
  $notelp = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? $telp_ads : $telp_organik;

  $kondisi_gtag = ($nowhatsapp == $wa_ads) ? 'wa_ads' : 'wa_organik';

  $greeting_check = check_greeting_langsung();
  $greeting_cookie = $_COOKIE['greeting'] ?? 'vx';
  $c_greeting = $greeting_check ?? $greeting_cookie;
  // $c_greeting = ($_COOKIE['greeting'] || $greeting_check) ?? 'vx';
  $c_greeting = (get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads')) ? $c_greeting : 'v0';

  //label
  $label = $_COOKIE['label'] ?? '';
  //jika ada label, tambahkan ke c_greeting
  if ($label) {
    $c_greeting .= "/{$label}";
  }

  $default_greeting = 'Hallo, Saya tertarik buat website jeniswebsite [' . $c_greeting . ']. Mohon infonya.';

  // Pakai URL dari shortcode jika ada, jika tidak pakai default WA
  $redirect_url = !empty($atts['redirect']) ? $atts['redirect'] : "https://wa.me/{$nowhatsapp}?text=" . urlencode($default_greeting);
?>

  <form class="input-chat" id="form-chat-new" action="#" method="POST">
    <div style="margin-bottom:20px;">
      Silahkan isi data berikut ini untuk melanjutkan chat WA
    </div>

    <label class="nama-input">
      <input class="input-control" id="input-nama-new" placeholder="Nama Anda" minlength="3" required>
      <span class="info" id="info-nama-new"></span>
    </label>

    <label class="nomor-input">
      <input type="number" class="input-control" id="input-whatsapp-new" placeholder="No Whatsapp" minlength="<?php echo $atts['walength']; ?>" required>
      <span class="info" id="info-wa-new"></span>
    </label>

    <div class="pertanyaan">
      <div class="pertanyaan-text">
        <!--        Website apa yang akan dibuat? -->
      </div>
      <div class="jawaban">
        <label class="website-input">
          <textarea rows="2" minlength="<?php echo $atts['weblength']; ?>" class="input-control" id="jenis-website-new" placeholder="Boleh tahu, rencananya ingin membuat website untuk keperluan apa, ya?" required></textarea>
          <span class="info" id="info-website-new"></span>
          <div class="info-new" id="info-new"></div>
        </label>
      </div>
    </div>
    <div class="frame-btn">
      <button type="submit" id="<?php echo $kondisi_gtag; ?>" data-href="<?php echo esc_url($redirect_url); ?>" target="_blank" class="button-green disable">
        <span class="icon-wa">
          <svg xmlns="http://www.w3.org/2000/svg" style="margin-bottom:-3px;margin-right:5px;" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16">
            <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z" />
          </svg>
        </span>
        <span> Whatsapp</span>
      </button>
    </div>
  </form>

  <style>
    .nama-input,
    .nomor-input,
    .website-input {
      margin-bottom: 20px !important;
      display: block;
    }

    .nama-input .input-control,
    .nomor-input .input-control,
    .website-input .input-control {
      margin-bottom: 0px !important;
    }

    .info {
      opacity: 0.6;
    }

    .info-new {
      opacity: 0.9;
      margin-top: 10px;
      margin-bottom: 10px;
      border-radius: 3px;
      font-weight: bold;
      font-size: 16px;
    }
  </style>

  <script>
    jQuery(function($) {
      let webLength = <?php echo $atts['weblength']; ?>;
      let pushSettings = "<?php echo $atts['push']; ?>".split(",").map(item => item.trim());
      const funnelAjaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
      const funnelStoragePrefix = "vd_form_funnel_";
      const getDevice = navigator.userAgent.match(/Android|BlackBerry|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i);
      const device = getDevice ? "mobile" : "pc";
      const formSessionKey = getOrCreateFormSessionKey();

      function getOrCreateFormSessionKey() {
        const storageKey = funnelStoragePrefix + "session_key";
        let sessionKey = sessionStorage.getItem(storageKey);
        if (!sessionKey) {
          sessionKey = "ffs_" + Date.now() + "_" + Math.random().toString(36).slice(2, 10);
          sessionStorage.setItem(storageKey, sessionKey);
        }
        return sessionKey;
      }

      function buildTrackedFlag(eventType) {
        return funnelStoragePrefix + eventType + "_" + window.location.pathname;
      }

      function trackFormEvent(eventType, persistOnce) {
        const trackedFlag = buildTrackedFlag(eventType);
        if (persistOnce && sessionStorage.getItem(trackedFlag) === "1") {
          return;
        }

        const payload = new URLSearchParams({
          action: "vd_track_form_funnel_event",
          event_type: eventType,
          session_key: formSessionKey,
          page_url: window.location.href,
          device: device
        });

        if (navigator.sendBeacon) {
          navigator.sendBeacon(funnelAjaxUrl, payload);
        } else {
          fetch(funnelAjaxUrl, {
            method: "POST",
            body: payload,
            keepalive: true,
            headers: {
              "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
            }
          }).catch(function() {});
        }

        if (persistOnce) {
          sessionStorage.setItem(trackedFlag, "1");
        }
      }

      trackFormEvent("form_view", true);

      let formStartTracked = false;
      let submitEnabledTracked = false;
      function ensureFormStartTracked() {
        if (formStartTracked) {
          return;
        }
        formStartTracked = true;
        trackFormEvent("form_start", true);
      }

      function ensureSubmitEnabledTracked() {
        if (submitEnabledTracked) {
          return;
        }
        submitEnabledTracked = true;
        trackFormEvent("submit_enabled", true);
      }

      function validateInputsNew() {
        let nama = $("#input-nama-new").val().trim();
        let wa = $("#input-whatsapp-new").val().trim();
        let website = $("#jenis-website-new").val().trim();
        let valid = true;
        let waLength = <?php echo $atts['walength']; ?>;
        let push = ["mobile", "pc"];

        if (nama.length < 3) {
          $("#info-nama-new").text("Nama minimal 3 karakter").css("color", "red");
          valid = false;
        } else {
          $("#info-nama-new").text("");
        }

        // jika wa tidak diawali 08
        if (wa.substring(0, 2) !== "08" && wa.length > 1 && wa.length < waLength) {
          $("#info-wa-new").html("Nomor WA tidak valid <br> Nomor WA minimal " + waLength + " digit").css("color", "red");
          valid = false;
        } else if (wa.substring(0, 2) !== "08" && wa.length > 2) {
          $("#info-wa-new").text("Nomor WA tidak valid").css("color", "red");
          valid = false;
        } else if (wa.length < waLength) {
          $("#info-wa-new").text("Nomor WA minimal " + waLength + " digit").css("color", "red");
          valid = false;
        } else {
          $("#info-wa-new").text("");
        }

        if (website.length < webLength) {
          $("#info-website-new").text("Jenis website minimal " + webLength + " karakter").css("color", "red");
          valid = false;
        } else {
          $("#info-website-new").text("");
        }

        // Enable button jika nama, wa, dan website valid
        // Bypass kondisi AI/cookie dilarang karena validasi AI sedang dinonaktifkan.
        // if (nama.length >= 3 && wa.length >= waLength && wa.substring(0, 2) === "08" && website.length >= webLength && getCookie("dilarang") !== "true") {
        if (nama.length >= 3 && wa.length >= waLength && wa.substring(0, 2) === "08" && website.length >= webLength) {
          $(".button-green")
            .removeClass("disable")
            .addClass("enable")
            .prop("disabled", false);
          ensureSubmitEnabledTracked();
          valid = true; // Override valid untuk allow submission
        } else {
          $(".button-green")
            .removeClass("enable")
            .addClass("disable")
            .prop("disabled", true);
        }

        return valid;
      }

      $(".input-control").on("input", validateInputsNew);
      $(".input-control").one("focus input", ensureFormStartTracked);

      $("#form-chat-new").on("submit", function(e) {
        e.preventDefault();

        $("#info-new").text("");
        $(".frame-btn button").html(
          '<span class="icon-wa"><svg style="margin-bottom:-6px; padding-right:5px" width="25" height="25" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#ffffff"><style>.spinner_z9k8{transform-origin:center;animation:spinner_StKS .75s infinite linear}@keyframes spinner_StKS{100%{transform:rotate(360deg)}}</style><path d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25"/><path d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z" class="spinner_z9k8"/></svg></span><span>Mengirim...</span>'
        );

        if (!validateInputsNew()) return;

        const nama = $("#input-nama-new").val().trim();
        const whatsapp = $("#input-whatsapp-new").val().trim();
        const website = $("#jenis-website-new").val().trim();
        trackFormEvent("submit_click", false);

        $.post(
          "<?php echo admin_url('admin-ajax.php'); ?>", {
            action: "rekap_chat_form",
            nama: nama,
            no_whatsapp: whatsapp,
            jenis_website: website,
            greeting: getCookie("greeting") || null,
            utm_content: getUrlParam("utm_content"),
            utm_medium: getUrlParam("utm_medium"),
            via: device
          },
          function(response) {

            if (response.success) {
              // Bypass kondisi ai_result === 'valid' karena validasi AI sedang dimatikan.
              // if (response.data.ai_result === 'dilarang') { ... }
              // else if (response.data.ai_result === 'valid') {
                $("#form-chat-new").trigger("reset");
                // Bypass kondisi cookie dilarang. Tetap jaga syarat panjang input dan device.
                if (website.length >= webLength && pushSettings.includes(device)) {
                  dataLayer.push({
                    event: 'klik_<?php echo $kondisi_gtag; ?>',
                    button_id: '<?php echo $kondisi_gtag; ?>',
                    nama: nama,
                    no_whatsapp: whatsapp,
                    jenis_website: website
                  });
                }

                setTimeout(function() {
                  var redirectUrl = "<?php echo esc_js($redirect_url); ?>";
                  redirectUrl = redirectUrl.replace('jeniswebsite', website);
                  window.open(redirectUrl, '_blank');
                  // window.open("<?php echo esc_js($redirect_url); ?>", '_blank');
                }, 350);

                // $("#info-new").text("Submit data berhasil").css("color", "green");
              // }

              // timeout 3 detik
              setTimeout(function() {
                $("#info-new").text("");
              }, 30000);
            }

            $(".frame-btn button").html(
              '<span class="icon-wa"><svg xmlns="http://www.w3.org/2000/svg" style="margin-bottom:-3px;margin-right:5px;" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z" /></svg></span><span>Whatsapp</span>'
            );
          }
        ).fail(function() {
          $(".frame-btn button").html(
            '<span class="icon-wa"><svg xmlns="http://www.w3.org/2000/svg" style="margin-bottom:-3px;margin-right:5px;" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z" /></svg></span><span>Whatsapp</span>'
          );

          var fallbackUrl = "<?php echo esc_js($redirect_url); ?>";
          fallbackUrl = fallbackUrl.replace('jeniswebsite', website);
          window.open(fallbackUrl, '_blank');
        });
      });

      function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
      }

      function getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
      }

      function setCookie(name, value, days) {
        // expired 30 hari
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = "expires=" + date.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";

      }
    });
  </script>

<?php
  return ob_get_clean();
}
add_shortcode('chat-form-new', 'chat_form_new');

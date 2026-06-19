<?php
function vd_get_default_whatsapp_url_template()
{
  return 'https://wa.me/{number}?text={message}';
}

function vd_get_whatsapp_url_template()
{
  $template = get_option('vd_whatsapp_url_template', vd_get_default_whatsapp_url_template());

  if (!is_string($template)) {
    return vd_get_default_whatsapp_url_template();
  }

  $template = trim($template);
  if ($template === '') {
    return vd_get_default_whatsapp_url_template();
  }

  return $template;
}

function vd_build_whatsapp_url($number, $message)
{
  $template = vd_get_whatsapp_url_template();

  if (strpos($template, '{number}') !== false || strpos($template, '{message}') !== false) {
    return strtr($template, [
      '{number}' => rawurlencode((string) $number),
      '{message}' => rawurlencode((string) $message),
    ]);
  }

  return $template;
}

function tampilan_baru()
{
  ob_start();
?>
  <style>
    .wa-float,
    .wa-float * {
      box-sizing: border-box;
    }

    .wa-float {
      position: fixed;
      bottom: 16px;
      right: 16px;
      z-index: 9999;
      display: none;
      align-items: center;
      gap: 12px;
      background-color: #25d366;
      color: white;
      font-family: sans-serif;
      font-size: 20px;
      font-weight: 600;
      padding: 10px 24px;
      border-radius: 40px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      cursor: pointer;
      text-decoration: none;
      animation: smooth-bounce 2s infinite;
      overflow: hidden;
      transition: transform 0.2s ease;
    }

    @media (max-width: 991px) {
      .wa-float {
        display: flex;
      }
    }

    .wa-float svg {
      width: 26px;
      height: 26px;
      fill: white;
      flex-shrink: 0;
      position: relative;
      z-index: 2;
    }

    .wa-float .ripple {
      position: absolute;
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      z-index: 1;
      animation: ripple 1.8s infinite ease-out;
    }

    @keyframes ripple {
      0% {
        transform: scale(0.3);
        opacity: 0.6;
      }

      70% {
        transform: scale(1.5);
        opacity: 0;
      }

      100% {
        transform: scale(1.5);
        opacity: 0;
      }
    }

    @keyframes smooth-bounce {

      0%,
      100% {
        transform: translateY(0);
        animation-timing-function: ease-in-out;
      }

      30% {
        transform: translateY(-8px);
        animation-timing-function: ease-in;
      }

      50% {
        transform: translateY(0);
        animation-timing-function: ease-out;
      }

      70% {
        transform: translateY(-4px);
        animation-timing-function: ease-in;
      }

      90% {
        transform: translateY(0);
      }
    }
  </style>
  <?php
  $is_ads = get_ads_logic() || (isset($_COOKIE['traffic']) && $_COOKIE['traffic'] == 'ads');
  $ads_greeting = 'vx';

  if ($is_ads) {
    $cookie_greeting = isset($_COOKIE['greeting']) ? sanitize_text_field(wp_unslash($_COOKIE['greeting'])) : '';
    $cookie_greeting = trim($cookie_greeting);
    if ($cookie_greeting !== '') {
      $ads_greeting = $cookie_greeting;
    }
  }

  $wa_number = $is_ads ? '6285729319861' : '6285701216057';
  $wa_message = $is_ads
    ? sprintf('Hallo, Saya tertarik buat website di Velocity Developer [%s]. Mohon infonya.', $ads_greeting)
    : 'Hallo, Saya tertarik buat website di Velocity Developer [v0]. Mohon infonya.';
  $wa_url = vd_build_whatsapp_url($wa_number, $wa_message);
  $wa_url_template = vd_get_whatsapp_url_template();

  $tracking_greeting = $is_ads ? $ads_greeting : 'v0';
  $tracking_type = $is_ads ? 'ads' : 'organic';
  $async_track_nonce = wp_create_nonce('vd_async_wa_click');
  $async_track_url = admin_url('admin-ajax.php');
  ?>
  <a href="<?php echo esc_url($wa_url); ?>" class="wa-float" target="_blank" rel="noopener" data-traffic-type="<?php echo esc_attr($tracking_type); ?>" data-greeting="<?php echo esc_attr($tracking_greeting); ?>" data-url-template="<?php echo esc_attr($wa_url_template); ?>" data-async-track-url="<?php echo esc_url($async_track_url); ?>" data-async-track-nonce="<?php echo esc_attr($async_track_nonce); ?>">
    <span class="ripple"></span>
    <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z" />
    </svg>
    WhatsApp
  </a>

  <script data-cfasync="false">
    document.addEventListener("DOMContentLoaded", function() {
      try {
        const waFloat = document.querySelector('.wa-float');
        if (!waFloat) {
          return;
        }

        const getCookieValue = function(name) {
          try {
            const value = `; ${document.cookie || ''}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length !== 2) {
              return '';
            }
            const raw = parts.pop().split(';').shift() || '';
            return decodeURIComponent(raw);
          } catch (e) {
            return '';
          }
        };

        const buildWhatsAppUrl = function(number, message) {
          const template = (waFloat.dataset.urlTemplate || '').trim() || 'https://wa.me/{number}?text={message}';
          return template
            .replace('{number}', encodeURIComponent(number || ''))
            .replace('{message}', encodeURIComponent(message || ''));
        };

        // Resolve final WA target in client-side to avoid stale cached HTML values.
        const resolveWaTarget = function() {
          try {
            const urlParams = new URLSearchParams(window.location.search || '');
            const hasAdsParams = urlParams.has('gclid') || urlParams.has('wbraid') || urlParams.has('gbraid') ||
              (urlParams.get('utm_source') === 'google' && (urlParams.has('utm_medium') || urlParams.has('utm_content')));
            const isAds = (waFloat.dataset.trafficType === 'ads') || hasAdsParams;

            let number = '6285701216057';
            let greeting = 'v0';
            let message = 'Hallo, Saya tertarik buat website di Velocity Developer [v0]. Mohon infonya.';

            if (isAds) {
              number = '6285729319861';
              let cookieGreeting = (getCookieValue('greeting') || '').trim();
              const queryGreeting = (urlParams.get('greeting') || '').trim();

              greeting = cookieGreeting || queryGreeting || (waFloat.dataset.greeting || '').trim() || 'vx';

              // Force update if we found a better greeting than vx
              if (greeting !== 'vx' && greeting !== 'v0') {
                // Ensure future calls see this
                waFloat.dataset.greeting = greeting;
              }

              message = `Hallo, Saya tertarik buat website di Velocity Developer [${greeting}]. Mohon infonya.`;
            }

            return {
              isAds: isAds,
              greeting: isAds ? greeting : 'v0',
              url: buildWhatsAppUrl(number, message)
            };
          } catch (err) {
            console.error('WA Logic Error:', err);
            // Fallback to default
            return {
              isAds: false,
              greeting: 'v0',
              url: waFloat.href || buildWhatsAppUrl('6285701216057', 'Hallo, Saya tertarik buat website di Velocity Developer [v0]. Mohon infonya.')
            };
          }
        };

        const initialWaTarget = resolveWaTarget();

        // Update visual immediately based on cookie/URL
        if (initialWaTarget && initialWaTarget.isAds) {
          waFloat.href = initialWaTarget.url;
          waFloat.dataset.trafficType = 'ads';
          waFloat.dataset.greeting = initialWaTarget.greeting;
        }

        waFloat.addEventListener('click', function() {
          try {
            const waTarget = resolveWaTarget();
            waFloat.href = waTarget.url;
            waFloat.dataset.trafficType = waTarget.isAds ? 'ads' : 'organic';
            waFloat.dataset.greeting = waTarget.greeting;

            // if (typeof dataLayer !== 'undefined' && Array.isArray(dataLayer)) {
            //   dataLayer.push({
            //     event: 'klik_wa_ads'
            //   });
            // }

            const ajaxUrl = waFloat.dataset.asyncTrackUrl || '';
            const nonce = waFloat.dataset.asyncTrackNonce || '';
            if (!ajaxUrl || !nonce) {
              return;
            }

            const eventId = (window.crypto && typeof window.crypto.randomUUID === 'function') ?
              window.crypto.randomUUID() :
              ('vdwa_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10));

            const payload = new URLSearchParams();
            payload.append('action', 'vd_async_track_wa_click');
            payload.append('nonce', nonce);
            payload.append('event_id', eventId);
            payload.append('greeting', waTarget.greeting || 'v0');
            payload.append('page_url', window.location.href);

            if (navigator.sendBeacon) {
              const beaconQueued = navigator.sendBeacon(ajaxUrl, payload);
              if (beaconQueued) {
                return;
              }
            }

            if (window.fetch) {
              fetch(ajaxUrl, {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                }
              }).catch(function() {});
            }
          } catch (clickErr) {
            console.error('WA Click Error:', clickErr);
          }
        });
      } catch (mainErr) {
        console.error('WA Init Error:', mainErr);
      }
    });
  </script>
<?php
  return ob_get_clean();
}
function whatsapp_floating()
{
  if (!is_page('form-chat') && !is_page('order')) {
    echo tampilan_baru();
  }
}
add_action('wp_footer', 'whatsapp_floating');

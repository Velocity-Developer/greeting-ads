<?php

/**
 * Monitor row baru pada tabel lead queue dan kirim notifikasi Telegram.
 *
 * Tujuan:
 * - Dipanggil oleh cron Linux tiap 1 menit.
 * - Mengirim alert setiap ada row baru di tabel wpu1_vd_lead_queue.
 * - Tidak bergantung pada WP-Cron.
 *
 * Contoh cron Linux:
 * * * * * /usr/bin/php /home/USER/public_html/wp-content/plugins/greeting-ads/monitor-lead-queue-cron.php >> /home/USER/logs/monitor-lead-queue.log 2>&1
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Jakarta');

$pluginDir = __DIR__;
$stateFile = $pluginDir . DIRECTORY_SEPARATOR . 'monitor-lead-queue-state.json';
$wpConfigPath = dirname($pluginDir, 3) . DIRECTORY_SEPARATOR . 'wp-config.php';

if (!file_exists($wpConfigPath)) {
    fwrite(STDERR, "wp-config.php tidak ditemukan: {$wpConfigPath}" . PHP_EOL);
    exit(1);
}

require_once $wpConfigPath;

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASSWORD')) {
    fwrite(STDERR, "Konstanta database WordPress tidak lengkap." . PHP_EOL);
    exit(1);
}

$tablePrefix = isset($table_prefix) && is_string($table_prefix) && $table_prefix !== '' ? $table_prefix : 'wp_';
$queueTable = $tablePrefix . 'vd_lead_queue';

// Ubah jika ingin kirim ke chat lain.
$telegramChatIds = [
    '260162734',
];

if (!defined('TOKEN_TELEGRAM_BOT') || TOKEN_TELEGRAM_BOT === '') {
    fwrite(STDERR, "TOKEN_TELEGRAM_BOT belum terdefinisi di wp-config.php." . PHP_EOL);
    exit(1);
}

$state = [
    'last_seen_id' => 0,
];

if (file_exists($stateFile)) {
    $decoded = json_decode((string) file_get_contents($stateFile), true);
    if (is_array($decoded) && isset($decoded['last_seen_id'])) {
        $state['last_seen_id'] = max(0, (int) $decoded['last_seen_id']);
    }
}

$mysqli = mysqli_init();
if ($mysqli === false) {
    fwrite(STDERR, "Gagal inisialisasi mysqli." . PHP_EOL);
    exit(1);
}

$connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if (!$connected) {
    fwrite(STDERR, "Gagal konek database: " . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

$mysqli->set_charset(defined('DB_CHARSET') && DB_CHARSET ? DB_CHARSET : 'utf8mb4');

$lastSeenId = (int) $state['last_seen_id'];
$sql = "SELECT id, event_id, nama, no_whatsapp, jenis_website, via, greeting, label, process_status, retry_count, rekap_form_id, telegram_status, created_at
        FROM `{$queueTable}`
        WHERE id > ?
        ORDER BY id ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "Gagal prepare query: " . $mysqli->error . PHP_EOL);
    $mysqli->close();
    exit(1);
}

$stmt->bind_param('i', $lastSeenId);
$stmt->execute();
$result = $stmt->get_result();

$maxSeenId = $lastSeenId;
$sentCount = 0;

while ($row = $result->fetch_assoc()) {
    $queueId = (int) $row['id'];
    $maxSeenId = max($maxSeenId, $queueId);

    $message = buildTelegramMessage($row, $queueTable);
    $sent = sendTelegramMessage(TOKEN_TELEGRAM_BOT, $telegramChatIds, $message);

    if ($sent) {
        $sentCount++;
    } else {
        fwrite(STDERR, "Gagal kirim Telegram untuk queue ID {$queueId}" . PHP_EOL);
    }
}

$stmt->close();
$mysqli->close();

if ($maxSeenId > $lastSeenId) {
    $state['last_seen_id'] = $maxSeenId;
    $state['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

echo '[' . date('Y-m-d H:i:s') . "] selesai. last_seen_id={$maxSeenId}, notif_terkirim={$sentCount}" . PHP_EOL;
exit(0);

function buildTelegramMessage(array $row, string $queueTable): string
{
    $queueId = (int) ($row['id'] ?? 0);
    $eventId = escapeHtml((string) ($row['event_id'] ?? ''));
    $nama = escapeHtml((string) ($row['nama'] ?? ''));
    $noWhatsapp = escapeHtml((string) ($row['no_whatsapp'] ?? ''));
    $jenisWebsite = escapeHtml((string) ($row['jenis_website'] ?? ''));
    $via = escapeHtml((string) ($row['via'] ?? ''));
    $greeting = escapeHtml((string) ($row['greeting'] ?? ''));
    $label = escapeHtml((string) ($row['label'] ?? ''));
    $processStatus = escapeHtml((string) ($row['process_status'] ?? ''));
    $retryCount = (int) ($row['retry_count'] ?? 0);
    $rekapFormId = (int) ($row['rekap_form_id'] ?? 0);
    $telegramStatus = escapeHtml((string) ($row['telegram_status'] ?? ''));
    $createdAt = escapeHtml((string) ($row['created_at'] ?? ''));

    return "<b>Lead Queue Baru vd.com</b>\n"
        . "Tabel: <b>{$queueTable}</b>\n"
        . "Queue ID: <b>{$queueId}</b>\n"
        . "Event ID: <b>{$eventId}</b>\n"
        . "Nama: <b>{$nama}</b>\n"
        . "No WA: <b>{$noWhatsapp}</b>\n"
        . "Jenis Web: <b>{$jenisWebsite}</b>\n"
        . "Via: <b>{$via}</b>\n"
        . "Greeting: <b>{$greeting}</b>\n"
        . "Label: <b>{$label}</b>\n"
        . "Status Saat Dicek: <b>{$processStatus}</b>\n"
        . "Retry Count: <b>{$retryCount}</b>\n"
        . "Rekap Form ID: <b>" . ($rekapFormId > 0 ? $rekapFormId : '-') . "</b>\n"
        . "Telegram Status: <b>" . ($telegramStatus !== '' ? $telegramStatus : '-') . "</b>\n"
        . "Created At: <b>{$createdAt}</b>\n";
}

function sendTelegramMessage(string $botToken, array $chatIds, string $message): bool
{
    if ($botToken === '' || empty($chatIds)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $allSuccess = true;

    foreach ($chatIds as $chatId) {
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $allSuccess = false;
            curl_close($ch);
            continue;
        }

        $decoded = json_decode((string) $response, true);
        if (empty($decoded['ok'])) {
            $allSuccess = false;
        }

        curl_close($ch);
    }

    return $allSuccess;
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

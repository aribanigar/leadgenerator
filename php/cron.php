<?php
/**
 * ViaKashmir Lead CRM – Cron Job
 * ──────────────────────────────────────────────────────────────────────────
 * Runs automatically to sync leads from Meta and Google Ads.
 *
 * Add to crontab (crontab -e):
 *   */15 * * * *  php /var/www/html/viakashmir-crm/cron.php >> /var/log/viakashmir-crm.log 2>&1
 *
 * Or in cPanel: Cron Jobs → Add New Cron Job → every 15 minutes
 *   Command: php /home/username/public_html/crm/cron.php
 */

define('BASE_DIR', __DIR__);

// Autoload
if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require BASE_DIR . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $file = BASE_DIR . '/src/' . str_replace(['ViaKashmir\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($file)) require $file;
    });
}

$cfg = require BASE_DIR . '/config.php';
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Kolkata');

echo "[" . date('Y-m-d H:i:s') . "] ViaKashmir CRM – starting sync\n";

// ── Meta ──────────────────────────────────────────────────────────────────
try {
    $result = \ViaKashmir\MetaLeads::sync();
    echo "[Meta] Synced {$result['synced']} new leads\n";
} catch (\Throwable $e) {
    echo "[Meta] ERROR: {$e->getMessage()}\n";
}

// ── Google Ads ────────────────────────────────────────────────────────────
try {
    $result = \ViaKashmir\GoogleAdsLeads::sync();
    echo "[Google] Synced {$result['synced']} new leads\n";
} catch (\Throwable $e) {
    echo "[Google] ERROR: {$e->getMessage()}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Sync complete\n\n";

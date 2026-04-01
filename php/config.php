<?php
/**
 * ViaKashmir Lead CRM – Configuration
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Copy this file to config.php (already done)
 * 2. Fill in your database credentials below
 * 3. Run install.php once to create the database tables
 * 4. Optionally set default API credentials here (can also be set per-account
 *    in the dashboard under "Ad Accounts")
 * 5. Set up the cron job:  */15 * * * *  php /path/to/your/app/cron.php
 */

return [

    // ── Database (MySQL) ────────────────────────────────────────────────────
    'db' => [
        'host'    => 'localhost',          // DB host
        'port'    => 3306,
        'name'    => 'viakashmir_crm',     // DB name (create this in phpMyAdmin/MySQL)
        'user'    => 'root',               // DB username
        'pass'    => '',                   // DB password
        'charset' => 'utf8mb4',
    ],

    // ── Meta / Facebook ─────────────────────────────────────────────────────
    // Users connect via "Sign in with Facebook" button — no manual tokens needed.
    // Developer only needs to set these two values ONCE:
    //
    // How to get App ID & Secret (5 min, done once by developer):
    //  1. Go to developers.facebook.com → My Apps → Create App → Business type
    //  2. Copy App ID and App Secret from Settings → Basic
    //  3. Under Settings → Basic → add your domain to "App Domains"
    //  4. Under Facebook Login → Settings → add Callback URL:
    //     https://yourdomain.com/auth/meta/callback
    //  5. Add products: "Facebook Login" + "Lead Ads Retrieval"
    //  6. Set App to "Live" mode (toggle at top of dashboard)
    'meta' => [
        'app_id'               => '',    // ← paste your Facebook App ID here
        'app_secret'           => '',    // ← paste your Facebook App Secret here
        'webhook_verify_token' => 'viakashmir_webhook_2024',
    ],

    // ── Google Ads (optional defaults – override per account in dashboard)
    'google' => [
        'developer_token' => '',   // From Google Ads → Tools → API Center
        'client_id'       => '',   // From Google Cloud Console → OAuth 2.0
        'client_secret'   => '',
        'refresh_token'   => '',
    ],

    // ── App Settings ────────────────────────────────────────────────────────
    'app' => [
        'timezone'  => 'Asia/Kolkata',
        'sync_days' => 30,   // How many days back to fetch leads on first sync
    ],

];

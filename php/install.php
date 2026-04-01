<?php
/**
 * ViaKashmir Lead CRM – Database Installer
 * ─────────────────────────────────────────────────────────────────────────────
 * Run this ONCE to create all database tables.
 *
 * Via browser:  https://yourdomain.com/install.php
 * Via CLI:      php install.php
 *
 * DELETE this file after running it for security.
 */

define('BASE_DIR', __DIR__);
$cfg = require BASE_DIR . '/config.php';
$db  = $cfg['db'];

// ── Connect ────────────────────────────────────────────────────────────────
try {
    // First connect without DB name to create it if needed
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}",
        $db['user'], $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db['name']}`");
    echo ok("Connected to MySQL and selected database `{$db['name']}`");
} catch (PDOException $e) {
    die(err("Cannot connect to MySQL: " . $e->getMessage() . "\nCheck your config.php credentials."));
}

// ── Create Tables ──────────────────────────────────────────────────────────
$tables = [

'ad_accounts' => "
    CREATE TABLE IF NOT EXISTS ad_accounts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        platform     ENUM('meta','google') NOT NULL,
        label        VARCHAR(100) NOT NULL,
        account_id   VARCHAR(100) NOT NULL,
        access_token TEXT,
        extra_config JSON DEFAULT ('{}'),
        active       TINYINT(1) DEFAULT 1,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

'team_members' => "
    CREATE TABLE IF NOT EXISTS team_members (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(150) NOT NULL UNIQUE,
        phone      VARCHAR(30),
        role       ENUM('agent','manager','admin') DEFAULT 'agent',
        active     TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

'leads' => "
    CREATE TABLE IF NOT EXISTS leads (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        external_id      VARCHAR(200) UNIQUE,
        source_platform  ENUM('meta','google') NOT NULL,
        source_account   INT,
        ad_name          VARCHAR(300),
        campaign_name    VARCHAR(300),
        form_name        VARCHAR(300),

        -- Contact info
        client_name      VARCHAR(200),
        email            VARCHAR(200),
        phone            VARCHAR(50),

        -- Travel details
        destination      VARCHAR(300),
        travel_duration  SMALLINT UNSIGNED,
        travel_from_date DATE,
        travel_to_date   DATE,
        adults           TINYINT UNSIGNED DEFAULT 0,
        children         TINYINT UNSIGNED DEFAULT 0,
        budget           VARCHAR(100),
        special_requests TEXT,

        -- CRM state
        status           ENUM('new','contacted','qualified','converted','lost') DEFAULT 'new',
        assigned_to      INT,
        contacted_at     DATETIME,
        converted_at     DATETIME,

        raw_data         JSON,
        created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (source_account) REFERENCES ad_accounts(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to)    REFERENCES team_members(id) ON DELETE SET NULL,
        INDEX idx_status   (status),
        INDEX idx_platform (source_platform),
        INDEX idx_assigned (assigned_to),
        INDEX idx_created  (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

'lead_notes' => "
    CREATE TABLE IF NOT EXISTS lead_notes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        lead_id    INT NOT NULL,
        author_id  INT,
        note       TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id)   REFERENCES leads(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES team_members(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo ok("Table `{$name}` ready");
    } catch (PDOException $e) {
        echo err("Failed to create `{$name}`: " . $e->getMessage());
    }
}

echo "\n";
echo ok("✅  Installation complete!");
echo "\n";
echo info("Next steps:");
echo info("1. Delete this file:  rm install.php  (important for security!)");
echo info("2. Run Composer:      composer install");
echo info("3. Set up cron job:   */15 * * * *  php " . __DIR__ . "/cron.php");
echo info("4. Open your CRM:     https://yourdomain.com/");
echo "\n";

// ── Helpers ────────────────────────────────────────────────────────────────
function ok(string $msg): string    { return isCLI() ? "✅  {$msg}\n" : "<p style='color:green'>✅ {$msg}</p>"; }
function err(string $msg): string   { return isCLI() ? "❌  {$msg}\n" : "<p style='color:red'>❌ {$msg}</p>"; }
function info(string $msg): string  { return isCLI() ? "ℹ️  {$msg}\n" : "<p style='color:#555'>ℹ️ {$msg}</p>"; }
function isCLI(): bool              { return PHP_SAPI === 'cli'; }

<?php
/**
 * ViaKashmir Lead CRM – Main Entry Point & Router
 * ─────────────────────────────────────────────────
 * All requests go through here (via .htaccess).
 * – /                      → Dashboard SPA
 * – /api/*                 → JSON API
 * – /auth/meta             → Start Facebook OAuth login
 * – /auth/meta/callback    → Facebook OAuth callback (auto-saves token)
 * – /api/webhooks/meta     → Meta real-time webhook
 */

define('BASE_DIR', __DIR__);

// Autoload
if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require BASE_DIR . '/vendor/autoload.php';
} else {
    // Manual autoload for services without Composer (fallback)
    spl_autoload_register(function ($class) {
        $file = BASE_DIR . '/src/' . str_replace(['ViaKashmir\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($file)) require $file;
    });
}

// Timezone
$cfg = require BASE_DIR . '/config.php';
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Kolkata');

// ── Route ──────────────────────────────────────────────────────────────────

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri    = '/' . trim($uri, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ── Meta OAuth Routes ───────────────────────────────────────────────────────

if ($uri === '/auth/meta') {
    \ViaKashmir\MetaOAuth::redirectToFacebook();
    exit;
}

if ($uri === '/auth/meta/callback') {
    \ViaKashmir\MetaOAuth::handleCallback();
    exit;
}

// Serve static public files (CSS, JS)
if (preg_match('#^/public/(.+)$#', $uri, $m)) {
    $file = BASE_DIR . '/public/' . $m[1];
    if (file_exists($file)) {
        $ext   = pathinfo($file, PATHINFO_EXTENSION);
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'ico' => 'image/x-icon'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}

// ── API Routes ──────────────────────────────────────────────────────────────

if (str_starts_with($uri, '/api')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($method === 'OPTIONS') { http_response_code(204); exit; }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── Leads ──────────────────────────────────────────────────────────────
    if ($uri === '/api/leads' && $method === 'GET') {
        echo json_encode(\ViaKashmir\Database::getLeads($_GET));
        exit;
    }

    if ($uri === '/api/leads/stats' && $method === 'GET') {
        echo json_encode(\ViaKashmir\Database::getStats());
        exit;
    }

    if (preg_match('#^/api/leads/(\d+)$#', $uri, $m) && $method === 'GET') {
        $lead = \ViaKashmir\Database::getLead((int)$m[1]);
        echo $lead ? json_encode($lead) : json_encode(['error' => 'Not found'], JSON_THROW_ON_ERROR);
        if (!$lead) http_response_code(404);
        exit;
    }

    if (preg_match('#^/api/leads/(\d+)/status$#', $uri, $m) && $method === 'PATCH') {
        $valid = ['new','contacted','qualified','converted','lost'];
        $status = $body['status'] ?? '';
        if (!in_array($status, $valid, true)) {
            http_response_code(400); echo json_encode(['error' => 'Invalid status']); exit;
        }
        \ViaKashmir\Database::updateLeadStatus((int)$m[1], $status);
        echo json_encode(\ViaKashmir\Database::getLead((int)$m[1]));
        exit;
    }

    if (preg_match('#^/api/leads/(\d+)/assign$#', $uri, $m) && $method === 'PATCH') {
        if (empty($body['member_id'])) {
            http_response_code(400); echo json_encode(['error' => 'member_id required']); exit;
        }
        \ViaKashmir\Database::assignLead((int)$m[1], (int)$body['member_id']);
        echo json_encode(\ViaKashmir\Database::getLead((int)$m[1]));
        exit;
    }

    if (preg_match('#^/api/leads/(\d+)/notes$#', $uri, $m) && $method === 'POST') {
        if (empty($body['note'])) {
            http_response_code(400); echo json_encode(['error' => 'note required']); exit;
        }
        \ViaKashmir\Database::addNote((int)$m[1], $body['note'], $body['author_id'] ?? null);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Accounts ───────────────────────────────────────────────────────────
    if ($uri === '/api/accounts' && $method === 'GET') {
        $accounts = \ViaKashmir\Database::getAccounts();
        // Mask access tokens
        $accounts = array_map(function ($a) {
            if ($a['access_token']) {
                $a['access_token'] = '••••••' . substr($a['access_token'], -4);
            }
            return $a;
        }, $accounts);
        echo json_encode($accounts);
        exit;
    }

    if ($uri === '/api/accounts' && $method === 'POST') {
        if (empty($body['platform']) || empty($body['label']) || empty($body['account_id'])) {
            http_response_code(400); echo json_encode(['error' => 'platform, label, account_id required']); exit;
        }
        $id = \ViaKashmir\Database::addAccount($body);
        http_response_code(201);
        echo json_encode(['id' => $id]);
        exit;
    }

    if (preg_match('#^/api/accounts/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \ViaKashmir\Database::deleteAccount((int)$m[1]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Team ───────────────────────────────────────────────────────────────
    if ($uri === '/api/team' && $method === 'GET') {
        echo json_encode(\ViaKashmir\Database::getTeam());
        exit;
    }

    if ($uri === '/api/team' && $method === 'POST') {
        if (empty($body['name']) || empty($body['email'])) {
            http_response_code(400); echo json_encode(['error' => 'name and email required']); exit;
        }
        $id = \ViaKashmir\Database::addTeamMember($body);
        http_response_code(201);
        echo json_encode(['id' => $id]);
        exit;
    }

    if (preg_match('#^/api/team/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \ViaKashmir\Database::removeTeamMember((int)$m[1]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Sync ───────────────────────────────────────────────────────────────
    if ($uri === '/api/sync' && $method === 'POST') {
        ob_start(); // buffer sync output (echoes to log, not response)

        $metaResult   = ['synced' => 0, 'error' => null];
        $googleResult = ['synced' => 0, 'error' => null];

        try {
            $metaResult = \ViaKashmir\MetaLeads::sync();
        } catch (\Throwable $e) {
            $metaResult['error'] = $e->getMessage();
        }

        try {
            $googleResult = \ViaKashmir\GoogleAdsLeads::sync();
        } catch (\Throwable $e) {
            $googleResult['error'] = $e->getMessage();
        }

        ob_end_clean();
        echo json_encode([
            'meta'      => $metaResult,
            'google'    => $googleResult,
            'timestamp' => date('c'),
        ]);
        exit;
    }

    // ── Meta Webhook ───────────────────────────────────────────────────────
    if ($uri === '/api/webhooks/meta') {
        if ($method === 'GET') {
            // Verification handshake
            $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
            $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
            $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

            if ($mode === 'subscribe' && $token === $cfg['meta']['webhook_verify_token']) {
                http_response_code(200);
                header('Content-Type: text/plain');
                echo $challenge;
            } else {
                http_response_code(403);
                echo 'Forbidden';
            }
            exit;
        }

        if ($method === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            if (($payload['object'] ?? '') === 'page') {
                $accounts = \ViaKashmir\Database::getAccounts('meta');
                foreach (($payload['entry'] ?? []) as $entry) {
                    $account = array_filter($accounts, fn($a) => $a['account_id'] === $entry['id']);
                    $account = reset($account) ?: ($accounts[0] ?? null);
                    \ViaKashmir\MetaLeads::processWebhook($entry, $account['id'] ?? null);
                }
            }
            http_response_code(200);
            echo 'OK';
            exit;
        }
    }

    // ── 404 for unmatched API routes ───────────────────────────────────────
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// ── Serve Dashboard SPA ─────────────────────────────────────────────────────
include BASE_DIR . '/templates/dashboard.php';

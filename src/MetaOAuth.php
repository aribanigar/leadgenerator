<?php
namespace ViaKashmir;

/**
 * Meta OAuth – "Sign in with Facebook" flow.
 *
 * The developer sets META_APP_ID + META_APP_SECRET in config.php ONCE.
 * After that, any staff member just clicks "Connect Facebook Account",
 * logs in, picks their Page — and the system stores the token automatically.
 *
 * Flow:
 *  1. /auth/meta         → redirect to Facebook login
 *  2. /auth/meta/callback → exchange code → long-lived token → fetch pages → save
 */
class MetaOAuth
{
    private const GRAPH_VER = 'v19.0';
    private const AUTH_URL  = 'https://www.facebook.com/' . self::GRAPH_VER . '/dialog/oauth';
    private const TOKEN_URL = 'https://graph.facebook.com/' . self::GRAPH_VER . '/oauth/access_token';
    private const GRAPH_URL = 'https://graph.facebook.com/' . self::GRAPH_VER;

    // Permissions needed to read leads from all sources
    private const SCOPES = [
        'leads_retrieval',
        'pages_manage_metadata',
        'pages_read_engagement',
        'ads_management',
        'business_management',
    ];

    // ── Step 1: Start OAuth ───────────────────────────────────────────────────

    public static function redirectToFacebook(): void
    {
        $cfg      = require BASE_DIR . '/config.php';
        $appId    = $cfg['meta']['app_id']    ?? '';
        $redirect = self::callbackUrl();

        if (!$appId) {
            die('<p style="font-family:sans-serif;color:red;padding:40px">
                 ❌ META_APP_ID is not set in config.php.<br>
                 Ask your developer to add the Facebook App ID and Secret.</p>');
        }

        // Store CSRF state token in session
        session_start();
        $state = bin2hex(random_bytes(16));
        $_SESSION['meta_oauth_state'] = $state;

        $url = self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirect,
            'scope'         => implode(',', self::SCOPES),
            'response_type' => 'code',
            'state'         => $state,
        ]);

        header('Location: ' . $url);
        exit;
    }

    // ── Step 2: Handle callback ───────────────────────────────────────────────

    public static function handleCallback(): void
    {
        session_start();

        // CSRF check
        $state = $_GET['state'] ?? '';
        if (!$state || $state !== ($_SESSION['meta_oauth_state'] ?? '')) {
            self::errorPage('Invalid OAuth state. Please try again.');
        }
        unset($_SESSION['meta_oauth_state']);

        // Error from Facebook (e.g. user cancelled)
        if (isset($_GET['error'])) {
            $msg = $_GET['error_description'] ?? 'Facebook login was cancelled.';
            self::errorPage($msg);
        }

        $code = $_GET['code'] ?? '';
        if (!$code) self::errorPage('No authorisation code received.');

        $cfg       = require BASE_DIR . '/config.php';
        $appId     = $cfg['meta']['app_id']     ?? '';
        $appSecret = $cfg['meta']['app_secret']  ?? '';
        $redirect  = self::callbackUrl();

        // Exchange code → short-lived user token
        $tokenData = self::graphGet(self::TOKEN_URL, [
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'redirect_uri'  => $redirect,
            'code'          => $code,
        ]);
        $shortToken = $tokenData['access_token'] ?? null;
        if (!$shortToken) self::errorPage('Could not get access token from Facebook.');

        // Exchange short-lived → long-lived user token (60 days)
        $longData = self::graphGet(self::TOKEN_URL, [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $appId,
            'client_secret'     => $appSecret,
            'fb_exchange_token' => $shortToken,
        ]);
        $userToken = $longData['access_token'] ?? $shortToken;

        // Fetch all Pages this user manages (with their own permanent page tokens)
        $pagesData = self::graphGet('/me/accounts', [
            'fields'       => 'id,name,access_token,category',
            'limit'        => 100,
            'access_token' => $userToken,
        ]);
        $pages = $pagesData['data'] ?? [];

        // Fetch Ad Accounts
        $adData = self::graphGet('/me/adaccounts', [
            'fields'       => 'id,name,account_status',
            'limit'        => 100,
            'access_token' => $userToken,
        ]);
        $adAccounts = array_filter($adData['data'] ?? [], fn($a) => ($a['account_status'] ?? 0) == 1);

        // Auto-save all active pages as connected accounts
        $saved = 0;
        foreach ($pages as $page) {
            // Page access tokens are permanent (never expire) — ideal
            Database::addAccount([
                'platform'     => 'meta',
                'label'        => $page['name'] . ' (Facebook Page)',
                'account_id'   => 'act_' . ($adAccounts[0]['id'] ?? $page['id']), // best-effort ad account
                'access_token' => $page['access_token'],
                'extra_config' => [
                    'page_id'    => $page['id'],
                    'page_name'  => $page['name'],
                    'user_token' => $userToken,
                    'ad_accounts'=> array_values($adAccounts),
                ],
            ]);
            $saved++;
        }

        // Redirect back to dashboard with success message
        header('Location: /?meta_connected=' . $saved);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function callbackUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/auth/meta/callback';
    }

    private static function graphGet(string $path, array $params = []): array
    {
        $url = str_starts_with($path, 'https://') ? $path : self::GRAPH_URL . $path;
        $url .= '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) self::errorPage("Connection error: $err");

        $json = json_decode($body, true) ?? [];
        if (isset($json['error'])) {
            self::errorPage('Facebook error: ' . ($json['error']['message'] ?? $body));
        }
        return $json;
    }

    private static function errorPage(string $msg): never
    {
        http_response_code(400);
        echo '<!DOCTYPE html><html><head>
            <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f3f4f6;margin:0}
            .box{background:white;padding:40px;border-radius:12px;max-width:480px;box-shadow:0 4px 20px rgba(0,0,0,.1);text-align:center}
            h2{color:#DC2626}p{color:#374151;line-height:1.6}a{color:#1A5C3A;font-weight:600}</style>
        </head><body><div class="box">
            <h2>Connection Failed</h2>
            <p>' . htmlspecialchars($msg) . '</p>
            <p><a href="/?view=accounts">← Back to Ad Accounts</a></p>
        </div></body></html>';
        exit;
    }
}

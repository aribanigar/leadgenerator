<?php
namespace ViaKashmir;

/**
 * Fetches leads from Meta (Facebook / Instagram / WhatsApp) Lead Ads.
 *
 * Process:
 *  1. For each active Meta ad account in the DB, find all Pages linked to it.
 *  2. For each Page, fetch all Lead Gen Forms.
 *  3. For each Form, fetch leads (filtered to since last sync).
 *  4. Normalise fields via FieldMapper and upsert into DB.
 */
class MetaLeads
{
    private const GRAPH_VER = 'v19.0';
    private const BASE      = 'https://graph.facebook.com/' . self::GRAPH_VER;

    private static array $lastSyncTime = [];   // formId => timestamp

    // ── Public Entry Point ───────────────────────────────────────────────────

    public static function sync(): array
    {
        $accounts = Database::getAccounts('meta');
        if (!$accounts) {
            echo "[Meta] No active Meta accounts configured.\n";
            return ['synced' => 0];
        }

        $total = 0;
        foreach ($accounts as $account) {
            $token = $account['access_token'] ?? null;
            if (!$token) {
                echo "[Meta] Skipping \"{$account['label']}\" – no access token.\n";
                continue;
            }

            try {
                $forms = self::fetchForms($account['account_id'], $token);
                echo "[Meta] \"{$account['label']}\": {$forms['count']} forms found.\n";

                foreach ($forms['forms'] as $form) {
                    $leads = self::fetchLeadsForForm($form['id'], $form['token'], (int)$account['id'], $form['name']);
                    foreach ($leads as $lead) {
                        Database::upsertLead($lead);
                        $total++;
                    }
                    self::$lastSyncTime[$form['id']] = time();
                    echo "[Meta]   Form \"{$form['name']}\": {$leads} leads imported.\n";
                }
            } catch (\Throwable $e) {
                echo "[Meta] Error for \"{$account['label']}\": {$e->getMessage()}\n";
            }
        }

        return ['synced' => $total];
    }

    // ── Webhook: process a single real-time lead event ───────────────────────

    public static function processWebhook(array $entry, ?int $accountDbId): void
    {
        foreach (($entry['changes'] ?? []) as $change) {
            if ($change['field'] !== 'leadgen') continue;
            $v = $change['value'];
            Database::upsertLead([
                'external_id'    => 'meta_' . $v['leadgen_id'],
                'source_platform'=> 'meta',
                'source_account' => $accountDbId,
                'ad_name'        => $v['ad_name'] ?? null,
                'form_name'      => isset($v['form_id']) ? 'Form ' . $v['form_id'] : null,
                'created_at'     => isset($v['created_time'])
                    ? date('Y-m-d H:i:s', $v['created_time'])
                    : date('Y-m-d H:i:s'),
                'raw_data'       => $v,
            ]);
        }
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private static function fetchForms(string $adAccountId, string $accessToken): array
    {
        // Get all Pages linked to this Business / access token
        $pages = self::graphGet('/me/accounts', ['fields' => 'id,name,access_token', 'limit' => 100], $accessToken);
        $forms = [];

        foreach (($pages['data'] ?? []) as $page) {
            $pageToken = $page['access_token'] ?? $accessToken;
            $res       = self::graphGet("/{$page['id']}/leadgen_forms", ['fields' => 'id,name', 'limit' => 100], $pageToken);
            foreach (($res['data'] ?? []) as $form) {
                $forms[] = ['id' => $form['id'], 'name' => $form['name'], 'token' => $pageToken];
            }
        }

        return ['forms' => $forms, 'count' => count($forms)];
    }

    private static function fetchLeadsForForm(string $formId, string $token, int $accountDbId, string $formName): array
    {
        $cfg  = require __DIR__ . '/../config.php';
        $days = $cfg['app']['sync_days'] ?? 30;

        $since = isset(self::$lastSyncTime[$formId])
            ? self::$lastSyncTime[$formId]
            : (time() - $days * 86400);

        $allLeads = [];
        $url      = "/{$formId}/leads";
        $params   = [
            'fields'    => 'id,created_time,field_data,ad_id,ad_name,campaign_id,campaign_name',
            'filtering' => json_encode([['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => $since]]),
            'limit'     => 100,
        ];

        while ($url) {
            $res = self::graphGet($url, $params, $token);
            foreach (($res['data'] ?? []) as $lead) {
                $mapped      = FieldMapper::map($lead['field_data'] ?? []);
                $allLeads[]  = array_merge($mapped, [
                    'external_id'    => 'meta_' . $lead['id'],
                    'source_platform'=> 'meta',
                    'source_account' => $accountDbId,
                    'ad_name'        => $lead['ad_name']      ?? null,
                    'campaign_name'  => $lead['campaign_name'] ?? null,
                    'form_name'      => $formName,
                    'created_at'     => isset($lead['created_time'])
                        ? date('Y-m-d H:i:s', strtotime($lead['created_time']))
                        : date('Y-m-d H:i:s'),
                    'raw_data'       => $lead,
                ]);
            }
            $url    = $res['paging']['next'] ?? null;
            $params = [];   // next page URL already has params
        }

        return $allLeads;
    }

    private static function graphGet(string $path, array $params = [], string $token = ''): array
    {
        $base = str_starts_with($path, 'https://') ? $path : self::BASE . $path;
        if ($params) $base .= '?' . http_build_query($params);
        if ($token && !str_contains($base, 'access_token=')) {
            $base .= (str_contains($base, '?') ? '&' : '?') . 'access_token=' . urlencode($token);
        }

        $ch = curl_init($base);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException("cURL error: {$err}");

        $json = json_decode($body, true);
        if (isset($json['error'])) {
            throw new \RuntimeException("Meta API error: " . ($json['error']['message'] ?? $body));
        }
        return $json ?? [];
    }
}

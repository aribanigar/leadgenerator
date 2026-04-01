<?php
namespace ViaKashmir;

use Google\Ads\GoogleAds\Lib\V18\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Util\V18\ResourceNames;
use Google\Ads\GoogleAds\V18\Services\SearchGoogleAdsRequest;

/**
 * Fetches leads from Google Ads Lead Form Extensions.
 *
 * Requires the google/ads-googleads composer package.
 * Get your Developer Token approved at: Google Ads → Tools → API Center
 */
class GoogleAdsLeads
{
    // ── Public Entry Point ───────────────────────────────────────────────────

    public static function sync(): array
    {
        $accounts = Database::getAccounts('google');
        if (!$accounts) {
            echo "[Google] No active Google Ads accounts configured.\n";
            return ['synced' => 0];
        }

        $total = 0;
        foreach ($accounts as $account) {
            try {
                $count = self::syncAccount($account);
                $total += $count;
                echo "[Google] \"{$account['label']}\": {$count} leads imported.\n";
            } catch (\Throwable $e) {
                echo "[Google] Error for \"{$account['label']}\": {$e->getMessage()}\n";
                if (str_contains($e->getMessage(), 'DEVELOPER_TOKEN')) {
                    echo "[Google] ⚠  Developer token may still be pending approval (1-2 business days).\n";
                }
            }
        }

        return ['synced' => $total];
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private static function syncAccount(array $account): int
    {
        $cfg   = require __DIR__ . '/../config.php';
        $extra = json_decode($account['extra_config'] ?? '{}', true) ?: [];

        // Merge account-level overrides with global config defaults
        $developerToken = $extra['developer_token'] ?? $cfg['google']['developer_token'] ?? null;
        $clientId       = $extra['client_id']       ?? $cfg['google']['client_id']       ?? null;
        $clientSecret   = $extra['client_secret']   ?? $cfg['google']['client_secret']   ?? null;
        $refreshToken   = $extra['refresh_token']   ?? $cfg['google']['refresh_token']   ?? null;

        if (!$developerToken || !$clientId || !$clientSecret || !$refreshToken) {
            throw new \RuntimeException('Missing Google Ads credentials. Check config.php or account extra_config.');
        }

        $client = (new GoogleAdsClientBuilder())
            ->withDeveloperToken($developerToken)
            ->withOAuth2Credential(
                (new \Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder())
                    ->withClientId($clientId)
                    ->withClientSecret($clientSecret)
                    ->withRefreshToken($refreshToken)
                    ->build()
            )
            ->build();

        $customerId = preg_replace('/[^0-9]/', '', $account['account_id']); // strip dashes
        $service    = $client->getGoogleAdsServiceClient();

        $query = '
            SELECT
              lead_form_submission_data.id,
              lead_form_submission_data.campaign,
              lead_form_submission_data.submission_date_time,
              lead_form_submission_data.lead_form_submission_fields
            FROM lead_form_submission_data
            ORDER BY lead_form_submission_data.submission_date_time DESC
            LIMIT 500
        ';

        $request = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query'       => $query,
        ]);

        $response = $service->search($request);
        $count    = 0;

        foreach ($response->iterateAllElements() as $row) {
            $sub        = $row->getLeadFormSubmissionData();
            $rawFields  = [];

            foreach ($sub->getLeadFormSubmissionFields() as $field) {
                $key            = strtolower($field->getFieldType()->name());
                $rawFields[$key] = $field->getFieldValue();
            }

            $mapped = FieldMapper::map($rawFields);
            $campaignResource = $sub->getCampaign();
            $campaignName     = $campaignResource ? basename(str_replace('~', '/', $campaignResource)) : null;

            $submissionTime = $sub->getSubmissionDateTime();
            Database::upsertLead(array_merge($mapped, [
                'external_id'    => 'google_' . $sub->getId(),
                'source_platform'=> 'google',
                'source_account' => (int)$account['id'],
                'campaign_name'  => $campaignName,
                'created_at'     => $submissionTime
                    ? date('Y-m-d H:i:s', strtotime($submissionTime))
                    : date('Y-m-d H:i:s'),
                'raw_data'       => ['submission_id' => $sub->getId()],
            ]));
            $count++;
        }

        return $count;
    }
}

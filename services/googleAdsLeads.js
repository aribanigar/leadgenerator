'use strict';
/**
 * Fetches leads from Google Ads lead form extensions.
 *
 * Requires: google-ads-api package + valid OAuth2 credentials.
 */

const { GoogleAdsApi } = require('google-ads-api');
const { getAccounts, upsertLead } = require('./database');
const { mapFields } = require('./fieldMapper');

function buildClient(account) {
  const extra = (() => {
    try { return JSON.parse(account.extra_config || '{}'); } catch { return {}; }
  })();

  return new GoogleAdsApi({
    client_id:        extra.client_id     || process.env.GOOGLE_ADS_CLIENT_ID,
    client_secret:    extra.client_secret || process.env.GOOGLE_ADS_CLIENT_SECRET,
    developer_token:  extra.developer_token || process.env.GOOGLE_ADS_DEVELOPER_TOKEN,
  });
}

async function syncGoogleAdsLeads() {
  const accounts = getAccounts('google');
  if (!accounts.length) {
    console.log('[Google] No active Google Ads accounts configured.');
    return { synced: 0 };
  }

  let totalSynced = 0;

  for (const account of accounts) {
    const extra = (() => {
      try { return JSON.parse(account.extra_config || '{}'); } catch { return {}; }
    })();

    const refreshToken = extra.refresh_token || process.env.GOOGLE_ADS_REFRESH_TOKEN;
    if (!refreshToken) {
      console.warn(`[Google] No refresh token for account "${account.label}"`);
      continue;
    }

    try {
      const client = buildClient(account);
      const customer = client.Customer({
        customer_id:   account.account_id,
        refresh_token: refreshToken,
      });

      // Query lead form submission assets
      // Google Ads API: lead_form_submission_data resource
      const results = await customer.query(`
        SELECT
          lead_form_submission_data.id,
          lead_form_submission_data.asset,
          lead_form_submission_data.campaign,
          lead_form_submission_data.ad_group,
          lead_form_submission_data.submission_date_time,
          lead_form_submission_data.lead_form_submission_fields
        FROM lead_form_submission_data
        ORDER BY lead_form_submission_data.submission_date_time DESC
        LIMIT 500
      `);

      for (const row of results) {
        const sub = row.lead_form_submission_data;
        // Convert field list to flat object
        const rawFields = {};
        for (const f of (sub.lead_form_submission_fields || [])) {
          rawFields[f.question_type?.toLowerCase?.() || f.field_type || 'unknown'] = f.field_value;
        }

        upsertLead({
          external_id:     `google_${sub.id}`,
          source_platform: 'google',
          source_account:  account.id,
          campaign_name:   sub.campaign || null,
          created_at:      sub.submission_date_time || new Date().toISOString(),
          raw_data:        sub,
          ...mapFields(rawFields),
        });
        totalSynced++;
      }

      console.log(`[Google] Account "${account.label}": imported ${results.length} leads`);
    } catch (err) {
      console.error(`[Google] Error for account "${account.label}": ${err.message}`);
      if (err.message?.includes('DEVELOPER_TOKEN')) {
        console.error('[Google] ⚠  Developer token may still be pending approval (1-2 business days).');
      }
    }
  }

  return { synced: totalSynced };
}

module.exports = { syncGoogleAdsLeads };

'use strict';
/**
 * Fetches leads from Meta (Facebook / Instagram / WhatsApp) Lead Ads.
 *
 * How it works:
 *  1. For each active Meta ad account, fetch all lead gen forms.
 *  2. For each form, fetch new leads (since last run).
 *  3. Normalise fields and upsert into local DB.
 */

const axios = require('axios');
const { getAccounts, upsertLead } = require('./database');
const { mapFields } = require('./fieldMapper');

const GRAPH_VERSION = 'v19.0';
const BASE = `https://graph.facebook.com/${GRAPH_VERSION}`;

// Track last sync time per account
const lastSyncTime = {};

async function fetchLeadsForForm(formId, accessToken, accountDbId, formName) {
  const since = lastSyncTime[formId]
    ? Math.floor(lastSyncTime[formId] / 1000)
    : Math.floor((Date.now() - 30 * 24 * 60 * 60 * 1000) / 1000); // 30 days on first run

  let url = `${BASE}/${formId}/leads`;
  const allLeads = [];

  while (url) {
    const resp = await axios.get(url, {
      params: {
        access_token: accessToken,
        fields: 'id,created_time,field_data,ad_id,ad_name,campaign_id,campaign_name',
        filtering: JSON.stringify([{ field: 'time_created', operator: 'GREATER_THAN', value: since }]),
        limit: 100,
      },
    });

    const data = resp.data;
    if (data.data) allLeads.push(...data.data);
    url = data.paging?.next || null;
  }

  return allLeads.map(lead => ({
    external_id: `meta_${lead.id}`,
    source_platform: 'meta',
    source_account: accountDbId,
    ad_name: lead.ad_name || null,
    campaign_name: lead.campaign_name || null,
    form_name: formName,
    created_at: lead.created_time,
    raw_data: lead,
    ...mapFields(lead.field_data || []),
  }));
}

async function fetchFormsForAdAccount(accountId, accessToken) {
  // Get all pages linked to the ad account
  const pagesResp = await axios.get(`${BASE}/me/accounts`, {
    params: { access_token: accessToken, fields: 'id,name,access_token', limit: 100 },
  });

  const forms = [];
  for (const page of (pagesResp.data?.data || [])) {
    try {
      const fResp = await axios.get(`${BASE}/${page.id}/leadgen_forms`, {
        params: { access_token: page.access_token || accessToken, fields: 'id,name', limit: 100 },
      });
      for (const f of (fResp.data?.data || [])) {
        forms.push({ id: f.id, name: f.name, token: page.access_token || accessToken });
      }
    } catch (err) {
      console.warn(`[Meta] Could not fetch forms for page ${page.id}: ${err.message}`);
    }
  }
  return forms;
}

async function syncMetaLeads() {
  const accounts = getAccounts('meta');
  if (!accounts.length) {
    console.log('[Meta] No active Meta ad accounts configured.');
    return { synced: 0 };
  }

  let totalSynced = 0;

  for (const account of accounts) {
    const token = account.access_token;
    if (!token) { console.warn(`[Meta] No access token for account ${account.label}`); continue; }

    try {
      const forms = await fetchFormsForAdAccount(account.account_id, token);
      console.log(`[Meta] Found ${forms.length} lead forms for "${account.label}"`);

      for (const form of forms) {
        try {
          const leads = await fetchLeadsForForm(form.id, form.token, account.id, form.name);
          for (const lead of leads) {
            upsertLead(lead);
            totalSynced++;
          }
          lastSyncTime[form.id] = Date.now();
          console.log(`[Meta] Form "${form.name}": imported ${leads.length} leads`);
        } catch (err) {
          console.error(`[Meta] Error fetching form ${form.id}: ${err.message}`);
        }
      }
    } catch (err) {
      console.error(`[Meta] Error for account "${account.label}": ${err.message}`);
    }
  }

  return { synced: totalSynced };
}

/**
 * Process a single lead payload from a Meta webhook.
 * Called from the webhook route when Meta POSTs a real-time lead.
 */
function processWebhookLead(entry, accountDbId) {
  for (const change of (entry.changes || [])) {
    if (change.field !== 'leadgen') continue;
    const v = change.value;
    upsertLead({
      external_id: `meta_${v.leadgen_id}`,
      source_platform: 'meta',
      source_account: accountDbId,
      ad_name: v.ad_name || null,
      form_name: v.form_id ? `Form ${v.form_id}` : null,
      created_at: new Date(v.created_time * 1000).toISOString(),
      raw_data: v,
      // field_data is not included in webhook – we'll fetch it
    });
  }
}

module.exports = { syncMetaLeads, processWebhookLead };

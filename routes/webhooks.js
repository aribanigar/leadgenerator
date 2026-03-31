'use strict';
/**
 * Meta Webhook endpoint.
 * Setup in Meta for Developers → Your App → Webhooks → Subscribe to "leadgen".
 * Callback URL: https://yourdomain.com/api/webhooks/meta
 */

const express = require('express');
const router  = express.Router();
const { processWebhookLead } = require('../services/metaLeads');
const { getAccounts }        = require('../services/database');

// Meta verification handshake
router.get('/meta', (req, res) => {
  const mode      = req.query['hub.mode'];
  const token     = req.query['hub.verify_token'];
  const challenge = req.query['hub.challenge'];

  if (mode === 'subscribe' && token === process.env.META_WEBHOOK_VERIFY_TOKEN) {
    console.log('[Webhook] Meta verified successfully');
    return res.status(200).send(challenge);
  }
  res.sendStatus(403);
});

// Receive real-time lead events
router.post('/meta', (req, res) => {
  const body = req.body;
  if (body.object !== 'page') return res.sendStatus(400);

  const accounts = getAccounts('meta');

  for (const entry of (body.entry || [])) {
    // Try to match page to an account (best-effort)
    const account = accounts.find(a => a.account_id === entry.id) || accounts[0];
    processWebhookLead(entry, account?.id || null);
  }

  res.sendStatus(200);
});

module.exports = router;

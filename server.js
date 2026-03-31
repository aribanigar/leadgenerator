'use strict';
require('dotenv').config();

const express  = require('express');
const cors     = require('cors');
const cron     = require('node-cron');
const path     = require('path');

// Run DB setup on first launch
require('./setup-database');

const { syncMetaLeads }      = require('./services/metaLeads');
const { syncGoogleAdsLeads } = require('./services/googleAdsLeads');

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// ── API Routes ────────────────────────────────────────────────────────────────
app.use('/api/leads',    require('./routes/leads'));
app.use('/api/accounts', require('./routes/accounts'));
app.use('/api/team',     require('./routes/team'));
app.use('/api/sync',     require('./routes/sync'));
app.use('/api/webhooks', require('./routes/webhooks'));

// Catch-all → SPA
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// ── Scheduled Sync ────────────────────────────────────────────────────────────
const CRON = process.env.SYNC_CRON || '*/15 * * * *';
cron.schedule(CRON, async () => {
  console.log('[Cron] Auto-syncing leads…');
  await Promise.all([
    syncMetaLeads().catch(e => console.error('[Cron] Meta error:', e.message)),
    syncGoogleAdsLeads().catch(e => console.error('[Cron] Google error:', e.message)),
  ]);
});

// ── Start ─────────────────────────────────────────────────────────────────────
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`\n🏔  ViaKashmir Lead CRM running on http://localhost:${PORT}`);
  console.log(`   Auto-sync cron: ${CRON}\n`);
});

module.exports = app;  // allows require() as a module in existing Express app

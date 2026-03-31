'use strict';
const express = require('express');
const router  = express.Router();
const { syncMetaLeads }       = require('../services/metaLeads');
const { syncGoogleAdsLeads }  = require('../services/googleAdsLeads');

// POST /api/sync  — manual trigger
router.post('/', async (req, res) => {
  try {
    const [meta, google] = await Promise.all([
      syncMetaLeads().catch(e => ({ error: e.message })),
      syncGoogleAdsLeads().catch(e => ({ error: e.message })),
    ]);
    res.json({ meta, google, timestamp: new Date().toISOString() });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;

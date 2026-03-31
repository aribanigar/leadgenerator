'use strict';
const express = require('express');
const router  = express.Router();
const db      = require('../services/database');

// GET /api/accounts
router.get('/', (req, res) => {
  const accounts = db.getAccounts();
  // Strip access tokens before sending to client
  res.json(accounts.map(a => ({
    ...a,
    access_token: a.access_token ? '••••••' + a.access_token.slice(-4) : null,
  })));
});

// POST /api/accounts
router.post('/', (req, res) => {
  const { platform, label, account_id, access_token, extra_config } = req.body;
  if (!platform || !label || !account_id)
    return res.status(400).json({ error: 'platform, label and account_id are required' });
  const id = db.addAccount({ platform, label, account_id, access_token, extra_config });
  res.status(201).json({ id });
});

// DELETE /api/accounts/:id
router.delete('/:id', (req, res) => {
  db.deleteAccount(parseInt(req.params.id));
  res.json({ ok: true });
});

module.exports = router;

'use strict';
const express = require('express');
const router  = express.Router();
const db      = require('../services/database');

// GET /api/team
router.get('/', (req, res) => res.json(db.getTeam()));

// POST /api/team
router.post('/', (req, res) => {
  const { name, email, phone, role } = req.body;
  if (!name || !email) return res.status(400).json({ error: 'name and email required' });
  const id = db.addTeamMember({ name, email, phone, role });
  res.status(201).json({ id });
});

// DELETE /api/team/:id
router.delete('/:id', (req, res) => {
  db.removeTeamMember(parseInt(req.params.id));
  res.json({ ok: true });
});

module.exports = router;

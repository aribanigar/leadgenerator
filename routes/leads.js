'use strict';
const express = require('express');
const router  = express.Router();
const db      = require('../services/database');

// GET /api/leads
router.get('/', (req, res) => {
  const { status, platform, assigned_to, search, page, limit } = req.query;
  const result = db.getLeads({
    status,
    platform,
    assignedTo: assigned_to ? parseInt(assigned_to) : undefined,
    search,
    page:  page  ? parseInt(page)  : 1,
    limit: limit ? parseInt(limit) : 50,
  });
  res.json(result);
});

// GET /api/leads/stats
router.get('/stats', (req, res) => {
  res.json(db.getStats());
});

// GET /api/leads/:id
router.get('/:id', (req, res) => {
  const lead = db.getLead(parseInt(req.params.id));
  if (!lead) return res.status(404).json({ error: 'Lead not found' });
  res.json(lead);
});

// PATCH /api/leads/:id/status
router.patch('/:id/status', (req, res) => {
  const { status } = req.body;
  const valid = ['new', 'contacted', 'qualified', 'converted', 'lost'];
  if (!valid.includes(status)) return res.status(400).json({ error: 'Invalid status' });
  const lead = db.updateLeadStatus(parseInt(req.params.id), status);
  res.json(lead);
});

// PATCH /api/leads/:id/assign
router.patch('/:id/assign', (req, res) => {
  const { member_id } = req.body;
  if (!member_id) return res.status(400).json({ error: 'member_id required' });
  const lead = db.assignLead(parseInt(req.params.id), parseInt(member_id));
  res.json(lead);
});

// POST /api/leads/:id/notes
router.post('/:id/notes', (req, res) => {
  const { note, author_id } = req.body;
  if (!note) return res.status(400).json({ error: 'note required' });
  db.addNote(parseInt(req.params.id), note, author_id ? parseInt(author_id) : null);
  res.json({ ok: true });
});

module.exports = router;

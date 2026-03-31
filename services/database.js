'use strict';
const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');

const DB_PATH = process.env.DB_PATH || './data/leads.db';
const dir = path.dirname(DB_PATH);
if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

const db = new Database(DB_PATH);
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

// ── Helpers ──────────────────────────────────────────────────────────────────

function getAccounts(platform = null) {
  if (platform) {
    return db.prepare('SELECT * FROM ad_accounts WHERE active=1 AND platform=?').all(platform);
  }
  return db.prepare('SELECT * FROM ad_accounts WHERE active=1').all();
}

function upsertLead(data) {
  const existing = data.external_id
    ? db.prepare('SELECT id FROM leads WHERE external_id=?').get(data.external_id)
    : null;

  if (existing) return existing.id;          // already imported – skip

  const stmt = db.prepare(`
    INSERT INTO leads (
      external_id, source_platform, source_account, ad_name, campaign_name, form_name,
      client_name, email, phone,
      destination, travel_duration, travel_from_date, travel_to_date,
      adults, children, budget, special_requests,
      raw_data, created_at
    ) VALUES (
      @external_id, @source_platform, @source_account, @ad_name, @campaign_name, @form_name,
      @client_name, @email, @phone,
      @destination, @travel_duration, @travel_from_date, @travel_to_date,
      @adults, @children, @budget, @special_requests,
      @raw_data, @created_at
    )
  `);

  const info = stmt.run({
    external_id: data.external_id || null,
    source_platform: data.source_platform,
    source_account: data.source_account || null,
    ad_name: data.ad_name || null,
    campaign_name: data.campaign_name || null,
    form_name: data.form_name || null,
    client_name: data.client_name || null,
    email: data.email || null,
    phone: data.phone || null,
    destination: data.destination || null,
    travel_duration: data.travel_duration || null,
    travel_from_date: data.travel_from_date || null,
    travel_to_date: data.travel_to_date || null,
    adults: data.adults || 0,
    children: data.children || 0,
    budget: data.budget || null,
    special_requests: data.special_requests || null,
    raw_data: JSON.stringify(data.raw_data || {}),
    created_at: data.created_at || new Date().toISOString(),
  });

  return info.lastInsertRowid;
}

function getLeads({ status, platform, assignedTo, search, page = 1, limit = 50 } = {}) {
  let where = [];
  let params = [];

  if (status)     { where.push("l.status = ?");           params.push(status); }
  if (platform)   { where.push("l.source_platform = ?");  params.push(platform); }
  if (assignedTo) { where.push("l.assigned_to = ?");      params.push(assignedTo); }
  if (search) {
    where.push("(l.client_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)");
    const q = `%${search}%`;
    params.push(q, q, q);
  }

  const whereClause = where.length ? 'WHERE ' + where.join(' AND ') : '';
  const offset = (page - 1) * limit;

  const rows = db.prepare(`
    SELECT l.*,
           t.name  AS assignee_name,
           t.email AS assignee_email
    FROM leads l
    LEFT JOIN team_members t ON t.id = l.assigned_to
    ${whereClause}
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
  `).all(...params, limit, offset);

  const total = db.prepare(`
    SELECT COUNT(*) AS n FROM leads l ${whereClause}
  `).get(...params).n;

  return { leads: rows, total, page, limit };
}

function getLead(id) {
  const lead = db.prepare(`
    SELECT l.*, t.name AS assignee_name
    FROM leads l
    LEFT JOIN team_members t ON t.id = l.assigned_to
    WHERE l.id = ?
  `).get(id);
  if (!lead) return null;
  lead.notes = db.prepare(`
    SELECT n.*, m.name AS author_name
    FROM lead_notes n
    LEFT JOIN team_members m ON m.id = n.author_id
    WHERE n.lead_id = ?
    ORDER BY n.created_at DESC
  `).all(id);
  return lead;
}

function updateLeadStatus(id, status, authorId = null) {
  const patch = { status };
  if (status === 'contacted') patch.contacted_at = new Date().toISOString();
  if (status === 'converted') patch.converted_at = new Date().toISOString();

  const sets = Object.keys(patch).map(k => `${k} = ?`).join(', ');
  db.prepare(`UPDATE leads SET ${sets} WHERE id = ?`).run(...Object.values(patch), id);

  addNote(id, `Status changed to "${status}"`, authorId);
  return getLead(id);
}

function assignLead(id, memberId, authorId = null) {
  db.prepare('UPDATE leads SET assigned_to = ? WHERE id = ?').run(memberId, id);
  const m = db.prepare('SELECT name FROM team_members WHERE id = ?').get(memberId);
  addNote(id, `Assigned to ${m ? m.name : 'team member'}`, authorId);
  return getLead(id);
}

function addNote(leadId, note, authorId = null) {
  db.prepare('INSERT INTO lead_notes (lead_id, author_id, note) VALUES (?, ?, ?)')
    .run(leadId, authorId || null, note);
}

// ── Stats ────────────────────────────────────────────────────────────────────

function getStats() {
  const rows = db.prepare(`
    SELECT status, COUNT(*) AS n FROM leads GROUP BY status
  `).all();
  const byPlatform = db.prepare(`
    SELECT source_platform, COUNT(*) AS n FROM leads GROUP BY source_platform
  `).all();
  const total = db.prepare('SELECT COUNT(*) AS n FROM leads').get().n;
  return { total, byStatus: rows, byPlatform };
}

// ── Ad Accounts ───────────────────────────────────────────────────────────────

function addAccount({ platform, label, account_id, access_token, extra_config = {} }) {
  return db.prepare(`
    INSERT INTO ad_accounts (platform, label, account_id, access_token, extra_config)
    VALUES (?, ?, ?, ?, ?)
  `).run(platform, label, account_id, access_token || null, JSON.stringify(extra_config))
    .lastInsertRowid;
}

function deleteAccount(id) {
  db.prepare('UPDATE ad_accounts SET active=0 WHERE id=?').run(id);
}

// ── Team ──────────────────────────────────────────────────────────────────────

function getTeam() {
  return db.prepare('SELECT * FROM team_members WHERE active=1 ORDER BY name').all();
}

function addTeamMember({ name, email, phone, role = 'agent' }) {
  return db.prepare(`
    INSERT INTO team_members (name, email, phone, role) VALUES (?, ?, ?, ?)
  `).run(name, email, phone || null, role).lastInsertRowid;
}

function removeTeamMember(id) {
  db.prepare('UPDATE team_members SET active=0 WHERE id=?').run(id);
}

module.exports = {
  db,
  getAccounts, upsertLead,
  getLeads, getLead, updateLeadStatus, assignLead, addNote,
  getStats,
  addAccount, deleteAccount,
  getTeam, addTeamMember, removeTeamMember,
};

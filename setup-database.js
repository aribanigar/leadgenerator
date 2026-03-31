#!/usr/bin/env node
/**
 * Run once: node setup-database.js
 * Creates the SQLite database with all required tables.
 */

const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');

const DB_PATH = process.env.DB_PATH || './data/leads.db';
const dir = path.dirname(DB_PATH);
if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

const db = new Database(DB_PATH);

db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

db.exec(`
  -- ── Ad Accounts ──────────────────────────────────────────────────────────
  CREATE TABLE IF NOT EXISTS ad_accounts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    platform    TEXT NOT NULL CHECK(platform IN ('meta','google')),
    label       TEXT NOT NULL,
    account_id  TEXT NOT NULL,
    access_token TEXT,
    extra_config TEXT DEFAULT '{}',
    active      INTEGER DEFAULT 1,
    created_at  TEXT DEFAULT (datetime('now'))
  );

  -- ── Team Members ─────────────────────────────────────────────────────────
  CREATE TABLE IF NOT EXISTS team_members (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT UNIQUE NOT NULL,
    phone      TEXT,
    role       TEXT DEFAULT 'agent',
    active     INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now'))
  );

  -- ── Leads ─────────────────────────────────────────────────────────────────
  CREATE TABLE IF NOT EXISTS leads (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    external_id      TEXT UNIQUE,
    source_platform  TEXT NOT NULL,
    source_account   INTEGER REFERENCES ad_accounts(id),
    ad_name          TEXT,
    campaign_name    TEXT,
    form_name        TEXT,

    -- Contact info
    client_name      TEXT,
    email            TEXT,
    phone            TEXT,

    -- Travel details
    destination      TEXT,
    travel_duration  INTEGER,
    travel_from_date TEXT,
    travel_to_date   TEXT,
    adults           INTEGER DEFAULT 0,
    children         INTEGER DEFAULT 0,
    budget           TEXT,
    special_requests TEXT,

    -- CRM state
    status           TEXT DEFAULT 'new'
                       CHECK(status IN ('new','contacted','qualified','converted','lost')),
    assigned_to      INTEGER REFERENCES team_members(id),
    contacted_at     TEXT,
    converted_at     TEXT,

    raw_data         TEXT DEFAULT '{}',
    created_at       TEXT DEFAULT (datetime('now')),
    updated_at       TEXT DEFAULT (datetime('now'))
  );

  -- ── Lead Notes / Activity Log ─────────────────────────────────────────────
  CREATE TABLE IF NOT EXISTS lead_notes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id    INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    author_id  INTEGER REFERENCES team_members(id),
    note       TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now'))
  );

  -- Trigger: keep updated_at fresh
  CREATE TRIGGER IF NOT EXISTS leads_updated
  AFTER UPDATE ON leads
  BEGIN
    UPDATE leads SET updated_at = datetime('now') WHERE id = NEW.id;
  END;
`);

console.log('✅  Database ready at', DB_PATH);
db.close();

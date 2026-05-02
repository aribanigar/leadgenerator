# ViaKashmir Lead CRM

PHP-based CRM that aggregates leads from Meta (Facebook/Instagram/WhatsApp) Lead Ads and Google Ads Lead Forms, normalises them into a unified schema, and provides a dashboard for managing, assigning, and following up on travel leads.

## Architecture

```
leadgenerator/
├── index.php          # Single entry point — routes all API requests + serves SPA
├── config.php         # DB credentials, Meta app keys, Google Ads tokens
├── install.php        # One-time DB table creation
├── cron.php           # Runs lead sync (*/15 * * * *  php cron.php)
├── src/
│   ├── Database.php       # PDO MySQL wrapper — leads, accounts, team, notes
│   ├── MetaLeads.php      # Meta Graph API — fetches leads from all linked Pages
│   ├── MetaOAuth.php      # Facebook OAuth flow (Sign in with Facebook)
│   ├── GoogleAdsLeads.php # Google Ads GAQL — lead form submissions
│   └── FieldMapper.php    # Normalises vendor field names → unified schema
├── templates/
│   └── dashboard.php  # Single-page app shell (sidebar + views + modals)
└── public/
    ├── app.js         # Vanilla JS — all API calls, rendering, interactions
    └── style.css      # Dashboard styles
```

## Database Schema (created by install.php)

- **`leads`** — all imported leads with unified fields: `client_name`, `email`, `phone`, `destination`, `travel_duration`, `travel_from_date`, `travel_to_date`, `adults`, `children`, `budget`, `special_requests`, `status`, `assigned_to`, `source_platform`, `source_account`, `raw_data`
- **`ad_accounts`** — connected Meta and Google Ads accounts with credentials
- **`team_members`** — sales agents/managers; leads can be assigned here
- **`lead_notes`** — audit trail and manual notes per lead

## Key API Endpoints (index.php)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/leads` | List leads with filters (status, platform, assignee, search, page) |
| GET | `/api/leads/{id}` | Single lead + notes |
| POST | `/api/leads/{id}/status` | Update lead status |
| POST | `/api/leads/{id}/assign` | Assign lead to team member |
| POST | `/api/leads/{id}/note` | Add note |
| GET | `/api/accounts` | List ad accounts |
| POST | `/api/accounts` | Add Google Ads account |
| DELETE | `/api/accounts/{id}` | Remove account |
| GET | `/api/team` | List team members |
| POST | `/api/team` | Add team member |
| DELETE | `/api/team/{id}` | Remove team member |
| GET | `/api/stats` | Dashboard stats (totals by status/platform) |
| POST | `/api/sync` | Trigger manual lead sync |
| GET | `/auth/meta` | Start Meta OAuth flow |
| GET | `/auth/meta/callback` | Meta OAuth callback |
| POST | `/webhook/meta` | Meta real-time lead webhook |

## Lead Status Flow

`new` → `contacted` → `qualified` → `converted` | `lost`

Status changes are logged automatically as lead notes.

## Field Mapping

`FieldMapper::map()` accepts both Meta format (`[{field, value}]`) and flat key/value arrays. It fuzzy-matches ~50 common field name variants to the 11 unified schema fields. Add new variants to `$map` in `src/FieldMapper.php`.

## Configuration

Copy `config.php` and fill in:
- `db.*` — MySQL connection
- `meta.app_id` + `meta.app_secret` — from developers.facebook.com (set once by developer)
- `google.*` — optional defaults; can be set per-account in the dashboard

## Setup

```bash
# 1. Install PHP deps
composer install

# 2. Create DB tables
php install.php

# 3. Set up cron
*/15 * * * *  php /path/to/leadgenerator/cron.php

# 4. Web server: point root to leadgenerator/, enable .htaccess rewrites
```

## Skills

### lead-research-assistant
`.claude/skills/lead-research-assistant/SKILL.md`

Enriches CRM leads via Apollo.io, scores them against the ViaKashmir ICP, generates WhatsApp/email outreach drafts, and can search Apollo for new B2B/B2C prospects. Use when asked to research, score, enrich, or build outreach for any lead.

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ViaKashmir – Lead Management CRM</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/public/style.css" />
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
        <path d="M14 2L26 24H2L14 2Z" fill="white" fill-opacity="0.9"/>
        <path d="M14 8L22 24H6L14 8Z" fill="#F5821F" fill-opacity="0.85"/>
        <circle cx="14" cy="22" r="2" fill="white"/>
      </svg>
    </div>
    <div class="brand-text">
      <span class="brand-name">ViaKashmir</span>
      <span class="brand-sub">Lead CRM</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="#" class="nav-item active" data-view="dashboard">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="#" class="nav-item" data-view="leads">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      All Leads
    </a>
    <a href="#" class="nav-item" data-view="accounts">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      Ad Accounts
    </a>
    <a href="#" class="nav-item" data-view="team">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Team
    </a>
  </nav>

  <div class="sidebar-footer">
    <button class="btn-sync" id="syncBtn" onclick="triggerSync()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
      Sync Leads Now
    </button>
    <div class="last-sync" id="lastSync">Last sync: —</div>
  </div>
</aside>

<!-- ── Main Content ──────────────────────────────────────────────────── -->
<main class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <h1 class="page-title" id="pageTitle">Dashboard</h1>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
    </div>
  </header>

  <!-- Dashboard View -->
  <section class="view active" id="view-dashboard">
    <div class="stat-grid" id="statsGrid"></div>
    <div class="dashboard-grid">
      <div class="card">
        <div class="card-header">
          <h3>Recent Leads</h3>
          <a href="#" class="link-sm" onclick="switchView('leads')">View all →</a>
        </div>
        <div id="recentLeads" class="recent-list"></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Leads by Source</h3></div>
        <div id="sourceChart" class="source-chart"></div>
      </div>
    </div>
  </section>

  <!-- All Leads View -->
  <section class="view" id="view-leads">
    <div class="filters-bar">
      <input type="text" id="searchInput" class="filter-input" placeholder="Search by name, phone, email…" oninput="debounceLoad()" />
      <select id="filterStatus" class="filter-select" onchange="loadLeads()">
        <option value="">All Statuses</option>
        <option value="new">New</option>
        <option value="contacted">Contacted</option>
        <option value="qualified">Qualified</option>
        <option value="converted">Converted</option>
        <option value="lost">Lost</option>
      </select>
      <select id="filterPlatform" class="filter-select" onchange="loadLeads()">
        <option value="">All Sources</option>
        <option value="meta">Meta (FB / IG / WA)</option>
        <option value="google">Google Ads</option>
      </select>
      <select id="filterAssignee" class="filter-select" onchange="loadLeads()">
        <option value="">All Agents</option>
      </select>
    </div>
    <div class="card table-card">
      <div class="table-wrap">
        <table class="leads-table">
          <thead>
            <tr>
              <th>Client</th><th>Contact</th><th>Destination</th>
              <th>Travel Details</th><th>Budget</th>
              <th>Source</th><th>Status</th><th>Assigned</th><th>Actions</th>
            </tr>
          </thead>
          <tbody id="leadsBody"><tr><td colspan="9" class="empty-msg">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="pagination" id="pagination"></div>
    </div>
  </section>

  <!-- Ad Accounts View -->
  <section class="view" id="view-accounts">
    <div class="two-col">
      <div class="card">
        <div class="card-header"><h3>Connected Ad Accounts</h3></div>
        <div id="accountsList"></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Connect New Account</h3></div>
        <div class="form-tabs">
          <button class="tab-btn active" onclick="switchTab('meta')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
            Meta / Facebook
          </button>
          <button class="tab-btn" onclick="switchTab('google')">
            <svg width="16" height="16" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z" fill="#EA4335"/></svg>
            Google Ads
          </button>
        </div>

        <!-- Meta Form -->
        <form id="metaForm" class="account-form" onsubmit="addAccount(event,'meta')">
          <div class="info-box">
            <strong>How to get your Meta credentials (5 min setup):</strong>
            <ol>
              <li>
                <strong>Ad Account ID —</strong>
                Go to <a href="https://business.facebook.com" target="_blank">business.facebook.com</a>
                → Settings → Ad Accounts → copy the number shown.
                Paste it here with <code>act_</code> prefix → e.g. <code>act_1234567890</code>
              </li>
              <li>
                <strong>Create a Developer App (once) —</strong>
                Go to <a href="https://developers.facebook.com/apps" target="_blank">developers.facebook.com/apps</a>
                → Create App → choose <em>Business</em> type → name it anything.
                Then on the app dashboard → Add Product → <em>Lead Ads Retrieval</em> → Set Up.
              </li>
              <li>
                <strong>Page Access Token —</strong>
                Go to <a href="https://developers.facebook.com/tools/explorer" target="_blank">developers.facebook.com/tools/explorer</a>
                → select your app from the top-right dropdown
                → click <em>User or Page</em> → <strong>Get Page Access Token</strong>
                → select your ViaKashmir Facebook Page
                → grant permissions: <code>leads_retrieval</code>, <code>pages_manage_metadata</code>, <code>pages_read_engagement</code>
                → click <strong>Generate Access Token</strong>.
              </li>
              <li>
                <strong>Extend to 60-day token —</strong>
                Click the <em>blue ⓘ icon</em> next to your token
                → Open in Access Token Tool → click <strong>Extend Access Token</strong>
                → copy the new token and paste it below.
                <span style="color:#D96A10;font-weight:600">⚠ Do this step or it expires in 1 hour!</span>
              </li>
            </ol>
          </div>
          <label>Account Label (nickname)
            <input name="label" type="text" placeholder="e.g. ViaKashmir Meta" required />
          </label>
          <label>Meta Ad Account ID
            <input name="account_id" type="text" placeholder="act_1234567890" required />
          </label>
          <label>Page Access Token
            <input name="access_token" type="password" placeholder="EAAxxxxxxx…" required />
          </label>
          <button type="submit" class="btn-primary">Connect Meta Account</button>
        </form>

        <!-- Google Form -->
        <form id="googleForm" class="account-form hidden" onsubmit="addAccount(event,'google')">
          <div class="info-box">
            <strong>How to get your Google Ads credentials:</strong>
            <ol>
              <li><strong>Developer Token:</strong> Google Ads dashboard → Tools → API Center</li>
              <li><strong>Customer ID:</strong> Top-right of Google Ads dashboard (format: XXX-XXX-XXXX)</li>
              <li><strong>OAuth Credentials:</strong> console.cloud.google.com → APIs & Services → Credentials → Create OAuth 2.0 Client ID</li>
            </ol>
          </div>
          <label>Account Label
            <input name="label" type="text" placeholder="e.g. ViaKashmir Google" required />
          </label>
          <label>Google Ads Customer ID
            <input name="account_id" type="text" placeholder="1234567890" required />
          </label>
          <label>OAuth Refresh Token
            <input name="refresh_token" type="password" placeholder="1//0gXxxxxxxx" required />
          </label>
          <label>Developer Token
            <input name="developer_token" type="password" placeholder="ABcdefGh…" required />
          </label>
          <label>Client ID
            <input name="client_id" type="text" placeholder="123456.apps.googleusercontent.com" required />
          </label>
          <label>Client Secret
            <input name="client_secret" type="password" placeholder="GOCSPx-…" required />
          </label>
          <button type="submit" class="btn-primary">Connect Google Ads Account</button>
        </form>
      </div>
    </div>
  </section>

  <!-- Team View -->
  <section class="view" id="view-team">
    <div class="two-col">
      <div class="card">
        <div class="card-header"><h3>Team Members</h3></div>
        <div id="teamList"></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Add Team Member</h3></div>
        <form class="account-form" onsubmit="addTeamMember(event)">
          <label>Full Name   <input name="name"  type="text"  placeholder="Aadil Bhat" required /></label>
          <label>Email       <input name="email" type="email" placeholder="aadil@viakashmir.in" required /></label>
          <label>Phone       <input name="phone" type="tel"   placeholder="+91-9876543210" /></label>
          <label>Role
            <select name="role">
              <option value="agent">Sales Agent</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </label>
          <button type="submit" class="btn-primary">Add Member</button>
        </form>
      </div>
    </div>
  </section>

</main>

<!-- ── Lead Detail Modal ─────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="leadModal">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modalTitle">Lead Details</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────────────────── -->
<div class="toast hidden" id="toast"></div>

<script src="/public/app.js"></script>
</body>
</html>

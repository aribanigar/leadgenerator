'use strict';
/* ── ViaKashmir Lead CRM – Frontend Logic ─────────────────────────────────── */

const API = '';   // same-origin
let currentPage   = 1;
let totalLeads    = 0;
let teamMembers   = [];
let activeView    = 'dashboard';

// ── Bootstrap ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  setTopbarDate();
  await loadTeam();
  await loadDashboard();
  switchView('dashboard');
});

function setTopbarDate() {
  const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  document.getElementById('topbarDate').textContent =
    new Date().toLocaleDateString('en-IN', opts);
}

// ── Navigation ────────────────────────────────────────────────────────────────
function switchView(view) {
  activeView = view;
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.view === view);
  });
  document.querySelectorAll('.view').forEach(el => {
    el.classList.toggle('active', el.id === `view-${view}`);
  });
  const titles = { dashboard: 'Dashboard', leads: 'All Leads', accounts: 'Ad Accounts', team: 'Team' };
  document.getElementById('pageTitle').textContent = titles[view] || view;

  if (view === 'leads')    { currentPage = 1; loadLeads(); }
  if (view === 'accounts') loadAccounts();
  if (view === 'team')     renderTeam();
}

document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', e => { e.preventDefault(); switchView(el.dataset.view); });
});

// ── Dashboard ─────────────────────────────────────────────────────────────────
async function loadDashboard() {
  const [stats, leadsResp] = await Promise.all([
    apiFetch('/api/leads/stats'),
    apiFetch('/api/leads?limit=5&page=1'),
  ]);
  renderStats(stats);
  renderRecentLeads(leadsResp.leads || []);
  renderSourceChart(stats.byPlatform || []);
}

function renderStats(stats) {
  const byStatus = {};
  (stats.byStatus || []).forEach(r => { byStatus[r.status] = r.n; });

  const cards = [
    { key: 'total',     label: 'Total Leads',  value: stats.total || 0,            cls: 'total' },
    { key: 'new',       label: 'New',           value: byStatus.new || 0,           cls: 'new' },
    { key: 'contacted', label: 'Contacted',     value: byStatus.contacted || 0,     cls: 'contacted' },
    { key: 'qualified', label: 'Qualified',     value: byStatus.qualified || 0,     cls: 'qualified' },
    { key: 'converted', label: 'Converted',     value: byStatus.converted || 0,     cls: 'converted' },
    { key: 'lost',      label: 'Lost',          value: byStatus.lost || 0,          cls: 'lost' },
  ];

  document.getElementById('statsGrid').innerHTML = cards.map(c => `
    <div class="stat-card ${c.cls}">
      <div class="stat-label">${c.label}</div>
      <div class="stat-value">${c.value}</div>
      <div class="stat-sub">leads</div>
    </div>
  `).join('');
}

function renderRecentLeads(leads) {
  const el = document.getElementById('recentLeads');
  if (!leads.length) { el.innerHTML = '<p class="empty-msg text-muted" style="padding:20px">No leads yet. Connect an ad account and sync.</p>'; return; }
  el.innerHTML = leads.map(l => `
    <div class="recent-item" onclick="openLead(${l.id})">
      <div class="recent-avatar">${initials(l.client_name)}</div>
      <div class="recent-info">
        <div class="recent-name">${l.client_name || '—'}</div>
        <div class="recent-meta">${l.phone || l.email || '—'} · ${sourceBadge(l.source_platform)}</div>
      </div>
      ${statusBadge(l.status)}
    </div>
  `).join('');
}

function renderSourceChart(platforms) {
  const total = platforms.reduce((s, p) => s + p.n, 0) || 1;
  const meta   = platforms.find(p => p.source_platform === 'meta')?.n   || 0;
  const google = platforms.find(p => p.source_platform === 'google')?.n || 0;

  document.getElementById('sourceChart').innerHTML = `
    <div class="source-bar">
      <div class="source-bar-label">
        <span>Meta (Facebook / Instagram / WhatsApp)</span><span>${meta}</span>
      </div>
      <div class="source-bar-track">
        <div class="source-bar-fill fill-meta" style="width:${(meta/total*100).toFixed(1)}%"></div>
      </div>
    </div>
    <div class="source-bar">
      <div class="source-bar-label">
        <span>Google Ads</span><span>${google}</span>
      </div>
      <div class="source-bar-track">
        <div class="source-bar-fill fill-google" style="width:${(google/total*100).toFixed(1)}%"></div>
      </div>
    </div>
    <p class="text-muted" style="font-size:11px;margin-top:8px">Total: ${total} leads</p>
  `;
}

// ── Leads Table ───────────────────────────────────────────────────────────────
let debounceTimer;
function debounceLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => { currentPage = 1; loadLeads(); }, 350);
}

async function loadLeads() {
  const status    = document.getElementById('filterStatus').value;
  const platform  = document.getElementById('filterPlatform').value;
  const assignedTo= document.getElementById('filterAssignee').value;
  const search    = document.getElementById('searchInput').value.trim();

  const params = new URLSearchParams({ page: currentPage, limit: 50 });
  if (status)     params.set('status',     status);
  if (platform)   params.set('platform',   platform);
  if (assignedTo) params.set('assigned_to',assignedTo);
  if (search)     params.set('search',     search);

  const resp = await apiFetch('/api/leads?' + params);
  totalLeads = resp.total || 0;
  renderLeadsTable(resp.leads || []);
  renderPagination(resp.page, resp.limit, resp.total);
}

function renderLeadsTable(leads) {
  const tbody = document.getElementById('leadsBody');
  if (!leads.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty-msg">No leads found. Try adjusting filters or sync your accounts.</td></tr>';
    return;
  }

  tbody.innerHTML = leads.map(l => `
    <tr onclick="openLead(${l.id})">
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="recent-avatar" style="width:32px;height:32px;font-size:12px">${initials(l.client_name)}</div>
          <div>
            <div style="font-weight:600">${esc(l.client_name || '—')}</div>
            <div style="font-size:11px;color:var(--slate-500)">${formatDate(l.created_at)}</div>
          </div>
        </div>
      </td>
      <td>
        <div style="font-size:13px">${esc(l.phone || '—')}</div>
        <div style="font-size:11px;color:var(--slate-500)">${esc(l.email || '')}</div>
      </td>
      <td>${esc(l.destination || '—')}</td>
      <td>
        <div style="font-size:12.5px">
          ${l.travel_duration ? `<b>${l.travel_duration}</b> days` : '—'}
          ${l.travel_from_date ? `<br><span style="color:var(--slate-500)">${formatDate(l.travel_from_date)}</span>` : ''}
        </div>
        <div style="font-size:11.5px;color:var(--slate-600)">
          ${l.adults ? `${l.adults} adults` : ''}${l.children ? `, ${l.children} kids` : ''}
        </div>
      </td>
      <td>${esc(l.budget || '—')}</td>
      <td onclick="event.stopPropagation()">${sourceBadge(l.source_platform)}</td>
      <td onclick="event.stopPropagation()">
        <select class="status-select" onchange="quickStatus(${l.id}, this.value)">
          ${statusOptions(l.status)}
        </select>
      </td>
      <td onclick="event.stopPropagation()">
        <select class="assign-select" onchange="quickAssign(${l.id}, this.value)">
          <option value="">Unassigned</option>
          ${teamMembers.map(m => `<option value="${m.id}" ${l.assigned_to === m.id ? 'selected' : ''}>${esc(m.name)}</option>`).join('')}
        </select>
      </td>
      <td onclick="event.stopPropagation()">
        <button class="btn-icon" onclick="openLead(${l.id})">View</button>
      </td>
    </tr>
  `).join('');
}

function renderPagination(page, limit, total) {
  const pages = Math.ceil(total / limit) || 1;
  const el = document.getElementById('pagination');
  const showing = Math.min(page * limit, total);
  el.innerHTML = `
    <span class="text-muted">Showing ${(page-1)*limit+1}–${showing} of ${total}</span>
    <button class="page-btn" ${page<=1?'disabled':''} onclick="goPage(${page-1})">← Prev</button>
    ${Array.from({length:Math.min(pages,7)},(_,i)=>{
      const p=i+Math.max(1,page-3);
      if(p>pages) return '';
      return `<button class="page-btn ${p===page?'active':''}" onclick="goPage(${p})">${p}</button>`;
    }).join('')}
    <button class="page-btn" ${page>=pages?'disabled':''} onclick="goPage(${page+1})">Next →</button>
  `;
}
function goPage(p) { currentPage = p; loadLeads(); }

async function quickStatus(id, status) {
  await apiFetch(`/api/leads/${id}/status`, 'PATCH', { status });
  showToast(`Status updated to "${status}"`);
  if (activeView === 'dashboard') loadDashboard();
}

async function quickAssign(id, memberId) {
  if (!memberId) return;
  await apiFetch(`/api/leads/${id}/assign`, 'PATCH', { member_id: parseInt(memberId) });
  showToast('Lead assigned');
}

// ── Lead Detail Modal ─────────────────────────────────────────────────────────
async function openLead(id) {
  const lead = await apiFetch(`/api/leads/${id}`);
  document.getElementById('modalTitle').textContent = lead.client_name || 'Lead Details';

  document.getElementById('modalBody').innerHTML = `
    <!-- Contact Info -->
    <div class="modal-section">
      <div class="modal-section-title">Contact Information</div>
      <div class="info-grid">
        <div class="info-field"><span class="if-label">Full Name</span><span class="if-value">${esc(lead.client_name||'—')}</span></div>
        <div class="info-field"><span class="if-label">Phone</span><span class="if-value">${esc(lead.phone||'—')}</span></div>
        <div class="info-field"><span class="if-label">Email</span><span class="if-value">${esc(lead.email||'—')}</span></div>
        <div class="info-field"><span class="if-label">Source</span><span class="if-value">${sourceBadge(lead.source_platform)}</span></div>
      </div>
    </div>

    <!-- Travel Details -->
    <div class="modal-section">
      <div class="modal-section-title">Travel Details</div>
      <div class="info-grid">
        <div class="info-field"><span class="if-label">Destination</span><span class="if-value">${esc(lead.destination||'—')}</span></div>
        <div class="info-field"><span class="if-label">Trip Duration</span><span class="if-value big">${lead.travel_duration ? lead.travel_duration+' days' : '—'}</span></div>
        <div class="info-field"><span class="if-label">Travel Date</span><span class="if-value">${lead.travel_from_date ? formatDate(lead.travel_from_date) : '—'}</span></div>
        <div class="info-field"><span class="if-label">Return Date</span><span class="if-value">${lead.travel_to_date ? formatDate(lead.travel_to_date) : '—'}</span></div>
        <div class="info-field"><span class="if-label">Adults</span><span class="if-value big">${lead.adults||0}</span></div>
        <div class="info-field"><span class="if-label">Children</span><span class="if-value big">${lead.children||0}</span></div>
        <div class="info-field"><span class="if-label">Budget</span><span class="if-value">${esc(lead.budget||'—')}</span></div>
        <div class="info-field"><span class="if-label">Special Requests</span><span class="if-value">${esc(lead.special_requests||'—')}</span></div>
      </div>
    </div>

    <!-- Ad Details -->
    <div class="modal-section">
      <div class="modal-section-title">Ad Details</div>
      <div class="info-grid">
        <div class="info-field"><span class="if-label">Campaign</span><span class="if-value">${esc(lead.campaign_name||'—')}</span></div>
        <div class="info-field"><span class="if-label">Ad Name</span><span class="if-value">${esc(lead.ad_name||'—')}</span></div>
        <div class="info-field"><span class="if-label">Form</span><span class="if-value">${esc(lead.form_name||'—')}</span></div>
        <div class="info-field"><span class="if-label">Received</span><span class="if-value">${formatDate(lead.created_at)}</span></div>
      </div>
    </div>

    <!-- Actions -->
    <div class="modal-section">
      <div class="modal-section-title">Take Action</div>
      <div class="modal-actions">
        <select id="modalStatus" onchange="updateLeadStatus(${lead.id})">
          <option disabled>── Change Status ──</option>
          ${statusOptions(lead.status)}
        </select>
        <select id="modalAssign" onchange="updateLeadAssign(${lead.id})">
          <option disabled>── Assign to Agent ──</option>
          <option value="">Unassigned</option>
          ${teamMembers.map(m=>`<option value="${m.id}" ${lead.assigned_to===m.id?'selected':''}>${esc(m.name)}</option>`).join('')}
        </select>
      </div>
      <div style="margin-top:8px;font-size:12px;color:var(--slate-500)">
        ${lead.contacted_at ? `Contacted: ${formatDate(lead.contacted_at)}` : ''}
        ${lead.assignee_name ? ` · Assigned to: ${esc(lead.assignee_name)}` : ''}
      </div>
    </div>

    <!-- Notes -->
    <div class="modal-section">
      <div class="modal-section-title">Notes & Activity</div>
      <div class="notes-list" id="notesList">
        ${(lead.notes||[]).map(n=>`
          <div class="note-item">
            <div>${esc(n.note)}</div>
            <div class="note-meta">${n.author_name ? esc(n.author_name)+' · ' : ''}${formatDate(n.created_at)}</div>
          </div>
        `).join('') || '<p class="text-muted" style="font-size:12.5px">No notes yet.</p>'}
      </div>
      <div class="note-form" style="margin-top:12px">
        <textarea id="noteText" placeholder="Add a note, call outcome, follow-up detail…"></textarea>
        <button class="btn-primary" style="white-space:nowrap;align-self:flex-end" onclick="addNote(${lead.id})">Add Note</button>
      </div>
    </div>
  `;

  document.getElementById('leadModal').classList.remove('hidden');
}

function closeModal() { document.getElementById('leadModal').classList.add('hidden'); }
document.getElementById('leadModal').addEventListener('click', e => {
  if (e.target.id === 'leadModal') closeModal();
});

async function updateLeadStatus(id) {
  const status = document.getElementById('modalStatus').value;
  await apiFetch(`/api/leads/${id}/status`, 'PATCH', { status });
  showToast(`Status set to "${status}"`);
  if (activeView === 'leads') loadLeads();
  if (activeView === 'dashboard') loadDashboard();
}

async function updateLeadAssign(id) {
  const memberId = document.getElementById('modalAssign').value;
  if (!memberId) return;
  await apiFetch(`/api/leads/${id}/assign`, 'PATCH', { member_id: parseInt(memberId) });
  showToast('Lead assigned');
}

async function addNote(id) {
  const note = document.getElementById('noteText').value.trim();
  if (!note) return;
  await apiFetch(`/api/leads/${id}/notes`, 'POST', { note });
  document.getElementById('noteText').value = '';
  // Refresh note list
  const lead = await apiFetch(`/api/leads/${id}`);
  document.getElementById('notesList').innerHTML = (lead.notes||[]).map(n=>`
    <div class="note-item">
      <div>${esc(n.note)}</div>
      <div class="note-meta">${n.author_name ? esc(n.author_name)+' · ' : ''}${formatDate(n.created_at)}</div>
    </div>
  `).join('') || '<p class="text-muted">No notes yet.</p>';
  showToast('Note added');
}

// ── Ad Accounts ───────────────────────────────────────────────────────────────
async function loadAccounts() {
  const accounts = await apiFetch('/api/accounts');
  const el = document.getElementById('accountsList');
  if (!accounts.length) {
    el.innerHTML = '<p class="text-muted" style="padding:20px">No accounts connected yet.</p>';
    return;
  }
  el.innerHTML = accounts.map(a => `
    <div class="account-item">
      <div class="account-platform ${a.platform === 'meta' ? 'platform-meta' : 'platform-google'}">
        ${a.platform === 'meta' ? 'f' : 'G'}
      </div>
      <div class="account-info">
        <div class="account-label">${esc(a.label)}</div>
        <div class="account-id">${a.platform === 'meta' ? 'Meta' : 'Google Ads'} · ${esc(a.account_id)}</div>
      </div>
      ${statusBadge(a.active ? 'converted' : 'lost')}
      <button class="btn-danger" onclick="deleteAccount(${a.id})" title="Disconnect">✕</button>
    </div>
  `).join('');
}

function switchTab(platform) {
  document.querySelectorAll('.tab-btn').forEach((b,i) => b.classList.toggle('active', (i===0&&platform==='meta')||(i===1&&platform==='google')));
  document.getElementById('metaForm').classList.toggle('hidden',   platform !== 'meta');
  document.getElementById('googleForm').classList.toggle('hidden', platform !== 'google');
}

async function addAccount(e, platform) {
  e.preventDefault();
  const form = e.target;
  const data  = Object.fromEntries(new FormData(form));
  const extra = {};
  if (platform === 'google') {
    extra.refresh_token   = data.refresh_token;
    extra.developer_token = data.developer_token;
    extra.client_id       = data.client_id;
    extra.client_secret   = data.client_secret;
    delete data.refresh_token; delete data.developer_token;
    delete data.client_id;     delete data.client_secret;
  }
  await apiFetch('/api/accounts', 'POST', { ...data, platform, extra_config: extra });
  showToast(`${platform === 'meta' ? 'Meta' : 'Google Ads'} account connected!`);
  form.reset();
  loadAccounts();
}

async function deleteAccount(id) {
  if (!confirm('Disconnect this account?')) return;
  await apiFetch(`/api/accounts/${id}`, 'DELETE');
  showToast('Account disconnected');
  loadAccounts();
}

// ── Team ──────────────────────────────────────────────────────────────────────
async function loadTeam() {
  teamMembers = await apiFetch('/api/team');
  renderTeam();
  populateAssigneeFilter();
}

function renderTeam() {
  const el = document.getElementById('teamList');
  if (!el) return;
  if (!teamMembers.length) {
    el.innerHTML = '<p class="text-muted" style="padding:20px">No team members yet. Add your first agent.</p>';
    return;
  }
  el.innerHTML = teamMembers.map(m => `
    <div class="team-item">
      <div class="team-avatar">${initials(m.name)}</div>
      <div class="team-info">
        <div class="team-name">${esc(m.name)}</div>
        <div class="team-email">${esc(m.email)}</div>
      </div>
      <span class="team-role">${esc(m.role)}</span>
      <button class="btn-danger" onclick="removeMember(${m.id})">✕</button>
    </div>
  `).join('');
}

function populateAssigneeFilter() {
  const sel = document.getElementById('filterAssignee');
  sel.innerHTML = '<option value="">All Agents</option>' +
    teamMembers.map(m => `<option value="${m.id}">${esc(m.name)}</option>`).join('');
}

async function addTeamMember(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  await apiFetch('/api/team', 'POST', data);
  showToast('Team member added');
  e.target.reset();
  await loadTeam();
}

async function removeMember(id) {
  if (!confirm('Remove this team member?')) return;
  await apiFetch(`/api/team/${id}`, 'DELETE');
  showToast('Removed');
  await loadTeam();
}

// ── Manual Sync ───────────────────────────────────────────────────────────────
async function triggerSync() {
  const btn = document.getElementById('syncBtn');
  btn.classList.add('syncing');
  btn.textContent = 'Syncing…';
  try {
    const result = await apiFetch('/api/sync', 'POST');
    const total = (result.meta?.synced||0) + (result.google?.synced||0);
    showToast(`Sync complete – ${total} new lead${total!==1?'s':''} imported`);
    document.getElementById('lastSync').textContent = 'Last sync: just now';
    if (activeView === 'dashboard') loadDashboard();
    if (activeView === 'leads')    loadLeads();
  } catch (err) {
    showToast('Sync failed: ' + err.message, 'error');
  } finally {
    btn.classList.remove('syncing');
    btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg> Sync Leads Now`;
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
async function apiFetch(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (body) opts.body = JSON.stringify(body);
  const resp = await fetch(API + url, opts);
  if (!resp.ok) {
    const err = await resp.json().catch(() => ({ error: resp.statusText }));
    throw new Error(err.error || 'Request failed');
  }
  return resp.json();
}

function statusBadge(status) {
  const map = {
    new:       ['badge-new',       'New'],
    contacted: ['badge-contacted', 'Contacted'],
    qualified: ['badge-qualified', 'Qualified'],
    converted: ['badge-converted', 'Converted'],
    lost:      ['badge-lost',      'Lost'],
  };
  const [cls, label] = map[status] || ['badge-new', status || 'New'];
  return `<span class="badge ${cls}">${label}</span>`;
}

function sourceBadge(platform) {
  if (platform === 'meta')   return '<span class="badge badge-meta">Meta</span>';
  if (platform === 'google') return '<span class="badge badge-google">Google</span>';
  return '<span class="badge">—</span>';
}

function statusOptions(current) {
  return [
    ['new',       'New'],
    ['contacted', 'Contacted'],
    ['qualified', 'Qualified'],
    ['converted', 'Converted'],
    ['lost',      'Lost'],
  ].map(([v, l]) => `<option value="${v}" ${current===v?'selected':''}>${l}</option>`).join('');
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').slice(0,2).map(w=>w[0]).join('').toUpperCase();
}

function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
}

let toastTimer;
function showToast(msg, type = '') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast' + (type ? ` ${type}` : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3500);
}

<?php
// mobile/index.php — SpendWise Mobile PWA shell
// No auth check here — handled client-side via API /me call
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SpendWise">
<meta name="theme-color" content="#0b0f0e">
<title>SpendWise</title>
<link rel="manifest" href="manifest.json">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
:root {
  --bg:      #0b0f0e;
  --surface: #131918;
  --card:    #162020;
  --border:  #1f2b28;
  --accent:  #00e5a0;
  --danger:  #ff4d6d;
  --warn:    #ffb84d;
  --text:    #e8f0ed;
  --muted:   #6b8c80;
  --nav-h:   64px;
  --safe-b:  env(safe-area-inset-bottom, 0px);
  --safe-t:  env(safe-area-inset-top, 0px);
}
html { height: 100%; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  height: 100%;
  overflow: hidden;
}

/* ── App shell ── */
#app { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }

/* ── Screens ── */
.screen { display: none; flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
.screen.active { display: flex; flex-direction: column; }
.screen-body { padding: 16px 16px calc(var(--nav-h) + var(--safe-b) + 16px); flex: 1; }

/* ── Top bar ── */
.topbar {
  padding: calc(var(--safe-t) + 12px) 16px 12px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.topbar-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 800; }
.topbar-title span { color: var(--accent); }
.topbar-right { display: flex; gap: 8px; align-items: center; }

/* ── Bottom nav ── */
.bottom-nav {
  position: fixed; bottom: 0; left: 0; right: 0;
  height: calc(var(--nav-h) + var(--safe-b));
  padding-bottom: var(--safe-b);
  background: var(--surface);
  border-top: 1px solid var(--border);
  display: flex; z-index: 100;
}
.nav-item {
  flex: 1; display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 3px;
  background: none; border: none; color: var(--muted);
  font-family: 'DM Sans', sans-serif; font-size: .6rem; font-weight: 500;
  cursor: pointer; transition: color .15s; padding-top: 6px;
  text-transform: uppercase; letter-spacing: .05em;
}
.nav-item svg { width: 22px; height: 22px; flex-shrink: 0; }
.nav-item.active { color: var(--accent); }
.nav-item.add-btn {
  color: var(--bg);
  background: var(--accent);
  border-radius: 16px;
  margin: 8px 6px;
  flex: 0 0 52px;
  height: 52px;
  padding: 0;
  align-self: center;
}

/* ── Auth screen ── */
.auth-wrap { display: flex; flex-direction: column; justify-content: center; min-height: 100vh; padding: 24px 20px; }
.auth-logo { text-align: center; margin-bottom: 32px; }
.auth-logo-icon { width: 56px; height: 56px; background: var(--accent); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 10px; }
.auth-logo-text { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; }
.auth-logo-text span { color: var(--accent); }
.auth-tabs { display: flex; background: var(--card); border-radius: 12px; padding: 4px; margin-bottom: 24px; }
.auth-tab { flex: 1; padding: 10px; border: none; border-radius: 9px; background: none; color: var(--muted); font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 500; cursor: pointer; transition: all .2s; }
.auth-tab.active { background: var(--accent); color: #0b0f0e; font-weight: 700; }
.auth-form { display: none; flex-direction: column; gap: 14px; }
.auth-form.active { display: flex; }
.field-label { font-size: .72rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 6px; }
.field-input {
  width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  padding: 14px 16px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 1rem;
  outline: none; transition: border-color .2s; -webkit-appearance: none;
}
.field-input:focus { border-color: var(--accent); }
.auth-error { background: rgba(255,77,109,.12); border: 1px solid rgba(255,77,109,.3); color: var(--danger); border-radius: 10px; padding: 11px 14px; font-size: .85rem; display: none; }
.auth-error.show { display: block; }

/* ── Buttons ── */
.btn-full { width: 100%; padding: 15px; background: var(--accent); color: #0b0f0e; border: none; border-radius: 12px; font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; cursor: pointer; letter-spacing: .01em; transition: opacity .2s; }
.btn-full:active { opacity: .85; }
.btn-full.loading { opacity: .6; pointer-events: none; }

/* ── Stat cards ── */
.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px; }
.stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; }
.stat-card.full { grid-column: 1 / -1; border-color: rgba(0,229,160,.2); background: rgba(0,229,160,.04); }
.stat-lbl { font-size: .68rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 5px; }
.stat-val { font-family: 'Syne', sans-serif; font-size: 1.45rem; font-weight: 700; letter-spacing: -.03em; }
.stat-val.inc { color: var(--accent); }
.stat-val.exp { color: var(--danger); }

/* ── Section header ── */
.sec-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.sec-title { font-family: 'Syne', sans-serif; font-size: .82rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; }
.sec-link { font-size: .78rem; color: var(--accent); }

/* ── Transaction rows ── */
.tx-list { display: flex; flex-direction: column; gap: 8px; }
.tx-row {
  display: flex; align-items: center; gap: 12px;
  background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  padding: 12px 14px;
}
.tx-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
.tx-icon.income  { background: rgba(0,229,160,.1); }
.tx-icon.expense { background: rgba(255,77,109,.1); }
.tx-info { flex: 1; min-width: 0; }
.tx-desc { font-size: .875rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tx-meta { font-size: .72rem; color: var(--muted); margin-top: 2px; }
.tx-amount { font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 700; white-space: nowrap; }
.tx-amount.income  { color: var(--accent); }
.tx-amount.expense { color: var(--danger); }

/* ── Category mini bars ── */
.cat-bars { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
.cat-bar-row { display: flex; align-items: center; gap: 10px; }
.cat-bar-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.cat-bar-name { font-size: .82rem; flex: 1; }
.cat-bar-bg { width: 90px; height: 5px; background: var(--border); border-radius: 4px; }
.cat-bar-fill { height: 5px; border-radius: 4px; }
.cat-bar-val { font-size: .78rem; color: var(--muted); min-width: 60px; text-align: right; }

/* ── Add transaction sheet ── */
.sheet-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 200; align-items: flex-end; }
.sheet-overlay.open { display: flex; }
.sheet {
  background: var(--surface); border-radius: 22px 22px 0 0; border-top: 1px solid var(--border);
  padding: 0 20px calc(var(--safe-b) + 20px); width: 100%; max-height: 90vh; overflow-y: auto;
  animation: slideUp .3s ease;
}
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.sheet-handle { width: 36px; height: 4px; background: var(--border); border-radius: 4px; margin: 12px auto 18px; }
.sheet-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 800; margin-bottom: 20px; }
.type-toggle { display: flex; gap: 8px; margin-bottom: 20px; }
.type-btn { flex: 1; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-family: 'Syne', sans-serif; font-size: .9rem; font-weight: 700; cursor: pointer; transition: all .2s; }
.type-btn.active.expense { background: rgba(255,77,109,.12); border-color: rgba(255,77,109,.4); color: var(--danger); }
.type-btn.active.income  { background: rgba(0,229,160,.12);  border-color: rgba(0,229,160,.4);  color: var(--accent); }
.amount-display { font-family: 'Syne', sans-serif; font-size: 2.8rem; font-weight: 800; text-align: center; letter-spacing: -.04em; margin-bottom: 20px; color: var(--text); }
.amount-display span { color: var(--muted); font-size: 1.8rem; }
.numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
.num-btn { padding: 17px; background: var(--card); border: none; border-radius: 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 1.2rem; font-weight: 500; cursor: pointer; transition: background .1s; }
.num-btn:active { background: var(--border); }
.num-btn.del { font-size: 1rem; }
.sheet-field { margin-bottom: 14px; }
.sheet-field select { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 13px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: .95rem; outline: none; -webkit-appearance: none; }
.sheet-field input[type=text], .sheet-field input[type=date] { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 13px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: .95rem; outline: none; -webkit-appearance: none; }

/* ── Goals screen ── */
.goal-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 16px; margin-bottom: 10px; }
.goal-card.warn { border-color: rgba(255,184,77,.3); }
.goal-card.over { border-color: rgba(255,77,109,.35); }
.goal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.goal-name { font-weight: 600; font-size: .9rem; }
.goal-cat  { font-size: .72rem; color: var(--muted); margin-top: 2px; }
.goal-pct  { font-family: 'Syne', sans-serif; font-size: .9rem; font-weight: 700; }
.goal-pct.ok   { color: var(--accent); }
.goal-pct.warn { color: var(--warn); }
.goal-pct.over { color: var(--danger); }
.goal-bar-bg   { width: 100%; height: 6px; background: var(--border); border-radius: 6px; margin-bottom: 8px; }
.goal-bar-fill { height: 6px; border-radius: 6px; }
.goal-bar-fill.ok   { background: var(--accent); }
.goal-bar-fill.warn { background: var(--warn); }
.goal-bar-fill.over { background: var(--danger); }
.goal-footer { display: flex; justify-content: space-between; font-size: .75rem; color: var(--muted); }

/* ── Toast ── */
.toast { position: fixed; top: calc(var(--safe-t) + 16px); left: 50%; transform: translateX(-50%); background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 10px 18px; font-size: .85rem; font-weight: 500; z-index: 999; white-space: nowrap; opacity: 0; transition: opacity .3s; pointer-events: none; }
.toast.show { opacity: 1; }
.toast.success { border-color: rgba(0,229,160,.4); color: var(--accent); }
.toast.error   { border-color: rgba(255,77,109,.4); color: var(--danger); }

/* ── Transactions screen ── */
.tx-filter-bar { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 14px; scrollbar-width: none; }
.tx-filter-bar::-webkit-scrollbar { display: none; }
.filter-chip { padding: 7px 14px; border-radius: 20px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-size: .8rem; white-space: nowrap; cursor: pointer; flex-shrink: 0; transition: all .15s; }
.filter-chip.active { background: rgba(0,229,160,.12); border-color: rgba(0,229,160,.4); color: var(--accent); }

/* ── Pull-to-refresh indicator ── */
.ptr-indicator { text-align: center; font-size: .78rem; color: var(--muted); padding: 8px 0; display: none; }

/* ── Empty ── */
.empty { text-align: center; padding: 40px 20px; color: var(--muted); font-size: .875rem; }

/* ── Profile screen ── */
.profile-avatar { width: 72px; height: 72px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: #0b0f0e; margin: 0 auto 12px; }
.profile-name { text-align: center; font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }
.profile-email { text-align: center; font-size: .85rem; color: var(--muted); margin-bottom: 24px; }
.menu-item { display: flex; align-items: center; gap: 14px; padding: 15px 16px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 10px; font-size: .9rem; cursor: pointer; transition: background .15s; }
.menu-item:active { background: var(--border); }
.menu-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* ── Spinner ── */
.spinner { display: flex; justify-content: center; padding: 32px 0; }
.spinner::after { content: ''; width: 28px; height: 28px; border: 2.5px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div id="app">

  <!-- ═══════════════════════════════════════════════════════
       AUTH SCREEN
  ═══════════════════════════════════════════════════════ -->
  <div id="screen-auth" class="screen" style="overflow-y:auto">
    <div class="auth-wrap">
      <div class="auth-logo">
        <div class="auth-logo-icon">💰</div>
        <div class="auth-logo-text">Spend<span>Wise</span></div>
      </div>

      <div class="auth-tabs">
        <button class="auth-tab active" onclick="switchAuthTab('login')">Sign In</button>
        <button class="auth-tab" onclick="switchAuthTab('register')">Register</button>
      </div>

      <div id="auth-error" class="auth-error"></div>

      <!-- Login form -->
      <div id="form-login" class="auth-form active">
        <div>
          <div class="field-label">Email</div>
          <input id="login-email" class="field-input" type="email" placeholder="you@example.com" autocomplete="email">
        </div>
        <div>
          <div class="field-label">Password</div>
          <input id="login-pass" class="field-input" type="password" placeholder="••••••••" autocomplete="current-password">
        </div>
        <button class="btn-full" id="login-btn" onclick="doLogin()">Sign In</button>
      </div>

      <!-- Register form -->
      <div id="form-register" class="auth-form">
        <div>
          <div class="field-label">Full Name</div>
          <input id="reg-name" class="field-input" type="text" placeholder="Juan dela Cruz" autocomplete="name">
        </div>
        <div>
          <div class="field-label">Email</div>
          <input id="reg-email" class="field-input" type="email" placeholder="juan@email.com" autocomplete="email">
        </div>
        <div>
          <div class="field-label">Password</div>
          <input id="reg-pass" class="field-input" type="password" placeholder="Min. 6 characters" autocomplete="new-password">
        </div>
        <button class="btn-full" id="reg-btn" onclick="doRegister()">Create Account</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════
       MAIN APP (all screens)
  ═══════════════════════════════════════════════════════ -->
  <div id="app-main" style="display:none;flex:1;flex-direction:column;overflow:hidden" class="screen active">

    <!-- ── Dashboard ── -->
    <div id="screen-home" class="screen active">
      <div class="topbar">
        <div>
          <div class="topbar-title">Spend<span>Wise</span></div>
        </div>
        <div class="topbar-right">
          <button onclick="loadDashboard()" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          </button>
        </div>
      </div>
      <div class="screen-body" id="home-body">
        <div class="spinner"></div>
      </div>
    </div>

    <!-- ── Transactions ── -->
    <div id="screen-txs" class="screen">
      <div class="topbar">
        <div class="topbar-title">Transactions</div>
      </div>
      <div class="screen-body">
        <div class="tx-filter-bar" id="tx-filter-bar">
          <button class="filter-chip active" data-filter="" onclick="setTxFilter(this,'')">All</button>
          <button class="filter-chip" data-filter="expense" onclick="setTxFilter(this,'expense')">Expenses</button>
          <button class="filter-chip" data-filter="income"  onclick="setTxFilter(this,'income')">Income</button>
        </div>
        <div id="txs-body"><div class="spinner"></div></div>
      </div>
    </div>

    <!-- ── Goals ── -->
    <div id="screen-goals" class="screen">
      <div class="topbar">
        <div class="topbar-title">Budget Goals</div>
      </div>
      <div class="screen-body" id="goals-body">
        <div class="spinner"></div>
      </div>
    </div>

    <!-- ── Profile ── -->
    <div id="screen-profile" class="screen">
      <div class="topbar">
        <div class="topbar-title">Profile</div>
      </div>
      <div class="screen-body" id="profile-body">
        <div class="spinner"></div>
      </div>
    </div>

    <!-- ── Bottom nav ── -->
    <nav class="bottom-nav">
      <button class="nav-item active" id="nav-home" onclick="showScreen('home')">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Home
      </button>
      <button class="nav-item" id="nav-txs" onclick="showScreen('txs')">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        Transactions
      </button>
      <button class="nav-item add-btn" onclick="openSheet()" aria-label="Add transaction">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <button class="nav-item" id="nav-goals" onclick="showScreen('goals')">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Goals
      </button>
      <button class="nav-item" id="nav-profile" onclick="showScreen('profile')">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </button>
    </nav>

  </div><!-- #app-main -->

</div><!-- #app -->

<!-- ═══════════════════════════════════════════════════════
     ADD TRANSACTION SHEET
═══════════════════════════════════════════════════════ -->
<div class="sheet-overlay" id="sheet-overlay" onclick="closeSheetOnBg(event)">
  <div class="sheet" id="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Add Transaction</div>

    <div class="type-toggle">
      <button class="type-btn expense active" onclick="setType('expense')">↓ Expense</button>
      <button class="type-btn income"          onclick="setType('income')">↑ Income</button>
    </div>

    <div class="amount-display"><span>₱</span><span id="amount-display">0</span></div>

    <div class="numpad">
      <?php foreach (['7','8','9','4','5','6','1','2','3','.','0','⌫'] as $k): ?>
        <button class="num-btn<?= $k==='⌫'?' del':'' ?>" onclick="numpad('<?= $k ?>')"><?= $k ?></button>
      <?php endforeach; ?>
    </div>

    <div class="sheet-field">
      <select id="sheet-category"><option value="">— Category —</option></select>
    </div>
    <div class="sheet-field">
      <input type="text" id="sheet-desc" placeholder="Description (optional)">
    </div>
    <div class="sheet-field">
      <input type="date" id="sheet-date">
    </div>

    <button class="btn-full" id="sheet-save-btn" onclick="saveTransaction()" style="margin-bottom:8px">Save Transaction</button>
    <button class="btn-full" onclick="closeSheet()" style="background:var(--card);color:var(--muted);margin-bottom:8px">Cancel</button>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const API = 'api/index.php';
let currentUser = null;
let txFilter    = '';
let amountStr   = '0';
let txType      = 'expense';
let categories  = [];

// ── API helper ───────────────────────────────────────────────
async function api(data) {
  const r = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
    credentials: 'same-origin',
  });
  return r.json();
}

// ── Toast ────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast show ' + type;
  setTimeout(() => t.classList.remove('show'), 2800);
}

// ── Auth tabs ────────────────────────────────────────────────
function switchAuthTab(tab) {
  document.querySelectorAll('.auth-tab').forEach((el,i) => el.classList.toggle('active', (i===0) === (tab==='login')));
  document.getElementById('form-login').classList.toggle('active', tab === 'login');
  document.getElementById('form-register').classList.toggle('active', tab === 'register');
  document.getElementById('auth-error').classList.remove('show');
}

function authError(msg) {
  const el = document.getElementById('auth-error');
  el.textContent = msg; el.classList.add('show');
}

async function doLogin() {
  const btn = document.getElementById('login-btn');
  btn.textContent = 'Signing in…'; btn.classList.add('loading');
  const res = await api({ action:'login', email: document.getElementById('login-email').value, password: document.getElementById('login-pass').value });
  btn.textContent = 'Sign In'; btn.classList.remove('loading');
  if (res.error) { authError(res.error); return; }
  currentUser = res.user;
  enterApp();
}

async function doRegister() {
  const btn = document.getElementById('reg-btn');
  btn.textContent = 'Creating…'; btn.classList.add('loading');
  const res = await api({ action:'register', username: document.getElementById('reg-name').value, email: document.getElementById('reg-email').value, password: document.getElementById('reg-pass').value });
  btn.textContent = 'Create Account'; btn.classList.remove('loading');
  if (res.error) { authError(res.error); return; }
  currentUser = res.user;
  enterApp();
}

// ── App entry / exit ─────────────────────────────────────────
function enterApp() {
  document.getElementById('screen-auth').style.display = 'none';
  document.getElementById('app-main').style.display    = 'flex';
  loadDashboard();
  loadCategories();
}

function exitApp() {
  api({ action:'logout' });
  currentUser = null;
  document.getElementById('app-main').style.display   = 'none';
  document.getElementById('screen-auth').style.display = 'flex';
}

// ── Screen nav ───────────────────────────────────────────────
function showScreen(name) {
  document.querySelectorAll('#app-main .screen').forEach(s => s.classList.remove('active'));
  document.getElementById('screen-' + name).classList.add('active');
  document.querySelectorAll('.nav-item[id^=nav]').forEach(b => b.classList.remove('active'));
  const navBtn = document.getElementById('nav-' + name);
  if (navBtn) navBtn.classList.add('active');

  if (name === 'txs')     loadTransactions();
  if (name === 'goals')   loadGoals();
  if (name === 'profile') renderProfile();
}

// ── Dashboard ────────────────────────────────────────────────
async function loadDashboard() {
  document.getElementById('home-body').innerHTML = '<div class="spinner"></div>';
  const d = await api({ action:'dashboard' });
  if (d.error) { toast(d.error,'error'); return; }

  const palette = ['#00e5a0','#ff4d6d','#ffb84d','#7b61ff','#00b4d8'];

  let catBars = '';
  const maxCat = d.categories.length ? Math.max(...d.categories.map(c=>(float=parseFloat(c.total)))) : 1;
  d.categories.forEach((c,i) => {
    const pct = maxCat > 0 ? Math.round((c.total/maxCat)*100) : 0;
    catBars += `<div class="cat-bar-row">
      <div class="cat-bar-dot" style="background:${palette[i%palette.length]}"></div>
      <div class="cat-bar-name">${esc(c.name??'Other')}</div>
      <div class="cat-bar-bg"><div class="cat-bar-fill" style="width:${pct}%;background:${palette[i%palette.length]}"></div></div>
      <div class="cat-bar-val">₱${fmt(c.total)}</div>
    </div>`;
  });

  let txRows = '';
  if (d.recent.length === 0) {
    txRows = '<div class="empty">No transactions yet.</div>';
  } else {
    d.recent.forEach(tx => {
      const icon = tx.type==='income' ? '↑' : '↓';
      txRows += `<div class="tx-row">
        <div class="tx-icon ${tx.type}">${icon}</div>
        <div class="tx-info">
          <div class="tx-desc">${esc(tx.description||tx.category||'Transaction')}</div>
          <div class="tx-meta">${esc(tx.category??'Uncategorized')} · ${fmtDate(tx.transaction_date)}</div>
        </div>
        <div class="tx-amount ${tx.type}">${tx.type==='income'?'+':'−'}₱${fmt(tx.amount)}</div>
      </div>`;
    });
  }

  let goalsHtml = '';
  d.goals.forEach(g => {
    const pct = g.amount>0 ? Math.min(Math.round((g.spent/g.amount)*100),100) : 0;
    const st  = g.spent>=g.amount ? 'over' : g.spent/g.amount>=0.8 ? 'warn' : 'ok';
    const col = {ok:'var(--accent)',warn:'var(--warn)',over:'var(--danger)'}[st];
    goalsHtml += `<div class="goal-card ${st}" style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div class="goal-name">${esc(g.name)}</div>
        <span class="goal-pct ${st}">${pct}%</span>
      </div>
      <div class="goal-bar-bg"><div class="goal-bar-fill ${st}" style="width:${pct}%"></div></div>
      <div class="goal-footer"><span>₱${fmt(g.spent)} of ₱${fmt(g.amount)}</span><span>${esc(g.category_name??'Overall')}</span></div>
    </div>`;
  });

  document.getElementById('home-body').innerHTML = `
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-lbl">Income</div><div class="stat-val inc">₱${fmt(d.income)}</div></div>
      <div class="stat-card"><div class="stat-lbl">Expenses</div><div class="stat-val exp">₱${fmt(d.expenses)}</div></div>
      <div class="stat-card full"><div class="stat-lbl">Total Balance</div><div class="stat-val ${d.balance>=0?'inc':'exp'}">₱${fmt(Math.abs(d.balance))}</div></div>
    </div>

    ${d.categories.length ? `
    <div class="sec-header"><span class="sec-title">Spending this month</span></div>
    <div class="cat-bars">${catBars}</div>` : ''}

    ${d.goals.length ? `
    <div class="sec-header" style="margin-bottom:10px"><span class="sec-title">Budget goals</span><a class="sec-link" onclick="showScreen('goals')" style="cursor:pointer">See all</a></div>
    ${goalsHtml}` : ''}

    <div class="sec-header"><span class="sec-title">Recent</span><a class="sec-link" onclick="showScreen('txs')" style="cursor:pointer">All →</a></div>
    <div class="tx-list">${txRows}</div>
  `;
}

// ── Transactions ─────────────────────────────────────────────
async function loadTransactions() {
  document.getElementById('txs-body').innerHTML = '<div class="spinner"></div>';
  const d = await api({ action:'transactions', type: txFilter, limit: 40 });
  if (d.error) { toast(d.error,'error'); return; }

  if (!d.transactions.length) {
    document.getElementById('txs-body').innerHTML = '<div class="empty">No transactions found.</div>';
    return;
  }

  let html = '<div class="tx-list">';
  d.transactions.forEach(tx => {
    html += `<div class="tx-row">
      <div class="tx-icon ${tx.type}">${tx.type==='income'?'↑':'↓'}</div>
      <div class="tx-info">
        <div class="tx-desc">${esc(tx.description||tx.category||'Transaction')}</div>
        <div class="tx-meta">${esc(tx.category??'Uncategorized')} · ${fmtDate(tx.transaction_date)}</div>
      </div>
      <div>
        <div class="tx-amount ${tx.type}">${tx.type==='income'?'+':'−'}₱${fmt(tx.amount)}</div>
        <button onclick="deleteTx(${tx.id})" style="display:block;margin-top:4px;font-size:.68rem;color:var(--muted);background:none;border:none;cursor:pointer;text-align:right">Delete</button>
      </div>
    </div>`;
  });
  html += '</div>';
  document.getElementById('txs-body').innerHTML = html;
}

function setTxFilter(btn, filter) {
  txFilter = filter;
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
  loadTransactions();
}

async function deleteTx(id) {
  if (!confirm('Delete this transaction?')) return;
  const r = await api({ action:'delete_transaction', id });
  if (r.success) { toast('Deleted'); loadTransactions(); loadDashboard(); }
  else toast(r.error||'Error','error');
}

// ── Goals ────────────────────────────────────────────────────
async function loadGoals() {
  document.getElementById('goals-body').innerHTML = '<div class="spinner"></div>';
  const d = await api({ action:'goals' });
  if (d.error) { toast(d.error,'error'); return; }

  if (!d.goals.length) {
    document.getElementById('goals-body').innerHTML = `
      <div class="empty">No budget goals yet.<br>
        <a href="../budget_goals.php" style="color:var(--accent)">Set goals on desktop →</a>
      </div>`;
    return;
  }

  let monthly = '', yearly = '';
  d.goals.forEach(g => {
    const pct = g.amount>0 ? Math.min(Math.round((g.spent/g.amount)*100),100) : 0;
    const st  = g.spent>=g.amount ? 'over' : g.spent/g.amount>=0.8 ? 'warn' : 'ok';
    const rem = g.amount - g.spent;
    const card = `<div class="goal-card ${st}">
      <div class="goal-top">
        <div><div class="goal-name">${esc(g.name)}</div><div class="goal-cat">${esc(g.category_name??'Overall spending')}</div></div>
        <span class="goal-pct ${st}">${pct}%</span>
      </div>
      <div class="goal-bar-bg"><div class="goal-bar-fill ${st}" style="width:${pct}%"></div></div>
      <div class="goal-footer">
        <span>₱${fmt(g.spent)} spent</span>
        <span>${rem>=0?'₱'+fmt(rem)+' left':'₱'+fmt(Math.abs(rem))+' over'}</span>
      </div>
    </div>`;
    if (g.period==='monthly') monthly += card;
    else yearly += card;
  });

  document.getElementById('goals-body').innerHTML = `
    ${monthly ? `<div class="sec-header" style="margin-bottom:10px"><span class="sec-title">Monthly</span></div>${monthly}` : ''}
    ${yearly  ? `<div class="sec-header" style="margin:14px 0 10px"><span class="sec-title">Yearly</span></div>${yearly}` : ''}
    <p style="text-align:center;margin-top:16px;font-size:.78rem;color:var(--muted)">
      Manage goals on the <a href="../budget_goals.php" style="color:var(--accent)">desktop version</a>
    </p>
  `;
}

// ── Profile ──────────────────────────────────────────────────
function renderProfile() {
  if (!currentUser) return;
  const init = currentUser.username.charAt(0).toUpperCase();
  document.getElementById('profile-body').innerHTML = `
    <div style="padding-top:12px">
      <div class="profile-avatar">${init}</div>
      <div class="profile-name">${esc(currentUser.username)}</div>
      <div style="text-align:center;font-size:.85rem;color:var(--muted);margin-bottom:24px">SpendWise Account</div>

      <a href="../dashboard.php" class="menu-item" style="text-decoration:none;color:var(--text)">
        <div class="menu-icon" style="background:rgba(0,229,160,.1)">🖥️</div>
        <div><div style="font-weight:500">Desktop Version</div><div style="font-size:.75rem;color:var(--muted);margin-top:2px">Full dashboard & reports</div></div>
      </a>
      <a href="../export.php" class="menu-item" style="text-decoration:none;color:var(--text)">
        <div class="menu-icon" style="background:rgba(0,229,160,.1)">📊</div>
        <div><div style="font-weight:500">Export Data</div><div style="font-size:.75rem;color:var(--muted);margin-top:2px">Download PDF or Excel</div></div>
      </a>
      <a href="../budget_goals.php" class="menu-item" style="text-decoration:none;color:var(--text)">
        <div class="menu-icon" style="background:rgba(255,184,77,.1)">🎯</div>
        <div><div style="font-weight:500">Budget Goals</div><div style="font-size:.75rem;color:var(--muted);margin-top:2px">Set & manage budgets</div></div>
      </a>
      <div class="menu-item" onclick="exitApp()" style="cursor:pointer">
        <div class="menu-icon" style="background:rgba(255,77,109,.1)">🚪</div>
        <div style="color:var(--danger);font-weight:500">Sign Out</div>
      </div>
    </div>
  `;
}

// ── Add transaction sheet ────────────────────────────────────
async function loadCategories() {
  const d = await api({ action:'categories' });
  if (d.categories) {
    categories = d.categories;
    const sel = document.getElementById('sheet-category');
    sel.innerHTML = '<option value="">— Category —</option>';
    d.categories.forEach(c => {
      sel.innerHTML += `<option value="${c.id}">${esc(c.name)}</option>`;
    });
  }
}

function openSheet() {
  amountStr = '0';
  document.getElementById('amount-display').textContent = '0';
  document.getElementById('sheet-desc').value  = '';
  document.getElementById('sheet-date').value  = new Date().toISOString().split('T')[0];
  document.getElementById('sheet-category').value = '';
  setType('expense');
  document.getElementById('sheet-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeSheet() {
  document.getElementById('sheet-overlay').classList.remove('open');
  document.body.style.overflow = '';
}

function closeSheetOnBg(e) {
  if (e.target === document.getElementById('sheet-overlay')) closeSheet();
}

function setType(t) {
  txType = t;
  document.querySelectorAll('.type-btn').forEach(b => {
    b.classList.toggle('active', b.classList.contains(t));
  });
}

function numpad(key) {
  if (key === '⌫') {
    amountStr = amountStr.length > 1 ? amountStr.slice(0,-1) : '0';
  } else if (key === '.') {
    if (!amountStr.includes('.')) amountStr += '.';
  } else {
    if (amountStr === '0') amountStr = key;
    else amountStr += key;
    // Limit to 2 decimal places
    const parts = amountStr.split('.');
    if (parts[1] && parts[1].length > 2) amountStr = parts[0]+'.'+parts[1].slice(0,2);
  }
  document.getElementById('amount-display').textContent = amountStr;
}

async function saveTransaction() {
  const amount = parseFloat(amountStr);
  if (!amount || amount <= 0) { toast('Enter an amount first','error'); return; }

  const btn = document.getElementById('sheet-save-btn');
  btn.textContent = 'Saving…'; btn.classList.add('loading');

  const res = await api({
    action:      'add_transaction',
    type:        txType,
    amount:      amount,
    category_id: document.getElementById('sheet-category').value,
    description: document.getElementById('sheet-desc').value,
    date:        document.getElementById('sheet-date').value,
  });

  btn.textContent = 'Save Transaction'; btn.classList.remove('loading');

  if (res.success) {
    closeSheet();
    toast('Transaction added!');
    loadDashboard();
    if (document.getElementById('screen-txs').classList.contains('active')) loadTransactions();
  } else {
    toast(res.error || 'Error saving', 'error');
  }
}

// ── Helpers ──────────────────────────────────────────────────
function fmt(n) { return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtDate(s) { const d=new Date(s+'T00:00:00'); return d.toLocaleDateString('en-PH',{month:'short',day:'numeric'}); }

// ── Boot ─────────────────────────────────────────────────────
(async () => {
  // Check existing session
  const r = await api({ action:'me' });
  if (r.user) {
    currentUser = r.user;
    enterApp();
  } else {
    document.getElementById('screen-auth').style.display = 'flex';
  }
})();

// Service Worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(() => {});
}
</script>
</body>
</html>

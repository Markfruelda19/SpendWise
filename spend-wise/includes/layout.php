<?php
/**
 * includes/layout.php
 * Call open_layout($title) to start the page shell,
 * then close_layout() at the bottom.
 * Requires $current_username (set by auth_guard.php).
 */

function open_layout(string $title = 'SpendWise'): void {
    global $current_username;
    $page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — SpendWise</title>
<!-- Apply saved theme BEFORE paint to avoid flash -->
<script>if(localStorage.getItem('sw-theme')==='light')document.documentElement.classList.add('light');</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ── Dark theme (default) ── */
:root {
  --bg:       #0b0f0e;
  --surface:  #131918;
  --card:     #162020;
  --border:   #1f2b28;
  --accent:   #00e5a0;
  --accent2:  #00b87a;
  --danger:   #ff4d6d;
  --warn:     #ffb84d;
  --text:     #e8f0ed;
  --muted:    #6b8c80;
  --sidebar:  220px;
}
/* ── Light theme ── */
html.light {
  --bg:      #f4f7f5;
  --surface: #ffffff;
  --card:    #ffffff;
  --border:  #d8e4df;
  --accent:  #00a372;
  --accent2: #007d57;
  --danger:  #e0294a;
  --warn:    #d97706;
  --text:    #0f1f1c;
  --muted:   #5a7a6e;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar);
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  transition: transform .3s;
}
.sidebar-logo {
  display: flex; align-items: center; gap: 10px;
  padding: 22px 20px 18px;
  border-bottom: 1px solid var(--border);
}
.sidebar-logo-icon { width:32px;height:32px;background:var(--accent);border-radius:8px;display:grid;place-items:center;font-size:15px;color:#0b0f0e;flex-shrink:0; }
.sidebar-logo-text { font-family:'Syne',sans-serif;font-size:1.06rem;font-weight:800;letter-spacing:-.03em; }
.sidebar-logo-text span { color:var(--accent); }
nav { padding: 16px 12px; flex: 1; }
.nav-group { margin-bottom: 24px; }
.nav-label { font-size:.68rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;padding:0 8px;margin-bottom:6px; }
.nav-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px;
  border-radius: 10px;
  color: var(--muted);
  text-decoration: none;
  font-size: .875rem;
  font-weight: 400;
  transition: background .15s, color .15s;
  margin-bottom: 2px;
}
.nav-link:hover { background: var(--card); color: var(--text); }
.nav-link.active { background: rgba(0,229,160,.12); color: var(--accent); font-weight: 500; }
.nav-link svg { flex-shrink:0;opacity:.8; }
.nav-link.active svg { opacity:1; }
.sidebar-user {
  padding: 14px 16px;
  border-top: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px;
}
.user-avatar {
  width:32px;height:32px;border-radius:50%;
  background: var(--accent2);
  display:grid;place-items:center;
  font-size:.8rem;font-weight:700;color:#0b0f0e;flex-shrink:0;
}
.user-name { font-size:.82rem;color:var(--text);font-weight:500;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.logout-link { font-size:.75rem;color:var(--muted);text-decoration:none;transition:color .2s; }
.logout-link:hover { color:var(--danger); }

/* ── Main area ── */
.main-wrap { margin-left: var(--sidebar); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar {
  height: 58px; background: var(--surface); border-bottom: 1px solid var(--border);
  display: flex; align-items: center; padding: 0 28px; gap: 16px;
  position: sticky; top: 0; z-index: 50;
}
.topbar-title { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; flex:1; }
.topbar-date { font-size:.8rem; color:var(--muted); }
.page-content { padding: 28px; flex: 1; }

/* ── Hamburger (mobile) ── */
.hamburger { display:none;background:none;border:none;color:var(--text);cursor:pointer;padding:4px; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main-wrap { margin-left: 0; }
  .hamburger { display:block; }
  .topbar { padding: 0 16px; }
  .page-content { padding: 16px; }
}

/* ── Utility ── */
.btn-primary { display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--accent);color:#0b0f0e;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-size:.875rem;font-weight:700;cursor:pointer;text-decoration:none;transition:background .2s,transform .1s; }
.btn-primary:hover { background:#00ffb3; }
.btn-primary:active { transform:scale(.97); }
.btn-ghost { display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:transparent;color:var(--muted);border:1px solid var(--border);border-radius:10px;font-size:.875rem;cursor:pointer;text-decoration:none;transition:background .2s,color .2s; }
.btn-ghost:hover { background:var(--card);color:var(--text); }
.btn-danger { display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:rgba(255,77,109,.12);color:var(--danger);border:1px solid rgba(255,77,109,.25);border-radius:10px;font-size:.875rem;cursor:pointer;text-decoration:none;transition:background .2s; }
.btn-danger:hover { background:rgba(255,77,109,.22); }
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">💰</div>
    <div class="sidebar-logo-text">Spend<span>Wise</span></div>
  </div>

  <nav>
    <div class="nav-group">
      <div class="nav-label">Overview</div>
      <a href="dashboard.php" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
      <a href="reports.php" class="nav-link <?= $page === 'reports' ? 'active' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Reports
      </a>
    </div>
    <div class="nav-group">
      <div class="nav-label">Money</div>
      <a href="add_transaction.php" class="nav-link <?= $page === 'add_transaction' ? 'active' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Add Transaction
      </a>
      <a href="transactions.php" class="nav-link <?= $page === 'transactions' ? 'active' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
        Transactions
      </a>
      <a href="export.php" class="nav-link <?= $page === 'export' ? 'active' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </a>
    </div>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($current_username, 0, 1)) ?></div>
    <span class="user-name"><?= htmlspecialchars($current_username) ?></span>
    <a href="auth/logout.php" class="logout-link" title="Logout">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>

<!-- Main -->
<div class="main-wrap">
  <header class="topbar">
    <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="topbar-title"><?= htmlspecialchars($title) ?></span>
    <span class="topbar-date"><?= date('F j, Y') ?></span>
    <button id="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme" title="Toggle light/dark mode" style="background:none;border:1px solid var(--border);border-radius:8px;color:var(--muted);cursor:pointer;padding:6px 9px;display:flex;align-items:center;transition:border-color .2s,color .2s;">
      <span id="theme-icon" style="font-size:15px">🌙</span>
    </button>
  </header>
  <div class="page-content">
<?php
} // end open_layout()

function close_layout(): void {
?>
  </div><!-- .page-content -->
</div><!-- .main-wrap -->

<script>
// ── Theme toggle ───────────────────────────────────────────
function applyTheme(theme) {
  const isLight = theme === 'light';
  document.documentElement.classList.toggle('light', isLight);
  const icon = document.getElementById('theme-icon');
  if (icon) icon.textContent = isLight ? '☀️' : '🌙';
}
function toggleTheme() {
  const next = document.documentElement.classList.contains('light') ? 'dark' : 'light';
  localStorage.setItem('sw-theme', next);
  applyTheme(next);
}
// Init icon on load
applyTheme(localStorage.getItem('sw-theme') || 'dark');

// ── Close sidebar on outside click (mobile) ────────────────
document.addEventListener('click', function(e) {
  const sb = document.getElementById('sidebar');
  if (sb && sb.classList.contains('open') && !sb.contains(e.target)) {
    sb.classList.remove('open');
  }
});
</script>
</body>
</html>
<?php
} // end close_layout()

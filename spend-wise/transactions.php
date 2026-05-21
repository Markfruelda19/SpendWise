<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

// ── Filters ─────────────────────────────────────────────────
$filter_type     = $_GET['type']        ?? '';
$filter_category = (int)($_GET['category'] ?? 0);
$filter_month    = $_GET['month']       ?? '';
$search          = trim($_GET['search'] ?? '');

// ── Build query ──────────────────────────────────────────────
$where  = ["t.user_id = :uid"];
$params = [':uid' => $current_user_id];

if ($filter_type && in_array($filter_type, ['income','expense'])) {
    $where[]             = "t.type = :type";
    $params[':type']     = $filter_type;
}
if ($filter_category) {
    $where[]             = "t.category_id = :cat";
    $params[':cat']      = $filter_category;
}
if ($filter_month) {
    $where[]             = "DATE_FORMAT(t.transaction_date,'%Y-%m') = :month";
    $params[':month']    = $filter_month;
}
if ($search) {
    $where[]             = "t.description LIKE :search";
    $params[':search']   = '%' . $search . '%';
}

$whereSQL = implode(' AND ', $where);

// Totals for filtered result
$totStmt = $pdo->prepare("
    SELECT
      COALESCE(SUM(CASE WHEN t.type='income'  THEN t.amount ELSE 0 END),0) AS income,
      COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END),0) AS expenses
    FROM transactions t
    WHERE $whereSQL
");
$totStmt->execute($params);
$totals = $totStmt->fetch();

// Rows
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS category_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE $whereSQL
    ORDER BY t.transaction_date DESC, t.id DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Categories for filter dropdown
$catStmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$catStmt->execute([$current_user_id]);
$categories = $catStmt->fetchAll();

// Unique months for filter dropdown
$monthStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(transaction_date,'%Y-%m') AS ym,
           DATE_FORMAT(transaction_date,'%M %Y') AS label
    FROM transactions WHERE user_id = ?
    ORDER BY ym DESC
");
$monthStmt->execute([$current_user_id]);
$months = $monthStmt->fetchAll();

open_layout('Transactions');
?>

<style>
/* Filters bar */
.filter-bar {
  display:flex;gap:10px;flex-wrap:wrap;align-items:center;
  background:var(--card);border:1px solid var(--border);border-radius:14px;
  padding:14px 18px;margin-bottom:22px;
}
.filter-bar input[type=text],
.filter-bar select {
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  padding:8px 12px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.85rem;
  outline:none;transition:border-color .2s;
}
.filter-bar input[type=text] { flex:1;min-width:160px; }
.filter-bar input[type=text]:focus,
.filter-bar select:focus { border-color:var(--accent); }
.filter-bar select option { background:var(--surface); }
.filter-btn { padding:8px 16px;background:var(--accent);color:#0b0f0e;border:none;border-radius:8px;font-family:'Syne',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;white-space:nowrap; }
.filter-btn:hover { background:#00ffb3; }
.reset-link { font-size:.82rem;color:var(--muted);text-decoration:none;white-space:nowrap; }
.reset-link:hover { color:var(--text); }

/* Summary strip */
.summary-strip {
  display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;
}
.summary-chip {
  background:var(--card);border:1px solid var(--border);border-radius:10px;
  padding:10px 18px;font-size:.82rem;
}
.summary-chip span { font-family:'Syne',sans-serif;font-weight:700;font-size:1rem; }
.summary-chip span.inc { color:var(--accent); }
.summary-chip span.exp { color:var(--danger); }

/* Table */
.panel { background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden; }
.panel-header { display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--border); }
.panel-title { font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700; }

table { width:100%;border-collapse:collapse; }
th { font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;padding:10px 20px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap; }
td { padding:11px 20px;font-size:.875rem;border-bottom:1px solid rgba(31,43,40,.5); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.015); }

.badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:500; }
.badge.income  { background:rgba(0,229,160,.12);color:var(--accent); }
.badge.expense { background:rgba(255,77,109,.12);color:var(--danger); }
.cat-badge { display:inline-block;padding:2px 8px;border-radius:6px;font-size:.72rem;background:rgba(255,255,255,.05);color:var(--muted); }
.action-link { color:var(--muted);text-decoration:none;font-size:.82rem;padding:4px 8px;border-radius:6px;transition:background .15s; }
.action-link:hover { background:var(--surface);color:var(--text); }

.empty { text-align:center;padding:56px 20px;color:var(--muted); }
.empty svg { margin-bottom:12px;opacity:.35; }

/* Deleted toast */
.del-banner {
  background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.25);color:var(--accent);
  border-radius:10px;padding:11px 18px;font-size:.85rem;margin-bottom:18px;display:none;
}
.del-banner.show { display:flex;align-items:center;gap:8px; }

@media(max-width:700px) {
  th.hide-sm, td.hide-sm { display:none; }
}
</style>

<?php if (isset($_GET['deleted'])): ?>
<div class="del-banner show">✓ Transaction deleted.</div>
<?php endif; ?>

<!-- Filter bar -->
<form method="GET" class="filter-bar">
  <input type="text" name="search" placeholder="Search description…" value="<?= htmlspecialchars($search) ?>">

  <select name="type">
    <option value="">All Types</option>
    <option value="income"  <?= $filter_type==='income'  ? 'selected':'' ?>>Income</option>
    <option value="expense" <?= $filter_type==='expense' ? 'selected':'' ?>>Expense</option>
  </select>

  <select name="category">
    <option value="0">All Categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $filter_category==$cat['id']?'selected':'' ?>>
        <?= htmlspecialchars($cat['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="month">
    <option value="">All Months</option>
    <?php foreach ($months as $m): ?>
      <option value="<?= $m['ym'] ?>" <?= $filter_month===$m['ym']?'selected':'' ?>>
        <?= htmlspecialchars($m['label']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button type="submit" class="filter-btn">Filter</button>
  <a href="transactions.php" class="reset-link">Reset</a>
</form>

<!-- Summary strip -->
<div class="summary-strip">
  <div class="summary-chip">
    <span class="inc">₱<?= number_format($totals['income'], 2) ?></span>
    <span style="color:var(--muted)"> income</span>
  </div>
  <div class="summary-chip">
    <span class="exp">₱<?= number_format($totals['expenses'], 2) ?></span>
    <span style="color:var(--muted)"> expenses</span>
  </div>
  <div class="summary-chip">
    <span style="color:var(--text)">
      <?= count($transactions) ?>
    </span>
    <span style="color:var(--muted)"> record<?= count($transactions)!==1?'s':'' ?></span>
  </div>
</div>

<!-- Table -->
<div class="panel">
  <div class="panel-header">
    <span class="panel-title">All Transactions</span>
    <a href="add_transaction.php" class="btn-primary" style="padding:7px 16px;font-size:.82rem;">+ Add New</a>
  </div>

  <?php if (empty($transactions)): ?>
    <div class="empty">
      <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
      <p>No transactions found.<br>
        <?php if ($filter_type || $filter_category || $filter_month || $search): ?>
          Try adjusting your filters.
        <?php else: ?>
          <a href="add_transaction.php" style="color:var(--accent)">Add your first transaction →</a>
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th class="hide-sm">Category</th>
          <th class="hide-sm">Type</th>
          <th style="text-align:right">Amount</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $tx): ?>
        <tr>
          <td style="color:var(--muted);white-space:nowrap">
            <?= date('M j, Y', strtotime($tx['transaction_date'])) ?>
          </td>
          <td><?= htmlspecialchars($tx['description'] ?: '—') ?></td>
          <td class="hide-sm">
            <span class="cat-badge"><?= htmlspecialchars($tx['category_name'] ?? 'Uncategorized') ?></span>
          </td>
          <td class="hide-sm">
            <span class="badge <?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span>
          </td>
          <td style="text-align:right;font-family:'Syne',sans-serif;font-weight:600;white-space:nowrap;color:<?= $tx['type']==='income'?'var(--accent)':'var(--danger)' ?>">
            <?= $tx['type']==='income' ? '+' : '−' ?>₱<?= number_format($tx['amount'], 2) ?>
          </td>
          <td style="text-align:right;white-space:nowrap">
            <a href="edit_transaction.php?id=<?= $tx['id'] ?>" class="action-link">Edit</a>
            <a href="delete_transaction.php?id=<?= $tx['id'] ?>" class="action-link"
               style="color:var(--danger)"
               onclick="return confirm('Delete this transaction?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php close_layout(); ?>
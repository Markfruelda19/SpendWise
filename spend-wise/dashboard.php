<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

// ── Summary totals (current month) ──────────────────────────
$month = date('Y-m');

$stmt = $pdo->prepare("
    SELECT
      COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS income,
      COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS expenses
    FROM transactions
    WHERE user_id = ? AND DATE_FORMAT(transaction_date,'%Y-%m') = ?
");
$stmt->execute([$current_user_id, $month]);
$summary = $stmt->fetch();
$income   = (float)$summary['income'];
$expenses = (float)$summary['expenses'];
$balance  = $income - $expenses;

// ── All-time balance ─────────────────────────────────────────
$stmt2 = $pdo->prepare("
    SELECT
      COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS total_income,
      COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS total_expenses
    FROM transactions WHERE user_id = ?
");
$stmt2->execute([$current_user_id]);
$all = $stmt2->fetch();
$total_balance = (float)$all['total_income'] - (float)$all['total_expenses'];

// ── Recent 8 transactions ────────────────────────────────────
$stmt3 = $pdo->prepare("
    SELECT t.*, c.name AS category_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 8
");
$stmt3->execute([$current_user_id]);
$recent = $stmt3->fetchAll();

// ── Expense by category (this month, for mini chart) ─────────
$stmt4 = $pdo->prepare("
    SELECT c.name, SUM(t.amount) AS total
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type='expense'
      AND DATE_FORMAT(t.transaction_date,'%Y-%m') = ?
    GROUP BY c.name
    ORDER BY total DESC
    LIMIT 6
");
$stmt4->execute([$current_user_id, $month]);
$cat_data = $stmt4->fetchAll();

open_layout('Dashboard');
?>

<style>
/* ── Stat cards ── */
.stats-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px; }
.stat-card { background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px 22px; }
.stat-card.accent { border-color:rgba(0,229,160,.25);background:rgba(0,229,160,.05); }
.stat-label { font-size:.75rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;display:flex;align-items:center;gap:6px; }
.stat-value { font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:700;letter-spacing:-.03em; }
.stat-value.income  { color:var(--accent); }
.stat-value.expense { color:var(--danger); }
.stat-value.balance { color:var(--text); }
.stat-sub { font-size:.75rem;color:var(--muted);margin-top:4px; }

/* ── Two-col layout ── */
.dash-grid { display:grid;grid-template-columns:1fr 340px;gap:20px; }
@media(max-width:1000px){ .dash-grid { grid-template-columns:1fr; } }

/* ── Table card ── */
.panel { background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden; }
.panel-header { display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border); }
.panel-title { font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700; }
.panel-body { padding:0; }

table { width:100%;border-collapse:collapse; }
th { font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;padding:10px 22px;text-align:left;border-bottom:1px solid var(--border); }
td { padding:12px 22px;font-size:.875rem;border-bottom:1px solid rgba(31,43,40,.5); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.02); }

.badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:500; }
.badge.income  { background:rgba(0,229,160,.12);color:var(--accent); }
.badge.expense { background:rgba(255,77,109,.12);color:var(--danger); }
.cat-badge { display:inline-block;padding:2px 8px;border-radius:6px;font-size:.72rem;background:rgba(255,255,255,.05);color:var(--muted); }

/* ── Category list ── */
.cat-list { padding:10px 0; }
.cat-row { display:flex;align-items:center;gap:10px;padding:10px 22px; }
.cat-color { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.cat-name { flex:1;font-size:.85rem; }
.cat-amount { font-size:.85rem;font-weight:500; }
.cat-bar-wrap { width:100%;height:4px;background:var(--border);border-radius:4px;margin-top:4px; }
.cat-bar { height:4px;border-radius:4px;background:var(--accent); }

/* ── Empty state ── */
.empty { text-align:center;padding:48px 20px;color:var(--muted); }
.empty svg { margin-bottom:12px;opacity:.4; }
.empty p { font-size:.9rem; }

/* ── Quick actions ── */
.quick-btns { display:flex;gap:10px;margin-bottom:28px;flex-wrap:wrap; }
</style>

<!-- Quick actions -->
<div class="quick-btns">
  <a href="add_transaction.php?type=expense" class="btn-primary">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Expense
  </a>
  <a href="add_transaction.php?type=income" class="btn-ghost">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Income
  </a>
  <a href="transactions.php" class="btn-ghost">View All</a>
</div>

<!-- Stat cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
      This Month — Income
    </div>
    <div class="stat-value income">₱<?= number_format($income, 2) ?></div>
    <div class="stat-sub"><?= date('F Y') ?></div>
  </div>

  <div class="stat-card">
    <div class="stat-label">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
      This Month — Expenses
    </div>
    <div class="stat-value expense">₱<?= number_format($expenses, 2) ?></div>
    <div class="stat-sub"><?= date('F Y') ?></div>
  </div>

  <div class="stat-card">
    <div class="stat-label">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Monthly Savings
    </div>
    <div class="stat-value <?= $balance >= 0 ? 'income' : 'expense' ?>">₱<?= number_format(abs($balance), 2) ?></div>
    <div class="stat-sub"><?= $balance >= 0 ? 'On track' : 'Over budget' ?></div>
  </div>

  <div class="stat-card accent">
    <div class="stat-label">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Total Balance
    </div>
    <div class="stat-value balance">₱<?= number_format($total_balance, 2) ?></div>
    <div class="stat-sub">All time</div>
  </div>
</div>

<!-- Main grid -->
<div class="dash-grid">

  <!-- Recent transactions -->
  <div class="panel">
    <div class="panel-header">
      <span class="panel-title">Recent Transactions</span>
      <a href="transactions.php" class="btn-ghost" style="padding:6px 14px;font-size:.78rem;">See all →</a>
    </div>
    <div class="panel-body">
      <?php if (empty($recent)): ?>
        <div class="empty">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
          <p>No transactions yet.<br>Add your first one!</p>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Description</th>
              <th>Category</th>
              <th>Type</th>
              <th style="text-align:right">Amount</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $tx): ?>
            <tr>
              <td style="color:var(--muted);white-space:nowrap"><?= date('M j', strtotime($tx['transaction_date'])) ?></td>
              <td><?= htmlspecialchars($tx['description'] ?: '—') ?></td>
              <td><span class="cat-badge"><?= htmlspecialchars($tx['category_name'] ?? 'Uncategorized') ?></span></td>
              <td><span class="badge <?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
              <td style="text-align:right;font-family:'Syne',sans-serif;font-weight:600;color:<?= $tx['type']==='income' ? 'var(--accent)' : 'var(--danger)' ?>">
                <?= $tx['type']==='income' ? '+' : '-' ?>₱<?= number_format($tx['amount'], 2) ?>
              </td>
              <td style="white-space:nowrap">
                <a href="edit_transaction.php?id=<?= $tx['id'] ?>" style="color:var(--muted);text-decoration:none;margin-right:8px;" title="Edit">✏️</a>
                <a href="delete_transaction.php?id=<?= $tx['id'] ?>" style="color:var(--muted);text-decoration:none;" title="Delete"
                   onclick="return confirm('Delete this transaction?')">🗑️</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Expenses by category -->
  <div class="panel">
    <div class="panel-header">
      <span class="panel-title">Expenses by Category</span>
      <span style="font-size:.78rem;color:var(--muted)"><?= date('M Y') ?></span>
    </div>
    <div class="cat-list">
      <?php
      $colors = ['#00e5a0','#ff4d6d','#ffb84d','#7b61ff','#00b4d8','#f77f00'];
      if (empty($cat_data)):
      ?>
        <div class="empty"><p style="padding:24px 0">No expenses this month.</p></div>
      <?php else:
        $max = max(array_column($cat_data, 'total'));
        foreach ($cat_data as $i => $row):
          $pct = $max > 0 ? round(($row['total'] / $max) * 100) : 0;
          $c   = $colors[$i % count($colors)];
      ?>
        <div class="cat-row">
          <div class="cat-color" style="background:<?= $c ?>"></div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
              <span class="cat-name"><?= htmlspecialchars($row['name'] ?? 'Uncategorized') ?></span>
              <span class="cat-amount" style="color:<?= $c ?>">₱<?= number_format($row['total'], 2) ?></span>
            </div>
            <div class="cat-bar-wrap">
              <div class="cat-bar" style="width:<?= $pct ?>%;background:<?= $c ?>"></div>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div><!-- .dash-grid -->

<?php
// ── Budget Goals summary (top 4 by urgency) ─────────────────
$bg_stmt = $pdo->prepare("
    SELECT
        g.id, g.name, g.amount, g.period,
        c.name AS category_name,
        COALESCE((
            SELECT SUM(t.amount)
            FROM transactions t
            WHERE t.user_id = g.user_id
              AND t.type = 'expense'
              AND (g.category_id IS NULL OR t.category_id = g.category_id)
              AND (
                  (g.period='monthly' AND DATE_FORMAT(t.transaction_date,'%Y-%m') = ?)
               OR (g.period='yearly'  AND YEAR(t.transaction_date) = ?)
              )
        ), 0) AS spent
    FROM budget_goals g
    LEFT JOIN categories c ON g.category_id = c.id
    WHERE g.user_id = ?
    ORDER BY (spent / g.amount) DESC
    LIMIT 4
");
$bg_stmt->execute([date('Y-m'), date('Y'), $current_user_id]);
$dash_goals = $bg_stmt->fetchAll();
?>

<?php if (!empty($dash_goals)): ?>
<div style="margin-top:20px;">
  <div class="panel">
    <div class="panel-header">
      <span class="panel-title">Budget Goals</span>
      <a href="budget_goals.php" class="btn-ghost" style="padding:6px 14px;font-size:.78rem;">Manage →</a>
    </div>
    <div style="padding:16px 22px;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
      <?php foreach ($dash_goals as $g):
        $spent   = (float)$g['spent'];
        $limit   = (float)$g['amount'];
        $pct     = $limit > 0 ? min(round(($spent/$limit)*100,1),100) : 0;
        $raw_pct = $limit > 0 ? ($spent/$limit)*100 : 0;
        if ($raw_pct >= 100)    $st = 'over';
        elseif ($raw_pct >= 80) $st = 'warn';
        else                     $st = 'ok';
        $colors = ['ok'=>'var(--accent)','warn'=>'var(--warn)','over'=>'var(--danger)'];
        $col = $colors[$st];
      ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-size:.85rem;font-weight:500;color:var(--text)"><?= htmlspecialchars($g['name']) ?></span>
          <span style="font-size:.72rem;color:<?= $col ?>;font-weight:600"><?= $pct ?>%</span>
        </div>
        <div style="width:100%;height:6px;background:var(--border);border-radius:6px;overflow:hidden;margin-bottom:8px;">
          <div style="height:6px;border-radius:6px;background:<?= $col ?>;width:<?= $pct ?>%;transition:width .5s;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.76rem;color:var(--muted);">
          <span>₱<?= number_format($spent,2) ?> spent</span>
          <span>₱<?= number_format($limit,2) ?> limit</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php close_layout(); ?>

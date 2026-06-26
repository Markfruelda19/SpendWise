<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

$current_month = date('Y-m');
$flash = '';
$flash_type = 'success';

// ── Handle POST actions (add / edit / delete) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add new goal ──────────────────────────────────────────
    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $category_id = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
        $period      = in_array($_POST['period'] ?? '', ['monthly','yearly']) ? $_POST['period'] : 'monthly';

        if ($name === '' || $amount <= 0) {
            $flash      = 'Please enter a name and a valid amount.';
            $flash_type = 'error';
        } else {
            // Prevent duplicate category goal for same period
            if ($category_id) {
                $chk = $pdo->prepare("SELECT id FROM budget_goals WHERE user_id=? AND category_id=? AND period=?");
                $chk->execute([$current_user_id, $category_id, $period]);
                if ($chk->fetch()) {
                    $flash      = 'A budget goal for that category and period already exists.';
                    $flash_type = 'error';
                }
            }
            if ($flash === '') {
                $ins = $pdo->prepare("INSERT INTO budget_goals (user_id,category_id,name,amount,period) VALUES (?,?,?,?,?)");
                $ins->execute([$current_user_id, $category_id, $name, $amount, $period]);
                $flash = 'Budget goal added!';
            }
        }
    }

    // ── Update goal ───────────────────────────────────────────
    if ($action === 'edit') {
        $id     = (int)($_POST['goal_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $period = in_array($_POST['period'] ?? '', ['monthly','yearly']) ? $_POST['period'] : 'monthly';
        $category_id = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;

        if ($name !== '' && $amount > 0 && $id) {
            $upd = $pdo->prepare("UPDATE budget_goals SET name=?,amount=?,period=?,category_id=? WHERE id=? AND user_id=?");
            $upd->execute([$name, $amount, $period, $category_id, $id, $current_user_id]);
            $flash = 'Goal updated!';
        } else {
            $flash = 'Invalid data.'; $flash_type = 'error';
        }
    }

    // ── Delete goal ───────────────────────────────────────────
    if ($action === 'delete') {
        $id  = (int)($_POST['goal_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM budget_goals WHERE id=? AND user_id=?");
        $del->execute([$id, $current_user_id]);
        $flash = 'Goal deleted.';
    }
}

// ── Fetch all goals + actual spending this month ──────────────
$goals_stmt = $pdo->prepare("
    SELECT
        g.*,
        c.name AS category_name,
        COALESCE((
            SELECT SUM(t.amount)
            FROM transactions t
            WHERE t.user_id    = g.user_id
              AND t.type       = 'expense'
              AND (g.category_id IS NULL OR t.category_id = g.category_id)
              AND (
                  (g.period = 'monthly' AND DATE_FORMAT(t.transaction_date,'%Y-%m') = ?)
               OR (g.period = 'yearly'  AND YEAR(t.transaction_date) = ?)
              )
        ), 0) AS spent
    FROM budget_goals g
    LEFT JOIN categories c ON g.category_id = c.id
    WHERE g.user_id = ?
    ORDER BY g.period ASC, g.name ASC
");
$goals_stmt->execute([$current_month, date('Y'), $current_user_id]);
$goals = $goals_stmt->fetchAll();

// ── Categories for the add/edit form ─────────────────────────
$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=? ORDER BY name");
$cat_stmt->execute([$current_user_id]);
$categories = $cat_stmt->fetchAll();

// ── Overview totals (this month) ─────────────────────────────
$tot_stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS income,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expenses
    FROM transactions
    WHERE user_id=? AND DATE_FORMAT(transaction_date,'%Y-%m')=?
");
$tot_stmt->execute([$current_user_id, $current_month]);
$totals = $tot_stmt->fetch();

// ── Separate monthly / yearly ─────────────────────────────────
$monthly_goals = array_filter($goals, fn($g) => $g['period'] === 'monthly');
$yearly_goals  = array_filter($goals, fn($g) => $g['period'] === 'yearly');

open_layout('Budget Goals');
?>

<style>
/* ── Flash message ── */
.flash { display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;font-size:.875rem;margin-bottom:20px; }
.flash.success { background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.3);color:var(--accent); }
.flash.error   { background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:var(--danger); }

/* ── Top row ── */
.top-row { display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:22px; }
.page-sub { font-size:.85rem;color:var(--muted);margin-top:3px; }

/* ── Overview strip ── */
.overview-strip { display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:26px; }
.ov-card { background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 18px; }
.ov-label { font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px; }
.ov-val   { font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:700; }
.ov-val.inc { color:var(--accent); }
.ov-val.exp { color:var(--danger); }

/* ── Section label ── */
.section-label { font-family:'Syne',sans-serif;font-size:.8rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin:24px 0 12px; }

/* ── Goal cards grid ── */
.goals-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;margin-bottom:8px; }

/* ── Single goal card ── */
.goal-card {
  background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px;
  transition:border-color .2s;position:relative;
}
.goal-card:hover { border-color:rgba(0,229,160,.2); }
.goal-card.over  { border-color:rgba(255,77,109,.35);background:rgba(255,77,109,.03); }
.goal-card.warn  { border-color:rgba(255,184,77,.3); }

.goal-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px; }
.goal-name   { font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;line-height:1.2; }
.goal-cat    { font-size:.72rem;color:var(--muted);margin-top:3px; }
.goal-period { font-size:.7rem;font-weight:600;color:var(--muted);background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:3px 8px;white-space:nowrap; }

/* Progress ring + bar */
.goal-amounts { display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px; }
.goal-spent   { font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700; }
.goal-spent.over { color:var(--danger); }
.goal-spent.warn { color:var(--warn); }
.goal-spent.ok   { color:var(--accent); }
.goal-limit   { font-size:.82rem;color:var(--muted); }

.goal-bar-wrap { width:100%;height:8px;background:var(--border);border-radius:8px;overflow:hidden;margin-bottom:10px; }
.goal-bar-fill { height:8px;border-radius:8px;transition:width .6s cubic-bezier(.4,0,.2,1); }
.goal-bar-fill.ok   { background:var(--accent); }
.goal-bar-fill.warn { background:var(--warn); }
.goal-bar-fill.over { background:var(--danger); }

.goal-footer { display:flex;align-items:center;justify-content:space-between;font-size:.78rem;color:var(--muted); }
.goal-remaining.over { color:var(--danger);font-weight:500; }
.goal-remaining.ok   { color:var(--accent);font-weight:500; }

.goal-actions { display:flex;gap:6px; }
.goal-act-btn { background:none;border:none;color:var(--muted);cursor:pointer;padding:3px 6px;border-radius:6px;font-size:.78rem;transition:background .15s,color .15s; }
.goal-act-btn:hover { background:var(--surface);color:var(--text); }
.goal-act-btn.del:hover { color:var(--danger); }

/* ── Status badge ── */
.status-pill { display:inline-block;font-size:.68rem;font-weight:600;padding:2px 8px;border-radius:20px;margin-left:8px; }
.status-pill.over { background:rgba(255,77,109,.12);color:var(--danger); }
.status-pill.warn { background:rgba(255,184,77,.12);color:var(--warn); }
.status-pill.ok   { background:rgba(0,229,160,.1);color:var(--accent); }

/* ── Add goal modal ── */
.modal-backdrop { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;backdrop-filter:blur(3px);align-items:center;justify-content:center; }
.modal-backdrop.open { display:flex; }
.modal { background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px 30px;width:100%;max-width:460px;animation:modalIn .25s ease; }
@keyframes modalIn { from{opacity:0;transform:scale(.96) translateY(12px)} to{opacity:1;transform:none} }
.modal h3 { font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:22px; }
.mfield { margin-bottom:16px; }
.mfield label { display:block;font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px; }
.mfield input,.mfield select,.mfield textarea {
  width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;
  padding:11px 13px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.92rem;outline:none;
  transition:border-color .2s,box-shadow .2s;
}
.mfield input:focus,.mfield select:focus { border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,229,160,.1); }
.mfield select option { background:var(--surface); }
.mrow2 { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.amount-wrap { position:relative; }
.amount-prefix { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600;pointer-events:none; }
.amount-wrap input { padding-left:28px; }
.modal-actions { display:flex;gap:10px;margin-top:20px;flex-wrap:wrap; }

/* ── Empty state ── */
.empty-goals { text-align:center;padding:52px 20px;background:var(--card);border:1px dashed var(--border);border-radius:16px;color:var(--muted); }
.empty-goals svg { opacity:.3;margin-bottom:12px; }
.empty-goals p { font-size:.9rem; }

@media(max-width:600px){
  .modal { margin:16px; }
  .mrow2 { grid-template-columns:1fr; }
}
</style>

<!-- Flash -->
<?php if ($flash): ?>
<div class="flash <?= $flash_type ?>">
  <?= $flash_type==='success' ? '✓' : '✕' ?> <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- Top row -->
<div class="top-row">
  <div>
    <div style="font-size:.85rem;color:var(--muted)">Track spending against targets • <?= date('F Y') ?></div>
  </div>
  <button class="btn-primary" onclick="openModal()">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Goal
  </button>
</div>

<!-- Overview strip -->
<div class="overview-strip">
  <div class="ov-card">
    <div class="ov-label">This Month Income</div>
    <div class="ov-val inc">₱<?= number_format($totals['income'], 2) ?></div>
  </div>
  <div class="ov-card">
    <div class="ov-label">This Month Expenses</div>
    <div class="ov-val exp">₱<?= number_format($totals['expenses'], 2) ?></div>
  </div>
  <div class="ov-card">
    <div class="ov-label">Goals Tracked</div>
    <div class="ov-val" style="color:var(--text)"><?= count($goals) ?></div>
  </div>
  <div class="ov-card">
    <?php
      $over_count = count(array_filter($goals, fn($g) => (float)$g['spent'] >= (float)$g['amount']));
    ?>
    <div class="ov-label">Over Budget</div>
    <div class="ov-val <?= $over_count > 0 ? 'exp' : 'inc' ?>"><?= $over_count ?></div>
  </div>
</div>

<?php
// ── Goal card renderer ──────────────────────────────────────
function render_goals(array $goals, array $categories): void {
  if (empty($goals)) {
    echo '<div class="empty-goals">
      <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <p>No goals yet. Click <strong>New Goal</strong> to add one.</p>
    </div>';
    return;
  }
  echo '<div class="goals-grid">';
  foreach ($goals as $g) {
    $spent    = (float)$g['spent'];
    $limit    = (float)$g['amount'];
    $pct      = $limit > 0 ? min(round(($spent / $limit) * 100, 1), 100) : 0;
    $raw_pct  = $limit > 0 ? ($spent / $limit) * 100 : 0;
    $remaining = $limit - $spent;

    if ($raw_pct >= 100)     { $status = 'over'; }
    elseif ($raw_pct >= 80)  { $status = 'warn'; }
    else                      { $status = 'ok'; }

    $cat_name = $g['category_name'] ?? 'Overall spending';
    $cat_id   = $g['category_id'];

    echo "
    <div class='goal-card {$status}'>
      <div class='goal-header'>
        <div>
          <div class='goal-name'>" . htmlspecialchars($g['name']) . "
            <span class='status-pill {$status}'>" . ($status==='over'?'Over budget':($status==='warn'?'Near limit':'On track')) . "</span>
          </div>
          <div class='goal-cat'>" . htmlspecialchars($cat_name) . "</div>
        </div>
        <span class='goal-period'>" . ucfirst($g['period']) . "</span>
      </div>

      <div class='goal-amounts'>
        <span class='goal-spent {$status}'>₱" . number_format($spent, 2) . "</span>
        <span class='goal-limit'>of ₱" . number_format($limit, 2) . "</span>
      </div>

      <div class='goal-bar-wrap'>
        <div class='goal-bar-fill {$status}' style='width:{$pct}%'></div>
      </div>

      <div class='goal-footer'>
        <span class='goal-remaining {$status}'>
          " . ($remaining >= 0
            ? "₱" . number_format($remaining, 2) . " remaining"
            : "₱" . number_format(abs($remaining), 2) . " over limit") . "
          · {$pct}%
        </span>
        <div class='goal-actions'>
          <button class='goal-act-btn'
            onclick='openEditModal({$g['id']}, " . htmlspecialchars(json_encode($g['name']), ENT_QUOTES) . ", {$limit}, " . json_encode($g['period']) . ", " . json_encode($cat_id) . ")'
            title='Edit'>
            ✏️ Edit
          </button>
          <form method='POST' style='display:inline' onsubmit='return confirm(\"Delete this goal?\")'>
            <input type='hidden' name='action' value='delete'>
            <input type='hidden' name='goal_id' value='{$g['id']}'>
            <button type='submit' class='goal-act-btn del' title='Delete'>🗑️ Delete</button>
          </form>
        </div>
      </div>
    </div>";
  }
  echo '</div>';
}
?>

<!-- Monthly goals -->
<div class="section-label">📅 Monthly Budgets</div>
<?php render_goals(array_values($monthly_goals), $categories); ?>

<!-- Yearly goals -->
<div class="section-label">📆 Yearly Budgets</div>
<?php render_goals(array_values($yearly_goals), $categories); ?>


<!-- ── Add Goal Modal ─────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-add">
  <div class="modal">
    <h3>➕ New Budget Goal</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">

      <div class="mfield">
        <label>Goal Name</label>
        <input type="text" name="name" placeholder="e.g. Food Budget, Monthly Savings" required>
      </div>

      <div class="mrow2">
        <div class="mfield">
          <label>Budget Limit</label>
          <div class="amount-wrap">
            <span class="amount-prefix">₱</span>
            <input type="number" name="amount" step="0.01" min="1" placeholder="0.00" required>
          </div>
        </div>
        <div class="mfield">
          <label>Period</label>
          <select name="period">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>
      </div>

      <div class="mfield">
        <label>Category <span style="color:var(--muted);text-transform:none;font-weight:400">(optional — leave blank for overall)</span></label>
        <select name="category_id">
          <option value="">— Overall spending —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn-primary">Save Goal</button>
        <button type="button" class="btn-ghost" onclick="closeModal('modal-add')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Goal Modal ────────────────────────────────────── -->
<div class="modal-backdrop" id="modal-edit">
  <div class="modal">
    <h3>✏️ Edit Budget Goal</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="goal_id" id="edit-id">

      <div class="mfield">
        <label>Goal Name</label>
        <input type="text" name="name" id="edit-name" required>
      </div>

      <div class="mrow2">
        <div class="mfield">
          <label>Budget Limit</label>
          <div class="amount-wrap">
            <span class="amount-prefix">₱</span>
            <input type="number" name="amount" id="edit-amount" step="0.01" min="1" required>
          </div>
        </div>
        <div class="mfield">
          <label>Period</label>
          <select name="period" id="edit-period">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>
      </div>

      <div class="mfield">
        <label>Category</label>
        <select name="category_id" id="edit-category">
          <option value="">— Overall spending —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn-primary">Update Goal</button>
        <button type="button" class="btn-ghost" onclick="closeModal('modal-edit')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal() {
  document.getElementById('modal-add').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
function openEditModal(id, name, amount, period, catId) {
  document.getElementById('edit-id').value       = id;
  document.getElementById('edit-name').value     = name;
  document.getElementById('edit-amount').value   = amount;
  document.getElementById('edit-period').value   = period;
  document.getElementById('edit-category').value = catId ?? '';
  document.getElementById('modal-edit').classList.add('open');
}
// Close on backdrop click
['modal-add','modal-edit'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) closeModal(id);
  });
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeModal('modal-add');
    closeModal('modal-edit');
  }
});
</script>

<?php close_layout(); ?>

<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

$id = (int)($_GET['id'] ?? 0);

// Fetch transaction (only the owner can edit)
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $current_user_id]);
$tx = $stmt->fetch();

if (!$tx) {
    header("Location: transactions.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['type'] ?? '';
    $amount      = (float)($_POST['amount'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $date        = $_POST['transaction_date'] ?? '';

    if (!in_array($type, ['income','expense'])) {
        $error = 'Invalid type.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE transactions
            SET type=?, amount=?, category_id=?, description=?, transaction_date=?
            WHERE id=? AND user_id=?
        ");
        $stmt->execute([$type, $amount, $category_id ?: null, $description, $date, $id, $current_user_id]);
        $success = 'Transaction updated!';
        // Re-fetch updated values
        $stmt2 = $pdo->prepare("SELECT * FROM transactions WHERE id=?");
        $stmt2->execute([$id]);
        $tx = $stmt2->fetch();
    }
}

// Load categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$current_user_id]);
$categories = $stmt->fetchAll();

open_layout('Edit Transaction');
?>

<style>
.form-card { background:var(--card);border:1px solid var(--border);border-radius:18px;padding:28px 32px;max-width:600px; }
.type-toggle { display:flex;gap:8px;margin-bottom:26px; }
.type-btn { flex:1;padding:11px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--muted);font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s; }
.type-btn.active.expense { background:rgba(255,77,109,.12);border-color:rgba(255,77,109,.4);color:var(--danger); }
.type-btn.active.income  { background:rgba(0,229,160,.12);border-color:rgba(0,229,160,.4);color:var(--accent); }
.field { margin-bottom:20px; }
.field label { display:block;font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px; }
.field input,.field select,.field textarea { width:100%;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;transition:border-color .2s,box-shadow .2s;outline:none; }
.field input:focus,.field select:focus,.field textarea:focus { border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,229,160,.1); }
.field select option { background:var(--surface); }
.field textarea { resize:vertical;min-height:80px; }
.row2 { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
@media(max-width:500px){ .row2{grid-template-columns:1fr;} }
.amount-wrap { position:relative; }
.amount-prefix { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600; }
.amount-wrap input { padding-left:30px; }
.msg { padding:12px 16px;border-radius:10px;font-size:.875rem;margin-bottom:18px; }
.msg.success { background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.3);color:var(--accent); }
.msg.error   { background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:var(--danger); }
</style>

<div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;">
  <a href="transactions.php" class="btn-ghost" style="padding:7px 14px;font-size:.8rem;">← Back</a>
  <span style="color:var(--muted);font-size:.85rem;">Editing transaction #<?= $id ?></span>
</div>

<div class="form-card">
  <?php if ($error):   ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="msg success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST">
    <div class="type-toggle">
      <button type="button" class="type-btn expense <?= $tx['type']==='expense'?'active':'' ?>" data-type="expense">↓ Expense</button>
      <button type="button" class="type-btn income  <?= $tx['type']==='income' ?'active':'' ?>" data-type="income">↑ Income</button>
    </div>
    <input type="hidden" name="type" id="type-input" value="<?= htmlspecialchars($tx['type']) ?>">

    <div class="field">
      <label>Amount</label>
      <div class="amount-wrap">
        <span class="amount-prefix">₱</span>
        <input type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($tx['amount']) ?>" required>
      </div>
    </div>

    <div class="row2">
      <div class="field">
        <label>Category</label>
        <select name="category_id">
          <option value="">— None —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $cat['id']==$tx['category_id']?'selected':'' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Date</label>
        <input type="date" name="transaction_date" value="<?= htmlspecialchars($tx['transaction_date']) ?>" required>
      </div>
    </div>

    <div class="field">
      <label>Description</label>
      <textarea name="description"><?= htmlspecialchars($tx['description']) ?></textarea>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn-primary">Update Transaction</button>
      <a href="transactions.php" class="btn-ghost">Cancel</a>
      <a href="delete_transaction.php?id=<?= $id ?>" class="btn-danger"
         onclick="return confirm('Delete this transaction permanently?')">Delete</a>
    </div>
  </form>
</div>

<script>
const typeInput = document.getElementById('type-input');
document.querySelectorAll('.type-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    typeInput.value = btn.dataset.type;
  });
});
</script>

<?php close_layout(); ?>
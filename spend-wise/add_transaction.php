<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

// Handle AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $type        = $_POST['type'] ?? '';
    $amount      = (float)($_POST['amount'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $date        = $_POST['transaction_date'] ?? date('Y-m-d');

    if (!in_array($type, ['income','expense']) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$current_user_id, $category_id ?: null, $type, $amount, $description, $date]);

    echo json_encode(['success' => true, 'message' => 'Transaction added!']);
    exit;
}

// Load categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$current_user_id]);
$categories = $stmt->fetchAll();

$prefill_type = $_GET['type'] ?? 'expense';

open_layout('Add Transaction');
?>

<style>
.form-card { background:var(--card);border:1px solid var(--border);border-radius:18px;padding:28px 32px;max-width:600px; }
.type-toggle { display:flex;gap:8px;margin-bottom:26px; }
.type-btn {
  flex:1;padding:11px;border-radius:10px;border:1px solid var(--border);
  background:transparent;color:var(--muted);font-family:'Syne',sans-serif;
  font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;
}
.type-btn.active.expense { background:rgba(255,77,109,.12);border-color:rgba(255,77,109,.4);color:var(--danger); }
.type-btn.active.income  { background:rgba(0,229,160,.12);border-color:rgba(0,229,160,.4);color:var(--accent); }
.field { margin-bottom:20px; }
.field label { display:block;font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px; }
.field input, .field select, .field textarea {
  width:100%;background:var(--surface);border:1px solid var(--border);border-radius:10px;
  padding:12px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;
  transition:border-color .2s,box-shadow .2s;outline:none;
}
.field input:focus, .field select:focus, .field textarea:focus {
  border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,229,160,.1);
}
.field select option { background:var(--surface); }
.field textarea { resize:vertical;min-height:80px; }
.row2 { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
@media(max-width:500px){ .row2 { grid-template-columns:1fr; } }
.amount-wrap { position:relative; }
.amount-prefix { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600; }
.amount-wrap input { padding-left:30px; }

.toast {
  display:none;position:fixed;bottom:28px;right:28px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:12px;padding:14px 20px;font-size:.88rem;
  box-shadow:0 8px 32px rgba(0,0,0,.4);z-index:9999;
  animation:slideUp .3s ease;
}
.toast.show { display:flex;align-items:center;gap:10px; }
.toast.success { border-color:rgba(0,229,160,.4);color:var(--accent); }
.toast.error   { border-color:rgba(255,77,109,.4);color:var(--danger); }
@keyframes slideUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

#submit-btn { min-width:160px; }
#submit-btn.loading { opacity:.7;pointer-events:none; }
</style>

<div class="form-card">
  <form id="tx-form">

    <!-- Type toggle -->
    <div class="type-toggle">
      <button type="button" class="type-btn expense <?= $prefill_type==='expense'?'active':'' ?>" data-type="expense">
        ↓ Expense
      </button>
      <button type="button" class="type-btn income <?= $prefill_type==='income'?'active':'' ?>" data-type="income">
        ↑ Income
      </button>
    </div>
    <input type="hidden" name="type" id="type-input" value="<?= htmlspecialchars($prefill_type) ?>">

    <!-- Amount -->
    <div class="field">
      <label>Amount</label>
      <div class="amount-wrap">
        <span class="amount-prefix">₱</span>
        <input type="number" name="amount" id="amount" placeholder="0.00" step="0.01" min="0.01" required>
      </div>
    </div>

    <div class="row2">
      <!-- Category -->
      <div class="field">
        <label>Category</label>
        <select name="category_id">
          <option value="">— Select —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Date -->
      <div class="field">
        <label>Date</label>
        <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
      </div>
    </div>

    <!-- Description -->
    <div class="field">
      <label>Description <span style="color:var(--muted);font-size:.75rem;text-transform:none">(optional)</span></label>
      <textarea name="description" placeholder="e.g. Lunch at Jollibee"></textarea>
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button type="submit" class="btn-primary" id="submit-btn">Save Transaction</button>
      <a href="dashboard.php" class="btn-ghost">Cancel</a>
      <span id="form-msg" style="font-size:.82rem;color:var(--muted);display:none;"></span>
    </div>

  </form>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const typeInput = document.getElementById('type-input');
const typeBtns  = document.querySelectorAll('.type-btn');

typeBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    typeBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    typeInput.value = btn.dataset.type;
  });
});

document.getElementById('tx-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('submit-btn');
  btn.classList.add('loading');
  btn.textContent = 'Saving…';

  try {
    const res  = await fetch('add_transaction.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(this)
    });
    const data = await res.json();

    showToast(data.message, data.success ? 'success' : 'error');

    if (data.success) {
      this.reset();
      // reset date to today, type to expense
      this.querySelector('[name="transaction_date"]').value = new Date().toISOString().split('T')[0];
      typeBtns.forEach((b,i) => b.classList.toggle('active', i===0));
      typeInput.value = 'expense';
    }
  } catch (err) {
    showToast('Something went wrong. Try again.', 'error');
  }

  btn.classList.remove('loading');
  btn.textContent = 'Save Transaction';
});

function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = (type === 'success' ? '✓ ' : '✕ ') + msg;
  t.className = 'toast show ' + type;
  setTimeout(() => t.classList.remove('show'), 3500);
}
</script>

<?php close_layout(); ?>
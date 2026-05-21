<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

$filter_month = $_GET['month'] ?? date('Y-m');
$filter_type  = $_GET['type']  ?? '';

// ── Fetch transactions for export preview ─────────────────────
$where  = ["t.user_id = :uid"];
$params = [':uid' => $current_user_id];

if ($filter_month) {
    $where[]          = "DATE_FORMAT(t.transaction_date,'%Y-%m') = :month";
    $params[':month'] = $filter_month;
}
if ($filter_type && in_array($filter_type, ['income','expense'])) {
    $where[]          = "t.type = :type";
    $params[':type']  = $filter_type;
}

$sql = "
    SELECT t.transaction_date, t.type, t.amount, t.description,
           c.name AS category
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY t.transaction_date DESC, t.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Totals
$income   = array_sum(array_map(fn($r) => $r['type']==='income'  ? (float)$r['amount'] : 0, $rows));
$expenses = array_sum(array_map(fn($r) => $r['type']==='expense' ? (float)$r['amount'] : 0, $rows));
$savings  = $income - $expenses;

// Available months
$monthStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(transaction_date,'%Y-%m') AS ym,
           DATE_FORMAT(transaction_date,'%M %Y') AS label
    FROM transactions WHERE user_id = ?
    ORDER BY ym DESC
");
$monthStmt->execute([$current_user_id]);
$months = $monthStmt->fetchAll();
if (empty($months)) $months = [['ym' => date('Y-m'), 'label' => date('F Y')]];

$month_label = date('F Y', strtotime($filter_month . '-01'));

// JSON-encode rows for JS export
$js_rows = json_encode(array_map(fn($r) => [
    'date'        => $r['transaction_date'],
    'type'        => ucfirst($r['type']),
    'category'    => $r['category'] ?? 'Uncategorized',
    'description' => $r['description'] ?? '',
    'amount'      => (float)$r['amount'],
], $rows));

open_layout('Export Reports');
?>

<style>
.export-grid { display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start; }
@media(max-width:860px){ .export-grid { grid-template-columns:1fr; } }

/* Filter bar */
.filter-bar { display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:22px; }
.filter-bar select { background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s; }
.filter-bar select:focus { border-color:var(--accent); }
.filter-bar select option { background:var(--surface); }
.filter-btn { padding:8px 18px;background:var(--accent);color:#0b0f0e;border:none;border-radius:8px;font-family:'Syne',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer; }
.filter-btn:hover { background:#00ffb3; }

/* Preview table */
.panel { background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden; }
.panel-header { display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--border); }
.panel-title { font-family:'Syne',sans-serif;font-size:.92rem;font-weight:700; }
table { width:100%;border-collapse:collapse; }
th { font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;padding:10px 20px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap; }
td { padding:10px 20px;font-size:.85rem;border-bottom:1px solid rgba(31,43,40,.4); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.015); }
.badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:500; }
.badge.income  { background:rgba(0,229,160,.12);color:var(--accent); }
.badge.expense { background:rgba(255,77,109,.12);color:var(--danger); }
.empty { text-align:center;padding:48px;color:var(--muted);font-size:.875rem; }

/* Export sidebar */
.export-card { background:var(--card);border:1px solid var(--border);border-radius:16px;padding:22px; }
.export-card h3 { font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;margin-bottom:18px; }
.export-btn {
  display:flex;align-items:center;gap:12px;width:100%;padding:14px 16px;
  border-radius:12px;border:1px solid var(--border);background:transparent;
  color:var(--text);font-family:'DM Sans',sans-serif;font-size:.875rem;
  cursor:pointer;transition:background .2s,border-color .2s;margin-bottom:10px;text-align:left;
}
.export-btn:hover { background:var(--surface);border-color:var(--accent); }
.export-btn:active { transform:scale(.98); }
.export-icon { width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:18px;flex-shrink:0; }
.export-icon.pdf   { background:rgba(255,77,109,.12); }
.export-icon.excel { background:rgba(0,229,160,.12); }
.export-label { font-weight:500; }
.export-sub   { font-size:.75rem;color:var(--muted);margin-top:2px; }

.summary-box { background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-top:16px; }
.summary-row { display:flex;justify-content:space-between;font-size:.85rem;padding:5px 0;border-bottom:1px solid rgba(31,43,40,.4); }
.summary-row:last-child { border-bottom:none;font-weight:600;padding-top:10px; }
.summary-row span:last-child { font-family:'Syne',sans-serif; }

.toast { display:none;position:absolute;bottom:20px;right:20px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 18px;font-size:.82rem;z-index:999; }
.toast.show { display:flex;align-items:center;gap:8px; }
.toast.success { border-color:rgba(0,229,160,.4);color:var(--accent); }
</style>

<!-- Filter -->
<form method="GET" class="filter-bar">
  <select name="month">
    <?php foreach ($months as $m): ?>
      <option value="<?= $m['ym'] ?>" <?= $m['ym']===$filter_month?'selected':'' ?>><?= $m['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type">
    <option value="">All Types</option>
    <option value="income"  <?= $filter_type==='income'  ?'selected':'' ?>>Income only</option>
    <option value="expense" <?= $filter_type==='expense' ?'selected':'' ?>>Expenses only</option>
  </select>
  <button type="submit" class="filter-btn">Apply</button>
</form>

<div class="export-grid">

  <!-- Preview table -->
  <div class="panel" style="position:relative">
    <div class="panel-header">
      <span class="panel-title">Preview — <?= htmlspecialchars($month_label) ?></span>
      <span style="font-size:.78rem;color:var(--muted)"><?= count($rows) ?> record<?= count($rows)!==1?'s':'' ?></span>
    </div>

    <?php if (empty($rows)): ?>
      <div class="empty">No transactions found for this period.</div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table id="preview-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Category</th>
              <th>Description</th>
              <th style="text-align:right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td style="white-space:nowrap;color:var(--muted)"><?= date('M j, Y', strtotime($r['transaction_date'])) ?></td>
              <td><span class="badge <?= $r['type'] ?>"><?= ucfirst($r['type']) ?></span></td>
              <td><?= htmlspecialchars($r['category'] ?? 'Uncategorized') ?></td>
              <td><?= htmlspecialchars($r['description'] ?? '—') ?></td>
              <td style="text-align:right;font-family:'Syne',sans-serif;font-weight:600;color:<?= $r['type']==='income'?'var(--accent)':'var(--danger)' ?>">
                <?= $r['type']==='income'?'+':'−' ?>₱<?= number_format($r['amount'],2) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="toast" id="toast"></div>
  </div>

  <!-- Export sidebar -->
  <div>
    <div class="export-card">
      <h3>Export Data</h3>

      <button class="export-btn" onclick="exportPDF()">
        <div class="export-icon pdf">📄</div>
        <div>
          <div class="export-label">Export as PDF</div>
          <div class="export-sub">Formatted report with summary</div>
        </div>
      </button>

      <button class="export-btn" onclick="exportExcel()">
        <div class="export-icon excel">📊</div>
        <div>
          <div class="export-label">Export as Excel</div>
          <div class="export-sub">Spreadsheet with all columns</div>
        </div>
      </button>

      <div class="summary-box">
        <div class="summary-row">
          <span>Income</span>
          <span style="color:var(--accent)">₱<?= number_format($income, 2) ?></span>
        </div>
        <div class="summary-row">
          <span>Expenses</span>
          <span style="color:var(--danger)">₱<?= number_format($expenses, 2) ?></span>
        </div>
        <div class="summary-row">
          <span>Net Savings</span>
          <span style="color:<?= $savings>=0?'var(--accent)':'var(--danger)' ?>">₱<?= number_format(abs($savings), 2) ?></span>
        </div>
      </div>
    </div>
  </div>

</div><!-- .export-grid -->

<!-- jsPDF + SheetJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
const DATA     = <?= $js_rows ?>;
const MONTH    = <?= json_encode($month_label) ?>;
const INCOME   = <?= json_encode(number_format($income,   2)) ?>;
const EXPENSES = <?= json_encode(number_format($expenses, 2)) ?>;
const SAVINGS  = <?= json_encode(number_format(abs($savings), 2)) ?>;
const SAV_SIGN = <?= $savings >= 0 ? 'true' : 'false' ?>;

function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = (type==='success'?'✓ ':'✕ ') + msg;
  t.className = 'toast show ' + type;
  setTimeout(() => t.classList.remove('show'), 3500);
}

// ── PDF Export ────────────────────────────────────────────────
function exportPDF() {
  if (!DATA.length) { showToast('No data to export.', 'error'); return; }

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });

  const accent  = [0, 229, 160];
  const danger  = [255, 77, 109];
  const dark    = [22, 32, 32];
  const muted   = [107, 140, 128];
  const pageW   = doc.internal.pageSize.getWidth();

  // Header bar
  doc.setFillColor(...dark);
  doc.rect(0, 0, pageW, 28, 'F');

  doc.setFontSize(16);
  doc.setTextColor(0, 229, 160);
  doc.setFont('helvetica','bold');
  doc.text('SpendWise', 14, 12);

  doc.setFontSize(9);
  doc.setTextColor(200, 220, 215);
  doc.setFont('helvetica','normal');
  doc.text('Expense Report', 14, 19);
  doc.text(MONTH, pageW - 14, 19, { align:'right' });

  // Summary chips
  const chips = [
    { label:'Income',   val:'PHP ' + INCOME,   color: accent },
    { label:'Expenses', val:'PHP ' + EXPENSES, color: danger },
    { label:'Savings',  val:'PHP ' + SAVINGS,  color: SAV_SIGN ? accent : danger },
  ];
  let cx = 14;
  chips.forEach(chip => {
    doc.setFillColor(30, 44, 44);
    doc.roundedRect(cx, 34, 56, 18, 3, 3, 'F');
    doc.setFontSize(7);
    doc.setTextColor(...muted);
    doc.text(chip.label.toUpperCase(), cx + 5, 40);
    doc.setFontSize(10);
    doc.setTextColor(...chip.color);
    doc.setFont('helvetica','bold');
    doc.text(chip.val, cx + 5, 47);
    doc.setFont('helvetica','normal');
    cx += 62;
  });

  // Table
  doc.autoTable({
    startY: 58,
    head: [['Date','Type','Category','Description','Amount']],
    body: DATA.map(r => [
      r.date,
      r.type,
      r.category,
      r.description || '—',
      (r.type==='Income' ? '+' : '−') + 'PHP ' + r.amount.toLocaleString('en-PH',{minimumFractionDigits:2}),
    ]),
    styles: {
      fontSize: 8,
      cellPadding: 3,
      textColor: [200, 220, 215],
      lineColor: [31, 43, 40],
      lineWidth: 0.3,
    },
    headStyles: {
      fillColor: dark,
      textColor: [107, 140, 128],
      fontStyle: 'bold',
      fontSize: 7,
    },
    alternateRowStyles: { fillColor: [18, 25, 24] },
    bodyStyles: { fillColor: [22, 32, 32] },
    columnStyles: {
      0: { cellWidth: 26 },
      1: { cellWidth: 20 },
      2: { cellWidth: 32 },
      3: { cellWidth: 'auto' },
      4: { cellWidth: 36, halign:'right' },
    },
    didParseCell(data) {
      if (data.section === 'body' && data.column.index === 1) {
        data.cell.styles.textColor = data.cell.raw === 'Income' ? accent : danger;
      }
      if (data.section === 'body' && data.column.index === 4) {
        data.cell.styles.textColor = data.cell.raw.startsWith('+') ? accent : danger;
      }
    },
    margin: { left:14, right:14 },
  });

  // Footer
  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(7);
    doc.setTextColor(...muted);
    doc.text(
      `Generated by SpendWise · Page ${i} of ${pageCount}`,
      pageW / 2,
      doc.internal.pageSize.getHeight() - 8,
      { align:'center' }
    );
  }

  doc.save(`SpendWise_${MONTH.replace(' ','_')}.pdf`);
  showToast('PDF downloaded!');
}

// ── Excel Export ──────────────────────────────────────────────
function exportExcel() {
  if (!DATA.length) { showToast('No data to export.', 'error'); return; }

  const wb = XLSX.utils.book_new();

  // ─ Sheet 1: Transactions ─
  const txHeaders = ['Date','Type','Category','Description','Amount (PHP)'];
  const txRows = DATA.map(r => [
    r.date, r.type, r.category,
    r.description || '',
    r.type === 'Income' ? r.amount : -r.amount,
  ]);
  const txSheet = XLSX.utils.aoa_to_sheet([txHeaders, ...txRows]);

  // Column widths
  txSheet['!cols'] = [
    { wch:14 },{ wch:10 },{ wch:16 },{ wch:32 },{ wch:16 },
  ];

  // Style header row (background + bold) — SheetJS free doesn't support full styling,
  // but we can set cell types and number formats
  txRows.forEach((row, i) => {
    const cell = txSheet[XLSX.utils.encode_cell({ r: i+1, c: 4 })];
    if (cell) {
      cell.t = 'n';
      cell.z = '#,##0.00';
    }
  });

  XLSX.utils.book_append_sheet(wb, txSheet, 'Transactions');

  // ─ Sheet 2: Summary ─
  const sumData = [
    ['SpendWise — ' + MONTH],
    [],
    ['Summary'],
    ['Income',  parseFloat(INCOME.replace(/,/g,''))],
    ['Expenses', parseFloat(EXPENSES.replace(/,/g,''))],
    ['Net Savings', SAV_SIGN
      ? parseFloat(SAVINGS.replace(/,/g,''))
      : -parseFloat(SAVINGS.replace(/,/g,''))],
    [],
    ['Generated', new Date().toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})],
  ];
  const sumSheet = XLSX.utils.aoa_to_sheet(sumData);
  sumSheet['!cols'] = [{ wch:18 },{ wch:18 }];
  // Number format for summary values
  ['B4','B5','B6'].forEach(addr => {
    if (sumSheet[addr]) { sumSheet[addr].t = 'n'; sumSheet[addr].z = '#,##0.00'; }
  });
  XLSX.utils.book_append_sheet(wb, sumSheet, 'Summary');

  XLSX.writeFile(wb, `SpendWise_${MONTH.replace(' ','_')}.xlsx`);
  showToast('Excel file downloaded!');
}
</script>

<?php close_layout(); ?>
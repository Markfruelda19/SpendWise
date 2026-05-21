<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

$selected_year  = (int)($_GET['year']  ?? date('Y'));
$selected_month = $_GET['month'] ?? date('Y-m');

// ── 1. Monthly income vs expenses (bar) — full selected year ──
$barStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS ym,
        DATE_FORMAT(transaction_date, '%b')    AS label,
        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS expenses
    FROM transactions
    WHERE user_id = ? AND YEAR(transaction_date) = ?
    GROUP BY ym, label
    ORDER BY ym ASC
");
$barStmt->execute([$current_user_id, $selected_year]);
$barRows = $barStmt->fetchAll();

// ── 2. Expense by category (pie) — selected month ─────────────
$pieStmt = $pdo->prepare("
    SELECT c.name, COALESCE(SUM(t.amount), 0) AS total
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense'
      AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
    GROUP BY c.name
    ORDER BY total DESC
");
$pieStmt->execute([$current_user_id, $selected_month]);
$pieRows = $pieStmt->fetchAll();

// ── 3. Daily spending line chart — selected month ─────────────
$lineStmt = $pdo->prepare("
    SELECT
        DAY(transaction_date)   AS day,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expenses,
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income
    FROM transactions
    WHERE user_id = ? AND DATE_FORMAT(transaction_date,'%Y-%m') = ?
    GROUP BY DAY(transaction_date)
    ORDER BY day ASC
");
$lineStmt->execute([$current_user_id, $selected_month]);
$lineRows = $lineStmt->fetchAll();

// ── 4. Summary totals for selected month ──────────────────────
$sumStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS expenses,
        COUNT(*) AS tx_count
    FROM transactions
    WHERE user_id = ? AND DATE_FORMAT(transaction_date,'%Y-%m') = ?
");
$sumStmt->execute([$current_user_id, $selected_month]);
$sum = $sumStmt->fetch();
$savings = (float)$sum['income'] - (float)$sum['expenses'];

// ── Available years for filter ────────────────────────────────
$yearStmt = $pdo->prepare("
    SELECT DISTINCT YEAR(transaction_date) AS yr
    FROM transactions WHERE user_id = ?
    ORDER BY yr DESC
");
$yearStmt->execute([$current_user_id]);
$years = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [date('Y')];

// ── Available months for filter ───────────────────────────────
$monthStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(transaction_date,'%Y-%m') AS ym,
           DATE_FORMAT(transaction_date,'%M %Y') AS label
    FROM transactions WHERE user_id = ?
    ORDER BY ym DESC
");
$monthStmt->execute([$current_user_id]);
$months = $monthStmt->fetchAll();
if (empty($months)) {
    $months = [['ym' => date('Y-m'), 'label' => date('F Y')]];
}

// ── Serialize chart data to JSON ──────────────────────────────
$bar_labels   = json_encode(array_column($barRows, 'label'));
$bar_income   = json_encode(array_map(fn($r) => (float)$r['income'],   $barRows));
$bar_expenses = json_encode(array_map(fn($r) => (float)$r['expenses'], $barRows));

$pie_labels = json_encode(array_column($pieRows, 'name'));
$pie_data   = json_encode(array_map(fn($r) => (float)$r['total'], $pieRows));

// Build a full day-by-day array for the selected month
$days_in_month = (int)date('t', strtotime($selected_month . '-01'));
$line_days = range(1, $days_in_month);
$line_exp  = array_fill(0, $days_in_month, 0);
$line_inc  = array_fill(0, $days_in_month, 0);
foreach ($lineRows as $r) {
    $idx = (int)$r['day'] - 1;
    $line_exp[$idx] = (float)$r['expenses'];
    $line_inc[$idx] = (float)$r['income'];
}
$line_labels   = json_encode($line_days);
$line_expenses = json_encode($line_exp);
$line_income   = json_encode($line_inc);

open_layout('Reports');
?>

<style>
/* ── Filters ── */
.report-filters {
  display:flex;gap:10px;flex-wrap:wrap;align-items:center;
  background:var(--card);border:1px solid var(--border);
  border-radius:14px;padding:14px 18px;margin-bottom:22px;
}
.report-filters select,.report-filters input {
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  padding:8px 12px;color:var(--text);font-family:'DM Sans',sans-serif;
  font-size:.85rem;outline:none;transition:border-color .2s;
}
.report-filters select:focus { border-color:var(--accent); }
.report-filters select option { background:var(--surface); }
.filter-btn {
  padding:8px 18px;background:var(--accent);color:#0b0f0e;
  border:none;border-radius:8px;font-family:'Syne',sans-serif;
  font-size:.82rem;font-weight:700;cursor:pointer;
}
.filter-btn:hover { background:#00ffb3; }

/* ── Stat strip ── */
.stat-strip { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px; }
.stat-chip { background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 18px; }
.stat-chip .s-label { font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px; }
.stat-chip .s-val { font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;letter-spacing:-.03em; }
.s-val.inc  { color:var(--accent); }
.s-val.exp  { color:var(--danger); }
.s-val.sav  { color:var(--text); }

/* ── Chart grid ── */
.chart-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px; }
.chart-full  { grid-column:1/-1; }
@media(max-width:800px){ .chart-grid { grid-template-columns:1fr; } }

.chart-panel {
  background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;
}
.chart-panel-header {
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 22px;border-bottom:1px solid var(--border);
}
.chart-panel-title { font-family:'Syne',sans-serif;font-size:.92rem;font-weight:700; }
.chart-panel-sub   { font-size:.76rem;color:var(--muted); }
.chart-body { padding:20px 22px; }

/* ── Top categories table ── */
.cat-table { width:100%;border-collapse:collapse;font-size:.85rem; }
.cat-table th { font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;padding:8px 0;border-bottom:1px solid var(--border); }
.cat-table td { padding:10px 0;border-bottom:1px solid rgba(31,43,40,.4); }
.cat-table tr:last-child td { border-bottom:none; }
.cat-bar-bg { width:100%;height:5px;background:var(--border);border-radius:4px;margin-top:5px; }
.cat-bar-fill { height:5px;border-radius:4px; }
.empty-state { text-align:center;padding:48px 16px;color:var(--muted);font-size:.875rem; }
</style>

<!-- Filters -->
<form method="GET" class="report-filters">
  <label style="font-size:.82rem;color:var(--muted)">Year</label>
  <select name="year">
    <?php foreach ($years as $y): ?>
      <option value="<?= $y ?>" <?= $y==$selected_year?'selected':'' ?>><?= $y ?></option>
    <?php endforeach; ?>
  </select>

  <label style="font-size:.82rem;color:var(--muted)">Month</label>
  <select name="month">
    <?php foreach ($months as $m): ?>
      <option value="<?= $m['ym'] ?>" <?= $m['ym']===$selected_month?'selected':'' ?>><?= $m['label'] ?></option>
    <?php endforeach; ?>
  </select>

  <button type="submit" class="filter-btn">Apply</button>
</form>

<!-- Summary strip -->
<div class="stat-strip">
  <div class="stat-chip">
    <div class="s-label">Income</div>
    <div class="s-val inc">₱<?= number_format($sum['income'],   2) ?></div>
  </div>
  <div class="stat-chip">
    <div class="s-label">Expenses</div>
    <div class="s-val exp">₱<?= number_format($sum['expenses'], 2) ?></div>
  </div>
  <div class="stat-chip">
    <div class="s-label">Savings</div>
    <div class="s-val sav <?= $savings>=0?'inc':'exp' ?>">₱<?= number_format(abs($savings), 2) ?></div>
  </div>
  <div class="stat-chip">
    <div class="s-label">Transactions</div>
    <div class="s-val sav"><?= $sum['tx_count'] ?></div>
  </div>
</div>

<!-- Chart grid -->
<div class="chart-grid">

  <!-- Bar: Monthly Income vs Expenses -->
  <div class="chart-panel chart-full">
    <div class="chart-panel-header">
      <span class="chart-panel-title">Monthly Income vs Expenses</span>
      <span class="chart-panel-sub"><?= $selected_year ?></span>
    </div>
    <div class="chart-body">
      <?php if (empty($barRows)): ?>
        <div class="empty-state">No data for <?= $selected_year ?>.</div>
      <?php else: ?>
        <canvas id="barChart" height="90"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pie: Expenses by category -->
  <div class="chart-panel">
    <div class="chart-panel-header">
      <span class="chart-panel-title">Expenses by Category</span>
      <span class="chart-panel-sub"><?= date('F Y', strtotime($selected_month.'-01')) ?></span>
    </div>
    <div class="chart-body">
      <?php if (empty($pieRows)): ?>
        <div class="empty-state">No expenses this month.</div>
      <?php else: ?>
        <canvas id="pieChart" height="220"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Line: Daily spending -->
  <div class="chart-panel">
    <div class="chart-panel-header">
      <span class="chart-panel-title">Daily Spending</span>
      <span class="chart-panel-sub"><?= date('F Y', strtotime($selected_month.'-01')) ?></span>
    </div>
    <div class="chart-body">
      <?php if (empty($lineRows)): ?>
        <div class="empty-state">No data this month.</div>
      <?php else: ?>
        <canvas id="lineChart" height="220"></canvas>
      <?php endif; ?>
    </div>
  </div>

</div><!-- .chart-grid -->

<!-- Top categories breakdown table -->
<div class="chart-panel">
  <div class="chart-panel-header">
    <span class="chart-panel-title">Category Breakdown</span>
    <span class="chart-panel-sub"><?= date('F Y', strtotime($selected_month.'-01')) ?></span>
  </div>
  <div class="chart-body">
    <?php if (empty($pieRows)): ?>
      <div class="empty-state">No expense data for this month.</div>
    <?php else:
      $total_exp = array_sum(array_column($pieRows, 'total'));
      $palette = ['#00e5a0','#ff4d6d','#ffb84d','#7b61ff','#00b4d8','#f77f00','#ef233c','#4cc9f0'];
    ?>
      <table class="cat-table">
        <thead>
          <tr>
            <th>Category</th>
            <th style="text-align:right">Amount</th>
            <th style="text-align:right">Share</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pieRows as $i => $row):
            $pct = $total_exp > 0 ? round(($row['total'] / $total_exp) * 100, 1) : 0;
            $col = $palette[$i % count($palette)];
          ?>
          <tr>
            <td>
              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $col ?>;margin-right:8px;vertical-align:middle;"></span>
              <?= htmlspecialchars($row['name'] ?? 'Uncategorized') ?>
              <div class="cat-bar-bg">
                <div class="cat-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
              </div>
            </td>
            <td style="text-align:right;font-family:'Syne',sans-serif;font-weight:600;color:var(--danger)">
              ₱<?= number_format($row['total'], 2) ?>
            </td>
            <td style="text-align:right;color:var(--muted)"><?= $pct ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── Shared theme ──────────────────────────────────────────────
const accent  = '#00e5a0';
const danger  = '#ff4d6d';
const warn    = '#ffb84d';
const muted   = '#6b8c80';
const border  = '#1f2b28';
const text    = '#e8f0ed';

const palette = ['#00e5a0','#ff4d6d','#ffb84d','#7b61ff','#00b4d8','#f77f00','#ef233c','#4cc9f0'];

Chart.defaults.color = muted;
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 12;

const gridOpts = {
  color: border,
  drawBorder: false,
};
const noLegend = { display: false };

// ── Bar Chart ─────────────────────────────────────────────────
const barEl = document.getElementById('barChart');
if (barEl) {
  new Chart(barEl, {
    type: 'bar',
    data: {
      labels:   <?= $bar_labels ?>,
      datasets: [
        {
          label: 'Income',
          data: <?= $bar_income ?>,
          backgroundColor: 'rgba(0,229,160,.65)',
          borderColor:     accent,
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false,
        },
        {
          label: 'Expenses',
          data: <?= $bar_expenses ?>,
          backgroundColor: 'rgba(255,77,109,.55)',
          borderColor:     danger,
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          align: 'end',
          labels: { color: muted, boxWidth: 12, boxHeight: 12, borderRadius: 4, useBorderRadius: true, padding: 16 },
        },
        tooltip: {
          backgroundColor: '#162020',
          borderColor: border,
          borderWidth: 1,
          padding: 12,
          callbacks: {
            label: ctx => ` ₱${ctx.parsed.y.toLocaleString('en-PH', {minimumFractionDigits:2})}`,
          },
        },
      },
      scales: {
        x: { grid: gridOpts, ticks: { color: muted } },
        y: {
          grid: gridOpts,
          ticks: {
            color: muted,
            callback: v => '₱' + v.toLocaleString(),
          },
        },
      },
    },
  });
}

// ── Pie Chart ─────────────────────────────────────────────────
const pieEl = document.getElementById('pieChart');
if (pieEl) {
  new Chart(pieEl, {
    type: 'doughnut',
    data: {
      labels:   <?= $pie_labels ?>,
      datasets: [{
        data:            <?= $pie_data ?>,
        backgroundColor: palette,
        borderColor:     '#162020',
        borderWidth:     3,
        hoverOffset:     6,
      }],
    },
    options: {
      responsive: true,
      cutout: '62%',
      plugins: {
        legend: {
          display: true,
          position: 'right',
          labels: {
            color: text,
            boxWidth: 10,
            boxHeight: 10,
            borderRadius: 3,
            useBorderRadius: true,
            padding: 12,
            font: { size: 11 },
          },
        },
        tooltip: {
          backgroundColor: '#162020',
          borderColor: border,
          borderWidth: 1,
          padding: 12,
          callbacks: {
            label: ctx => ` ₱${ctx.parsed.toLocaleString('en-PH', {minimumFractionDigits:2})}`,
          },
        },
      },
    },
  });
}

// ── Line Chart ────────────────────────────────────────────────
const lineEl = document.getElementById('lineChart');
if (lineEl) {
  new Chart(lineEl, {
    type: 'line',
    data: {
      labels: <?= $line_labels ?>,
      datasets: [
        {
          label: 'Expenses',
          data:  <?= $line_expenses ?>,
          borderColor:     danger,
          backgroundColor: 'rgba(255,77,109,.08)',
          pointBackgroundColor: danger,
          pointRadius: 3,
          pointHoverRadius: 5,
          tension: 0.4,
          fill: true,
        },
        {
          label: 'Income',
          data:  <?= $line_income ?>,
          borderColor:     accent,
          backgroundColor: 'rgba(0,229,160,.06)',
          pointBackgroundColor: accent,
          pointRadius: 3,
          pointHoverRadius: 5,
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          align: 'end',
          labels: { color: muted, boxWidth: 12, boxHeight: 12, borderRadius: 4, useBorderRadius: true, padding: 14 },
        },
        tooltip: {
          backgroundColor: '#162020',
          borderColor: border,
          borderWidth: 1,
          padding: 12,
          callbacks: {
            title: ctx => 'Day ' + ctx[0].label,
            label: ctx => ` ₱${ctx.parsed.y.toLocaleString('en-PH', {minimumFractionDigits:2})}`,
          },
        },
      },
      scales: {
        x: {
          grid: gridOpts,
          ticks: { color: muted, maxTicksLimit: 10 },
          title: { display: true, text: 'Day of month', color: muted, font: { size: 11 } },
        },
        y: {
          grid: gridOpts,
          ticks: { color: muted, callback: v => '₱' + v.toLocaleString() },
        },
      },
    },
  });
}
</script>

<?php close_layout(); ?>
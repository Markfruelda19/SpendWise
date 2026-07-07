<?php
/**
 * mobile/api/index.php
 * Single-entry JSON API for the SpendWise mobile app.
 * All requests: POST with JSON body { "action": "...", ...params }
 * Auth via PHP session (shared with desktop app).
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

session_start();
require_once '../../config/database.php';

// Auth check for all actions except login/register
$action = $_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

$public_actions = ['login', 'register'];
if (!in_array($action, $public_actions) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid = (int)($_SESSION['user_id'] ?? 0);

function ok(array $data = []): void {
    echo json_encode(['success' => true] + $data);
    exit;
}
function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

switch ($action) {

    // ── Auth ────────────────────────────────────────────────────
    case 'login':
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        if (!$email || !$password) fail('Email and password required.');
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) fail('Invalid credentials.', 401);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        ok(['user' => ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email']]]);

    case 'register':
        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        if (!$username || !$email || strlen($password) < 6) fail('All fields required. Password min 6 chars.');
        $chk = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) fail('Email already in use.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins  = $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?,?,?)");
        $ins->execute([$username, $email, $hash]);
        $new_uid = (int)$pdo->lastInsertId();
        $cats = ['Food','Transportation','Bills','Shopping','Salary','Entertainment','Health','Other'];
        $ci = $pdo->prepare("INSERT INTO categories (user_id,name) VALUES (?,?)");
        foreach ($cats as $c) $ci->execute([$new_uid, $c]);
        $_SESSION['user_id']  = $new_uid;
        $_SESSION['username'] = $username;
        ok(['user' => ['id' => $new_uid, 'username' => $username, 'email' => $email]]);

    case 'logout':
        session_destroy();
        ok();

    case 'me':
        ok(['user' => ['id' => $uid, 'username' => $_SESSION['username']]]);

    // ── Dashboard summary ───────────────────────────────────────
    case 'dashboard':
        $month = date('Y-m');
        $stmt = $pdo->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS income,
              COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expenses
            FROM transactions WHERE user_id=? AND DATE_FORMAT(transaction_date,'%Y-%m')=?
        ");
        $stmt->execute([$uid, $month]);
        $totals = $stmt->fetch();

        $all = $pdo->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS ti,
              COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS te
            FROM transactions WHERE user_id=?
        ");
        $all->execute([$uid]);
        $allrow = $all->fetch();

        // Recent 5
        $rec = $pdo->prepare("
            SELECT t.id,t.type,t.amount,t.description,t.transaction_date,c.name AS category
            FROM transactions t LEFT JOIN categories c ON t.category_id=c.id
            WHERE t.user_id=? ORDER BY t.transaction_date DESC,t.id DESC LIMIT 5
        ");
        $rec->execute([$uid]);

        // Expense by category this month
        $cat = $pdo->prepare("
            SELECT c.name, SUM(t.amount) AS total
            FROM transactions t LEFT JOIN categories c ON t.category_id=c.id
            WHERE t.user_id=? AND t.type='expense' AND DATE_FORMAT(t.transaction_date,'%Y-%m')=?
            GROUP BY c.name ORDER BY total DESC LIMIT 5
        ");
        $cat->execute([$uid, $month]);

        // Goals
        $goals = $pdo->prepare("
            SELECT g.id,g.name,g.amount,g.period,c.name AS category_name,
              COALESCE((SELECT SUM(t2.amount) FROM transactions t2
                WHERE t2.user_id=g.user_id AND t2.type='expense'
                AND (g.category_id IS NULL OR t2.category_id=g.category_id)
                AND DATE_FORMAT(t2.transaction_date,'%Y-%m')=?
              ),0) AS spent
            FROM budget_goals g LEFT JOIN categories c ON g.category_id=c.id
            WHERE g.user_id=? AND g.period='monthly'
            ORDER BY (spent/g.amount) DESC LIMIT 3
        ");
        $goals->execute([$month, $uid]);

        ok([
            'month'   => date('F Y'),
            'income'  => (float)$totals['income'],
            'expenses'=> (float)$totals['expenses'],
            'savings' => (float)$totals['income'] - (float)$totals['expenses'],
            'balance' => (float)$allrow['ti'] - (float)$allrow['te'],
            'recent'  => $rec->fetchAll(),
            'categories' => $cat->fetchAll(),
            'goals'   => $goals->fetchAll(),
        ]);

    // ── Transactions ────────────────────────────────────────────
    case 'transactions':
        $month  = $input['month']    ?? '';
        $type   = $input['type']     ?? '';
        $search = $input['search']   ?? '';
        $limit  = min((int)($input['limit'] ?? 30), 100);
        $offset = (int)($input['offset'] ?? 0);

        $where = ["t.user_id=:uid"]; $p = [':uid'=>$uid];
        if ($month) { $where[] = "DATE_FORMAT(t.transaction_date,'%Y-%m')=:month"; $p[':month']=$month; }
        if (in_array($type,['income','expense'])) { $where[] = "t.type=:type"; $p[':type']=$type; }
        if ($search) { $where[] = "t.description LIKE :s"; $p[':s']="%$search%"; }

        $sql = "SELECT t.*,c.name AS category FROM transactions t LEFT JOIN categories c ON t.category_id=c.id WHERE ".implode(' AND ',$where)." ORDER BY t.transaction_date DESC,t.id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql); $stmt->execute($p);
        ok(['transactions' => $stmt->fetchAll()]);

    case 'add_transaction':
        $type   = $input['type']   ?? '';
        $amount = (float)($input['amount'] ?? 0);
        $cat_id = ($input['category_id'] ?? '') !== '' ? (int)$input['category_id'] : null;
        $desc   = trim($input['description'] ?? '');
        $date   = $input['date'] ?? date('Y-m-d');
        if (!in_array($type,['income','expense']) || $amount <= 0) fail('Invalid data.');
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id,category_id,type,amount,description,transaction_date) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$uid,$cat_id,$type,$amount,$desc,$date]);
        ok(['id' => (int)$pdo->lastInsertId()]);

    case 'delete_transaction':
        $id = (int)($input['id'] ?? 0);
        if (!$id) fail('Missing id.');
        $pdo->prepare("DELETE FROM transactions WHERE id=? AND user_id=?")->execute([$id,$uid]);
        ok();

    // ── Categories ──────────────────────────────────────────────
    case 'categories':
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=? ORDER BY name");
        $stmt->execute([$uid]);
        ok(['categories' => $stmt->fetchAll()]);

    // ── Budget goals ────────────────────────────────────────────
    case 'goals':
        $month = date('Y-m');
        $stmt  = $pdo->prepare("
            SELECT g.*,c.name AS category_name,
              COALESCE((SELECT SUM(t.amount) FROM transactions t
                WHERE t.user_id=g.user_id AND t.type='expense'
                AND (g.category_id IS NULL OR t.category_id=g.category_id)
                AND (
                  (g.period='monthly' AND DATE_FORMAT(t.transaction_date,'%Y-%m')=?)
                  OR (g.period='yearly' AND YEAR(t.transaction_date)=?)
                )
              ),0) AS spent
            FROM budget_goals g LEFT JOIN categories c ON g.category_id=c.id
            WHERE g.user_id=? ORDER BY g.period,g.name
        ");
        $stmt->execute([$month, date('Y'), $uid]);
        ok(['goals' => $stmt->fetchAll()]);

    default:
        fail('Unknown action.', 404);
}

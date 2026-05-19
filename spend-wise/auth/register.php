<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';

    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hash]);

            // Seed default categories for new user
            $uid  = $pdo->lastInsertId();
            $cats = ['Food', 'Transportation', 'Bills', 'Shopping', 'Salary', 'Entertainment', 'Health', 'Other'];
            $ins  = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
            foreach ($cats as $c) { $ins->execute([$uid, $c]); }

            $success = "Account created! You can now sign in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpendWise — Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0b0f0e;
    --surface:   #131918;
    --border:    #1f2b28;
    --accent:    #00e5a0;
    --accent2:   #00b87a;
    --danger:    #ff4d6d;
    --success:   #00e5a0;
    --text:      #e8f0ed;
    --muted:     #6b8c80;
    --card:      #162020;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: grid;
    place-items: center;
    overflow: hidden;
  }
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse 60% 50% at 90% 10%, rgba(0,229,160,.07) 0%, transparent 70%),
      radial-gradient(ellipse 50% 60% at 10% 80%, rgba(0,229,160,.05) 0%, transparent 70%);
    pointer-events: none;
  }
  .wrap {
    width: 100%;
    max-width: 440px;
    padding: 24px;
    animation: fadeUp .6s ease both;
  }
  @keyframes fadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .logo { display:flex; align-items:center; gap:10px; margin-bottom:36px; }
  .logo-icon { width:38px; height:38px; background:var(--accent); border-radius:10px; display:grid; place-items:center; font-size:18px; }
  .logo-text { font-family:'Syne',sans-serif; font-size:1.4rem; font-weight:800; letter-spacing:-.03em; }
  .logo-text span { color:var(--accent); }
  h1 { font-family:'Syne',sans-serif; font-size:1.9rem; font-weight:700; line-height:1.1; margin-bottom:8px; letter-spacing:-.04em; }
  .subtitle { color:var(--muted); font-size:.95rem; margin-bottom:28px; }
  .card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:28px 32px; }
  .msg {
    border-radius:10px;
    padding:12px 16px;
    font-size:.875rem;
    margin-bottom:18px;
  }
  .msg.error   { background:rgba(255,77,109,.1); border:1px solid rgba(255,77,109,.3); color:var(--danger); }
  .msg.success { background:rgba(0,229,160,.1); border:1px solid rgba(0,229,160,.3); color:var(--success); }
  .row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .field { margin-bottom:16px; }
  label { display:block; font-size:.8rem; font-weight:500; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:8px; }
  input { width:100%; background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px 16px; color:var(--text); font-family:'DM Sans',sans-serif; font-size:.95rem; transition:border-color .2s,box-shadow .2s; outline:none; }
  input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,229,160,.1); }
  .btn { width:100%; padding:14px; background:var(--accent); color:#0b0f0e; border:none; border-radius:12px; font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; cursor:pointer; transition:background .2s,transform .1s; margin-top:4px; }
  .btn:hover { background:#00ffb3; }
  .btn:active { transform:scale(.98); }
  .divider { text-align:center; color:var(--muted); font-size:.85rem; margin:18px 0; position:relative; }
  .divider::before,.divider::after { content:''; position:absolute; top:50%; width:calc(50% - 24px); height:1px; background:var(--border); }
  .divider::before { left:0; } .divider::after { right:0; }
  .link-btn { display:block; text-align:center; color:var(--accent); font-size:.9rem; font-weight:500; text-decoration:none; padding:12px; border:1px solid var(--border); border-radius:12px; transition:background .2s,border-color .2s; }
  .link-btn:hover { background:rgba(0,229,160,.07); border-color:var(--accent2); }
  .hint { font-size:.78rem; color:var(--muted); margin-top:5px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">💰</div>
    <div class="logo-text">Spend<span>Wise</span></div>
  </div>

  <h1>Create your<br>account.</h1>
  <p class="subtitle">Start tracking your money today.</p>

  <div class="card">
    <?php if ($error):   ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg success"><?= htmlspecialchars($success) ?> <a href="login.php" style="color:var(--accent);font-weight:600;">Sign in →</a></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="username" placeholder="Juan dela Cruz" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="juan@email.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="row">
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required>
          <p class="hint">Min. 6 characters</p>
        </div>
        <div class="field">
          <label>Confirm</label>
          <input type="password" name="confirm_password" placeholder="••••••••" required>
        </div>
      </div>
      <button class="btn" type="submit">Create Account →</button>
    </form>
    <?php endif; ?>

    <div class="divider">already have an account?</div>
    <a href="login.php" class="link-btn">Sign In</a>
  </div>
</div>
</body>
</html>
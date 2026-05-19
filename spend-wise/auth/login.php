<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: ../dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpendWise — Login</title>
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

  /* Animated background mesh */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse 60% 50% at 10% 20%, rgba(0,229,160,.07) 0%, transparent 70%),
      radial-gradient(ellipse 50% 60% at 85% 80%, rgba(0,229,160,.05) 0%, transparent 70%);
    pointer-events: none;
  }

  .wrap {
    width: 100%;
    max-width: 420px;
    padding: 24px;
    animation: fadeUp .6s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 40px;
  }

  .logo-icon {
    width: 38px; height: 38px;
    background: var(--accent);
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 18px;
  }

  .logo-text {
    font-family: 'Syne', sans-serif;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--text);
  }

  .logo-text span { color: var(--accent); }

  h1 {
    font-family: 'Syne', sans-serif;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 8px;
    letter-spacing: -0.04em;
  }

  .subtitle {
    color: var(--muted);
    font-size: .95rem;
    margin-bottom: 32px;
  }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
  }

  .error-msg {
    background: rgba(255,77,109,.1);
    border: 1px solid rgba(255,77,109,.3);
    color: var(--danger);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: .875rem;
    margin-bottom: 20px;
  }

  .field { margin-bottom: 18px; }

  label {
    display: block;
    font-size: .8rem;
    font-weight: 500;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: 8px;
  }

  input {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 13px 16px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: .95rem;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }

  input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0,229,160,.1);
  }

  .btn {
    width: 100%;
    padding: 14px;
    background: var(--accent);
    color: #0b0f0e;
    border: none;
    border-radius: 12px;
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s, transform .1s;
    margin-top: 8px;
    letter-spacing: .01em;
  }

  .btn:hover { background: #00ffb3; }
  .btn:active { transform: scale(.98); }

  .divider {
    text-align: center;
    color: var(--muted);
    font-size: .85rem;
    margin: 20px 0;
    position: relative;
  }

  .divider::before, .divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: calc(50% - 24px);
    height: 1px;
    background: var(--border);
  }
  .divider::before { left: 0; }
  .divider::after  { right: 0; }

  .link-btn {
    display: block;
    text-align: center;
    color: var(--accent);
    font-size: .9rem;
    font-weight: 500;
    text-decoration: none;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: background .2s, border-color .2s;
  }

  .link-btn:hover {
    background: rgba(0,229,160,.07);
    border-color: var(--accent2);
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">💰</div>
    <div class="logo-text">Spend<span>Wise</span></div>
  </div>

  <h1>Welcome<br>back.</h1>
  <p class="subtitle">Sign in to track your finances.</p>

  <div class="card">
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button class="btn" type="submit">Sign In →</button>
    </form>

    <div class="divider">or</div>
    <a href="register.php" class="link-btn">Create a new account</a>
  </div>
</div>
</body>
</html>
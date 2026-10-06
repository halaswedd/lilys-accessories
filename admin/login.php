<?php
require_once __DIR__ . '/../config/db.php';

// Already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }
        $error = 'Wrong username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login · <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root { --pink:#F27C96; --pink-soft:#FBD3DC; --ink:#1A1A1A; --bg:#FFFDFC; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:var(--bg); font-family:'Jost',sans-serif; color:var(--ink); padding:20px; }
        .card { width:100%; max-width:380px; text-align:center; }
        .card img { width:180px; max-width:60%; margin-bottom:24px; }
        input { width:100%; padding:14px 16px; margin-bottom:12px; border:1px solid var(--pink-soft);
                border-radius:10px; font:inherit; background:#fff; }
        input:focus { outline:none; border-color:var(--pink); }
        button { width:100%; padding:14px; border:0; border-radius:10px; background:var(--pink);
                 color:#fff; font:inherit; letter-spacing:.08em; text-transform:uppercase; cursor:pointer; }
        button:hover { opacity:.9; }
        .error { background:var(--pink-soft); padding:10px; border-radius:8px; margin-bottom:12px; font-size:14px; }
    </style>
</head>
<body>
    <form class="card" method="post">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="<?= SITE_NAME ?>">
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input name="username" placeholder="Username" autocomplete="username" required>
        <input name="password" type="password" placeholder="Password" autocomplete="current-password" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>
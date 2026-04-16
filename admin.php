<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/bootstrap.php';

portalSessionStart();

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password      = $_POST['password'] ?? '';
    $adminPassword = $_ENV['ADMIN_PORTAL_PASSWORD'] ?? '';
    $kianaPassword = $_ENV['ADMIN_KIANA_PASSWORD'] ?? '';

    if (!empty($adminPassword) && $password === $adminPassword) {
        loginAdmin('full');
        header('Location: /admin-dashboard.php');
        exit();
    } elseif (!empty($kianaPassword) && $password === $kianaPassword) {
        loginAdmin('limited');
        header('Location: /admin-dashboard.php');
        exit();
    } else {
        $error = 'Incorrect password.';
    }
}

if (isLoggedInAsAdmin()) {
    header('Location: /admin-dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — The Right Ring Portal</title>
  <link rel="icon" type="image/png" href="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png">
  <link rel="stylesheet" href="/assets/portal.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <img class="logo" src="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png" alt="The Right Ring">
    <h2>Admin Login</h2>
    <p class="subtitle">Ring Portal Administration</p>

    <?php if (!empty($error)): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin.php">
      <div class="form-group" style="text-align:left">
        <label for="password">Admin Password</label>
        <input type="password" id="password" name="password" class="form-control"
               required autofocus placeholder="Enter admin password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Log In</button>
    </form>
  </div>
</div>
</body>
</html>

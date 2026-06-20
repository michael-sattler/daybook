<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login($_POST['password'] ?? '')) {
        header('Location: /index.php');
        exit;
    }
    $error = 'Incorrect password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Daybook</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
  <form class="login-box" method="post">
    <h1>Daybook</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <input type="password" name="password" placeholder="Password" autofocus required>
    <button type="submit">Log In</button>
  </form>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login($_POST['password'] ?? '')) {
        header('Location: /');
        exit;
    }
    $error = 'Incorrect password.';
}

ob_start();
?>
  <form class="login-box" method="post">
    <img src="/assets/images/logo-square.png" alt="Daybook" class="login-logo">
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <input type="password" name="password" placeholder="Password" autofocus required>
    <button type="submit">Log In</button>
  </form>
<?php
$content = ob_get_clean();
$pageTitle = 'Login - Daybook';
$bodyClass = 'login-page';
include __DIR__ . '/elements/layout-public.php';

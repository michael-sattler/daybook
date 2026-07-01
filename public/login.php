<?php
require_once __DIR__ . '/config/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login($mysqli, $_POST['email'] ?? '', $_POST['password'] ?? '')) {
        header('Location: /');
        exit;
    }
    $error = 'Incorrect email or password.';
}

ob_start();
?>
  <form class="login-box" method="post">
    <img src="/assets/images/logo-square.png" alt="Daybook" class="login-logo">
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <input type="email" name="email" placeholder="Email" autofocus required autocomplete="username">
    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
    <button type="submit">Log In</button>
  </form>
<?php
$content = ob_get_clean();
$pageTitle = 'Login - Daybook';
$bodyClass = 'login-page';
include __DIR__ . '/elements/layout-public.php';

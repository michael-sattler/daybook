<?php
require_once __DIR__ . '/config/config.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$inviteEmail = '';

if ($token !== '') {
    require_once __DIR__ . '/app/includes/functions-permissions.php';
    $stmt = $mysqli->prepare(
        'SELECT email, accepted_at, expires_at FROM project_invites WHERE token = ?'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $invite = $stmt->get_result()->fetch_assoc();
    if (!$invite || $invite['accepted_at']) {
        $error = 'This invite link is invalid or has already been used.';
    } elseif ($invite['expires_at'] && (int)$invite['expires_at'] < time()) {
        $error = 'This invite link has expired.';
    } else {
        $inviteEmail = $invite['email'];
    }
} else {
    $error = 'Missing invite token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $inviteEmail && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = accept_project_invite($mysqli, $token, $password);
        if (!empty($result['error'])) {
            $error = $result['error'];
        } else {
            header('Location: /projects/' . $result['project_slug']);
            exit;
        }
    }
}

ob_start();
?>
  <form class="login-box" method="post">
    <img src="/assets/images/logo-square.png" alt="Daybook" class="login-logo">
    <h2 class="login-subtitle">Accept invitation</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($inviteEmail && !$error): ?>
      <p class="login-hint">Create your account for <strong><?= htmlspecialchars($inviteEmail) ?></strong></p>
      <input type="password" name="password" placeholder="Password" required minlength="8" autocomplete="new-password">
      <input type="password" name="password_confirm" placeholder="Confirm password" required minlength="8" autocomplete="new-password">
      <button type="submit">Create account</button>
    <?php endif; ?>
  </form>
<?php
$content = ob_get_clean();
$pageTitle = 'Accept invite - Daybook';
$bodyClass = 'login-page';
include __DIR__ . '/elements/layout-public.php';

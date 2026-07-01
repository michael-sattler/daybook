<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/includes/functions-permissions.php';
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['name'])) {
            $name = trim($_POST['name'] ?? '');
            if (strlen($name) > 100) {
                $error = 'Name must be 100 characters or fewer.';
            } else {
                $uid = current_user_id();
                $upd = $mysqli->prepare('UPDATE users SET name = ? WHERE id = ?');
                $dbName = $name === '' ? null : $name;
                $upd->bind_param('si', $dbName, $uid);
                $upd->execute();
                start_session();
                $_SESSION['user_name'] = $name;
                $success = 'Name updated.';
            }
        } elseif (isset($_POST['email'])) {
            $email = strtolower(trim($_POST['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address.';
            } else {
                $stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE id = ?');
                $uid = current_user_id();
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $current = $_POST['current_password'] ?? '';
                if (!$row || !password_verify($current, $row['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $upd = $mysqli->prepare('UPDATE users SET email = ? WHERE id = ?');
                    $upd->bind_param('si', $email, $uid);
                    $upd->execute();
                    start_session();
                    $_SESSION['user_email'] = $email;
                    $success = 'Email updated.';
                }
            }
        } elseif (isset($_POST['new_password'])) {
            $password = $_POST['new_password'] ?? '';
            $confirm = $_POST['new_password_confirm'] ?? '';
            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE id = ?');
                $uid = current_user_id();
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $current = $_POST['current_password_pw'] ?? '';
                if (!$row || !password_verify($current, $row['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $mysqli->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $upd->bind_param('si', $hash, $uid);
                    $upd->execute();
                    $success = 'Password updated.';
                }
            }
        }
    } catch (Throwable $e) {
        $error = 'Could not save changes.';
    }
}

$email = current_user_email();
$name = current_user_name();

ob_start();
?>
  <div class="account-page">
    <h1>Account settings</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <section class="account-section">
      <h2>Name</h2>
      <p class="account-hint">Shown on the task list when you are assigned.</p>
      <form method="post" class="account-form">
        <label>Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" maxlength="100" autocomplete="name" placeholder="Your display name">
        <button type="submit">Update name</button>
      </form>
    </section>

    <section class="account-section">
      <h2>Email</h2>
      <form method="post" class="account-form">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
        <label>Current password</label>
        <input type="password" name="current_password" required autocomplete="current-password">
        <button type="submit">Update email</button>
      </form>
    </section>

    <section class="account-section">
      <h2>Password</h2>
      <form method="post" class="account-form">
        <label>New password</label>
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <label>Confirm new password</label>
        <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
        <label>Current password</label>
        <input type="password" name="current_password_pw" required autocomplete="current-password">
        <button type="submit">Update password</button>
      </form>
    </section>

    <p><a href="/">← Back to Daybook</a></p>
  </div>
<?php
$content = ob_get_clean();
$pageTitle = 'Account - Daybook';
include __DIR__ . '/elements/layout.php';

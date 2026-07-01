<?php
// Session/login helpers. Loaded by config.php after the platform file.

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function is_logged_in(): bool {
    start_session();
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int {
    start_session();
    return (int)($_SESSION['user_id'] ?? 0);
}

function is_daybookstaff(): bool {
    start_session();
    return !empty($_SESSION['is_daybookstaff']);
}

function require_login(): void {
    if (!is_logged_in()) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }
        header('Location: /login');
        exit;
    }
}

function load_user_into_session(mysqli $mysqli, array $user): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = trim((string)($user['name'] ?? ''));
    $_SESSION['is_daybookstaff'] = !empty($user['is_daybookstaff']);
}

function attempt_login(mysqli $mysqli, string $email, string $password): bool {
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return false;
    }
    $stmt = $mysqli->prepare('SELECT id, email, name, password_hash, is_daybookstaff FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    load_user_into_session($mysqli, $user);
    return true;
}

function logout(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
}

function current_user_email(): string {
    start_session();
    return trim((string)($_SESSION['user_email'] ?? ''));
}

function current_user_name(): string {
    start_session();
    return trim((string)($_SESSION['user_name'] ?? ''));
}

function current_user_display_name(): string {
    require_once __DIR__ . '/../app/includes/functions-universal.php';
    return user_display_name(current_user_name(), current_user_email());
}

function current_user_initial(): string {
    $name = current_user_name();
    if ($name !== '') {
        return strtoupper(substr($name, 0, 1));
    }
    $email = current_user_email();
    if ($email === '') {
        return '?';
    }
    return strtoupper(substr($email, 0, 1));
}

function require_daybookstaff(): void {
    require_login();
    if (!is_daybookstaff()) {
        fail('Forbidden', 403);
    }
}

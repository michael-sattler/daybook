<?php
// Session/login helpers. Loaded by config.php after the platform file, so
// $authPasswordHash is already in scope.

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function is_logged_in(): bool {
    start_session();
    return !empty($_SESSION['logged_in']);
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

function attempt_login(string $password): bool {
    global $authPasswordHash;
    if (password_verify($password, $authPasswordHash)) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        return true;
    }
    return false;
}

function logout(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
}

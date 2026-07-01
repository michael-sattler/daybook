<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$userId = current_user_id();

if ($method === 'GET') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) fail('project_id is required');
    permissions_require_project_access($mysqli, $projectId);
    respond(permissions_project_caps($mysqli, $projectId));
}

if ($method === 'PUT') {
    $body = json_body();
    $sets = [];
    $types = '';
    $params = [];

    if (array_key_exists('name', $body)) {
        $name = trim($body['name'] ?? '');
        if (strlen($name) > 100) {
            fail('Name must be 100 characters or fewer');
        }
        $sets[] = 'name = ?';
        $types .= 's';
        $params[] = $name === '' ? null : $name;
    }

    if (array_key_exists('email', $body)) {
        $email = strtolower(trim($body['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Valid email is required');
        }
        $check = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->bind_param('si', $email, $userId);
        $check->execute();
        if ($check->get_result()->fetch_row()) {
            fail('Email already in use');
        }
        $sets[] = 'email = ?';
        $types .= 's';
        $params[] = $email;
    }

    if (array_key_exists('password', $body)) {
        $password = $body['password'] ?? '';
        $current = $body['current_password'] ?? '';
        if ($password === '') fail('password is required');
        $stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            fail('Current password is incorrect', 403);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sets[] = 'password_hash = ?';
        $types .= 's';
        $params[] = $hash;
    }

    if (!$sets) fail('No editable fields supplied');

    $types .= 'i';
    $params[] = $userId;
    $stmt = $mysqli->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
    bind_dynamic($stmt, $types, $params);
    $stmt->execute();

    if (array_key_exists('email', $body)) {
        start_session();
        $_SESSION['user_email'] = $email;
    }
    if (array_key_exists('name', $body)) {
        start_session();
        $_SESSION['user_name'] = trim($body['name'] ?? '');
    }

    respond([
        'ok' => true,
        'email' => current_user_email(),
        'name' => current_user_name(),
        'display_name' => current_user_display_name(),
    ]);
}

fail('Method not allowed', 405);

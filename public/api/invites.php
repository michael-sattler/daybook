<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$body = $method === 'POST' ? json_body() : [];

if ($method === 'POST' && ($body['action'] ?? '') === 'accept') {
    $result = accept_project_invite($mysqli, $body['token'] ?? '', $body['password'] ?? '');
    if (!empty($result['error'])) {
        fail($result['error'], $result['code'] ?? 400);
    }
    respond($result);
}

require_login();

if ($method === 'GET') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) fail('project_id is required');
    if (!permissions_can_manage_members($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }
    $stmt = $mysqli->prepare(
        'SELECT id, email, role, token, created_at, expires_at
         FROM project_invites
         WHERE project_id = ? AND accepted_at IS NULL
         ORDER BY created_at DESC'
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['invite_url'] = permissions_invite_url($row['token']);
        unset($row['token']);
    }
    unset($row);
    respond($rows);
}

if ($method === 'POST') {
    $projectId = (int)($body['project_id'] ?? 0);
    $email = strtolower(trim($body['email'] ?? ''));
    $role = $body['role'] ?? '';
    if (!$projectId || $email === '' || !in_array($role, INVITE_ROLES, true)) {
        fail('project_id, email, and valid role are required');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Valid email is required');
    }
    if (!permissions_can_manage_members($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }

    $memberCheck = $mysqli->prepare(
        'SELECT u.id FROM users u
         INNER JOIN project_members pm ON pm.user_id = u.id AND pm.project_id = ?
         WHERE u.email = ?'
    );
    $memberCheck->bind_param('is', $projectId, $email);
    $memberCheck->execute();
    if ($memberCheck->get_result()->fetch_row()) {
        fail('User is already a member of this project');
    }

    $pendingCheck = $mysqli->prepare(
        'SELECT id FROM project_invites WHERE project_id = ? AND email = ? AND accepted_at IS NULL'
    );
    $pendingCheck->bind_param('is', $projectId, $email);
    $pendingCheck->execute();
    if ($pendingCheck->get_result()->fetch_row()) {
        fail('A pending invite already exists for this email');
    }

    permissions_ensure_user_for_invite_email($mysqli, $email);

    $token = permissions_generate_token();
    $now = time();
    $expiresAt = $now + (60 * 60 * 24 * 30);
    $invitedBy = current_user_id();

    $stmt = $mysqli->prepare(
        'INSERT INTO project_invites (project_id, email, role, token, invited_by_user_id, created_at, expires_at)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->bind_param('isssiii', $projectId, $email, $role, $token, $invitedBy, $now, $expiresAt);
    $stmt->execute();

    respond([
        'id' => $mysqli->insert_id,
        'email' => $email,
        'role' => $role,
        'invite_url' => permissions_invite_url($token),
        'expires_at' => $expiresAt,
    ], 201);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $stmt = $mysqli->prepare('SELECT project_id FROM project_invites WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) fail('Not found', 404);
    if (!permissions_can_manage_members($mysqli, (int)$row['project_id'])) {
        fail('Forbidden', 403);
    }
    $del = $mysqli->prepare('DELETE FROM project_invites WHERE id = ?');
    $del->bind_param('i', $id);
    $del->execute();
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

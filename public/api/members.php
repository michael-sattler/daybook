<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();
$userId = current_user_id();

if ($method === 'GET') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) fail('project_id is required');
    permissions_require_project_access($mysqli, $projectId);

    $stmt = $mysqli->prepare(
        'SELECT pm.id, pm.user_id, pm.role, pm.created_at, u.email, u.name,
                (p.owner_user_id = pm.user_id) AS is_owner
         FROM project_members pm
         INNER JOIN users u ON u.id = pm.user_id
         INNER JOIN projects p ON p.id = pm.project_id
         WHERE pm.project_id = ?
         ORDER BY pm.role, u.email'
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    respond($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'PUT') {
    $memberId = (int)($body['id'] ?? 0);
    $role = $body['role'] ?? '';
    if (!$memberId || !in_array($role, PROJECT_ROLES, true)) {
        fail('id and valid role are required');
    }

    $stmt = $mysqli->prepare('SELECT project_id, user_id FROM project_members WHERE id = ?');
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) fail('Not found', 404);

    $projectId = (int)$member['project_id'];
    if (!permissions_can_manage_members($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }

    $targetStmt = $mysqli->prepare('SELECT is_daybookstaff FROM users WHERE id = ?');
    $targetId = (int)$member['user_id'];
    $targetStmt->bind_param('i', $targetId);
    $targetStmt->execute();
    $targetUser = $targetStmt->get_result()->fetch_assoc();
    if ($targetUser && $targetUser['is_daybookstaff']) {
        fail('Cannot change Daybookstaff member role', 403);
    }

    $upd = $mysqli->prepare('UPDATE project_members SET role = ? WHERE id = ?');
    $upd->bind_param('si', $role, $memberId);
    $upd->execute();
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $memberId = (int)($_GET['id'] ?? 0);
    if (!$memberId) fail('id is required');

    $stmt = $mysqli->prepare('SELECT pm.project_id, pm.user_id, u.is_daybookstaff
        FROM project_members pm INNER JOIN users u ON u.id = pm.user_id WHERE pm.id = ?');
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) fail('Not found', 404);

    $projectId = (int)$member['project_id'];
    $targetId = (int)$member['user_id'];

    if ($targetId === $userId) {
        if ($member['is_daybookstaff']) {
            fail('Daybookstaff cannot leave projects', 403);
        }
        if (!is_daybookstaff() && permissions_current_project_role($mysqli, $projectId) !== 'admin') {
            fail('Forbidden', 403);
        }
    } else {
        if (!permissions_can_manage_members($mysqli, $projectId)) {
            fail('Forbidden', 403);
        }
        if ($member['is_daybookstaff']) {
            fail('Cannot remove Daybookstaff', 403);
        }
    }

    $del = $mysqli->prepare('DELETE FROM project_members WHERE id = ?');
    $del->bind_param('i', $memberId);
    $del->execute();

    $mysqli->query(
        'UPDATE items SET assigned_user_id = NULL WHERE project_id = '
        . (int)$projectId . ' AND assigned_user_id = ' . (int)$targetId
    );

    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    $projectId = (int)($body['project_id'] ?? 0);
    $newOwnerId = (int)($body['new_owner_user_id'] ?? 0);
    if (!$projectId || !$newOwnerId) {
        fail('project_id and new_owner_user_id are required');
    }
    if (!permissions_can_manage_members($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }

    $check = $mysqli->prepare(
        'SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?'
    );
    $check->bind_param('ii', $projectId, $newOwnerId);
    $check->execute();
    if (!$check->get_result()->fetch_row()) {
        fail('New owner must be a project member');
    }

    $upd = $mysqli->prepare('UPDATE projects SET owner_user_id = ? WHERE id = ?');
    $upd->bind_param('ii', $newOwnerId, $projectId);
    $upd->execute();
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

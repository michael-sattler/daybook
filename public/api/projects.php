<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-colors.php';
require_once __DIR__ . '/../app/includes/functions-slug.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$userId = current_user_id();

function project_row(mysqli $mysqli, int $id): ?array {
    $stmt = $mysqli->prepare(
        'SELECT p.id, p.name, p.slug, p.sort_order, p.bg_color, p.text_color, p.owner_user_id,
                pm.role AS my_role
         FROM projects p
         LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
         WHERE p.id = ?'
    );
    $uid = current_user_id();
    $stmt->bind_param('ii', $uid, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $row['is_owner'] = (int)$row['owner_user_id'] === $uid;
    return $row;
}

if ($method === 'GET') {
    if (is_daybookstaff()) {
        $sql = 'SELECT p.id, p.name, p.slug, p.sort_order, p.bg_color, p.text_color, p.owner_user_id,
                       pm.role AS my_role
                FROM projects p
                LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
                ORDER BY p.sort_order, p.id';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $userId);
    } else {
        $sql = 'SELECT p.id, p.name, p.slug, p.sort_order, p.bg_color, p.text_color, p.owner_user_id,
                       pm.role AS my_role
                FROM projects p
                INNER JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
                ORDER BY p.sort_order, p.id';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['is_owner'] = (int)$row['owner_user_id'] === $userId;
    }
    unset($row);
    respond($rows);
}

if ($method === 'POST') {
    if (!permissions_can_create_project($mysqli)) {
        fail('Forbidden', 403);
    }
    $body = json_body();
    $name = trim($body['name'] ?? '');
    if ($name === '') fail('Name is required');
    [$count, $maxOrder] = $mysqli->query('SELECT COUNT(*), COALESCE(MAX(sort_order),0) FROM projects')->fetch_row();

    $bg = $body['bg_color'] ?? null;
    $text = $body['text_color'] ?? null;
    if (!$bg && !$text) {
        [$bg, $text] = PASTEL_ROYGBIV_PALETTE[$count % count(PASTEL_ROYGBIV_PALETTE)];
    }

    $slug = unique_project_slug($mysqli, slugify($name));
    $sortOrder = $maxOrder + 1;
    $now = time();

    $stmt = $mysqli->prepare(
        'INSERT INTO projects (name, slug, owner_user_id, sort_order, bg_color, text_color, created_at)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->bind_param('ssiissi', $name, $slug, $userId, $sortOrder, $bg, $text, $now);
    $stmt->execute();
    $id = (int)$mysqli->insert_id;

    $role = 'admin';
    $mStmt = $mysqli->prepare(
        'INSERT INTO project_members (project_id, user_id, role, created_at) VALUES (?,?,?,?)'
    );
    $mStmt->bind_param('iisi', $id, $userId, $role, $now);
    $mStmt->execute();

    seed_project_defaults($mysqli, $id);
    respond(project_row($mysqli, $id), 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');
    if (!permissions_can_edit_project($mysqli, $id)) {
        fail('Forbidden', 403);
    }

    $sets = [];
    $types = '';
    $params = [];
    if (array_key_exists('name', $body)) {
        $name = trim($body['name']);
        if ($name === '') fail('name cannot be blank');
        $sets[] = 'name = ?';
        $types .= 's';
        $params[] = $name;
        $slug = unique_project_slug($mysqli, slugify($name), $id);
        $sets[] = 'slug = ?';
        $types .= 's';
        $params[] = $slug;
    }
    if (array_key_exists('bg_color', $body)) { $sets[] = 'bg_color = ?'; $types .= 's'; $params[] = $body['bg_color'] ?: null; }
    if (array_key_exists('text_color', $body)) { $sets[] = 'text_color = ?'; $types .= 's'; $params[] = $body['text_color'] ?: null; }
    if (!$sets) fail('No editable fields supplied');
    $types .= 'i';
    $params[] = $id;
    $stmt = $mysqli->prepare('UPDATE projects SET ' . implode(', ', $sets) . ' WHERE id = ?');
    bind_dynamic($stmt, $types, $params);
    $stmt->execute();

    respond(project_row($mysqli, $id) ?: ['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    if (!permissions_can_delete_project($mysqli, $id)) {
        fail('Forbidden', 403);
    }
    $stmt = $mysqli->prepare('DELETE FROM projects WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    $body = json_body();
    $order = $body['order'] ?? [];
    if (!is_array($order) || !$order) fail('order array is required');
    foreach ($order as $id) {
        $pid = (int)$id;
        if (!permissions_can_edit_project($mysqli, $pid)) {
            fail('Forbidden', 403);
        }
    }
    $stmt = $mysqli->prepare('UPDATE projects SET sort_order = ? WHERE id = ?');
    foreach ($order as $i => $id) {
        $sortOrder = $i + 1;
        $idInt = (int)$id;
        $stmt->bind_param('ii', $sortOrder, $idInt);
        $stmt->execute();
    }
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-colors.php';
require_once __DIR__ . '/../app/includes/functions-slug.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $mysqli->query('SELECT id, name, slug, sort_order, bg_color, text_color FROM projects ORDER BY sort_order, id');
    respond($result->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'POST') {
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

    $stmt = $mysqli->prepare('INSERT INTO projects (name, slug, sort_order, bg_color, text_color, created_at) VALUES (?,?,?,?,?,?)');
    $stmt->bind_param('ssissi', $name, $slug, $sortOrder, $bg, $text, $now);
    $stmt->execute();
    $id = $mysqli->insert_id;
    seed_project_defaults($mysqli, $id);
    respond(['id' => $id, 'name' => $name, 'slug' => $slug, 'sort_order' => $sortOrder, 'bg_color' => $bg, 'text_color' => $text], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');

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

    $stmt = $mysqli->prepare('SELECT id, name, slug, sort_order, bg_color, text_color FROM projects WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    respond($project ?: ['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $stmt = $mysqli->prepare('DELETE FROM projects WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    $body = json_body();
    $order = $body['order'] ?? [];
    if (!is_array($order) || !$order) fail('order array is required');
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

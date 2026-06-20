<?php
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/color_palettes.php';
require_once __DIR__ . '/../includes/slug.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, name, slug, sort_order, bg_color, text_color FROM projects ORDER BY sort_order, id')->fetchAll();
    respond($rows);
}

if ($method === 'POST') {
    $body = json_body();
    $name = trim($body['name'] ?? '');
    if ($name === '') fail('Name is required');
    [$count, $maxOrder] = $pdo->query('SELECT COUNT(*), COALESCE(MAX(sort_order),0) FROM projects')->fetch(PDO::FETCH_NUM);

    $bg = $body['bg_color'] ?? null;
    $text = $body['text_color'] ?? null;
    if (!$bg && !$text) {
        [$bg, $text] = PASTEL_ROYGBIV_PALETTE[$count % count(PASTEL_ROYGBIV_PALETTE)];
    }

    $slug = unique_project_slug($pdo, slugify($name));

    $stmt = $pdo->prepare('INSERT INTO projects (name, slug, sort_order, bg_color, text_color) VALUES (?,?,?,?,?)');
    $stmt->execute([$name, $slug, $maxOrder + 1, $bg, $text]);
    $id = (int)$pdo->lastInsertId();
    seed_project_defaults($pdo, $id);
    respond(['id' => $id, 'name' => $name, 'slug' => $slug, 'sort_order' => $maxOrder + 1, 'bg_color' => $bg, 'text_color' => $text], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');

    $sets = [];
    $params = [];
    if (array_key_exists('name', $body)) {
        $name = trim($body['name']);
        if ($name === '') fail('name cannot be blank');
        $sets[] = 'name = ?';
        $params[] = $name;
    }
    if (array_key_exists('bg_color', $body)) { $sets[] = 'bg_color = ?'; $params[] = $body['bg_color'] ?: null; }
    if (array_key_exists('text_color', $body)) { $sets[] = 'text_color = ?'; $params[] = $body['text_color'] ?: null; }
    if (!$sets) fail('No editable fields supplied');
    $params[] = $id;
    $pdo->prepare('UPDATE projects SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    $body = json_body();
    $order = $body['order'] ?? [];
    if (!is_array($order) || !$order) fail('order array is required');
    $stmt = $pdo->prepare('UPDATE projects SET sort_order = ? WHERE id = ?');
    foreach ($order as $i => $id) {
        $stmt->execute([$i + 1, (int)$id]);
    }
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

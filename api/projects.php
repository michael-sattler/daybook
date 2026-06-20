<?php
require_once __DIR__ . '/../includes/api_common.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, name, sort_order FROM projects ORDER BY sort_order, id')->fetchAll();
    respond($rows);
}

if ($method === 'POST') {
    $body = json_body();
    $name = trim($body['name'] ?? '');
    if ($name === '') fail('Name is required');
    $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM projects')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO projects (name, sort_order) VALUES (?, ?)');
    $stmt->execute([$name, $maxOrder + 1]);
    $id = (int)$pdo->lastInsertId();
    seed_project_defaults($pdo, $id);
    respond(['id' => $id, 'name' => $name, 'sort_order' => $maxOrder + 1], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    $name = trim($body['name'] ?? '');
    if (!$id || $name === '') fail('id and name are required');
    $pdo->prepare('UPDATE projects SET name = ? WHERE id = ?')->execute([$name, $id]);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

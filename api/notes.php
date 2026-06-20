<?php
require_once __DIR__ . '/../includes/api_common.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) fail('item_id is required');
    $stmt = $pdo->prepare('SELECT id, item_id, body, created_at, updated_at FROM notes WHERE item_id = ? ORDER BY created_at, id');
    $stmt->execute([$itemId]);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = json_body();
    $itemId = (int)($body['item_id'] ?? 0);
    $text = trim($body['body'] ?? '');
    if (!$itemId || $text === '') fail('item_id and body are required');
    $stmt = $pdo->prepare('INSERT INTO notes (item_id, body) VALUES (?, ?)');
    $stmt->execute([$itemId, $text]);
    respond(['id' => (int)$pdo->lastInsertId(), 'item_id' => $itemId, 'body' => $text], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    $text = trim($body['body'] ?? '');
    if (!$id || $text === '') fail('id and body are required');
    $pdo->prepare('UPDATE notes SET body = ? WHERE id = ?')->execute([$text, $id]);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $pdo->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

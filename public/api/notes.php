<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) fail('item_id is required');
    permissions_require_item_view($mysqli, $itemId);

    $stmt = $mysqli->prepare('SELECT id, item_id, body, created_at, updated_at FROM notes WHERE item_id = ? ORDER BY created_at, id');
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    respond($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'POST') {
    $body = json_body();
    $itemId = (int)($body['item_id'] ?? 0);
    $text = trim($body['body'] ?? '');
    if (!$itemId || $text === '') fail('item_id and body are required');
    $item = permissions_require_item_view($mysqli, $itemId);
    if (!permissions_can_edit_docs_notes($mysqli, $item)) {
        fail('Forbidden', 403);
    }
    $now = time();
    $stmt = $mysqli->prepare('INSERT INTO notes (item_id, body, created_at, updated_at) VALUES (?,?,?,?)');
    $stmt->bind_param('isii', $itemId, $text, $now, $now);
    $stmt->execute();
    respond(['id' => $mysqli->insert_id, 'item_id' => $itemId, 'body' => $text, 'created_at' => $now, 'updated_at' => $now], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    $text = trim($body['body'] ?? '');
    if (!$id || $text === '') fail('id and body are required');
    $stmt = $mysqli->prepare('SELECT item_id FROM notes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    if (!$note) fail('Not found', 404);
    $item = permissions_fetch_item($mysqli, (int)$note['item_id']);
    if (!$item || !permissions_can_edit_docs_notes($mysqli, $item)) {
        fail('Forbidden', 403);
    }
    $now = time();
    $stmt = $mysqli->prepare('UPDATE notes SET body = ?, updated_at = ? WHERE id = ?');
    $stmt->bind_param('sii', $text, $now, $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $stmt = $mysqli->prepare('SELECT item_id FROM notes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    if (!$note) fail('Not found', 404);
    $item = permissions_fetch_item($mysqli, (int)$note['item_id']);
    if (!$item || !permissions_can_edit_docs_notes($mysqli, $item)) {
        fail('Forbidden', 403);
    }
    $stmt = $mysqli->prepare('DELETE FROM notes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

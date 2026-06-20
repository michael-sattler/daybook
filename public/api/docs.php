<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) fail('item_id is required');
    $stmt = $mysqli->prepare('SELECT id, item_id, label, url, sort_order FROM docs WHERE item_id = ? ORDER BY sort_order, id');
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    respond($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'POST') {
    $body = json_body();
    $itemId = (int)($body['item_id'] ?? 0);
    $url = trim($body['url'] ?? '');
    if (!$itemId || $url === '') fail('item_id and url are required');

    $stmt = $mysqli->prepare('SELECT COALESCE(MAX(sort_order),0) FROM docs WHERE item_id = ?');
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $stmt->bind_result($maxOrder);
    $stmt->fetch();
    $stmt->free_result();

    $label = $body['label'] ?? '';
    $sortOrder = $maxOrder + 1;
    $stmt = $mysqli->prepare('INSERT INTO docs (item_id, label, url, sort_order) VALUES (?,?,?,?)');
    $stmt->bind_param('issi', $itemId, $label, $url, $sortOrder);
    $stmt->execute();
    respond(['id' => $mysqli->insert_id, 'item_id' => $itemId, 'label' => $label, 'url' => $url], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');
    $label = $body['label'] ?? '';
    $url = $body['url'] ?? '';
    $stmt = $mysqli->prepare('UPDATE docs SET label = ?, url = ? WHERE id = ?');
    $stmt->bind_param('ssi', $label, $url, $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $stmt = $mysqli->prepare('DELETE FROM docs WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

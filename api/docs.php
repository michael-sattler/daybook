<?php
require_once __DIR__ . '/../includes/api_common.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) fail('item_id is required');
    $stmt = $pdo->prepare('SELECT id, item_id, label, url, sort_order FROM docs WHERE item_id = ? ORDER BY sort_order, id');
    $stmt->execute([$itemId]);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = json_body();
    $itemId = (int)($body['item_id'] ?? 0);
    $url = trim($body['url'] ?? '');
    if (!$itemId || $url === '') fail('item_id and url are required');
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM docs WHERE item_id = ?');
    $stmt->execute([$itemId]);
    $maxOrder = (int)$stmt->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO docs (item_id, label, url, sort_order) VALUES (?,?,?,?)');
    $ins->execute([$itemId, $body['label'] ?? '', $url, $maxOrder + 1]);
    respond(['id' => (int)$pdo->lastInsertId(), 'item_id' => $itemId, 'label' => $body['label'] ?? '', 'url' => $url], 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');
    $pdo->prepare('UPDATE docs SET label = ?, url = ? WHERE id = ?')
        ->execute([$body['label'] ?? '', $body['url'] ?? '', $id]);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $pdo->prepare('DELETE FROM docs WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

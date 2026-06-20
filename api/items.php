<?php
require_once __DIR__ . '/../includes/api_common.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

const EDITABLE_FIELDS = [
    'category_id', 'subsystem', 'item_text', 'url', 'priority_id', 'status_id', 'project_id',
];

function item_select_sql(): string {
    return "SELECT i.id, i.sort_order, i.project_id, p.name AS project_name,
                   i.category_id, c.name AS category_name,
                   i.subsystem, i.item_text, i.url,
                   i.priority_id, pr.name AS priority_name,
                   i.order_in_priority,
                   i.status_id, s.name AS status_name,
                   i.created_at, i.updated_at
            FROM items i
            LEFT JOIN projects p ON p.id = i.project_id
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN priorities pr ON pr.id = i.priority_id
            LEFT JOIN statuses s ON s.id = i.status_id";
}

if ($method === 'GET') {
    $where = [];
    $params = [];
    foreach (['project_id', 'category_id', 'priority_id', 'status_id'] as $field) {
        if (!empty($_GET[$field])) {
            $where[] = "i.$field = ?";
            $params[] = (int)$_GET[$field];
        }
    }
    if (!empty($_GET['q'])) {
        $where[] = "(i.item_text LIKE ? OR i.subsystem LIKE ? OR i.url LIKE ?)";
        $needle = '%' . $_GET['q'] . '%';
        array_push($params, $needle, $needle, $needle);
    }
    $sql = item_select_sql();
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

    $orderBy = $_GET['order_by'] ?? 'sort_order';
    $sql .= $orderBy === 'priority'
        ? ' ORDER BY pr.sort_order IS NULL, pr.sort_order, i.order_in_priority'
        : ' ORDER BY i.sort_order';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = json_body();
    $projectId = (int)($body['project_id'] ?? 0);
    if (!$projectId) fail('project_id is required');

    $sortOrder = next_sort_order($pdo);
    $priorityId = !empty($body['priority_id']) ? (int)$body['priority_id'] : null;
    $orderInPriority = 0;
    if ($priorityId) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(order_in_priority),0) FROM items WHERE priority_id = ?');
        $stmt->execute([$priorityId]);
        $orderInPriority = (int)$stmt->fetchColumn() + 1;
    }

    $stmt = $pdo->prepare('INSERT INTO items
        (sort_order, project_id, category_id, subsystem, item_text, url, priority_id, order_in_priority, status_id)
        VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $sortOrder,
        $projectId,
        !empty($body['category_id']) ? (int)$body['category_id'] : null,
        $body['subsystem'] ?? '',
        $body['item_text'] ?? '',
        $body['url'] ?? '',
        $priorityId,
        $orderInPriority,
        !empty($body['status_id']) ? (int)$body['status_id'] : null,
    ]);
    $id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(item_select_sql() . ' WHERE i.id = ?');
    $stmt->execute([$id]);
    respond($stmt->fetch(), 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');

    $sets = [];
    $params = [];
    foreach (EDITABLE_FIELDS as $field) {
        if (array_key_exists($field, $body)) {
            $sets[] = "$field = ?";
            $value = $body[$field];
            $params[] = ($value === '' && str_ends_with($field, '_id')) ? null : $value;
        }
    }
    if (!$sets) fail('No editable fields supplied');
    $params[] = $id;
    $pdo->prepare('UPDATE items SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

    if (array_key_exists('priority_id', $body)) {
        $newPriorityId = !empty($body['priority_id']) ? (int)$body['priority_id'] : null;
        if ($newPriorityId) {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(order_in_priority),0) FROM items WHERE priority_id = ? AND id != ?');
            $stmt->execute([$newPriorityId, $id]);
            $nextOrder = (int)$stmt->fetchColumn() + 1;
            $pdo->prepare('UPDATE items SET order_in_priority = ? WHERE id = ?')->execute([$nextOrder, $id]);
        } else {
            $pdo->prepare('UPDATE items SET order_in_priority = 0 WHERE id = ?')->execute([$id]);
        }
    }

    $stmt = $pdo->prepare(item_select_sql() . ' WHERE i.id = ?');
    $stmt->execute([$id]);
    respond($stmt->fetch());
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    // Reorder items within a priority (drag-and-drop).
    $body = json_body();
    $priorityId = (int)($body['priority_id'] ?? 0);
    $order = $body['order'] ?? [];
    if (!$priorityId || !is_array($order) || !$order) fail('priority_id and order array are required');
    $stmt = $pdo->prepare('UPDATE items SET order_in_priority = ? WHERE id = ? AND priority_id = ?');
    foreach ($order as $i => $itemId) {
        $stmt->execute([$i + 1, (int)$itemId, $priorityId]);
    }
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

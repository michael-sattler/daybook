<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

const EDITABLE_FIELDS = [
    'category_id', 'subsystem_id', 'item_text', 'url', 'priority_id', 'status_id', 'project_id',
];
// bind_param type char for each editable field ('s' covers nullable int-ish
// foreign keys too - mysqli accepts numeric strings/NULL fine as 's').
const FIELD_TYPES = [
    'category_id' => 's', 'subsystem_id' => 's', 'item_text' => 's',
    'url' => 's', 'priority_id' => 's', 'status_id' => 's', 'project_id' => 's',
];

function item_select_sql(): string {
    return "SELECT i.id, i.sort_order, i.project_id, p.name AS project_name,
                   p.bg_color AS project_bg_color, p.text_color AS project_text_color,
                   i.category_id, c.name AS category_name,
                   i.subsystem_id, sub.name AS subsystem_name,
                   sub.bg_color AS subsystem_bg_color, sub.text_color AS subsystem_text_color,
                   i.item_text, i.url,
                   i.priority_id, pr.name AS priority_name,
                   pr.bg_color AS priority_bg_color, pr.text_color AS priority_text_color,
                   i.order_in_priority,
                   i.status_id, s.name AS status_name,
                   s.bg_color AS status_bg_color, s.text_color AS status_text_color,
                   i.created_at, i.updated_at
            FROM items i
            LEFT JOIN projects p ON p.id = i.project_id
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN subsystems sub ON sub.id = i.subsystem_id
            LEFT JOIN priorities pr ON pr.id = i.priority_id
            LEFT JOIN statuses s ON s.id = i.status_id";
}

function fetch_one_item(mysqli $mysqli, int $id): array {
    $stmt = $mysqli->prepare(item_select_sql() . ' WHERE i.id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if ($method === 'GET') {
    $where = [];
    $types = '';
    $params = [];
    foreach (['project_id', 'category_id', 'priority_id', 'status_id'] as $field) {
        if (!empty($_GET[$field])) {
            $where[] = "i.$field = ?";
            $types .= 'i';
            $params[] = (int)$_GET[$field];
        }
    }
    if (!empty($_GET['q'])) {
        $where[] = "(i.item_text LIKE ? OR sub.name LIKE ? OR i.url LIKE ?)";
        $needle = '%' . $_GET['q'] . '%';
        $types .= 'sss';
        array_push($params, $needle, $needle, $needle);
    }
    $sql = item_select_sql();
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

    $orderBy = $_GET['order_by'] ?? 'sort_order';
    $sql .= $orderBy === 'priority'
        ? ' ORDER BY pr.sort_order IS NULL, pr.sort_order, i.order_in_priority'
        : ' ORDER BY i.sort_order';

    $stmt = $mysqli->prepare($sql);
    if ($params) bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    respond($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'POST') {
    $body = json_body();
    $projectId = (int)($body['project_id'] ?? 0);
    if (!$projectId) fail('project_id is required');

    $sortOrder = next_sort_order($mysqli);
    $priorityId = !empty($body['priority_id']) ? (int)$body['priority_id'] : null;
    $orderInPriority = 0;
    if ($priorityId) {
        $stmt = $mysqli->prepare('SELECT COALESCE(MAX(order_in_priority),0) FROM items WHERE priority_id = ?');
        $stmt->bind_param('i', $priorityId);
        $stmt->execute();
        $stmt->bind_result($orderInPriority);
        $stmt->fetch();
        $stmt->free_result();
        $orderInPriority++;
    }

    $categoryId = !empty($body['category_id']) ? (int)$body['category_id'] : null;
    $subsystemId = !empty($body['subsystem_id']) ? (int)$body['subsystem_id'] : null;
    $itemText = $body['item_text'] ?? '';
    $url = $body['url'] ?? '';
    $statusId = !empty($body['status_id']) ? (int)$body['status_id'] : null;
    $now = time();

    $stmt = $mysqli->prepare('INSERT INTO items
        (sort_order, project_id, category_id, subsystem_id, item_text, url, priority_id, order_in_priority, status_id, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param(
        'iiiissiiiii',
        $sortOrder, $projectId, $categoryId, $subsystemId, $itemText, $url,
        $priorityId, $orderInPriority, $statusId, $now, $now
    );
    $stmt->execute();
    respond(fetch_one_item($mysqli, $mysqli->insert_id), 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');

    $sets = [];
    $types = '';
    $params = [];
    foreach (EDITABLE_FIELDS as $field) {
        if (array_key_exists($field, $body)) {
            $sets[] = "$field = ?";
            $types .= FIELD_TYPES[$field];
            $value = $body[$field];
            $params[] = ($value === '' && str_ends_with($field, '_id')) ? null : $value;
        }
    }
    if (!$sets) fail('No editable fields supplied');
    $sets[] = 'updated_at = ?';
    $types .= 'i';
    $params[] = time();
    $types .= 'i';
    $params[] = $id;
    $stmt = $mysqli->prepare('UPDATE items SET ' . implode(', ', $sets) . ' WHERE id = ?');
    bind_dynamic($stmt, $types, $params);
    $stmt->execute();

    if (array_key_exists('priority_id', $body)) {
        $newPriorityId = !empty($body['priority_id']) ? (int)$body['priority_id'] : null;
        if ($newPriorityId) {
            $stmt = $mysqli->prepare('SELECT COALESCE(MAX(order_in_priority),0) FROM items WHERE priority_id = ? AND id != ?');
            $stmt->bind_param('ii', $newPriorityId, $id);
            $stmt->execute();
            $stmt->bind_result($nextOrder);
            $stmt->fetch();
            $stmt->free_result();
            $nextOrder++;
            $stmt = $mysqli->prepare('UPDATE items SET order_in_priority = ? WHERE id = ?');
            $stmt->bind_param('ii', $nextOrder, $id);
            $stmt->execute();
        } else {
            $stmt = $mysqli->prepare('UPDATE items SET order_in_priority = 0 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
    }

    respond(fetch_one_item($mysqli, $id));
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('id is required');
    $stmt = $mysqli->prepare('DELETE FROM items WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    // Reorder items within a priority (drag-and-drop).
    $body = json_body();
    $priorityId = (int)($body['priority_id'] ?? 0);
    $order = $body['order'] ?? [];
    if (!$priorityId || !is_array($order) || !$order) fail('priority_id and order array are required');
    $stmt = $mysqli->prepare('UPDATE items SET order_in_priority = ? WHERE id = ? AND priority_id = ?');
    foreach ($order as $i => $itemId) {
        $orderInPriority = $i + 1;
        $idInt = (int)$itemId;
        $stmt->bind_param('iii', $orderInPriority, $idInt, $priorityId);
        $stmt->execute();
    }
    respond(['ok' => true]);
}

fail('Method not allowed', 405);

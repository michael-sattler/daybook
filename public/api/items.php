<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

const EDITABLE_FIELDS = [
    'category_id', 'subsystem_id', 'item_text', 'url', 'priority_id', 'status_id', 'project_id', 'assigned_user_id', 'due_date',
];

function normalize_due_date(mixed $value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    $value = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        fail('due_date must be YYYY-MM-DD', 400);
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        fail('due_date is not a valid date', 400);
    }
    return $value;
}

const PENDING_STATUS_NAMES = ['TODO', 'UNDERWAY', 'BLOCKED'];

function assignment_from_input(mixed $value): array {
    if ($value === 'owner') {
        return ['assigned_to_project_owner' => 1, 'assigned_user_id' => null];
    }
    if ($value === '' || $value === null) {
        return ['assigned_to_project_owner' => 0, 'assigned_user_id' => null];
    }
    return ['assigned_to_project_owner' => 0, 'assigned_user_id' => (int)$value];
}

/** Render a nullable integer as a SQL literal (empty/null -> NULL). */
function sql_nullable_int(mixed $value): string {
    if ($value === null || $value === '') {
        return 'NULL';
    }
    return (string)(int)$value;
}

/** Render a nullable string as an escaped, quoted SQL literal (null -> NULL). */
function sql_quoted_string(mysqli $mysqli, ?string $value): string {
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $mysqli->real_escape_string($value) . "'";
}

function require_valid_assignment(mysqli $mysqli, int $projectId, mixed $value): array {
    $assignment = assignment_from_input($value);
    if ($assignment['assigned_to_project_owner'] && !permissions_project_owner_id($mysqli, $projectId)) {
        fail('This project has no owner', 400);
    }
    return $assignment;
}

function item_select_sql(): string {
    $ownerExpr = sql_project_owner_user_id_expr('p');
    return "SELECT i.id, i.sort_order, i.project_id, p.name AS project_name,
                   p.bg_color AS project_bg_color, p.text_color AS project_text_color,
                   {$ownerExpr} AS project_owner_user_id,
                   i.created_by_user_id, i.assigned_user_id, i.assigned_to_project_owner,
                   " . sql_project_owner_assignee_name('ou_direct') . " AS project_owner_assignee_label,
                   " . sql_item_assignee_name() . " AS assignee_name,
                   CASE WHEN i.assigned_to_project_owner = 1 THEN
                        COALESCE(
                            ou_direct.email,
                            (
                                SELECT u2.email
                                FROM project_members pm2
                                INNER JOIN users u2 ON u2.id = pm2.user_id
                                WHERE pm2.project_id = p.id AND pm2.role = 'admin'
                                ORDER BY pm2.created_at, pm2.id
                                LIMIT 1
                            )
                        )
                        ELSE u.email
                   END AS assignee_email,
                   i.category_id, c.name AS category_name,
                   i.subsystem_id, sub.name AS subsystem_name,
                   sub.bg_color AS subsystem_bg_color, sub.text_color AS subsystem_text_color,
                   i.item_text, i.url,
                   i.priority_id, pr.name AS priority_name,
                   pr.bg_color AS priority_bg_color, pr.text_color AS priority_text_color,
                   i.order_in_priority,
                   i.status_id, s.name AS status_name,
                   s.bg_color AS status_bg_color, s.text_color AS status_text_color,
                   i.due_date,
                   i.created_at, i.updated_at
            FROM items i
            LEFT JOIN projects p ON p.id = i.project_id
            LEFT JOIN users u ON u.id = i.assigned_user_id
            LEFT JOIN users ou_direct ON ou_direct.id = p.owner_user_id
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN subsystems sub ON sub.id = i.subsystem_id
            LEFT JOIN priorities pr ON pr.id = i.priority_id
            LEFT JOIN statuses s ON s.id = i.status_id";
}

function fetch_one_item(mysqli $mysqli, int $id): ?array {
    $stmt = $mysqli->prepare(item_select_sql() . ' WHERE i.id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

if ($method === 'GET') {
    $userId = current_user_id();
    $where = [];
    $types = '';
    $params = [];

    $projectId = !empty($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    if ($projectId) {
        permissions_require_project_access($mysqli, $projectId);
        $where[] = 'i.project_id = ?';
        $types .= 'i';
        $params[] = $projectId;
        $vis = permissions_item_visibility_clause($mysqli, $projectId, $userId, 'i');
        if ($vis !== '1=1') {
            $where[] = $vis;
        }
    } elseif (!is_daybookstaff()) {
        fail('project_id is required', 400);
    }

    foreach (['category_id', 'priority_id'] as $field) {
        if (!empty($_GET[$field])) {
            $where[] = "i.$field = ?";
            $types .= 'i';
            $params[] = (int)$_GET[$field];
        }
    }
    if (!empty($_GET['status_id'])) {
        if ($_GET['status_id'] === 'pending') {
            $pending = "'" . implode("','", PENDING_STATUS_NAMES) . "'";
            $where[] = "(i.status_id IS NULL OR s.name IN ($pending))";
        } else {
            $where[] = 'i.status_id = ?';
            $types .= 'i';
            $params[] = (int)$_GET['status_id'];
        }
    } elseif (!empty($_GET['active_only'])) {
        $pending = "'" . implode("','", PENDING_STATUS_NAMES) . "'";
        $where[] = "(i.status_id IS NULL OR s.name IN ($pending))";
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
    if (!permissions_can_create_item($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }

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
    if (array_key_exists('assigned_user_id', $body) && !permissions_can_assign_items($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }
    $assignment = require_valid_assignment($mysqli, $projectId, $body['assigned_user_id'] ?? null);
    $assignedUserId = $assignment['assigned_user_id'];
    $assignedToProjectOwner = $assignment['assigned_to_project_owner'];
    $dueDate = array_key_exists('due_date', $body) ? normalize_due_date($body['due_date']) : null;
    $createdBy = current_user_id();
    $now = time();

    $sql = 'INSERT INTO items
        (sort_order, project_id, created_by_user_id, assigned_user_id, assigned_to_project_owner,
         category_id, subsystem_id, item_text, url, priority_id, order_in_priority, status_id, due_date, created_at, updated_at)
        VALUES ('
        . (int)$sortOrder . ', '
        . (int)$projectId . ', '
        . sql_nullable_int($createdBy) . ', '
        . sql_nullable_int($assignedUserId) . ', '
        . (int)$assignedToProjectOwner . ', '
        . sql_nullable_int($categoryId) . ', '
        . sql_nullable_int($subsystemId) . ', '
        . sql_quoted_string($mysqli, (string)$itemText) . ', '
        . sql_quoted_string($mysqli, (string)$url) . ', '
        . sql_nullable_int($priorityId) . ', '
        . (int)$orderInPriority . ', '
        . sql_nullable_int($statusId) . ', '
        . sql_quoted_string($mysqli, $dueDate) . ', '
        . (int)$now . ', '
        . (int)$now . ')';
    if (!$mysqli->query($sql)) {
        debug_log('item insert failed: ' . $mysqli->error . ' | SQL: ' . $sql);
        fail('Could not create item: ' . $mysqli->error, 500);
    }
    respond(fetch_one_item($mysqli, $mysqli->insert_id), 201);
}

if ($method === 'PUT') {
    $body = json_body();
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('id is required');

    foreach (EDITABLE_FIELDS as $field) {
        if (!array_key_exists($field, $body)) {
            continue;
        }
        permissions_require_item_edit($mysqli, $id, $field);
    }

    $sets = [];
    foreach (EDITABLE_FIELDS as $field) {
        if ($field === 'assigned_user_id' || !array_key_exists($field, $body)) {
            continue;
        }
        $value = $body[$field];
        if ($field === 'due_date') {
            $sets[] = 'due_date = ' . sql_quoted_string($mysqli, normalize_due_date($value));
        } elseif (str_ends_with($field, '_id')) {
            $sets[] = "$field = " . sql_nullable_int($value);
        } else {
            $sets[] = "$field = " . sql_quoted_string($mysqli, (string)$value);
        }
    }
    if (array_key_exists('assigned_user_id', $body)) {
        $item = permissions_fetch_item($mysqli, $id);
        $assignment = require_valid_assignment($mysqli, (int)$item['project_id'], $body['assigned_user_id']);
        $sets[] = 'assigned_to_project_owner = ' . (int)$assignment['assigned_to_project_owner'];
        $sets[] = 'assigned_user_id = ' . sql_nullable_int($assignment['assigned_user_id']);
    }
    if (!$sets) fail('No editable fields supplied');
    $sets[] = 'updated_at = ' . time();
    $sql = 'UPDATE items SET ' . implode(', ', $sets) . ' WHERE id = ' . (int)$id;
    if (!$mysqli->query($sql)) {
        debug_log('item update failed: ' . $mysqli->error . ' | SQL: ' . $sql);
        fail('Could not save changes: ' . $mysqli->error, 500);
    }

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
    $item = permissions_fetch_item($mysqli, $id);
    if (!$item) fail('Not found', 404);
    if (!permissions_can_delete_item($mysqli, $item)) {
        fail('Forbidden', 403);
    }
    $stmt = $mysqli->prepare('DELETE FROM items WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($method === 'PATCH') {
    $body = json_body();
    $priorityId = (int)($body['priority_id'] ?? 0);
    $order = $body['order'] ?? [];
    if (!$priorityId || !is_array($order) || !$order) fail('priority_id and order array are required');

    $stmt = $mysqli->prepare('SELECT project_id FROM items WHERE id = ?');
    $firstId = (int)$order[0];
    $stmt->bind_param('i', $firstId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) fail('Not found', 404);
    $projectId = (int)$row['project_id'];
    if (!permissions_can_reorder_items($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }

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

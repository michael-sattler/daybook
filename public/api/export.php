<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
require_login();

$projectId = (int)($_GET['project_id'] ?? 0);
if (!$projectId) {
    http_response_code(400);
    exit('project_id is required');
}
permissions_require_project_access($mysqli, $projectId);

$userId = current_user_id();
$where = ['i.project_id = ?'];
$types = 'i';
$params = [$projectId];
$vis = permissions_item_visibility_clause($mysqli, $projectId, $userId, 'i');
if ($vis !== '1=1') {
    $where[] = $vis;
}

$sql = "SELECT i.sort_order, i.item_text, c.name AS category, sub.name AS subsystem,
               i.url, pr.name AS priority,
               " . sql_item_assignee_name() . " AS assignee,
               s.name AS status
        FROM items i
        LEFT JOIN projects p ON p.id = i.project_id
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN subsystems sub ON sub.id = i.subsystem_id
        LEFT JOIN priorities pr ON pr.id = i.priority_id
        LEFT JOIN statuses s ON s.id = i.status_id
        LEFT JOIN users u ON u.id = i.assigned_user_id
        LEFT JOIN users ou_direct ON ou_direct.id = p.owner_user_id
        WHERE " . implode(' AND ', $where) . '
        ORDER BY i.sort_order';

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$slugStmt = $mysqli->prepare('SELECT slug FROM projects WHERE id = ?');
$slugStmt->bind_param('i', $projectId);
$slugStmt->execute();
$slug = $slugStmt->get_result()->fetch_assoc()['slug'] ?? 'project';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="daybook-' . $slug . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['#', 'Item', 'Category', 'Subsystem', 'URL', 'Priority', 'Status', 'Assignee']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['sort_order'],
        $row['item_text'],
        $row['category'] ?? '',
        $row['subsystem'] ?? '',
        $row['url'],
        $row['priority'] ?? '',
        $row['status'] ?? '',
        $row['assignee'] ?? '',
    ]);
}
fclose($out);
exit;

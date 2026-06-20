<?php
// Generic CRUD + reorder handler for simple ordered lookup lists
// (categories, priorities: project-scoped; statuses: global).
function handle_option_list(PDO $pdo, string $table, bool $projectScoped): void {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if ($projectScoped) {
            $projectId = (int)($_GET['project_id'] ?? 0);
            if (!$projectId) fail('project_id is required');
            $stmt = $pdo->prepare("SELECT id, name, sort_order FROM {$table} WHERE project_id = ? ORDER BY sort_order, id");
            $stmt->execute([$projectId]);
        } else {
            $stmt = $pdo->query("SELECT id, name, sort_order FROM {$table} ORDER BY sort_order, id");
        }
        respond($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $body = json_body();
        $name = trim($body['name'] ?? '');
        if ($name === '') fail('Name is required');
        if ($projectScoped) {
            $projectId = (int)($body['project_id'] ?? 0);
            if (!$projectId) fail('project_id is required');
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM {$table} WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $maxOrder = (int)$stmt->fetchColumn();
            $ins = $pdo->prepare("INSERT INTO {$table} (project_id, name, sort_order) VALUES (?,?,?)");
            $ins->execute([$projectId, $name, $maxOrder + 1]);
        } else {
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM {$table}")->fetchColumn();
            $ins = $pdo->prepare("INSERT INTO {$table} (name, sort_order) VALUES (?,?)");
            $ins->execute([$name, $maxOrder + 1]);
        }
        respond(['id' => (int)$pdo->lastInsertId(), 'name' => $name], 201);
    }

    if ($method === 'PUT') {
        $body = json_body();
        $id = (int)($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        if (!$id || $name === '') fail('id and name are required');
        $pdo->prepare("UPDATE {$table} SET name = ? WHERE id = ?")->execute([$name, $id]);
        respond(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) fail('id is required');
        $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
        respond(['ok' => true]);
    }

    if ($method === 'PATCH') {
        $body = json_body();
        $order = $body['order'] ?? [];
        if (!is_array($order) || !$order) fail('order array is required');
        $stmt = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
        foreach ($order as $i => $id) {
            $stmt->execute([$i + 1, (int)$id]);
        }
        respond(['ok' => true]);
    }

    fail('Method not allowed', 405);
}

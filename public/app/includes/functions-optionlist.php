<?php
// Generic CRUD + reorder handler for simple ordered lookup lists
// (subsystems, priorities: project-scoped; statuses: global). Categories use this
// too but without color support.

require_once __DIR__ . '/functions-permissions.php';

function option_list_project_id_from_request(string $method, array $body): int {
    if ($method === 'GET') {
        return (int)($_GET['project_id'] ?? 0);
    }
    if (!empty($body['project_id'])) {
        return (int)$body['project_id'];
    }
    return 0;
}

function option_list_resolve_project_id(mysqli $mysqli, string $table, int $rowId): int {
    if ($table === 'statuses') {
        return 0;
    }
    $stmt = $mysqli->prepare("SELECT project_id FROM {$table} WHERE id = ?");
    $stmt->bind_param('i', $rowId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['project_id'] : 0;
}

function option_list_require_read(mysqli $mysqli, string $table, bool $projectScoped, int $projectId): void {
    if ($table === 'statuses') {
        return;
    }
    if ($projectScoped && $projectId) {
        permissions_require_project_access($mysqli, $projectId);
    }
}

function option_list_require_write(mysqli $mysqli, string $table, bool $projectScoped, int $projectId): void {
    if ($table === 'statuses') {
        if (!permissions_can_edit_global_statuses()) {
            fail('Forbidden', 403);
        }
        return;
    }
    if (!$projectId) {
        fail('project_id is required');
    }
    if (!permissions_can_edit_project_metadata($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }
}

function handle_option_list(mysqli $mysqli, string $table, bool $projectScoped, array $colorPalette = []): void {
    $method = $_SERVER['REQUEST_METHOD'];
    $body = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? json_body() : [];
    $columns = $table === 'categories' ? 'id, name, sort_order' : 'id, name, sort_order, bg_color, text_color';

    if ($method === 'GET') {
        if ($projectScoped) {
            $projectId = (int)($_GET['project_id'] ?? 0);
            if (!$projectId) fail('project_id is required');
            option_list_require_read($mysqli, $table, $projectScoped, $projectId);
            $stmt = $mysqli->prepare("SELECT {$columns} FROM {$table} WHERE project_id = ? ORDER BY sort_order, id");
            $stmt->bind_param('i', $projectId);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $mysqli->query("SELECT {$columns} FROM {$table} ORDER BY sort_order, id");
        }
        respond($result->fetch_all(MYSQLI_ASSOC));
    }

    if ($method === 'POST') {
        $name = trim($body['name'] ?? '');
        if ($name === '') fail('Name is required');

        if (!$projectScoped) {
            option_list_require_write($mysqli, $table, $projectScoped, 0);
        }

        $projectId = null;
        if ($projectScoped) {
            $projectId = (int)($body['project_id'] ?? 0);
            if (!$projectId) fail('project_id is required');
            option_list_require_write($mysqli, $table, $projectScoped, $projectId);
            $stmt = $mysqli->prepare("SELECT COUNT(*), COALESCE(MAX(sort_order),0) FROM {$table} WHERE project_id = ?");
            $stmt->bind_param('i', $projectId);
            $stmt->execute();
            $stmt->bind_result($count, $maxOrder);
            $stmt->fetch();
            $stmt->free_result();
        } else {
            $row = $mysqli->query("SELECT COUNT(*), COALESCE(MAX(sort_order),0) FROM {$table}")->fetch_row();
            [$count, $maxOrder] = $row;
        }

        $bg = $body['bg_color'] ?? null;
        $text = $body['text_color'] ?? null;
        if (!$bg && !$text && $colorPalette) {
            [$bg, $text] = $colorPalette[$count % count($colorPalette)];
        }
        $sortOrder = $maxOrder + 1;

        if ($table === 'categories') {
            $stmt = $mysqli->prepare("INSERT INTO {$table} (project_id, name, sort_order) VALUES (?,?,?)");
            $stmt->bind_param('isi', $projectId, $name, $sortOrder);
            $stmt->execute();
        } elseif ($projectScoped) {
            $stmt = $mysqli->prepare("INSERT INTO {$table} (project_id, name, sort_order, bg_color, text_color) VALUES (?,?,?,?,?)");
            $stmt->bind_param('isiss', $projectId, $name, $sortOrder, $bg, $text);
            $stmt->execute();
        } else {
            $stmt = $mysqli->prepare("INSERT INTO {$table} (name, sort_order, bg_color, text_color) VALUES (?,?,?,?)");
            $stmt->bind_param('siss', $name, $sortOrder, $bg, $text);
            $stmt->execute();
        }
        respond(['id' => $mysqli->insert_id, 'name' => $name, 'bg_color' => $bg, 'text_color' => $text], 201);
    }

    if ($method === 'PUT') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) fail('id is required');
        $projectId = option_list_resolve_project_id($mysqli, $table, $id);
        option_list_require_write($mysqli, $table, $projectScoped, $projectId ?: (int)($body['project_id'] ?? 0));

        $sets = [];
        $types = '';
        $params = [];
        if (array_key_exists('name', $body)) {
            $name = trim($body['name']);
            if ($name === '') fail('name cannot be blank');
            $sets[] = 'name = ?';
            $types .= 's';
            $params[] = $name;
        }
        if ($table !== 'categories') {
            if (array_key_exists('bg_color', $body)) {
                $sets[] = 'bg_color = ?';
                $types .= 's';
                $params[] = $body['bg_color'] ?: null;
            }
            if (array_key_exists('text_color', $body)) {
                $sets[] = 'text_color = ?';
                $types .= 's';
                $params[] = $body['text_color'] ?: null;
            }
        }
        if (!$sets) fail('No editable fields supplied');
        $types .= 'i';
        $params[] = $id;
        $stmt = $mysqli->prepare("UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ?');
        bind_dynamic($stmt, $types, $params);
        $stmt->execute();
        respond(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) fail('id is required');
        $projectId = option_list_resolve_project_id($mysqli, $table, $id);
        option_list_require_write($mysqli, $table, $projectScoped, $projectId);
        $stmt = $mysqli->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        respond(['ok' => true]);
    }

    if ($method === 'PATCH') {
        $order = $body['order'] ?? [];
        if (!is_array($order) || !$order) fail('order array is required');
        $firstId = (int)$order[0];
        $projectId = option_list_resolve_project_id($mysqli, $table, $firstId);
        option_list_require_write($mysqli, $table, $projectScoped, $projectId);
        $stmt = $mysqli->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
        foreach ($order as $i => $id) {
            $sortOrder = $i + 1;
            $idInt = (int)$id;
            $stmt->bind_param('ii', $sortOrder, $idInt);
            $stmt->execute();
        }
        respond(['ok' => true]);
    }

    fail('Method not allowed', 405);
}

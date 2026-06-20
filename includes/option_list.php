<?php
// Generic CRUD + reorder handler for simple ordered lookup lists
// (subsystems, priorities: project-scoped; statuses: global). Categories use this
// too but without color support.
//
// $colorPalette: an optional list of [bg,text] pairs to cycle through when a new
// row is created without an explicit color, so new entries get a sensible default
// instead of "no color".
function handle_option_list(PDO $pdo, string $table, bool $projectScoped, array $colorPalette = []): void {
    $method = $_SERVER['REQUEST_METHOD'];
    $columns = $table === 'categories' ? 'id, name, sort_order' : 'id, name, sort_order, bg_color, text_color';

    if ($method === 'GET') {
        if ($projectScoped) {
            $projectId = (int)($_GET['project_id'] ?? 0);
            if (!$projectId) fail('project_id is required');
            $stmt = $pdo->prepare("SELECT {$columns} FROM {$table} WHERE project_id = ? ORDER BY sort_order, id");
            $stmt->execute([$projectId]);
        } else {
            $stmt = $pdo->query("SELECT {$columns} FROM {$table} ORDER BY sort_order, id");
        }
        respond($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $body = json_body();
        $name = trim($body['name'] ?? '');
        if ($name === '') fail('Name is required');

        $projectId = null;
        if ($projectScoped) {
            $projectId = (int)($body['project_id'] ?? 0);
            if (!$projectId) fail('project_id is required');
            $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(MAX(sort_order),0) FROM {$table} WHERE project_id = ?");
            $stmt->execute([$projectId]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*), COALESCE(MAX(sort_order),0) FROM {$table}");
        }
        [$count, $maxOrder] = $stmt->fetch(PDO::FETCH_NUM);

        $bg = $body['bg_color'] ?? null;
        $text = $body['text_color'] ?? null;
        if (!$bg && !$text && $colorPalette) {
            [$bg, $text] = $colorPalette[$count % count($colorPalette)];
        }

        if ($table === 'categories') {
            $ins = $pdo->prepare("INSERT INTO {$table} (project_id, name, sort_order) VALUES (?,?,?)");
            $ins->execute([$projectId, $name, $maxOrder + 1]);
        } elseif ($projectScoped) {
            $ins = $pdo->prepare("INSERT INTO {$table} (project_id, name, sort_order, bg_color, text_color) VALUES (?,?,?,?,?)");
            $ins->execute([$projectId, $name, $maxOrder + 1, $bg, $text]);
        } else {
            $ins = $pdo->prepare("INSERT INTO {$table} (name, sort_order, bg_color, text_color) VALUES (?,?,?,?)");
            $ins->execute([$name, $maxOrder + 1, $bg, $text]);
        }
        respond(['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'bg_color' => $bg, 'text_color' => $text], 201);
    }

    if ($method === 'PUT') {
        $body = json_body();
        $id = (int)($body['id'] ?? 0);
        if (!$id) fail('id is required');

        $sets = [];
        $params = [];
        if (array_key_exists('name', $body)) {
            $name = trim($body['name']);
            if ($name === '') fail('name cannot be blank');
            $sets[] = 'name = ?';
            $params[] = $name;
        }
        if ($table !== 'categories') {
            if (array_key_exists('bg_color', $body)) { $sets[] = 'bg_color = ?'; $params[] = $body['bg_color'] ?: null; }
            if (array_key_exists('text_color', $body)) { $sets[] = 'text_color = ?'; $params[] = $body['text_color'] ?: null; }
        }
        if (!$sets) fail('No editable fields supplied');
        $params[] = $id;
        $pdo->prepare("UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
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

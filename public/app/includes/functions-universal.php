<?php
// General-purpose functions shared by every /api endpoint. Assumes
// config/config.php has already been required (global $mysqli available).

function json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function fail(string $message, int $code = 400): void {
    respond(['error' => $message], $code);
}

function db_escape(mysqli $mysqli, string $value): string {
    return $mysqli->real_escape_string($value);
}

// mysqli_stmt::bind_param needs its arguments by reference, which rules out a
// plain call with a variable-length array. This rebuilds the reference list
// so callers can bind a dynamic number of params (e.g. partial UPDATE SETs).
function bind_dynamic(mysqli_stmt $stmt, string $types, array $params): void {
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function next_sort_order(mysqli $mysqli): int {
    $row = $mysqli->query('SELECT COALESCE(MAX(sort_order),0) AS m FROM items')->fetch_assoc();
    return (int)$row['m'] + 1;
}

function seed_project_defaults(mysqli $mysqli, int $projectId): void {
    require_once __DIR__ . '/functions-colors.php';

    $categories = ['bug', 'style', 'business', 'feature', 'rebuild', 'content', 'test', 'migrate', 'other'];
    $stmt = $mysqli->prepare('INSERT INTO categories (project_id, name, sort_order) VALUES (?,?,?)');
    foreach ($categories as $i => $name) {
        $order = $i + 1;
        $stmt->bind_param('isi', $projectId, $name, $order);
        $stmt->execute();
    }

    $priorities = ['1-Critical', '2-High', '3-Medium', '4-Low', '5-Someday'];
    $stmt = $mysqli->prepare('INSERT INTO priorities (project_id, name, sort_order, bg_color, text_color) VALUES (?,?,?,?,?)');
    foreach ($priorities as $i => $name) {
        [$bg, $text] = PRIORITY_GRADIENT_PALETTE[$i % count(PRIORITY_GRADIENT_PALETTE)];
        $order = $i + 1;
        $stmt->bind_param('isiss', $projectId, $name, $order, $bg, $text);
        $stmt->execute();
    }
    // Subsystems are an open, per-project list with no seed entries - new ones
    // get the next grey shade from GREY_PALETTE when created (see api/subsystems.php).
}

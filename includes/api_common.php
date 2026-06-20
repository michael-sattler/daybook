<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
require_login();

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

function next_sort_order(PDO $pdo): int {
    $row = $pdo->query('SELECT COALESCE(MAX(sort_order),0) AS m FROM items')->fetch();
    return (int)$row['m'] + 1;
}

function seed_project_defaults(PDO $pdo, int $projectId): void {
    require_once __DIR__ . '/color_palettes.php';

    $categories = ['bug','style','business','feature','rebuild','content','test','migrate','other'];
    $stmt = $pdo->prepare('INSERT INTO categories (project_id, name, sort_order) VALUES (?,?,?)');
    foreach ($categories as $i => $name) {
        $stmt->execute([$projectId, $name, $i + 1]);
    }

    $priorities = ['1-Critical','2-High','3-Medium','4-Low','5-Someday'];
    $stmt = $pdo->prepare('INSERT INTO priorities (project_id, name, sort_order, bg_color, text_color) VALUES (?,?,?,?,?)');
    foreach ($priorities as $i => $name) {
        [$bg, $text] = PRIORITY_GRADIENT_PALETTE[$i % count(PRIORITY_GRADIENT_PALETTE)];
        $stmt->execute([$projectId, $name, $i + 1, $bg, $text]);
    }
    // Subsystems are an open, per-project list with no seed entries - new ones
    // get the next grey shade from GREY_PALETTE when created (see api/subsystems.php).
}

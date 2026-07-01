<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $payload = [
        'id' => current_user_id(),
        'email' => current_user_email(),
        'name' => current_user_name(),
        'display_name' => current_user_display_name(),
        'is_daybookstaff' => is_daybookstaff(),
    ];
    $projectId = (int)($_GET['project_id'] ?? 0);
    if ($projectId) {
        permissions_require_project_access($mysqli, $projectId);
        $payload['caps'] = permissions_project_caps($mysqli, $projectId);
    }
    respond($payload);
}

fail('Method not allowed', 405);

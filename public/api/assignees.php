<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) fail('project_id is required');
    permissions_require_project_access($mysqli, $projectId);
    try {
        respond(permissions_project_assignee_options_list($mysqli, $projectId));
    } catch (Throwable $e) {
        debug_log('assignees failed: ' . $e->getMessage());
        respond(permissions_project_members_list($mysqli, $projectId));
    }
}

fail('Method not allowed', 405);

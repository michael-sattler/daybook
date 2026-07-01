<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-optionlist.php';
require_once __DIR__ . '/../app/includes/functions-colors.php';
require_once __DIR__ . '/../app/includes/functions-permissions.php';
header('Content-Type: application/json');
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PATCH') {
    $body = json_body();
    if (($body['action'] ?? '') === 'sync_palette') {
        $projectId = (int)($body['project_id'] ?? 0);
        if (!$projectId) fail('project_id is required');
        if (!permissions_can_edit_project_metadata($mysqli, $projectId)) {
            fail('Forbidden', 403);
        }
        $updated = sync_priority_palette_for_project($mysqli, $projectId);
        respond(['ok' => true, 'updated' => $updated]);
    }
}

handle_option_list($mysqli, 'priorities', true, PRIORITY_GRADIENT_PALETTE);

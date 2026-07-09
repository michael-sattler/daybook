<?php
// Permission checks for the user layer (docs/scope-userlayer.md).
// Requires config.php (session + $mysqli).

require_once __DIR__ . '/functions-universal.php';

const PROJECT_ROLES = ['admin', 'manager', 'contributor', 'viewer'];
const INVITE_ROLES = ['admin', 'manager', 'contributor', 'viewer'];

function permissions_load_user(mysqli $mysqli, int $userId): ?array {
    $stmt = $mysqli->prepare('SELECT id, email, name, is_daybookstaff, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function permissions_member_role(mysqli $mysqli, int $projectId, int $userId): ?string {
    $stmt = $mysqli->prepare('SELECT role FROM project_members WHERE project_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['role'] : null;
}

function permissions_project_owner_id(mysqli $mysqli, int $projectId): ?int {
    $expr = sql_project_owner_user_id_expr('p');
    $stmt = $mysqli->prepare("SELECT {$expr} AS owner_user_id FROM projects p WHERE p.id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && $row['owner_user_id'] ? (int)$row['owner_user_id'] : null;
}

function permissions_project_owner_display_name(mysqli $mysqli, int $projectId): string {
    $ownerId = permissions_project_owner_id($mysqli, $projectId);
    if (!$ownerId) {
        return '';
    }
    $stmt = $mysqli->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->bind_param('i', $ownerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return trim((string)($row['name'] ?? ''));
}

function permissions_is_project_owner(mysqli $mysqli, int $projectId, int $userId): bool {
    $ownerId = permissions_project_owner_id($mysqli, $projectId);
    return $ownerId !== null && $ownerId === $userId;
}

function permissions_item_effective_assignee_id(array $item): ?int {
    if (!empty($item['assigned_to_project_owner'])) {
        $ownerId = $item['project_owner_user_id'] ?? null;
        return $ownerId ? (int)$ownerId : null;
    }
    return !empty($item['assigned_user_id']) ? (int)$item['assigned_user_id'] : null;
}

function permissions_has_assignment_in_project(mysqli $mysqli, int $projectId, int $userId): bool {
    $ownerExpr = sql_project_owner_user_id_expr('p');
    $stmt = $mysqli->prepare(
        "SELECT 1 FROM items i
         INNER JOIN projects p ON p.id = i.project_id
         WHERE i.project_id = ?
           AND (i.assigned_user_id = ? OR (i.assigned_to_project_owner = 1 AND {$ownerExpr} = ?))
         LIMIT 1"
    );
    $stmt->bind_param('iii', $projectId, $userId, $userId);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

function permissions_fetch_item(mysqli $mysqli, int $itemId): ?array {
    $ownerExpr = sql_project_owner_user_id_expr('p');
    $stmt = $mysqli->prepare(
        "SELECT i.*, {$ownerExpr} AS project_owner_user_id
         FROM items i
         JOIN projects p ON p.id = i.project_id
         WHERE i.id = ?"
    );
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function permissions_role_rank(?string $role): int {
    return match ($role) {
        'admin' => 4,
        'manager' => 3,
        'contributor' => 2,
        'viewer' => 1,
        default => 0,
    };
}

/** Project membership role, or null if not a member (Daybookstaff may still act site-wide). */
function permissions_current_project_role(mysqli $mysqli, int $projectId): ?string {
    $userId = current_user_id();
    if (!$userId) {
        return null;
    }
    return permissions_member_role($mysqli, $projectId, $userId);
}

function permissions_can_access_project(mysqli $mysqli, int $projectId): bool {
    if (is_daybookstaff()) {
        return true;
    }
    return permissions_current_project_role($mysqli, $projectId) !== null;
}

function permissions_can_create_project(mysqli $mysqli): bool {
    if (is_daybookstaff()) {
        return true;
    }
    // Any user with admin on at least one project, or any logged-in user who can create:
    // matrix: Admin yes, others no. New users invited as non-admin cannot create until admin somewhere.
    // Simplest: daybookstaff OR has admin membership on any project OR no projects yet (first project).
    $userId = current_user_id();
    if (!$userId) {
        return false;
    }
    $stmt = $mysqli->prepare("SELECT 1 FROM project_members WHERE user_id = ? AND role = 'admin' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        return true;
    }
    $cStmt = $mysqli->prepare('SELECT COUNT(*) FROM project_members WHERE user_id = ?');
    $cStmt->bind_param('i', $userId);
    $cStmt->execute();
    $cStmt->bind_result($count);
    $cStmt->fetch();
    return (int)$count === 0;
}

function permissions_can_delete_project(mysqli $mysqli, int $projectId): bool {
    $userId = current_user_id();
    if (!$userId) {
        return false;
    }
    if (is_daybookstaff()) {
        return true;
    }
    return permissions_is_project_owner($mysqli, $projectId, $userId)
        && permissions_current_project_role($mysqli, $projectId) === 'admin';
}

function permissions_can_edit_project(mysqli $mysqli, int $projectId): bool {
    if (is_daybookstaff()) {
        return true;
    }
    return permissions_current_project_role($mysqli, $projectId) === 'admin';
}

function permissions_can_manage_members(mysqli $mysqli, int $projectId): bool {
    if (is_daybookstaff()) {
        return true;
    }
    return permissions_current_project_role($mysqli, $projectId) === 'admin';
}

function permissions_can_edit_project_metadata(mysqli $mysqli, int $projectId): bool {
    $userId = current_user_id();
    if (!$userId) {
        return false;
    }
    if (is_daybookstaff()) {
        return true;
    }
    return permissions_is_project_owner($mysqli, $projectId, $userId)
        && permissions_current_project_role($mysqli, $projectId) === 'admin';
}

function permissions_can_edit_global_statuses(): bool {
    return is_daybookstaff();
}

function permissions_can_create_item(mysqli $mysqli, int $projectId): bool {
    if (!permissions_can_access_project($mysqli, $projectId)) {
        return false;
    }
    if (is_daybookstaff()) {
        return true;
    }
    $role = permissions_current_project_role($mysqli, $projectId);
    return in_array($role, ['admin', 'manager'], true);
}

function permissions_can_assign_items(mysqli $mysqli, int $projectId): bool {
    if (is_daybookstaff()) {
        return true;
    }
    $role = permissions_current_project_role($mysqli, $projectId);
    return in_array($role, ['admin', 'manager'], true);
}

/**
 * Whether the user can see a task in a project (visibility, not edit).
 */
function permissions_can_view_item(mysqli $mysqli, array $item, int $userId): bool {
    $projectId = (int)$item['project_id'];
    if (is_daybookstaff()) {
        return true;
    }
    if (!permissions_can_access_project($mysqli, $projectId)) {
        return false;
    }
    $role = permissions_member_role($mysqli, $projectId, $userId);
    if ($role === 'admin') {
        return true;
    }
    if (permissions_has_assignment_in_project($mysqli, $projectId, $userId)) {
        return true;
    }
    if (!empty($item['created_by_user_id']) && (int)$item['created_by_user_id'] === $userId) {
        return true;
    }
    return false;
}

function permissions_item_visibility_clause(mysqli $mysqli, int $projectId, int $userId, string $alias = 'i'): string {
    if (is_daybookstaff()) {
        return '1=1';
    }
    $role = permissions_member_role($mysqli, $projectId, $userId);
    if ($role === 'admin') {
        return '1=1';
    }
    if (permissions_has_assignment_in_project($mysqli, $projectId, $userId)) {
        return '1=1';
    }
    $uid = (int)$userId;
    return "({$alias}.created_by_user_id = {$uid})";
}

function permissions_can_delete_item(mysqli $mysqli, array $item): bool {
    $userId = current_user_id();
    if (!$userId) {
        return false;
    }
    if (is_daybookstaff()) {
        return true;
    }
    $projectId = (int)$item['project_id'];
    if (!permissions_can_view_item($mysqli, $item, $userId)) {
        return false;
    }
    if (permissions_is_project_owner($mysqli, $projectId, $userId)
        && permissions_current_project_role($mysqli, $projectId) === 'admin') {
        return true;
    }
    $role = permissions_current_project_role($mysqli, $projectId);
    if (in_array($role, ['admin', 'manager'], true)
        && !empty($item['created_by_user_id'])
        && (int)$item['created_by_user_id'] === $userId) {
        return true;
    }
    return false;
}

function permissions_can_edit_item(mysqli $mysqli, array $item, string $field): bool {
    $userId = current_user_id();
    if (!$userId) {
        return false;
    }
    $projectId = (int)$item['project_id'];
    if (!permissions_can_view_item($mysqli, $item, $userId)) {
        return false;
    }

    if ($field === 'assigned_user_id') {
        return permissions_can_assign_items($mysqli, $projectId);
    }

    if ($field === 'project_id') {
        if (is_daybookstaff()) {
            return true;
        }
        if (permissions_current_project_role($mysqli, $projectId) !== 'admin') {
            return false;
        }
        return permissions_is_project_owner($mysqli, $projectId, $userId);
    }

    if (is_daybookstaff()) {
        return true;
    }

    $role = permissions_current_project_role($mysqli, $projectId);

    if ($field === 'status_id') {
        return in_array($role, ['admin', 'manager', 'contributor'], true);
    }

    if (in_array($role, ['admin', 'manager'], true)) {
        return true;
    }

    if ($role === 'contributor') {
        $assigneeId = permissions_item_effective_assignee_id($item);
        return $assigneeId !== null && $assigneeId === $userId;
    }

    return false;
}

function permissions_can_reorder_items(mysqli $mysqli, int $projectId): bool {
    if (!permissions_can_access_project($mysqli, $projectId)) {
        return false;
    }
    if (is_daybookstaff()) {
        return true;
    }
    $role = permissions_current_project_role($mysqli, $projectId);
    return in_array($role, ['admin', 'manager', 'contributor'], true);
}

function permissions_can_edit_docs_notes(mysqli $mysqli, array $item): bool {
    $userId = current_user_id();
    if (!$userId) {
        return false;
    }
    if (!permissions_can_view_item($mysqli, $item, $userId)) {
        return false;
    }
    if (is_daybookstaff()) {
        return true;
    }
    $projectId = (int)$item['project_id'];
    if (permissions_is_project_owner($mysqli, $projectId, $userId)
        && permissions_current_project_role($mysqli, $projectId) === 'admin') {
        return true;
    }
    $role = permissions_current_project_role($mysqli, $projectId);
    if (in_array($role, ['admin', 'manager', 'contributor'], true)) {
        $assigneeId = permissions_item_effective_assignee_id($item);
        return $assigneeId !== null && $assigneeId === $userId;
    }
    return false;
}

/** Build permission flags for the current user on a project (for API / front-end). */
function permissions_project_caps(mysqli $mysqli, int $projectId): array {
    $role = permissions_current_project_role($mysqli, $projectId);
    $isOwner = permissions_is_project_owner($mysqli, $projectId, current_user_id());
    return [
        'role' => $role,
        'is_daybookstaff' => is_daybookstaff(),
        'is_owner' => $isOwner,
        'owner_user_id' => permissions_project_owner_id($mysqli, $projectId),
        'owner_name' => permissions_project_owner_display_name($mysqli, $projectId),
        'can_edit_project' => permissions_can_edit_project($mysqli, $projectId),
        'can_delete_project' => permissions_can_delete_project($mysqli, $projectId),
        'can_manage_members' => permissions_can_manage_members($mysqli, $projectId),
        'can_edit_metadata' => permissions_can_edit_project_metadata($mysqli, $projectId),
        'can_create_item' => permissions_can_create_item($mysqli, $projectId),
        'can_assign_items' => permissions_can_assign_items($mysqli, $projectId),
        'can_reorder' => permissions_can_reorder_items($mysqli, $projectId),
        'can_export' => permissions_can_access_project($mysqli, $projectId),
    ];
}

function permissions_require_project_access(mysqli $mysqli, int $projectId): void {
    if (!permissions_can_access_project($mysqli, $projectId)) {
        fail('Forbidden', 403);
    }
}

function permissions_require_item_view(mysqli $mysqli, int $itemId): array {
    $item = permissions_fetch_item($mysqli, $itemId);
    if (!$item) {
        fail('Not found', 404);
    }
    if (!permissions_can_view_item($mysqli, $item, current_user_id())) {
        fail('Forbidden', 403);
    }
    return $item;
}

function permissions_require_item_edit(mysqli $mysqli, int $itemId, string $field): array {
    $item = permissions_fetch_item($mysqli, $itemId);
    if (!$item) {
        fail('Not found', 404);
    }
    if (!permissions_can_edit_item($mysqli, $item, $field)) {
        fail('Forbidden', 403);
    }
    return $item;
}

function permissions_generate_token(): string {
    return bin2hex(random_bytes(32));
}

function permissions_invite_url(string $token): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/invite/' . $token;
}

/**
 * @return array{ok: true, project_slug: string}|array{error: string, code?: int}
 */
function accept_project_invite(mysqli $mysqli, string $token, string $password): array {
    $token = trim($token);
    if ($token === '' || $password === '') {
        return ['error' => 'token and password are required', 'code' => 400];
    }

    $stmt = $mysqli->prepare(
        'SELECT id, project_id, email, role, expires_at, accepted_at FROM project_invites WHERE token = ?'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $invite = $stmt->get_result()->fetch_assoc();
    if (!$invite || $invite['accepted_at']) {
        return ['error' => 'Invalid or expired invite', 'code' => 400];
    }
    if ($invite['expires_at'] && (int)$invite['expires_at'] < time()) {
        return ['error' => 'Invite has expired', 'code' => 400];
    }

    $email = strtolower($invite['email']);
    $check = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        return ['error' => 'An account with this email already exists. Log in instead.', 'code' => 400];
    }

    $now = time();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $isStaff = 0;
    $uStmt = $mysqli->prepare(
        'INSERT INTO users (email, password_hash, is_daybookstaff, created_at) VALUES (?,?,?,?)'
    );
    $uStmt->bind_param('ssii', $email, $hash, $isStaff, $now);
    $uStmt->execute();
    $userId = (int)$mysqli->insert_id;

    $projectId = (int)$invite['project_id'];
    $role = $invite['role'];
    $mStmt = $mysqli->prepare(
        'INSERT INTO project_members (project_id, user_id, role, created_at) VALUES (?,?,?,?)'
    );
    $mStmt->bind_param('iisi', $projectId, $userId, $role, $now);
    $mStmt->execute();

    $iStmt = $mysqli->prepare('UPDATE project_invites SET accepted_at = ? WHERE id = ?');
    $inviteId = (int)$invite['id'];
    $iStmt->bind_param('ii', $now, $inviteId);
    $iStmt->execute();

    load_user_into_session($mysqli, [
        'id' => $userId,
        'email' => $email,
        'name' => '',
        'is_daybookstaff' => 0,
    ]);

    $slugStmt = $mysqli->prepare('SELECT slug FROM projects WHERE id = ?');
    $slugStmt->bind_param('i', $projectId);
    $slugStmt->execute();
    $slug = $slugStmt->get_result()->fetch_assoc()['slug'] ?? '';

    return ['ok' => true, 'project_slug' => $slug];
}

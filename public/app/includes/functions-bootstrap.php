<?php
// One-time bootstrap: seed Daybookstaff and migrate legacy single-user data.
// Called from database.php after connection is established.

function bootstrap_users_layer(mysqli $mysqli): void {
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'users'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $count = (int)$mysqli->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
    if ($count > 0) {
        return;
    }

    global $daybookStaffEmail, $daybookStaffPasswordHash, $authUserEmail, $authPasswordHash;

    $email = trim((string)($daybookStaffEmail ?? $authUserEmail ?? ''));
    $hash = (string)($daybookStaffPasswordHash ?? $authPasswordHash ?? '');

    if ($email === '' || $hash === '' || str_contains($hash, 'CHANGE_ME')) {
        debug_log('bootstrap_users_layer: skipped — set daybookStaffEmail and daybookStaffPasswordHash in platform config');
        return;
    }

    $now = time();
    $isStaff = 1;
    $stmt = $mysqli->prepare(
        'INSERT INTO users (email, password_hash, is_daybookstaff, created_at) VALUES (?,?,?,?)'
    );
    $stmt->bind_param('ssii', $email, $hash, $isStaff, $now);
    $stmt->execute();
    $staffId = (int)$mysqli->insert_id;

    $mysqli->query('UPDATE projects SET owner_user_id = ' . $staffId . ' WHERE owner_user_id IS NULL');
    $mysqli->query('UPDATE items SET created_by_user_id = ' . $staffId . ' WHERE created_by_user_id IS NULL');

    $projects = $mysqli->query('SELECT id FROM projects');
    $memberStmt = $mysqli->prepare(
        "INSERT IGNORE INTO project_members (project_id, user_id, role, created_at) VALUES (?,?,'admin',?)"
    );
    while ($row = $projects->fetch_assoc()) {
        $pid = (int)$row['id'];
        $memberStmt->bind_param('iii', $pid, $staffId, $now);
        $memberStmt->execute();
    }

    debug_log('bootstrap_users_layer: created Daybookstaff user id=' . $staffId . ' email=' . $email);
}

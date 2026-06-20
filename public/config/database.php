<?php
// Establishes the global $mysqli connection used by every db-touching file.
// Requires $dbHost/$dbName/$dbUser/$dbPass to already be defined by the
// platform config file (development/staging/production.config.php).

mysqli_report(MYSQLI_REPORT_OFF);

$mysqli = mysqli_init();
if (!$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort ?? null)) {
    debug_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

<?php
// Central platform-aware config loader. Every page in /public should start with:
//   require_once(__DIR__ . '/../config/config.php');
// or, for files inside /public/api or /public/app, the equivalent relative path.

define('PUBLIC_ROOT', __DIR__ . '/..');
define('PROJECT_ROOT', PUBLIC_ROOT . '/..');
define('LOG_PATH', PUBLIC_ROOT . '/logs');

// Platform file is chosen by APP_ENV (set via .htaccess `SetEnv APP_ENV production`
// on the live server) if present, otherwise by whichever platform file actually
// exists - exactly one of development/staging/production.config.php should be
// present on any given server. None of these three are committed to git.
$envName = $_SERVER['APP_ENV'] ?? (getenv('APP_ENV') ?: null);
if (!$envName) {
    foreach (['production', 'staging', 'development'] as $candidate) {
        if (file_exists(__DIR__ . "/{$candidate}.config.php")) {
            $envName = $candidate;
            break;
        }
    }
}
$envName = $envName ?: 'development';
define('APP_ENV', $envName);

$platformFile = __DIR__ . "/{$envName}.config.php";
if (!file_exists($platformFile)) {
    throw new RuntimeException(
        "Missing config/{$envName}.config.php - copy config/template.config.php, fill in real credentials, and do not commit it."
    );
}
function debug_log($message): void {
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0775, true);
    }
    $line = is_string($message) ? $message : print_r($message, true);
    file_put_contents(LOG_PATH . '/app.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
}

require $platformFile; // defines $dbHost, $dbName, $dbUser, $dbPass, $authPasswordHash

require __DIR__ . '/database.php'; // establishes global $mysqli
require __DIR__ . '/auth.php';     // session/login helpers

<?php
// Copy this file to development.config.php, staging.config.php, or
// production.config.php (matching whichever platform this server is) and
// fill in real values. None of those three filenames are committed to git.

$dbHost = 'localhost';
$dbName = 'CHANGE_ME_dbname';
$dbUser = 'CHANGE_ME_dbuser';
$dbPass = 'CHANGE_ME_dbpass';

// Daybookstaff bootstrap account (created automatically when users table is empty).
// Generate hash with: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
$daybookStaffEmail = 'CHANGE_ME_email@example.com';
$daybookStaffPasswordHash = 'CHANGE_ME_PASSWORD_HASH';

// Legacy keys (optional fallback for bootstrap if daybookStaff* are not set):
// $authPasswordHash = '...';
// $authUserEmail = '...';

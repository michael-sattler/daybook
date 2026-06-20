NOTE: default password (for personal use during testing): daybook2026!!!!!

# Pick any login password you want (e.g. MySecureDaybook2026!), then generate a hash for that exact string:
php -r "echo password_hash('MySecureDaybook2026!', PASSWORD_DEFAULT);"

# Put the hash output into production.config.php directly via FTP:
$authPasswordHash = '$2y$10$...long hash string...';

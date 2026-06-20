<?php
// Copy this file's values to includes/config.local.php on the server (gitignored)
// or just edit these directly after upload. Never commit real credentials.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'CHANGE_ME_dbname',
        'user' => 'CHANGE_ME_dbuser',
        'pass' => 'CHANGE_ME_dbpass',
    ],
    // Generate a hash with: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
    'auth' => [
        'password_hash' => 'CHANGE_ME_PASSWORD_HASH',
    ],
];

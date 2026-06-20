<?php
function app_config(): array {
    static $config = null;
    if ($config === null) {
        $local = __DIR__ . '/config.local.php';
        $config = file_exists($local) ? require $local : require __DIR__ . '/config.php';
    }
    return $config;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = app_config()['db'];
        $port = $c['port'] ?? null;
        $dsn = "mysql:host={$c['host']};" . ($port ? "port={$port};" : '') . "dbname={$c['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

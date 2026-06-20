<?php
require_once __DIR__ . '/config/config.php';
logout();
header('Location: /login');
exit;

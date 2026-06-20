<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-optionlist.php';
header('Content-Type: application/json');
require_login();

handle_option_list($mysqli, 'statuses', false, [
    ['#e5e7eb', '#374151'],
    ['#bfdbfe', '#1e3a8a'],
    ['#bbf7d0', '#14532d'],
    ['#fecaca', '#7f1d1d'],
]);

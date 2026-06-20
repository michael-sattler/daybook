<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/includes/functions-universal.php';
require_once __DIR__ . '/../app/includes/functions-optionlist.php';
require_once __DIR__ . '/../app/includes/functions-colors.php';
header('Content-Type: application/json');
require_login();

handle_option_list($mysqli, 'priorities', true, PRIORITY_GRADIENT_PALETTE);

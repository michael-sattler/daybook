<?php
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/option_list.php';
handle_option_list(db(), 'statuses', false);

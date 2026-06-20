<?php
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/option_list.php';
require_once __DIR__ . '/../includes/color_palettes.php';
handle_option_list(db(), 'subsystems', true, GREY_PALETTE);

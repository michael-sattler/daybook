<?php
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/option_list.php';
require_once __DIR__ . '/../includes/color_palettes.php';
handle_option_list(db(), 'statuses', false, [
    ['#e5e7eb', '#374151'],
    ['#bfdbfe', '#1e3a8a'],
    ['#bbf7d0', '#14532d'],
    ['#fecaca', '#7f1d1d'],
]);

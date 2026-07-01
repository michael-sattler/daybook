<?php
require __DIR__ . '/../public/config/config.php';
require __DIR__ . '/../public/app/includes/functions-colors.php';

$projects = $mysqli->query('SELECT id FROM projects');
while ($row = $projects->fetch_assoc()) {
    $id = (int)$row['id'];
    $n = sync_priority_palette_for_project($mysqli, $id);
    echo "project {$id}: {$n} priorities updated\n";
}

<?php
// Default color rotations used when a new option-list entry is created without
// an explicit color. Each entry is [bg_color, text_color]. Users can always
// override via the color picker afterwards.

const GREY_PALETTE = [
    ['#f3f4f6', '#374151'],
    ['#e5e7eb', '#374151'],
    ['#d1d5db', '#1f2937'],
    ['#9ca3af', '#1f2937'],
    ['#6b7280', '#ffffff'],
    ['#4b5563', '#ffffff'],
];

const PASTEL_ROYGBIV_PALETTE = [
    ['#ffd6d6', '#7a2e2e'], // red
    ['#ffe2c2', '#7a4a16'], // orange
    ['#fff6c2', '#6b5b12'], // yellow
    ['#d6f5d6', '#1f5c2e'], // green
    ['#cfe3ff', '#1e3a8a'], // blue
    ['#d9d6ff', '#3730a3'], // indigo
    ['#f0d6f7', '#6b21a8'], // violet
];

const PRIORITY_GRADIENT_PALETTE = [
/*    ['#b91c1c', '#ffffff'], // deep warning red
    ['#dc4f4f', '#ffffff'],
    ['#f08080', '#3a1212'],
    ['#ddc1c1', '#3a2a2a'],
    ['#c9d6e8', '#1f2937'],
    ['#a9c6e8', '#1f2937'],
    ['#dbeafe', '#1e3a5f'], // pale blue "someday"
*/
    ['#586cff', '#ffffff'], // periwinkle
    ['#7989ff', '#ffffff'],
    ['#9ba7ff', '#ffffff'],
    ['#bcc4ff', '#ffffff'],
    ['#dee2ff', '#586cff'],
    ['#eef0ff', '#586cff'],
    ['#ffffff', '#586cff'], // white "someday"

];

/** Colors for a priority from its sort_order (1-based). */
function priority_palette_colors(int $sortOrder): array {
    $palette = PRIORITY_GRADIENT_PALETTE;
    $index = max(0, $sortOrder - 1) % count($palette);
    return $palette[$index];
}

/** Re-apply PRIORITY_GRADIENT_PALETTE to every priority row in a project. */
function sync_priority_palette_for_project(mysqli $mysqli, int $projectId): int {
    $stmt = $mysqli->prepare('SELECT id, sort_order FROM priorities WHERE project_id = ? ORDER BY sort_order, id');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $upd = $mysqli->prepare('UPDATE priorities SET bg_color = ?, text_color = ? WHERE id = ?');
    foreach ($rows as $row) {
        [$bg, $text] = priority_palette_colors((int)$row['sort_order']);
        $id = (int)$row['id'];
        $upd->bind_param('ssi', $bg, $text, $id);
        $upd->execute();
    }
    return count($rows);
}

// Swatches offered in the color-picker popup (paint bucket / "T" buttons).
const COMMON_COLOR_SWATCHES = [
    '#ffffff', '#f3f4f6', '#e5e7eb', '#9ca3af', '#4b5563', '#1f2937', '#000000',
    '#fecaca', '#fca5a5', '#dc2626', '#7f1d1d',
    '#fed7aa', '#fb923c', '#c2410c',
    '#fef08a', '#facc15', '#a16207',
    '#bbf7d0', '#4ade80', '#15803d',
    '#bfdbfe', '#60a5fa', '#1d4ed8',
    '#ddd6fe', '#a78bfa', '#5b21b6',
    '#fbcfe8', '#f472b6', '#9d174d',
];

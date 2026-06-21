<?php
function slugify(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'project';
}

function unique_project_slug(mysqli $mysqli, string $base, ?int $excludeId = null): string {
    $slug = $base;
    $i = 2;
    if ($excludeId !== null) {
        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM projects WHERE slug = ? AND id != ?');
    } else {
        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM projects WHERE slug = ?');
    }
    while (true) {
        if ($excludeId !== null) {
            $stmt->bind_param('si', $slug, $excludeId);
        } else {
            $stmt->bind_param('s', $slug);
        }
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->free_result();
        if ((int)$count === 0) return $slug;
        $slug = $base . '-' . $i++;
    }
}

<?php
function slugify(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'project';
}

function unique_project_slug(PDO $pdo, string $base): string {
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE slug = ?');
    while (true) {
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $i++;
    }
}

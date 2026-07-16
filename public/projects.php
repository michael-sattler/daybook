<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/includes/functions-universal.php';
require_once __DIR__ . '/app/includes/functions-permissions.php';
require_login();

$userId = current_user_id();

// Completed statuses match the task grid ("Show completed" / active_only).
$completedStatuses = ['OK', 'N/A'];

if (is_daybookstaff()) {
    $sql = 'SELECT p.id, p.name, p.description, p.slug, p.sort_order, p.bg_color, p.text_color, p.owner_user_id,
                   pm.role AS my_role
            FROM projects p
            LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
            ORDER BY p.sort_order, p.id';
} else {
    $sql = 'SELECT p.id, p.name, p.description, p.slug, p.sort_order, p.bg_color, p.text_color, p.owner_user_id,
                   pm.role AS my_role
            FROM projects p
            INNER JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
            ORDER BY p.sort_order, p.id';
}
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$projectIds = array_map(static fn($p) => (int)$p['id'], $projects);
$membersByProject = [];
$statsByProject = [];

if ($projectIds) {
    $idList = implode(',', $projectIds);
    $memberSql = "SELECT pm.project_id, COALESCE(u.name, '') AS name, COALESCE(u.email, '') AS email
                  FROM project_members pm
                  LEFT JOIN users u ON u.id = pm.user_id
                  WHERE pm.project_id IN ({$idList})
                  ORDER BY pm.project_id, pm.role, u.name, u.email";
    $memberRows = $mysqli->query($memberSql)->fetch_all(MYSQLI_ASSOC);
    foreach ($memberRows as $row) {
        $pid = (int)$row['project_id'];
        $label = user_display_name($row['name'], $row['email']);
        if ($label === '') {
            continue;
        }
        $membersByProject[$pid][] = $label;
    }

    $completedList = "'" . implode("','", $completedStatuses) . "'";
    foreach ($projectIds as $pid) {
        $vis = permissions_item_visibility_clause($mysqli, $pid, $userId);
        $statsSql = "SELECT
                        SUM(CASE WHEN s.name IN ({$completedList}) THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN s.name IS NULL OR s.name NOT IN ({$completedList}) THEN 1 ELSE 0 END) AS outstanding,
                        MAX(i.updated_at) AS last_updated_at
                     FROM items i
                     LEFT JOIN statuses s ON s.id = i.status_id
                     WHERE i.project_id = {$pid} AND ({$vis})";
        $stats = $mysqli->query($statsSql)->fetch_assoc() ?: [];
        $lastText = null;
        $lastAt = !empty($stats['last_updated_at']) ? (int)$stats['last_updated_at'] : null;
        if ($lastAt) {
            $lastSql = "SELECT i.item_text
                        FROM items i
                        WHERE i.project_id = {$pid} AND ({$vis}) AND i.updated_at = {$lastAt}
                        ORDER BY i.id DESC
                        LIMIT 1";
            $lastRow = $mysqli->query($lastSql)->fetch_assoc();
            $lastText = $lastRow['item_text'] ?? null;
        }
        $statsByProject[$pid] = [
            'completed' => (int)($stats['completed'] ?? 0),
            'outstanding' => (int)($stats['outstanding'] ?? 0),
            'last_updated_at' => $lastAt,
            'last_item_text' => $lastText,
        ];
    }
}

function project_card_truncate(string $text, int $max = 72): string {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

function project_card_format_date(?int $ts): string {
    if (!$ts) {
        return '';
    }
    return date('M j, Y', $ts);
}

ob_start();
?>
  <div class="projects-page">
    <h1>All Projects</h1>
    <?php if (empty($projects)): ?>
      <p class="projects-empty">You don’t have access to any projects yet.</p>
    <?php else: ?>
      <div class="project-card-grid">
        <?php foreach ($projects as $project):
            $pid = (int)$project['id'];
            $bg = $project['bg_color'] ?: '#e5e7eb';
            $text = $project['text_color'] ?: '#111827';
            $slug = htmlspecialchars($project['slug'] ?? '', ENT_QUOTES);
            $name = htmlspecialchars($project['name'] ?? '');
            $description = trim((string)($project['description'] ?? ''));
            $role = $project['my_role'] ?? null;
            $stats = $statsByProject[$pid] ?? [
                'completed' => 0,
                'outstanding' => 0,
                'last_updated_at' => null,
                'last_item_text' => null,
            ];
            $members = $membersByProject[$pid] ?? [];
            $open = (int)$stats['outstanding'];
            $done = (int)$stats['completed'];
            $lastAt = $stats['last_updated_at'];
            $lastText = trim((string)($stats['last_item_text'] ?? ''));
            ?>
          <a class="project-card" href="/projects/<?= $slug ?>"
             style="background: <?= htmlspecialchars($bg) ?>; color: <?= htmlspecialchars($text) ?>">
            <span class="project-card-body">
              <span class="project-card-name"><?= $name ?></span>
              <?php if ($description !== ''): ?>
                <span class="project-card-description"><?= htmlspecialchars($description) ?></span>
              <?php endif; ?>
            </span>
            <span class="project-card-meta">
              <div class="label">Tasks</div>
              <span class="project-card-counts">
                <span><?= $open ?> open</span>
                <span class="project-card-meta-sep" aria-hidden="true">·</span>
                <span><?= $done ?> done</span>
              </span>
              <?php if ($lastAt && $lastText !== ''): ?>
                <span class="project-card-updated" title="<?= htmlspecialchars($lastText) ?>">
                  <span class="label">Updated</span> <?= htmlspecialchars(project_card_format_date($lastAt)) ?>:
                  <?= htmlspecialchars(project_card_truncate($lastText)) ?>
                </span>
              <?php elseif ($lastAt): ?>
                <div class="label">Updated</div>
                <span class="project-card-updated"><?= htmlspecialchars(project_card_format_date($lastAt)) ?></span>
              <?php else: ?>
                <div class="label">Updated</div>
                <span class="project-card-updated">No tasks yet</span>
              <?php endif; ?>
              <?php if ($members): ?>
                <div   class="label">Members</div>
                <span class="project-card-members">
              
                  <?php foreach ($members as $memberLabel): ?>
                    <span class="project-card-member-pill"><?= htmlspecialchars($memberLabel) ?></span>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
              <?php if ($role): ?>
                <div class="label">Role</div>
                <span class="project-card-role"><?= htmlspecialchars($role) ?></span>
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$pageTitle = 'All Projects - Daybook';
include __DIR__ . '/elements/layout.php';

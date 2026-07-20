<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/includes/functions-universal.php';
require_once __DIR__ . '/app/includes/functions-permissions.php';
require_login();

$userId = (int)current_user_id();

// Completed statuses match the task grid ("Show completed" / active_only).
$completedStatuses = ['OK', 'N/A'];

function project_card_query_projects(mysqli $mysqli, int $userId, bool $includeDescription, bool $includeArchivedCol): ?array {
    $descCol = $includeDescription ? 'p.description,' : '';
    $archivedCol = $includeArchivedCol ? 'p.archived,' : '';
    if (is_daybookstaff()) {
        $sql = "SELECT p.id, p.name, {$descCol} p.slug, p.sort_order, {$archivedCol} p.bg_color, p.text_color, p.owner_user_id,
                       pm.role AS my_role
                FROM projects p
                LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = {$userId}
                ORDER BY p.sort_order, p.id";
    } else {
        $sql = "SELECT p.id, p.name, {$descCol} p.slug, p.sort_order, {$archivedCol} p.bg_color, p.text_color, p.owner_user_id,
                       pm.role AS my_role
                FROM projects p
                INNER JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = {$userId}
                ORDER BY p.sort_order, p.id";
    }
    $result = $mysqli->query($sql);
    if (!$result) {
        return null;
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function project_card_can_see_all_items(mysqli $mysqli, int $projectId, int $userId): bool {
    if (is_daybookstaff()) {
        return true;
    }
    $pid = (int)$projectId;
    $uid = (int)$userId;
    $roleResult = $mysqli->query(
        "SELECT role FROM project_members WHERE project_id = {$pid} AND user_id = {$uid} LIMIT 1"
    );
    $roleRow = $roleResult ? $roleResult->fetch_assoc() : null;
    if (($roleRow['role'] ?? '') === 'admin') {
        return true;
    }
    $ownerExpr = sql_project_owner_user_id_expr('p');
    $assignResult = $mysqli->query(
        "SELECT 1 FROM items i
         INNER JOIN projects p ON p.id = i.project_id
         WHERE i.project_id = {$pid}
           AND (i.assigned_user_id = {$uid} OR (i.assigned_to_project_owner = 1 AND {$ownerExpr} = {$uid}))
         LIMIT 1"
    );
    return $assignResult && (bool)$assignResult->fetch_row();
}

function project_card_visibility_clause(mysqli $mysqli, int $projectId, int $userId, string $alias = 'i'): string {
    if (project_card_can_see_all_items($mysqli, $projectId, $userId)) {
        return '1=1';
    }
    $uid = (int)$userId;
    return "({$alias}.created_by_user_id = {$uid})";
}

function project_card_truncate(string $text, int $max = 72): string {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return rtrim(substr($text, 0, $max - 1)) . '…';
}

function project_card_format_date(?int $ts): string {
    if (!$ts) {
        return '';
    }
    return date('M j, Y', $ts);
}

function projects_page_can_create(mysqli $mysqli): bool {
    if (is_daybookstaff()) {
        return true;
    }
    $uid = (int)current_user_id();
    if (!$uid) {
        return false;
    }
    $adminResult = $mysqli->query(
        "SELECT 1 FROM project_members WHERE user_id = {$uid} AND role = 'admin' LIMIT 1"
    );
    if ($adminResult && $adminResult->fetch_row()) {
        return true;
    }
    $countResult = $mysqli->query(
        "SELECT COUNT(*) AS c FROM project_members WHERE user_id = {$uid}"
    );
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    return (int)($countRow['c'] ?? 0) === 0;
}

$projects = project_card_query_projects($mysqli, $userId, true, true);
if ($projects === null) {
    // Production may not have migration 0014 yet (archived column).
    $projects = project_card_query_projects($mysqli, $userId, true, false);
}
if ($projects === null) {
    // Production may not have migration 0013 yet (description column).
    $projects = project_card_query_projects($mysqli, $userId, false, false) ?? [];
}
foreach ($projects as &$projectRow) {
    $projectRow['description'] = $projectRow['description'] ?? null;
    $projectRow['archived'] = (int)($projectRow['archived'] ?? 0);
}
unset($projectRow);

$projectIds = array_map(static fn($p) => (int)$p['id'], $projects);
$membersByProject = [];
$statsByProject = [];

if ($projectIds) {
    $idList = implode(',', array_map('intval', $projectIds));
    $memberResult = $mysqli->query(
        "SELECT pm.project_id, COALESCE(u.name, '') AS name, COALESCE(u.email, '') AS email
         FROM project_members pm
         LEFT JOIN users u ON u.id = pm.user_id
         WHERE pm.project_id IN ({$idList})
         ORDER BY pm.project_id, pm.role, u.name, u.email"
    );
    $memberRows = $memberResult ? $memberResult->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($memberRows as $row) {
        $pid = (int)$row['project_id'];
        $label = user_display_name($row['name'], $row['email']);
        if ($label === '') {
            continue;
        }
        $membersByProject[$pid][] = $label;
    }

    $completedList = "'" . implode("','", array_map(
        static fn($s) => db_escape($mysqli, $s),
        $completedStatuses
    )) . "'";

    foreach ($projectIds as $pid) {
        $pid = (int)$pid;
        $vis = project_card_visibility_clause($mysqli, $pid, $userId);
        $statsResult = $mysqli->query(
            "SELECT
                SUM(CASE WHEN s.name IN ({$completedList}) THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN s.name IS NULL OR s.name NOT IN ({$completedList}) THEN 1 ELSE 0 END) AS outstanding,
                MAX(i.updated_at) AS last_updated_at
             FROM items i
             LEFT JOIN statuses s ON s.id = i.status_id
             WHERE i.project_id = {$pid} AND ({$vis})"
        );
        $stats = $statsResult ? ($statsResult->fetch_assoc() ?: []) : [];
        $lastText = null;
        $lastAt = !empty($stats['last_updated_at']) ? (int)$stats['last_updated_at'] : null;
        if ($lastAt) {
            $lastResult = $mysqli->query(
                "SELECT i.item_text
                 FROM items i
                 WHERE i.project_id = {$pid} AND ({$vis}) AND i.updated_at = {$lastAt}
                 ORDER BY i.id DESC
                 LIMIT 1"
            );
            $lastRow = $lastResult ? $lastResult->fetch_assoc() : null;
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

$canCreateProject = projects_page_can_create($mysqli);

ob_start();
?>
  <div class="projects-page">
    <div class="projects-page-header">
      <h1>All Projects</h1>
      <?php if (!empty($projects)): ?>
        <div class="projects-page-controls">
          <label class="filter-toggle projects-archived-toggle">
            <input type="checkbox" id="include-archived-toggle">
            <span class="filter-toggle-track" aria-hidden="true"></span>
            <span class="filter-toggle-label">Include Archived projects</span>
          </label>
          <div class="projects-sort-tabs" role="tablist" aria-label="Sort projects">
            <button type="button" class="projects-sort-tab active" role="tab" aria-selected="true" data-sort="updated">
              Last updated
            </button>
            <button type="button" class="projects-sort-tab" role="tab" aria-selected="false" data-sort="name">
              Project Name
            </button>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php if (empty($projects) && !$canCreateProject): ?>
      <p class="projects-empty">You don’t have access to any projects yet.</p>
    <?php else: ?>
      <div class="project-card-grid" id="project-card-grid">
        <?php foreach ($projects as $project):
            $pid = (int)$project['id'];
            $bg = $project['bg_color'] ?: '#e5e7eb';
            $text = $project['text_color'] ?: '#111827';
            $slug = htmlspecialchars($project['slug'] ?? '', ENT_QUOTES);
            $name = htmlspecialchars($project['name'] ?? '');
            $rawName = (string)($project['name'] ?? '');
            $nameSortKey = function_exists('mb_strtolower')
                ? mb_strtolower($rawName, 'UTF-8')
                : strtolower($rawName);
            $nameSort = htmlspecialchars($nameSortKey, ENT_QUOTES);
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
            $isArchived = (int)($project['archived'] ?? 0) === 1;
            $cardClass = 'project-card' . ($isArchived ? ' project-card-archived' : '');
            ?>
          <a class="<?= $cardClass ?>" href="/projects/<?= $slug ?>"
             data-name="<?= $nameSort ?>"
             data-updated="<?= $lastAt ? (int)$lastAt : 0 ?>"
             data-archived="<?= $isArchived ? '1' : '0' ?>"
             <?= $isArchived ? 'hidden' : '' ?>
             style="background: <?= htmlspecialchars($bg) ?>; color: <?= htmlspecialchars($text) ?>">
            <span class="project-card-body">
              <span class="project-card-name"><?= $name ?></span>
              <?php if ($isArchived): ?>
                <span class="project-card-archived-badge">Archived</span>
              <?php endif; ?>
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
                <span class="label">Last Change</span>
                <span class="project-card-updated" title="<?= htmlspecialchars($lastText) ?>">
                  <?= htmlspecialchars(project_card_format_date($lastAt)) ?>
                </span>
              <?php elseif ($lastAt): ?>
                <div class="label">Last Change</div>
                <span class="project-card-updated"><?= htmlspecialchars(project_card_format_date($lastAt)) ?></span>
              <?php else: ?>
                <div class="label">Last Change</div>
                <span class="project-card-updated">No tasks yet</span>
              <?php endif; ?>
              <?php if ($members): ?>
                <div class="label">Members</div>
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
        <?php if ($canCreateProject): ?>
          <button type="button" id="create-project-card" class="project-card project-card-create">
            <div class="" style=";opacity: 0.7;font-size: 5rem;"><i class="fa-solid fa-plus"></i></div>
            <span class="project-card-create-label">Create a project</span>
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($canCreateProject): ?>
  <div id="create-project-modal" class="modal-overlay hidden">
    <div class="modal-box modal-wide">
      <h2>Create a project</h2>
      <form id="create-project-form" class="project-details-form">
        <label for="create-project-name">Name</label>
        <input type="text" id="create-project-name" maxlength="255" required autocomplete="off">

        <label for="create-project-description">Description</label>
        <textarea id="create-project-description" rows="3" maxlength="2000" placeholder="Short description for the All Projects page..."></textarea>

        <label>Background color</label>
        <div id="create-project-bg-swatches" class="color-swatch-grid"></div>
        <input type="color" id="create-project-bg" value="#ffd6d6">

        <label>Text color</label>
        <div id="create-project-text-swatches" class="color-swatch-grid"></div>
        <input type="color" id="create-project-text" value="#7a2e2e">

        <label>Preview</label>
        <div id="create-project-preview" class="create-project-preview">
          <span class="create-project-preview-name">Project name</span>
          <span class="create-project-preview-desc">Description preview</span>
        </div>

        <div class="modal-actions">
          <button type="button" id="create-project-cancel">Cancel</button>
          <button type="submit" id="create-project-submit" class="primary">Create project</button>
        </div>
      </form>
    </div>
  </div>
  <div id="toast" class="toast hidden"></div>
  <?php endif; ?>
<?php
$content = ob_get_clean();
$pageTitle = 'All Projects - Daybook';
$pageScripts = '';
if (!empty($projects) || $canCreateProject) {
    $pageScripts = '<script src="/assets/js/projects-page.js?v='
        . filemtime(__DIR__ . '/assets/js/projects-page.js')
        . '"></script>';
}
include __DIR__ . '/elements/layout.php';

<?php
require_once __DIR__ . '/config/config.php';
require_login();

ob_start();
?>
<div class="project-strip" id="project-strip">
  <div class="project-strip-control">
    <button type="button" id="project-strip-toggle" class="project-strip-toggle" aria-expanded="false" aria-haspopup="listbox">
      <span id="project-strip-name" class="project-strip-name"></span>
      <span id="project-strip-role" class="project-strip-role hidden"></span>
      <span class="project-strip-chevron" aria-hidden="true">▾</span>
    </button>
    <ul id="project-strip-menu" class="project-strip-menu hidden" role="listbox"></ul>
  </div>
</div>

<div class="filterbar">
  <div class="filterbar-filters">
    <input type="text" id="filter-q" placeholder="Search item / subsystem / URL...">
    <select id="filter-category"><option value="">All Categories</option></select>
    <select id="filter-priority"><option value="">All Priorities</option></select>
    <select id="filter-status"><option value="">All Statuses</option></select>
    <button id="clear-filters-btn" class="link-btn" type="button">Clear Filters</button>
  </div>
  <div class="filterbar-toggles">
    <label class="filter-toggle">
      <input type="checkbox" id="priority-sort-toggle" checked>
      <span class="filter-toggle-track" aria-hidden="true"></span>
      <span class="filter-toggle-label">Priority Sort</span>
    </label>
    <label class="filter-toggle">
      <input type="checkbox" id="show-completed-toggle">
      <span class="filter-toggle-track" aria-hidden="true"></span>
      <span class="filter-toggle-label">Show completed</span>
    </label>
  </div>
  <div class="filterbar-actions">
    <button id="export-btn" class="link-btn" type="button"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
    <span class="export-tasklist-wrap">
      <button id="export-tasklist-btn" class="link-btn" type="button"><i class="fa-solid fa-clipboard"></i> Copy Task List</button>
      <i id="export-tasklist-copied" class="fa-solid fa-circle-check export-tasklist-copied hidden" aria-hidden="true" title="Copied"></i>
    </span>
    <div class="filterbar-config" id="filterbar-config">
      <button type="button" id="filter-config-toggle" class="config-pill" aria-expanded="false" aria-haspopup="menu">
        Project Settings <span class="config-pill-chevron" aria-hidden="true">▾</span>
      </button>
      <ul id="filter-config-menu" class="filter-config-menu hidden" role="menu">
        <li role="none"><button type="button" id="manage-details-btn" class="filter-config-option hidden" role="menuitem">Project Details</button></li>
        <li role="none"><button type="button" id="manage-members-btn" class="filter-config-option hidden" role="menuitem">Members</button></li>
        <li role="none"><button type="button" id="manage-categories-btn" class="filter-config-option" role="menuitem">Categories</button></li>
        <li role="none"><button type="button" id="manage-subsystems-btn" class="filter-config-option" role="menuitem">Subsystems</button></li>
        <li role="none"><button type="button" id="manage-priorities-btn" class="filter-config-option" role="menuitem">Priorities</button></li>
        <li role="none"><button type="button" id="manage-statuses-btn" class="filter-config-option" role="menuitem">Statuses</button></li>
      </ul>
    </div>
  </div>
</div>

<div class="grid-wrap">
  <table id="items-table">
    <thead>
      <tr>
        <th class="col-sort">#</th>
        <th class="col-category">Category</th>
        <th class="col-subsystem">Subsystem</th>
        <th class="col-item">Item</th>
        <th class="col-url">URL</th>
        <th class="col-project">Project</th>
        <th class="col-priority">Priority</th>
        <th class="col-order">Order</th>
        <th class="col-due">Due</th>
        <th class="col-status">Status</th>
        <th class="col-assignee">Assignee</th>
        <th class="col-docs">Docs</th>
        <th class="col-notes">Notes</th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody id="items-tbody">
    </tbody>
  </table>
  <div class="grid-actions">
    <button id="add-item-btn" class="primary">+ New Item</button>
  </div>
</div>

<!-- Generic option-list management modal (categories / subsystems / priorities / statuses / projects) -->
<div id="option-modal" class="modal-overlay hidden">
  <div class="modal-box">
    <h2 id="option-modal-title">Manage Options</h2>
    <ul id="option-modal-list" class="option-list"></ul>
    <form id="option-modal-add-form" class="inline-form">
      <input type="text" id="option-modal-new-name" placeholder="New value...">
      <button type="submit">Add</button>
    </form>
    <p id="option-modal-palette-row" class="option-modal-extra hidden">
      <button type="button" id="option-modal-sync-palette" class="link-btn">Apply priority palette from code</button>
      <span class="option-modal-hint">Updates colors for all priorities in this project from <code>functions-colors.php</code>.</span>
    </p>
    <div class="modal-actions">
      <button id="option-modal-close" class="primary">Done</button>
    </div>
  </div>
</div>

<!-- Project details modal (title + description) -->
<div id="project-details-modal" class="modal-overlay hidden">
  <div class="modal-box">
    <h2>Project Details</h2>
    <form id="project-details-form" class="project-details-form">
      <label for="project-details-name">Title</label>
      <input type="text" id="project-details-name" maxlength="255" required autocomplete="off">
      <label for="project-details-description">Description</label>
      <textarea id="project-details-description" rows="4" maxlength="2000" placeholder="Short description for the All Projects page..."></textarea>
      <label class="project-details-archived">
        <input type="checkbox" id="project-details-archived">
        <span>Archived</span>
      </label>
      <div class="modal-actions">
        <button type="button" id="project-details-cancel">Cancel</button>
        <button type="submit" class="primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Docs + Notes detail modal for a single item -->
<div id="detail-modal" class="modal-overlay hidden">
  <div class="modal-box modal-wide">
    <h2>Item Details</h2>
    <p class="detail-item-text" id="detail-item-text"></p>

    <section>
      <h3>Docs</h3>
      <ul id="detail-docs-list" class="docs-list"></ul>
      <form id="detail-docs-form" class="inline-form">
        <input type="text" id="detail-doc-label" placeholder="Label (optional)">
        <input type="text" id="detail-doc-url" placeholder="https://...">
        <button type="submit">Add Doc</button>
      </form>
    </section>

    <section>
      <h3>Notes</h3>
      <ul id="detail-notes-list" class="notes-list"></ul>
      <form id="detail-notes-form" class="inline-form">
        <textarea id="detail-note-body" placeholder="Add a note..." rows="2"></textarea>
        <button type="submit">Add Note</button>
      </form>
    </section>

    <div class="modal-actions">
      <button id="detail-modal-close" class="primary">Close</button>
    </div>
  </div>
</div>

<!-- Project members modal -->
<div id="members-modal" class="modal-overlay hidden">
  <div class="modal-box modal-wide">
    <h2>Project members</h2>
    <ul id="members-list" class="member-list"></ul>
    <h3>Pending invites</h3>
    <ul id="invites-list" class="member-list"></ul>
    <form id="invite-form" class="inline-form">
      <input type="email" id="invite-email" placeholder="Email to invite" required>
      <select id="invite-role">
        <option value="admin">Admin</option>
        <option value="manager">Manager</option>
        <option value="contributor" selected>Contributor</option>
        <option value="viewer">Viewer</option>
      </select>
      <button type="submit">Create invite link</button>
    </form>
    <p id="invite-link-row" class="hidden">
      <input type="text" id="invite-link-url" readonly>
      <button type="button" id="copy-invite-link" class="link-btn">Copy link</button>
    </p>
    <div class="modal-actions">
      <button id="members-modal-close" class="primary">Done</button>
    </div>
  </div>
</div>

<!-- Due date picker modal -->
<div id="due-date-modal" class="modal-overlay hidden">
  <div class="modal-box modal-narrow">
    <h2>Due date</h2>
    <p class="detail-item-text" id="due-date-item-text"></p>
    <form id="due-date-form" class="due-date-form">
      <label for="due-date-input">Date</label>
      <input type="date" id="due-date-input">
      <div class="modal-actions">
        <button type="button" id="due-date-clear" class="link-btn">Clear</button>
        <button type="button" id="due-date-cancel">Cancel</button>
        <button type="submit" class="primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div id="toast" class="toast hidden"></div>
<?php
$content = ob_get_clean();
$initialProjectSlug = htmlspecialchars($_GET['project_slug'] ?? '', ENT_QUOTES);
$pageScripts = "<script>window.INITIAL_PROJECT_SLUG = \"{$initialProjectSlug}\";</script>"
    . '<script src="/assets/js/app.js?v=' . filemtime(__DIR__ . '/assets/js/app.js') . '"></script>';
include __DIR__ . '/elements/layout.php';

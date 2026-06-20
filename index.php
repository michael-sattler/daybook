<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daybook</title>
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
</head>
<body>
<header class="topbar">
  <h1>Daybook</h1>
  <div class="topbar-controls">
    <label>Project
      <select id="project-select"></select>
    </label>
    <button id="manage-projects-btn" class="icon-btn" title="Manage projects">⚙ Projects</button>
    <button id="sort-priority-btn">Sort by Priority &gt; Order</button>
    <button id="add-item-btn" class="primary">+ New Item</button>
    <a href="/logout.php" class="logout-link">Log out</a>
  </div>
</header>

<div class="filterbar">
  <input type="text" id="filter-q" placeholder="Search item / subsystem / URL...">
  <select id="filter-category"><option value="">All Categories</option></select>
  <select id="filter-priority"><option value="">All Priorities</option></select>
  <select id="filter-status"><option value="">All Statuses</option></select>
  <button id="manage-categories-btn" class="link-btn">Edit Categories</button>
  <button id="manage-priorities-btn" class="link-btn">Edit Priorities</button>
  <button id="manage-statuses-btn" class="link-btn">Edit Statuses</button>
  <button id="clear-filters-btn" class="link-btn">Clear Filters</button>
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
        <th class="col-status">Status</th>
        <th class="col-docs">Docs</th>
        <th class="col-notes">Notes</th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody id="items-tbody">
    </tbody>
  </table>
</div>

<!-- Generic option-list management modal (categories / priorities / statuses) -->
<div id="option-modal" class="modal-overlay hidden">
  <div class="modal">
    <h2 id="option-modal-title">Manage Options</h2>
    <ul id="option-modal-list" class="option-list"></ul>
    <form id="option-modal-add-form" class="inline-form">
      <input type="text" id="option-modal-new-name" placeholder="New value...">
      <button type="submit">Add</button>
    </form>
    <div class="modal-actions">
      <button id="option-modal-close" class="primary">Done</button>
    </div>
  </div>
</div>

<!-- Project management modal -->
<div id="project-modal" class="modal-overlay hidden">
  <div class="modal">
    <h2>Manage Projects</h2>
    <ul id="project-modal-list" class="option-list"></ul>
    <form id="project-modal-add-form" class="inline-form">
      <input type="text" id="project-modal-new-name" placeholder="New project name...">
      <button type="submit">Add</button>
    </form>
    <div class="modal-actions">
      <button id="project-modal-close" class="primary">Done</button>
    </div>
  </div>
</div>

<!-- Docs + Notes detail modal for a single item -->
<div id="detail-modal" class="modal-overlay hidden">
  <div class="modal modal-wide">
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

<div id="toast" class="toast hidden"></div>

<script src="/assets/js/app.js"></script>
</body>
</html>

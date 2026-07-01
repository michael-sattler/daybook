<?php $userInitial = current_user_initial(); ?>
<header class="topbar">
  <div>
    <a href="/"><img src="/assets/images/logo_horiz-header.png" alt="Daybook Logo" class="logo-header"></a>
    <span class="">Lightweight task management for development projects</span>
    </div>
  <div class="topbar-controls">
    <div class="user-menu" id="user-menu">
      <button type="button" id="user-menu-toggle" class="user-menu-toggle" aria-expanded="false" aria-haspopup="menu" aria-label="Account menu">
        <span class="user-menu-avatar" aria-hidden="true"><?= htmlspecialchars($userInitial) ?></span>
        <span class="user-menu-chevron" aria-hidden="true">▾</span>
      </button>
      <ul id="user-menu-dropdown" class="user-menu-dropdown hidden" role="menu">
        <li role="none">
          <button type="button" id="user-menu-projects" class="user-menu-option" role="menuitem">Edit projects</button>
        </li>
        <li role="none">
          <a href="/logout" class="user-menu-option" role="menuitem">Log out</a>
        </li>
      </ul>
    </div>
  </div>
</header>

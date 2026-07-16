/* Shared topbar behavior (user menu) — loaded on every authenticated layout page */
(function () {
  'use strict';

  function closeUserMenu() {
    const menu = document.getElementById('user-menu-dropdown');
    const toggle = document.getElementById('user-menu-toggle');
    if (menu) menu.classList.add('hidden');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function toggleUserMenu() {
    const menu = document.getElementById('user-menu-dropdown');
    const toggle = document.getElementById('user-menu-toggle');
    if (!menu || !toggle) return;
    const opening = menu.classList.contains('hidden');
    if (opening) {
      menu.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
    } else {
      closeUserMenu();
    }
  }

  window.DaybookTopbar = {
    closeUserMenu,
    toggleUserMenu,
  };

  function bindTopbar() {
    const toggle = document.getElementById('user-menu-toggle');
    if (toggle) {
      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleUserMenu();
      });
    }

    const projectsBtn = document.getElementById('user-menu-projects');
    if (projectsBtn) {
      projectsBtn.addEventListener('click', () => {
        closeUserMenu();
        if (typeof window.openDaybookProjectsManager === 'function') {
          window.openDaybookProjectsManager();
        } else {
          window.location.href = '/projects';
        }
      });
    }

    document.addEventListener('click', (e) => {
      if (!e.target.closest('#user-menu')) closeUserMenu();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeUserMenu();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindTopbar);
  } else {
    bindTopbar();
  }
})();

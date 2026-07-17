/* All Projects page: sort tabs + create-project modal */
(function () {
  'use strict';

  const SORT_STORAGE_KEY = 'daybook.projects.sort';
  const COLOR_SWATCHES = [
    '#ffffff', '#f3f4f6', '#e5e7eb', '#9ca3af', '#4b5563', '#1f2937', '#000000',
    '#fecaca', '#fca5a5', '#dc2626', '#7f1d1d',
    '#fed7aa', '#fb923c', '#c2410c',
    '#fef08a', '#facc15', '#a16207',
    '#bbf7d0', '#4ade80', '#15803d',
    '#bfdbfe', '#60a5fa', '#1d4ed8',
    '#ddd6fe', '#a78bfa', '#5b21b6',
    '#fbcfe8', '#f472b6', '#9d174d',
    '#ffd6d6', '#ffe2c2', '#fff6c2', '#d6f5d6', '#cfe3ff', '#d9d6ff', '#f0d6f7',
  ];

  const DEFAULT_BG = '#ffd6d6';
  const DEFAULT_TEXT = '#7a2e2e';

  function $(id) {
    return document.getElementById(id);
  }

  function toast(msg, isError) {
    const el = $('toast');
    if (!el) {
      window.alert(msg);
      return;
    }
    el.textContent = msg;
    el.classList.remove('hidden');
    el.classList.toggle('toast-error', !!isError);
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.add('hidden'), 2200);
  }

  async function api(path, options = {}) {
    const res = await fetch(path, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });
    if (res.status === 401) {
      window.location.href = '/login';
      return;
    }
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;
    if (!res.ok) throw new Error((data && data.error) || 'Request failed');
    return data;
  }

  function openModal() {
    const form = $('create-project-form');
    if (form) form.reset();
    $('create-project-bg').value = DEFAULT_BG;
    $('create-project-text').value = DEFAULT_TEXT;
    updatePreview();
    $('create-project-modal').classList.remove('hidden');
    $('create-project-name').focus();
  }

  function closeModal() {
    $('create-project-modal').classList.add('hidden');
  }

  function updatePreview() {
    const preview = $('create-project-preview');
    const name = $('create-project-name').value.trim() || 'Project name';
    const description = $('create-project-description').value.trim() || 'Description preview';
    const bg = $('create-project-bg').value || DEFAULT_BG;
    const text = $('create-project-text').value || DEFAULT_TEXT;
    preview.style.background = bg;
    preview.style.color = text;
    preview.querySelector('.create-project-preview-name').textContent = name;
    preview.querySelector('.create-project-preview-desc').textContent = description;
  }

  function renderSwatches(containerId, inputId) {
    const container = $(containerId);
    const input = $(inputId);
    if (!container || !input) return;
    container.innerHTML = COLOR_SWATCHES.map(
      (c) => `<button type="button" class="color-swatch" data-color="${c}" style="background:${c}" aria-label="${c}"></button>`
    ).join('');
    container.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-color]');
      if (!btn) return;
      input.value = btn.dataset.color;
      updatePreview();
    });
    input.addEventListener('input', updatePreview);
  }

  async function submitCreate(e) {
    e.preventDefault();
    const name = $('create-project-name').value.trim();
    const description = $('create-project-description').value.trim();
    const bg_color = $('create-project-bg').value || DEFAULT_BG;
    const text_color = $('create-project-text').value || DEFAULT_TEXT;
    if (!name) {
      toast('Name is required', true);
      return;
    }
    const submitBtn = $('create-project-submit');
    if (submitBtn) submitBtn.disabled = true;
    try {
      const created = await api('/api/projects', {
        method: 'POST',
        body: JSON.stringify({ name, description, bg_color, text_color }),
      });
      const slug = created && created.slug;
      if (slug) {
        window.location.assign(`/projects/${slug}`);
        return;
      }
      window.location.reload();
    } catch (err) {
      toast(err.message || 'Could not create project', true);
      if (submitBtn) submitBtn.disabled = false;
    }
  }

  function bindCreateModal() {
    const openBtn = $('create-project-card');
    if (!openBtn) return;
    openBtn.addEventListener('click', openModal);
    $('create-project-cancel')?.addEventListener('click', closeModal);
    $('create-project-form')?.addEventListener('submit', submitCreate);
    $('create-project-name')?.addEventListener('input', updatePreview);
    $('create-project-description')?.addEventListener('input', updatePreview);
    renderSwatches('create-project-bg-swatches', 'create-project-bg');
    renderSwatches('create-project-text-swatches', 'create-project-text');
    $('create-project-modal')?.addEventListener('click', (e) => {
      if (e.target.id === 'create-project-modal') closeModal();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !$('create-project-modal')?.classList.contains('hidden')) {
        closeModal();
      }
    });
  }

  function readStoredSort() {
    try {
      const value = localStorage.getItem(SORT_STORAGE_KEY);
      if (value === 'name' || value === 'updated') return value;
    } catch (_) { /* ignore */ }
    return 'updated';
  }

  function storeSort(sort) {
    try {
      localStorage.setItem(SORT_STORAGE_KEY, sort);
    } catch (_) { /* ignore */ }
  }

  function sortProjectCards(sort) {
    const grid = $('project-card-grid');
    if (!grid) return;

    const createCard = grid.querySelector('.project-card-create');
    const cards = Array.from(grid.querySelectorAll('.project-card:not(.project-card-create)'));

    cards.sort((a, b) => {
      if (sort === 'name') {
        const nameA = a.dataset.name || '';
        const nameB = b.dataset.name || '';
        const cmp = nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
        if (cmp !== 0) return cmp;
        return (a.dataset.updated || '0').localeCompare(b.dataset.updated || '0');
      }
      const updatedA = Number(a.dataset.updated || 0);
      const updatedB = Number(b.dataset.updated || 0);
      if (updatedB !== updatedA) return updatedB - updatedA;
      const nameA = a.dataset.name || '';
      const nameB = b.dataset.name || '';
      return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
    });

    cards.forEach((card) => grid.appendChild(card));
    if (createCard) grid.appendChild(createCard);
  }

  function setActiveSortTab(sort) {
    document.querySelectorAll('.projects-sort-tab').forEach((tab) => {
      const isActive = tab.dataset.sort === sort;
      tab.classList.toggle('active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  }

  function bindSortTabs() {
    const tabs = document.querySelectorAll('.projects-sort-tab');
    if (!tabs.length) return;

    const initial = readStoredSort();
    setActiveSortTab(initial);
    sortProjectCards(initial);

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const sort = tab.dataset.sort;
        if (sort !== 'name' && sort !== 'updated') return;
        setActiveSortTab(sort);
        storeSort(sort);
        sortProjectCards(sort);
      });
    });
  }

  function bind() {
    bindSortTabs();
    bindCreateModal();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();

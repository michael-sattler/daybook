/* Daybook app - vanilla JS, no build step */
(function () {
  'use strict';

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

  // Maps an item field to the option list + grid render style used to colorize it.
  const FIELD_COLOR_CONFIG = {
    subsystem_id: { list: 'subsystems', mode: 'pill' },
    project_id: { list: 'projects', mode: 'pill' },
    status_id: { list: 'statuses', mode: 'pill' },
    priority_id: { list: 'priorities', mode: 'fill-cell' },
  };

  const state = {
    projects: [],
    categories: [],
    subsystems: [],
    priorities: [],
    statuses: [],
    items: [],
    currentProjectId: null,
    orderBy: 'sort_order', // or 'priority'
    filters: { q: '', category_id: '', priority_id: '', status_id: '' },
    detailItemId: null,
  };

  // ---------- low-level fetch helpers ----------

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

  const get = (path) => api(path);
  const post = (path, body) => api(path, { method: 'POST', body: JSON.stringify(body) });
  const put = (path, body) => api(path, { method: 'PUT', body: JSON.stringify(body) });
  const patch = (path, body) => api(path, { method: 'PATCH', body: JSON.stringify(body) });
  const del = (path) => api(path, { method: 'DELETE' });

  function toast(msg, isError) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.remove('hidden');
    el.classList.toggle('toast-error', !!isError);
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.add('hidden'), 2200);
  }

  function debounce(fn, ms) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
  }

  function optionsHtml(list, selectedId, blankLabel) {
    let html = `<option value="">${blankLabel || '—'}</option>`;
    for (const opt of list) {
      const sel = String(opt.id) === String(selectedId) ? 'selected' : '';
      html += `<option value="${opt.id}" ${sel}>${escapeHtml(opt.name)}</option>`;
    }
    return html;
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  // ---------- bootstrap ----------

  async function init() {
    state.projects = await get('/api/projects');
    if (!state.projects.length) {
      const created = await post('/api/projects', { name: 'General' });
      state.projects = [created];
    }
    const requestedSlug = window.INITIAL_PROJECT_SLUG || '';
    const matched = requestedSlug && state.projects.find((p) => p.slug === requestedSlug);
    const initialProject = matched || state.projects[0];
    state.currentProjectId = initialProject.id;
    pushProjectUrl(initialProject);
    renderProjectSelect();
    await loadProjectScopedLists();
    await loadItems();
    bindGlobalEvents();
  }

  function pushProjectUrl(project) {
    if (!project || !project.slug) return;
    const url = `/projects/${project.slug}`;
    if (window.location.pathname !== url) window.history.pushState({}, '', url);
  }

  async function loadProjectScopedLists() {
    const pid = state.currentProjectId;
    [state.categories, state.subsystems, state.priorities, state.statuses] = await Promise.all([
      get(`/api/categories?project_id=${pid}`),
      get(`/api/subsystems?project_id=${pid}`),
      get(`/api/priorities?project_id=${pid}`),
      get('/api/statuses'),
    ]);
    renderFilterSelects();
  }

  async function loadItems() {
    const params = new URLSearchParams();
    params.set('project_id', state.currentProjectId);
    params.set('order_by', state.orderBy);
    if (state.filters.q) params.set('q', state.filters.q);
    if (state.filters.category_id) params.set('category_id', state.filters.category_id);
    if (state.filters.priority_id) params.set('priority_id', state.filters.priority_id);
    if (state.filters.status_id) params.set('status_id', state.filters.status_id);
    state.items = await get('/api/items?' + params.toString());
    renderItems();
  }

  // ---------- rendering ----------

  function renderProjectSelect() {
    const sel = document.getElementById('project-select');
    sel.innerHTML = state.projects
      .map((p) => `<option value="${p.id}" ${p.id === state.currentProjectId ? 'selected' : ''}>${escapeHtml(p.name)}</option>`)
      .join('');
  }

  function renderFilterSelects() {
    document.getElementById('filter-category').innerHTML =
      '<option value="">All Categories</option>' + state.categories.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    document.getElementById('filter-priority').innerHTML =
      '<option value="">All Priorities</option>' + state.priorities.map((p) => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
    document.getElementById('filter-status').innerHTML =
      '<option value="">All Statuses</option>' + state.statuses.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
  }

  function renderItems() {
    const tbody = document.getElementById('items-tbody');
    tbody.innerHTML = '';
    for (const item of state.items) tbody.appendChild(buildRow(item));
    if (state.orderBy === 'priority') makeSortable(tbody);
  }

  function makeSortable(tbody) {
    Sortable.create(tbody, {
      animation: 150,
      handle: '.drag-handle',
      // Dragging is confined to rows that share the same priority - crossing into
      // a different priority's block is rejected so "Order" only ever reorders
      // within one priority bucket.
      onMove: (evt) => {
        if (!evt.related || !evt.related.dataset) return true;
        return evt.dragged.dataset.priorityId === evt.related.dataset.priorityId;
      },
      onEnd: async (evt) => {
        const priorityId = evt.item.dataset.priorityId;
        if (!priorityId) return;
        const ids = Array.from(tbody.children)
          .filter((tr) => tr.dataset.priorityId === priorityId)
          .map((tr) => tr.dataset.id);
        try {
          await patch('/api/items', { priority_id: Number(priorityId), order: ids });
        } catch (e) {
          toast(e.message, true);
        }
      },
    });
  }

  function buildRow(item) {
    const tr = document.createElement('tr');
    tr.dataset.id = item.id;
    tr.dataset.priorityId = item.priority_id || '';

    tr.innerHTML = `
      <td class="col-sort">${item.sort_order}</td>
      <td class="col-category"><select data-field="category_id">${optionsHtml(state.categories, item.category_id)}</select></td>
      <td class="col-subsystem"><select data-field="subsystem_id">${optionsHtml(state.subsystems, item.subsystem_id)}</select></td>
      <td class="col-item"><textarea data-field="item_text" rows="1">${escapeHtml(item.item_text)}</textarea></td>
      <td class="col-url"><input type="text" data-field="url" value="${escapeHtml(item.url)}"></td>
      <td class="col-project"><select data-field="project_id">${optionsHtml(state.projects, item.project_id, '')}</select></td>
      <td class="col-priority"><select data-field="priority_id">${optionsHtml(state.priorities, item.priority_id)}</select></td>
      <td class="col-order">${state.orderBy === 'priority' && item.priority_id ? '<span class="drag-handle" title="Drag to reorder">⠿</span> ' : ''}${item.order_in_priority || ''}</td>
      <td class="col-status"><select data-field="status_id">${optionsHtml(state.statuses, item.status_id)}</select></td>
      <td class="col-docs"><button class="link-btn detail-btn" data-tab="docs">Docs</button></td>
      <td class="col-notes"><button class="link-btn detail-btn" data-tab="notes">Notes</button></td>
      <td class="col-actions"><button class="icon-btn delete-item-btn" title="Delete">✕</button></td>
    `;

    bindRowEvents(tr, item);
    autoResizeTextarea(tr.querySelector('textarea[data-field="item_text"]'));

    for (const [field, cfg] of Object.entries(FIELD_COLOR_CONFIG)) {
      const sel = tr.querySelector(`select[data-field="${field}"]`);
      paintColorSelect(sel, state[cfg.list], sel.value, cfg.mode);
    }
    return tr;
  }

  function paintColorSelect(selectEl, list, selectedId, mode) {
    if (!selectEl) return;
    const opt = list.find((o) => String(o.id) === String(selectedId));
    const bg = (opt && opt.bg_color) || '';
    const text = (opt && opt.text_color) || '';
    const cell = selectEl.closest('td');
    if (mode === 'fill-cell') {
      if (cell) { cell.style.backgroundColor = bg; cell.style.color = text; }
      selectEl.style.backgroundColor = 'transparent';
      selectEl.style.color = text;
      selectEl.classList.remove('color-pill');
    } else {
      selectEl.style.backgroundColor = bg;
      selectEl.style.color = text;
      selectEl.classList.toggle('color-pill', !!bg);
    }
  }

  function autoResizeTextarea(ta) {
    if (!ta) return;
    const resize = () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
    ta.addEventListener('input', resize);
    resize();
  }

  function bindRowEvents(tr, item) {
    const debouncedSave = debounce((field, value) => saveItemField(item.id, field, value), 500);

    tr.querySelectorAll('select[data-field]').forEach((el) => {
      el.addEventListener('change', () => {
        saveItemField(item.id, el.dataset.field, el.value);
        const colorCfg = FIELD_COLOR_CONFIG[el.dataset.field];
        if (colorCfg) paintColorSelect(el, state[colorCfg.list], el.value, colorCfg.mode);
        if (el.dataset.field === 'priority_id') tr.dataset.priorityId = el.value || '';
      });
    });
    tr.querySelectorAll('input[data-field]').forEach((el) => {
      el.addEventListener('input', () => debouncedSave(el.dataset.field, el.value));
      el.addEventListener('blur', () => saveItemField(item.id, el.dataset.field, el.value));
    });
    tr.querySelectorAll('textarea[data-field]').forEach((el) => {
      el.addEventListener('input', () => debouncedSave(el.dataset.field, el.value));
      el.addEventListener('blur', () => saveItemField(item.id, el.dataset.field, el.value));
    });

    tr.querySelector('.delete-item-btn').addEventListener('click', async () => {
      if (!confirm('Delete this item?')) return;
      try {
        await del(`/api/items?id=${item.id}`);
        await loadItems();
      } catch (e) {
        toast(e.message, true);
      }
    });

    tr.querySelectorAll('.detail-btn').forEach((btn) => {
      btn.addEventListener('click', () => openDetailModal(item));
    });
  }

  async function saveItemField(itemId, field, value) {
    try {
      const updated = await put('/api/items', { id: itemId, [field]: value });
      const idx = state.items.findIndex((i) => i.id === itemId);
      if (idx !== -1) state.items[idx] = updated;
      // priority/project changes can shift ordering/filters - re-render fully to stay correct
      if (field === 'priority_id' || field === 'project_id') {
        if (field === 'project_id' && Number(value) !== state.currentProjectId) {
          await loadItems();
        } else {
          renderItems();
        }
      }
      toast('Saved');
    } catch (e) {
      toast(e.message, true);
    }
  }

  // ---------- top-level controls ----------

  function bindGlobalEvents() {
    document.getElementById('project-select').addEventListener('change', async (e) => {
      state.currentProjectId = Number(e.target.value);
      pushProjectUrl(state.projects.find((p) => p.id === state.currentProjectId));
      await loadProjectScopedLists();
      await loadItems();
    });

    window.addEventListener('popstate', async () => {
      const match = window.location.pathname.match(/^\/projects\/([a-z0-9-]+)\/?$/);
      const slug = match ? match[1] : '';
      const project = (slug && state.projects.find((p) => p.slug === slug)) || state.projects[0];
      if (!project || project.id === state.currentProjectId) return;
      state.currentProjectId = project.id;
      renderProjectSelect();
      await loadProjectScopedLists();
      await loadItems();
    });

    document.getElementById('sort-priority-btn').addEventListener('click', async () => {
      state.orderBy = state.orderBy === 'priority' ? 'sort_order' : 'priority';
      document.getElementById('sort-priority-btn').classList.toggle('active', state.orderBy === 'priority');
      await loadItems();
    });

    document.getElementById('add-item-btn').addEventListener('click', async () => {
      try {
        const created = await post('/api/items', { project_id: state.currentProjectId, item_text: '' });
        await loadItems();
        const row = document.querySelector(`tr[data-id="${created.id}"]`);
        if (row) row.querySelector('textarea[data-field="item_text"]').focus();
      } catch (e) {
        toast(e.message, true);
      }
    });

    document.getElementById('filter-q').addEventListener('input', debounce((e) => {
      state.filters.q = e.target.value;
      loadItems();
    }, 400));
    document.getElementById('filter-category').addEventListener('change', (e) => {
      state.filters.category_id = e.target.value;
      loadItems();
    });
    document.getElementById('filter-priority').addEventListener('change', (e) => {
      state.filters.priority_id = e.target.value;
      loadItems();
    });
    document.getElementById('filter-status').addEventListener('change', (e) => {
      state.filters.status_id = e.target.value;
      loadItems();
    });
    document.getElementById('clear-filters-btn').addEventListener('click', () => {
      state.filters = { q: '', category_id: '', priority_id: '', status_id: '' };
      document.getElementById('filter-q').value = '';
      document.getElementById('filter-category').value = '';
      document.getElementById('filter-priority').value = '';
      document.getElementById('filter-status').value = '';
      loadItems();
    });

    document.getElementById('manage-categories-btn').addEventListener('click', () => openOptionModal('categories'));
    document.getElementById('manage-subsystems-btn').addEventListener('click', () => openOptionModal('subsystems'));
    document.getElementById('manage-priorities-btn').addEventListener('click', () => openOptionModal('priorities'));
    document.getElementById('manage-statuses-btn').addEventListener('click', () => openOptionModal('statuses'));
    document.getElementById('manage-projects-btn').addEventListener('click', () => openOptionModal('projects'));

    document.getElementById('option-modal-close').addEventListener('click', closeOptionModal);
    document.getElementById('detail-modal-close').addEventListener('click', closeDetailModal);
  }

  // ---------- option list modals (categories / subsystems / priorities / statuses / projects) ----------

  const OPTION_CONFIG = {
    categories: { endpoint: '/api/categories', title: 'Manage Categories', projectScoped: true, hasColor: false },
    subsystems: { endpoint: '/api/subsystems', title: 'Manage Subsystems', projectScoped: true, hasColor: true },
    priorities: { endpoint: '/api/priorities', title: 'Manage Priorities', projectScoped: true, hasColor: true },
    statuses: { endpoint: '/api/statuses', title: 'Manage Statuses', projectScoped: false, hasColor: true },
    projects: { endpoint: '/api/projects', title: 'Manage Projects', projectScoped: false, hasColor: true },
  };
  let currentOptionType = null;

  function openOptionModal(type) {
    currentOptionType = type;
    const cfg = OPTION_CONFIG[type];
    document.getElementById('option-modal-title').textContent = cfg.title;
    renderOptionModalList();
    document.getElementById('option-modal').classList.remove('hidden');

    const form = document.getElementById('option-modal-add-form');
    form.onsubmit = async (e) => {
      e.preventDefault();
      const input = document.getElementById('option-modal-new-name');
      const name = input.value.trim();
      if (!name) return;
      const body = { name };
      if (cfg.projectScoped) body.project_id = state.currentProjectId;
      try {
        const created = await post(cfg.endpoint, body);
        input.value = '';
        if (type === 'projects') state.projects.push(created);
        await refreshOptionList(type);
        renderOptionModalList();
      } catch (err) {
        toast(err.message, true);
      }
    };
  }

  async function refreshOptionList(type) {
    const cfg = OPTION_CONFIG[type];
    const url = cfg.projectScoped ? `${cfg.endpoint}?project_id=${state.currentProjectId}` : cfg.endpoint;
    state[type] = await get(url);
    if (type === 'projects') renderProjectSelect();
    renderFilterSelects();
    renderItems();
  }

  function renderOptionModalList() {
    const cfg = OPTION_CONFIG[currentOptionType];
    const ul = document.getElementById('option-modal-list');
    const list = state[currentOptionType];
    ul.innerHTML = '';
    for (const opt of list) {
      const li = document.createElement('li');
      li.dataset.id = opt.id;
      li.innerHTML = `
        <span class="drag-handle" title="Drag to reorder">⠿</span>
        <input type="text" class="option-name-input" value="${escapeHtml(opt.name)}">
        ${cfg.hasColor ? `
          <button type="button" class="icon-btn color-btn bucket-btn" title="Background color" style="background:${opt.bg_color || 'transparent'}"><i class="fa-solid fa-fill-drip"></i></button>
          <button type="button" class="icon-btn color-btn text-btn" title="Text color" style="color:${opt.text_color || 'inherit'}"><i class="fa-solid fa-font"></i></button>
        ` : ''}
        <button class="icon-btn delete-option-btn" title="Delete">✕</button>
      `;
      li.querySelector('.option-name-input').addEventListener('change', async (e) => {
        try {
          await put(cfg.endpoint, { id: opt.id, name: e.target.value.trim() });
          await refreshOptionList(currentOptionType);
        } catch (err) {
          toast(err.message, true);
        }
      });
      if (cfg.hasColor) {
        li.querySelector('.bucket-btn').addEventListener('click', (e) => {
          openColorPicker(e.currentTarget, opt.bg_color, async (hex) => {
            try {
              await put(cfg.endpoint, { id: opt.id, bg_color: hex });
              await refreshOptionList(currentOptionType);
              renderOptionModalList();
            } catch (err) {
              toast(err.message, true);
            }
          });
        });
        li.querySelector('.text-btn').addEventListener('click', (e) => {
          openColorPicker(e.currentTarget, opt.text_color, async (hex) => {
            try {
              await put(cfg.endpoint, { id: opt.id, text_color: hex });
              await refreshOptionList(currentOptionType);
              renderOptionModalList();
            } catch (err) {
              toast(err.message, true);
            }
          });
        });
      }
      li.querySelector('.delete-option-btn').addEventListener('click', async () => {
        const extra = currentOptionType === 'projects' ? ' and all its items' : '';
        if (!confirm(`Delete "${opt.name}"${extra}?`)) return;
        try {
          await del(`${cfg.endpoint}?id=${opt.id}`);
          if (currentOptionType === 'projects') {
            state.projects = state.projects.filter((p) => p.id !== opt.id);
            if (state.currentProjectId === opt.id) {
              state.currentProjectId = state.projects[0] ? state.projects[0].id : null;
              pushProjectUrl(state.projects[0]);
              await loadProjectScopedLists();
              await loadItems();
            }
            renderProjectSelect();
          }
          await refreshOptionList(currentOptionType);
          renderOptionModalList();
        } catch (err) {
          toast(err.message, true);
        }
      });
      ul.appendChild(li);
    }
    Sortable.create(ul, {
      handle: '.drag-handle',
      animation: 150,
      onEnd: async () => {
        const ids = Array.from(ul.children).map((li) => li.dataset.id);
        try {
          await patch(cfg.endpoint, { order: ids });
          await refreshOptionList(currentOptionType);
        } catch (err) {
          toast(err.message, true);
        }
      },
    });
  }

  function closeOptionModal() {
    document.getElementById('option-modal').classList.add('hidden');
  }

  // ---------- color picker popover ----------

  function openColorPicker(anchorEl, currentColor, onPick) {
    document.querySelectorAll('.color-picker-popover').forEach((p) => p.remove());

    const pop = document.createElement('div');
    pop.className = 'color-picker-popover';
    pop.innerHTML = `
      <div class="color-swatch-grid">
        ${COMMON_COLOR_SWATCHES.map((c) => `<button type="button" class="color-swatch" data-color="${c}" style="background:${c}"></button>`).join('')}
      </div>
      <div class="color-picker-custom">
        <input type="color" value="${currentColor || '#ffffff'}">
        <button type="button" class="clear-color-btn link-btn">Clear</button>
      </div>
    `;
    document.body.appendChild(pop);

    const rect = anchorEl.getBoundingClientRect();
    pop.style.position = 'fixed';
    pop.style.top = `${rect.bottom + 4}px`;
    pop.style.left = `${rect.left}px`;

    pop.querySelectorAll('.color-swatch').forEach((btn) => {
      btn.addEventListener('click', () => { onPick(btn.dataset.color); pop.remove(); });
    });
    pop.querySelector('input[type="color"]').addEventListener('change', (e) => {
      onPick(e.target.value);
      pop.remove();
    });
    pop.querySelector('.clear-color-btn').addEventListener('click', () => { onPick(''); pop.remove(); });

    setTimeout(() => {
      document.addEventListener('click', closeOnOutsideClick);
    }, 0);
    function closeOnOutsideClick(e) {
      if (!pop.contains(e.target) && e.target !== anchorEl) {
        pop.remove();
        document.removeEventListener('click', closeOnOutsideClick);
      }
    }
  }

  // ---------- detail modal (docs + notes) ----------

  async function openDetailModal(item) {
    state.detailItemId = item.id;
    document.getElementById('detail-item-text').textContent = item.item_text || '(no description)';
    document.getElementById('detail-modal').classList.remove('hidden');
    await Promise.all([renderDocsList(), renderNotesList()]);

    document.getElementById('detail-docs-form').onsubmit = async (e) => {
      e.preventDefault();
      const label = document.getElementById('detail-doc-label');
      const url = document.getElementById('detail-doc-url');
      if (!url.value.trim()) return;
      try {
        await post('/api/docs', { item_id: state.detailItemId, label: label.value.trim(), url: url.value.trim() });
        label.value = '';
        url.value = '';
        await renderDocsList();
      } catch (err) {
        toast(err.message, true);
      }
    };

    document.getElementById('detail-notes-form').onsubmit = async (e) => {
      e.preventDefault();
      const body = document.getElementById('detail-note-body');
      if (!body.value.trim()) return;
      try {
        await post('/api/notes', { item_id: state.detailItemId, body: body.value.trim() });
        body.value = '';
        await renderNotesList();
      } catch (err) {
        toast(err.message, true);
      }
    };
  }

  async function renderDocsList() {
    const docs = await get(`/api/docs?item_id=${state.detailItemId}`);
    const ul = document.getElementById('detail-docs-list');
    ul.innerHTML = '';
    for (const d of docs) {
      const li = document.createElement('li');
      li.innerHTML = `
        <a href="${escapeHtml(d.url)}" target="_blank" rel="noopener">${escapeHtml(d.label || d.url)}</a>
        <button class="icon-btn delete-doc-btn" title="Delete">✕</button>
      `;
      li.querySelector('.delete-doc-btn').addEventListener('click', async () => {
        await del(`/api/docs?id=${d.id}`);
        await renderDocsList();
      });
      ul.appendChild(li);
    }
    updateDetailButtonCount('docs', docs.length);
  }

  async function renderNotesList() {
    const notes = await get(`/api/notes?item_id=${state.detailItemId}`);
    const ul = document.getElementById('detail-notes-list');
    ul.innerHTML = '';
    for (const n of notes) {
      const li = document.createElement('li');
      const date = new Date(n.created_at * 1000).toLocaleString();
      li.innerHTML = `
        <div class="note-meta">${date}</div>
        <textarea class="note-body" rows="2">${escapeHtml(n.body)}</textarea>
        <button class="icon-btn delete-note-btn" title="Delete">✕</button>
      `;
      const textarea = li.querySelector('.note-body');
      textarea.addEventListener('blur', async () => {
        if (textarea.value.trim() === n.body) return;
        try {
          await put('/api/notes', { id: n.id, body: textarea.value.trim() });
          toast('Note saved');
        } catch (err) {
          toast(err.message, true);
        }
      });
      li.querySelector('.delete-note-btn').addEventListener('click', async () => {
        await del(`/api/notes?id=${n.id}`);
        await renderNotesList();
      });
      ul.appendChild(li);
    }
    updateDetailButtonCount('notes', notes.length);
  }

  function updateDetailButtonCount(tab, count) {
    const row = document.querySelector(`tr[data-id="${state.detailItemId}"]`);
    if (!row) return;
    const btn = row.querySelector(`.detail-btn[data-tab="${tab}"]`);
    if (btn) btn.textContent = `${tab === 'docs' ? 'Docs' : 'Notes'}${count ? ` (${count})` : ''}`;
  }

  function closeDetailModal() {
    document.getElementById('detail-modal').classList.add('hidden');
    state.detailItemId = null;
  }

  document.addEventListener('DOMContentLoaded', init);
})();

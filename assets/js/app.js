/* Daybook app - vanilla JS, no build step */
(function () {
  'use strict';

  const state = {
    projects: [],
    categories: [],
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
      window.location.href = '/login.php';
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
    state.projects = await get('/api/projects.php');
    if (!state.projects.length) {
      const created = await post('/api/projects.php', { name: 'General' });
      state.projects = [created];
    }
    state.currentProjectId = state.projects[0].id;
    renderProjectSelect();
    await loadProjectScopedLists();
    await loadItems();
    bindGlobalEvents();
  }

  async function loadProjectScopedLists() {
    const pid = state.currentProjectId;
    [state.categories, state.priorities, state.statuses] = await Promise.all([
      get(`/api/categories.php?project_id=${pid}`),
      get(`/api/priorities.php?project_id=${pid}`),
      get('/api/statuses.php'),
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
    state.items = await get('/api/items.php?' + params.toString());
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

    if (state.orderBy === 'priority') {
      const groups = new Map();
      for (const it of state.items) {
        const key = it.priority_id || 'none';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(it);
      }
      for (const [key, rows] of groups) {
        const label = key === 'none' ? '(No Priority)' : (rows[0].priority_name || '(No Priority)');
        const headerRow = document.createElement('tr');
        headerRow.className = 'priority-group-header';
        headerRow.innerHTML = `<td colspan="12">${escapeHtml(label)}</td>`;
        tbody.appendChild(headerRow);

        const groupBody = document.createElement('tbody');
        groupBody.className = 'priority-group';
        groupBody.dataset.priorityId = key === 'none' ? '' : key;
        for (const item of rows) groupBody.appendChild(buildRow(item));
        tbody.appendChild(groupBody);

        if (key !== 'none') makeSortable(groupBody);
      }
    } else {
      for (const item of state.items) tbody.appendChild(buildRow(item));
    }
  }

  function makeSortable(groupBody) {
    Sortable.create(groupBody, {
      animation: 150,
      handle: '.drag-handle',
      onEnd: async () => {
        const ids = Array.from(groupBody.children).map((tr) => tr.dataset.id);
        try {
          await patch('/api/items.php', { priority_id: Number(groupBody.dataset.priorityId), order: ids });
        } catch (e) {
          toast(e.message, true);
        }
      },
    });
  }

  function buildRow(item) {
    const tr = document.createElement('tr');
    tr.dataset.id = item.id;

    tr.innerHTML = `
      <td class="col-sort">${item.sort_order}</td>
      <td class="col-category"><select data-field="category_id">${optionsHtml(state.categories, item.category_id)}</select></td>
      <td class="col-subsystem"><input type="text" data-field="subsystem" value="${escapeHtml(item.subsystem)}"></td>
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
    return tr;
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
      el.addEventListener('change', () => saveItemField(item.id, el.dataset.field, el.value));
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
        await del(`/api/items.php?id=${item.id}`);
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
      const updated = await put('/api/items.php', { id: itemId, [field]: value });
      const idx = state.items.findIndex((i) => i.id === itemId);
      if (idx !== -1) state.items[idx] = updated;
      // priority/project changes can shift grouping/filters - re-render fully to stay correct
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
        const created = await post('/api/items.php', { project_id: state.currentProjectId, item_text: '' });
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
    document.getElementById('manage-priorities-btn').addEventListener('click', () => openOptionModal('priorities'));
    document.getElementById('manage-statuses-btn').addEventListener('click', () => openOptionModal('statuses'));
    document.getElementById('manage-projects-btn').addEventListener('click', openProjectModal);

    document.getElementById('option-modal-close').addEventListener('click', closeOptionModal);
    document.getElementById('project-modal-close').addEventListener('click', closeProjectModal);
    document.getElementById('detail-modal-close').addEventListener('click', closeDetailModal);
  }

  // ---------- option list modals (categories / priorities / statuses) ----------

  const OPTION_CONFIG = {
    categories: { endpoint: '/api/categories.php', title: 'Manage Categories', projectScoped: true },
    priorities: { endpoint: '/api/priorities.php', title: 'Manage Priorities', projectScoped: true },
    statuses: { endpoint: '/api/statuses.php', title: 'Manage Statuses', projectScoped: false },
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
        await post(cfg.endpoint, body);
        input.value = '';
        await refreshOptionList(type);
        renderOptionModalList();
      } catch (err) {
        toast(err.message, true);
      }
    };
  }

  function listForType(type) {
    return state[type];
  }

  async function refreshOptionList(type) {
    const cfg = OPTION_CONFIG[type];
    const url = cfg.projectScoped ? `${cfg.endpoint}?project_id=${state.currentProjectId}` : cfg.endpoint;
    state[type] = await get(url);
    renderFilterSelects();
    renderItems();
  }

  function renderOptionModalList() {
    const cfg = OPTION_CONFIG[currentOptionType];
    const ul = document.getElementById('option-modal-list');
    const list = listForType(currentOptionType);
    ul.innerHTML = '';
    for (const opt of list) {
      const li = document.createElement('li');
      li.dataset.id = opt.id;
      li.innerHTML = `
        <span class="drag-handle" title="Drag to reorder">⠿</span>
        <input type="text" class="option-name-input" value="${escapeHtml(opt.name)}">
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
      li.querySelector('.delete-option-btn').addEventListener('click', async () => {
        if (!confirm(`Delete "${opt.name}"? Items using it will be cleared to blank.`)) return;
        try {
          await del(`${cfg.endpoint}?id=${opt.id}`);
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

  // ---------- project modal ----------

  function openProjectModal() {
    renderProjectModalList();
    document.getElementById('project-modal').classList.remove('hidden');

    const form = document.getElementById('project-modal-add-form');
    form.onsubmit = async (e) => {
      e.preventDefault();
      const input = document.getElementById('project-modal-new-name');
      const name = input.value.trim();
      if (!name) return;
      try {
        const created = await post('/api/projects.php', { name });
        state.projects.push(created);
        input.value = '';
        renderProjectModalList();
        renderProjectSelect();
      } catch (err) {
        toast(err.message, true);
      }
    };
  }

  function renderProjectModalList() {
    const ul = document.getElementById('project-modal-list');
    ul.innerHTML = '';
    for (const proj of state.projects) {
      const li = document.createElement('li');
      li.innerHTML = `
        <input type="text" class="option-name-input" value="${escapeHtml(proj.name)}">
        <button class="icon-btn delete-option-btn" title="Delete">✕</button>
      `;
      li.querySelector('.option-name-input').addEventListener('change', async (e) => {
        try {
          await put('/api/projects.php', { id: proj.id, name: e.target.value.trim() });
          proj.name = e.target.value.trim();
          renderProjectSelect();
        } catch (err) {
          toast(err.message, true);
        }
      });
      li.querySelector('.delete-option-btn').addEventListener('click', async () => {
        if (!confirm(`Delete project "${proj.name}" and all its items?`)) return;
        try {
          await del(`/api/projects.php?id=${proj.id}`);
          state.projects = state.projects.filter((p) => p.id !== proj.id);
          if (state.currentProjectId === proj.id) {
            state.currentProjectId = state.projects[0] ? state.projects[0].id : null;
            await loadProjectScopedLists();
            await loadItems();
          }
          renderProjectModalList();
          renderProjectSelect();
        } catch (err) {
          toast(err.message, true);
        }
      });
      ul.appendChild(li);
    }
  }

  function closeProjectModal() {
    document.getElementById('project-modal').classList.add('hidden');
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
        await post('/api/docs.php', { item_id: state.detailItemId, label: label.value.trim(), url: url.value.trim() });
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
        await post('/api/notes.php', { item_id: state.detailItemId, body: body.value.trim() });
        body.value = '';
        await renderNotesList();
      } catch (err) {
        toast(err.message, true);
      }
    };
  }

  async function renderDocsList() {
    const docs = await get(`/api/docs.php?item_id=${state.detailItemId}`);
    const ul = document.getElementById('detail-docs-list');
    ul.innerHTML = '';
    for (const d of docs) {
      const li = document.createElement('li');
      li.innerHTML = `
        <a href="${escapeHtml(d.url)}" target="_blank" rel="noopener">${escapeHtml(d.label || d.url)}</a>
        <button class="icon-btn delete-doc-btn" title="Delete">✕</button>
      `;
      li.querySelector('.delete-doc-btn').addEventListener('click', async () => {
        await del(`/api/docs.php?id=${d.id}`);
        await renderDocsList();
      });
      ul.appendChild(li);
    }
    updateDetailButtonCount('docs', docs.length);
  }

  async function renderNotesList() {
    const notes = await get(`/api/notes.php?item_id=${state.detailItemId}`);
    const ul = document.getElementById('detail-notes-list');
    ul.innerHTML = '';
    for (const n of notes) {
      const li = document.createElement('li');
      const date = new Date(n.created_at).toLocaleString();
      li.innerHTML = `
        <div class="note-meta">${date}</div>
        <textarea class="note-body" rows="2">${escapeHtml(n.body)}</textarea>
        <button class="icon-btn delete-note-btn" title="Delete">✕</button>
      `;
      const textarea = li.querySelector('.note-body');
      textarea.addEventListener('blur', async () => {
        if (textarea.value.trim() === n.body) return;
        try {
          await put('/api/notes.php', { id: n.id, body: textarea.value.trim() });
          toast('Note saved');
        } catch (err) {
          toast(err.message, true);
        }
      });
      li.querySelector('.delete-note-btn').addEventListener('click', async () => {
        await del(`/api/notes.php?id=${n.id}`);
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

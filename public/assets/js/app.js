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
    projectMembers: [],
    projectAssignees: [],
    currentProjectId: null,
    orderBy: 'sort_order', // or 'priority'
    showCompleted: true,
    filters: { q: '', category_id: '', priority_id: '', status_id: '' },
    detailItemId: null,
    me: null,
    caps: null,
  };

  const PROJECT_COOKIE = 'daybook_project';
  const PROJECT_OWNER_ASSIGNEE = 'owner';

  function getProjectCookie() {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${PROJECT_COOKIE}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function setProjectCookie(project) {
    const slug = projectSlug(project);
    if (!slug) return;
    const maxAge = 60 * 60 * 24 * 365;
    document.cookie = `${PROJECT_COOKIE}=${encodeURIComponent(slug)}; path=/; max-age=${maxAge}; SameSite=Lax`;
  }

  function findProjectBySlug(slug) {
    return slug && state.projects.find((p) => projectSlug(p) === slug);
  }

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
    const meData = await get('/api/me');
    state.me = meData;
    state.projects = await get('/api/projects');
    if (!state.projects.length) {
      if (meData.is_daybookstaff) {
        const created = await post('/api/projects', { name: 'General' });
        state.projects = [created];
      } else {
        toast('You have no projects yet. Ask an admin for an invite.', true);
        return;
      }
    }
    const requestedSlug = window.INITIAL_PROJECT_SLUG || '';
    const cookieSlug = getProjectCookie();
    const initialProject =
      findProjectBySlug(requestedSlug)
      || findProjectBySlug(cookieSlug)
      || state.projects[0];
    state.currentProjectId = Number(initialProject.id);
    setProjectCookie(initialProject);
    navigateToProject(initialProject, { replace: true });
    renderProjectStrip();
    await loadProjectScopedLists();
    await loadMeAndCaps();
    await loadProjectMembers();
    await loadItems();
    applyPermissionUI();
    syncFilterbarToggles();
    bindGlobalEvents();
  }

  async function loadMeAndCaps() {
    const data = await get(`/api/me?project_id=${state.currentProjectId}`);
    state.me = data;
    state.caps = data.caps || null;
    if (Array.isArray(data.project_assignees) && data.project_assignees.length) {
      state.projectAssignees = data.project_assignees;
    } else if (Array.isArray(data.project_members) && data.project_members.length) {
      state.projectAssignees = data.project_members;
    }
    if (Array.isArray(data.project_members)) {
      state.projectMembers = data.project_members;
    }
    const project = findProject(state.currentProjectId);
    if (project && state.caps) {
      if (state.caps.owner_name) project.owner_name = state.caps.owner_name;
      if (state.caps.owner_user_id) project.resolved_owner_user_id = state.caps.owner_user_id;
      if (state.caps.project_owner_assignee_label) {
        project.project_owner_assignee_label = state.caps.project_owner_assignee_label;
      }
    }
    renderProjectStrip();
  }

  function currentProjectRoleLabel() {
    const caps = state.caps;
    let role = caps?.role;
    if (!role) {
      const project = findProject(state.currentProjectId);
      role = project?.my_role;
    }
    if (caps?.is_daybookstaff && !role) return 'Daybookstaff';
    if (!role) return '';
    return role.charAt(0).toUpperCase() + role.slice(1);
  }

  async function loadProjectAssignees() {
    if (!state.currentProjectId) {
      state.projectAssignees = [];
      return;
    }
    try {
      const assignees = await get(`/api/assignees?project_id=${state.currentProjectId}`);
      if (Array.isArray(assignees)) {
        state.projectAssignees = assignees;
        if (state.items.length) renderItems();
      }
    } catch {
      if (!state.projectAssignees.length && state.projectMembers.length) {
        state.projectAssignees = state.projectMembers.slice();
        if (state.items.length) renderItems();
      } else if (!state.projectAssignees.length) {
        toast('Could not load assignee options', true);
      }
    }
  }

  async function loadProjectMembers() {
    if (!state.currentProjectId) {
      state.projectMembers = [];
      return;
    }
    try {
      const members = await get(`/api/members?project_id=${state.currentProjectId}`);
      if (Array.isArray(members)) {
        state.projectMembers = members;
      }
    } catch {
      if (!state.projectMembers.length) {
        toast('Could not load project members', true);
      }
    }
    await loadProjectAssignees();
  }

  function assigneeMemberList() {
    const assignees = Array.isArray(state.projectAssignees) ? state.projectAssignees : [];
    if (assignees.length) return assignees;
    return Array.isArray(state.projectMembers) ? state.projectMembers : [];
  }

  function memberDisplayName(member) {
    const name = String(member?.name ?? '').trim();
    return name || member?.email || '';
  }

  function memberLabel(member) {
    const base = memberDisplayName(member);
    return Number(member?.pending_invite) === 1 ? `${base} (invited)` : base;
  }

  function applyPermissionUI() {
    const caps = state.caps || {};
    const setVisible = (id, show) => {
      const el = document.getElementById(id);
      if (el) el.classList.toggle('hidden', !show);
    };
    setVisible('add-item-btn', !!caps.can_create_item);
    setVisible('manage-categories-btn', !!caps.can_edit_metadata);
    setVisible('manage-subsystems-btn', !!caps.can_edit_metadata);
    setVisible('manage-priorities-btn', !!caps.can_edit_metadata);
    setVisible('manage-statuses-btn', !!state.me?.is_daybookstaff);
    setVisible('manage-members-btn', !!caps.can_manage_members);
    setVisible('export-btn', !!caps.can_export);
    setVisible('export-tasklist-btn', !!caps.can_export);
  }

  const COMPLETED_STATUS_NAMES = new Set(['OK', 'N/A']);

  function taskListExportField(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
  }

  function formatTaskListExportDate(date) {
    return date.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  function itemsForTaskListExport() {
    if (state.showCompleted) return state.items;
    return state.items.filter((item) => {
      const status = taskListExportField(item.status_name);
      return !status || !COMPLETED_STATUS_NAMES.has(status);
    });
  }

  function taskListTaggedField(label, value) {
    const text = taskListExportField(value);
    return text ? `[${label}]: ${text}` : '';
  }

  function taskListTaggedLine(fields) {
    return fields
      .map(([label, value]) => taskListTaggedField(label, value))
      .filter(Boolean)
      .join(' ');
  }

  function buildTaskListExportText() {
    const project = findProject(state.currentProjectId);
    const projectName = taskListExportField(project?.name) || 'Project';
    const header = `${projectName}: Task List | ${formatTaskListExportDate(new Date())}`;
    const rows = itemsForTaskListExport().map((item, index) => {
      const parts = [String(index + 1)];
      const itemText = taskListExportField(item.item_text);
      if (itemText) parts.push(itemText);
      const tagged = taskListTaggedLine([
        ['priority', item.priority_name],
        ['category', item.category_name],
        ['subsystem', item.subsystem_name],
        ['assignee', item.assignee_name || item.assignee_email],
        ['status', item.status_name],
      ]);
      if (tagged) parts.push(tagged);
      return parts.join(' ');
    });
    return [header, ...rows].join('\n');
  }

  let copyTaskListIconTimer;

  function showCopyTaskListSuccess() {
    const icon = document.getElementById('export-tasklist-copied');
    if (!icon) return;
    icon.classList.remove('hidden');
    clearTimeout(copyTaskListIconTimer);
    copyTaskListIconTimer = setTimeout(() => icon.classList.add('hidden'), 2000);
  }

  async function copyTaskListExport() {
    const text = buildTaskListExportText();
    try {
      await navigator.clipboard.writeText(text);
      showCopyTaskListSuccess();
    } catch (e) {
      toast('Could not copy to clipboard', true);
    }
  }

  function canEditItemFields(item) {
    const caps = state.caps || {};
    if (caps.is_daybookstaff) return true;
    if (['admin', 'manager'].includes(caps.role)) return true;
    if (caps.role === 'contributor') {
      return Number(effectiveAssigneeUserId(item)) === Number(state.me?.id);
    }
    return false;
  }

  function canEditItemStatus() {
    const caps = state.caps || {};
    if (caps.is_daybookstaff) return true;
    return ['admin', 'manager', 'contributor'].includes(caps.role);
  }

  function canDeleteItem(item) {
    const caps = state.caps || {};
    if (caps.is_daybookstaff) return true;
    if (caps.is_owner && caps.role === 'admin') return true;
    if (['admin', 'manager'].includes(caps.role)
      && Number(item.created_by_user_id) === Number(state.me?.id)) return true;
    return false;
  }

  function membersModalLabel(member) {
    return memberDisplayName(member);
  }

  function resolvedProjectOwnerUserId(projectId) {
    const project = findProject(projectId);
    if (project?.resolved_owner_user_id) return Number(project.resolved_owner_user_id);
    if (project?.owner_user_id) return Number(project.owner_user_id);
    if (sameId(projectId, state.currentProjectId) && state.caps?.owner_user_id) {
      return Number(state.caps.owner_user_id);
    }
    const item = state.items.find((i) => sameId(i.project_id, projectId) && i.project_owner_user_id);
    if (item?.project_owner_user_id) return Number(item.project_owner_user_id);
    const members = sameId(projectId, state.currentProjectId) ? assigneeMemberList() : [];
    const flagged = members.find((m) => Number(m.is_owner) === 1);
    if (flagged?.user_id) return Number(flagged.user_id);
    const admin = members.find((m) => m.role === 'admin');
    if (admin?.user_id) return Number(admin.user_id);
    return null;
  }

  function projectOwnerMember(projectId) {
    const ownerUserId = resolvedProjectOwnerUserId(projectId);
    if (!ownerUserId) return null;
    return assigneeMemberList().find((m) => sameId(m.user_id, ownerUserId)) || null;
  }

  function projectOwnerAssigneeLabel(projectId, item) {
    const fromItem = String(item?.project_owner_assignee_label ?? '').trim();
    if (fromItem) return fromItem;
    const project = findProject(projectId);
    const fromProject = String(project?.project_owner_assignee_label ?? project?.owner_name ?? '').trim();
    if (fromProject) return fromProject;
    if (sameId(projectId, state.currentProjectId)) {
      const capsLabel = String(state.caps?.project_owner_assignee_label ?? state.caps?.owner_name ?? '').trim();
      if (capsLabel) return capsLabel;
    }
    const byFlag = assigneeMemberList().find((m) => sameId(projectId, state.currentProjectId) && Number(m.is_owner) === 1);
    if (byFlag) {
      const flaggedName = String(byFlag.name ?? '').trim();
      if (flaggedName) return flaggedName;
    }
    const owner = projectOwnerMember(projectId);
    const memberName = String(owner?.name ?? '').trim();
    if (memberName) return memberName;
    return 'Project Owner';
  }

  function effectiveAssigneeUserId(item) {
    if (Number(item.assigned_to_project_owner) === 1) {
      return item.project_owner_user_id
        ? Number(item.project_owner_user_id)
        : resolvedProjectOwnerUserId(item.project_id);
    }
    return item.assigned_user_id ?? null;
  }

  function memberOptionsHtml(item) {
    const ownerUserId = resolvedProjectOwnerUserId(item.project_id);
    const ownerSelected = Number(item.assigned_to_project_owner) === 1;
    const unassigned = !ownerSelected && !item.assigned_user_id;
    let html = `<option value="" ${unassigned ? 'selected' : ''}>--</option>`;
    html += `<option value="${PROJECT_OWNER_ASSIGNEE}" ${ownerSelected ? 'selected' : ''}>${escapeHtml(projectOwnerAssigneeLabel(item.project_id, item))}</option>`;
    for (const m of assigneeMemberList()) {
      if (ownerUserId && sameId(m.user_id, ownerUserId)) continue;
      const sel = !ownerSelected && sameId(m.user_id, item.assigned_user_id) ? 'selected' : '';
      html += `<option value="${m.user_id}" ${sel}>${escapeHtml(memberLabel(m))}</option>`;
    }
    return html;
  }

  function sameId(a, b) {
    return Number(a) === Number(b);
  }

  function findProject(id) {
    return state.projects.find((p) => sameId(p.id, id));
  }

  function slugifyName(name) {
    const slug = String(name ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return slug || 'project';
  }

  function projectSlug(project) {
    return (project && (project.slug || slugifyName(project.name))) || '';
  }

  function projectUrl(project) {
    const slug = projectSlug(project);
    return slug ? `/projects/${slug}` : null;
  }

  function navigateToProject(project, { replace = false, reload = false } = {}) {
    const url = projectUrl(project);
    if (!url || window.location.pathname === url) return false;
    if (reload) {
      window.location.assign(url);
      return true;
    }
    if (replace) window.history.replaceState({}, '', url);
    else window.history.pushState({}, '', url);
    return true;
  }

  async function switchProject(projectId) {
    const project = findProject(projectId);
    if (!project) return;
    state.currentProjectId = Number(project.id);
    setProjectCookie(project);
    closeProjectStripMenu();
    renderProjectStrip();
    navigateToProject(project);
    await loadProjectScopedLists();
    await loadMeAndCaps();
    await loadProjectMembers();
    await loadItems();
    applyPermissionUI();
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
    if (!state.showCompleted) params.set('active_only', '1');
    state.items = await get('/api/items?' + params.toString());
    renderItems();
  }

  // ---------- rendering ----------

  function renderProjectStrip() {
    const project = findProject(state.currentProjectId);
    const strip = document.getElementById('project-strip');
    const toggle = document.getElementById('project-strip-toggle');
    const nameEl = document.getElementById('project-strip-name');
    const roleEl = document.getElementById('project-strip-role');
    const menu = document.getElementById('project-strip-menu');
    if (!project || !strip || !toggle || !nameEl || !menu) return;

    const bg = project.bg_color || '#e5e7eb';
    const text = project.text_color || '#1f2937';
    strip.style.backgroundColor = bg;
    strip.style.color = text;
    toggle.style.backgroundColor = bg;
    toggle.style.color = text;
    nameEl.textContent = project.name;

    const roleLabel = currentProjectRoleLabel();
    if (roleEl) {
      roleEl.textContent = roleLabel;
      roleEl.classList.toggle('hidden', !roleLabel);
      if (roleLabel) {
        roleEl.setAttribute('aria-label', `Your role: ${roleLabel}`);
      } else {
        roleEl.removeAttribute('aria-label');
      }
    }

    menu.innerHTML = state.projects.map((p) => {
      const active = sameId(p.id, state.currentProjectId);
      const swatch = p.bg_color || '#e5e7eb';
      return `<li role="presentation"><button type="button" class="project-strip-option${active ? ' active' : ''}" data-project-id="${p.id}" role="option" aria-selected="${active}">
        <span class="project-strip-swatch" style="background:${swatch}"></span>${escapeHtml(p.name)}
      </button></li>`;
    }).join('');
    if (!menu.classList.contains('hidden')) positionProjectStripMenu();
  }

  function positionProjectStripMenu() {
    const chevron = document.querySelector('.project-strip-chevron');
    const menu = document.getElementById('project-strip-menu');
    const control = document.querySelector('.project-strip-control');
    if (!chevron || !menu || !control) return;
    const chevronRect = chevron.getBoundingClientRect();
    const controlRect = control.getBoundingClientRect();
    const caretCenter = chevronRect.left + chevronRect.width / 2 - controlRect.left;
    menu.style.left = `${caretCenter}px`;
    menu.style.right = 'auto';
    menu.style.transform = 'translateX(-50%)';
  }

  function closeProjectStripMenu() {
    const menu = document.getElementById('project-strip-menu');
    const toggle = document.getElementById('project-strip-toggle');
    if (menu) menu.classList.add('hidden');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function toggleProjectStripMenu() {
    const menu = document.getElementById('project-strip-menu');
    const toggle = document.getElementById('project-strip-toggle');
    if (!menu || !toggle) return;
    const opening = menu.classList.contains('hidden');
    if (opening) {
      menu.classList.remove('hidden');
      positionProjectStripMenu();
      toggle.setAttribute('aria-expanded', 'true');
    } else {
      closeProjectStripMenu();
    }
  }

  function closeUserMenu() {
    const menu = document.getElementById('user-menu-dropdown');
    const toggle = document.getElementById('user-menu-toggle');
    if (menu) menu.classList.add('hidden');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function closeFilterConfigMenu() {
    const menu = document.getElementById('filter-config-menu');
    const toggle = document.getElementById('filter-config-toggle');
    if (menu) menu.classList.add('hidden');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function toggleFilterConfigMenu() {
    const menu = document.getElementById('filter-config-menu');
    const toggle = document.getElementById('filter-config-toggle');
    if (!menu || !toggle) return;
    const opening = menu.classList.contains('hidden');
    if (opening) {
      closeProjectStripMenu();
      closeUserMenu();
      menu.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
    } else {
      closeFilterConfigMenu();
    }
  }

  function syncFilterbarToggles() {
    const priorityToggle = document.getElementById('priority-sort-toggle');
    if (priorityToggle) priorityToggle.checked = state.orderBy === 'priority';
    const completedToggle = document.getElementById('show-completed-toggle');
    if (completedToggle) completedToggle.checked = state.showCompleted;
  }

  function toggleUserMenu() {
    const menu = document.getElementById('user-menu-dropdown');
    const toggle = document.getElementById('user-menu-toggle');
    if (!menu || !toggle) return;
    const opening = menu.classList.contains('hidden');
    if (opening) {
      closeProjectStripMenu();
      menu.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
    } else {
      closeUserMenu();
    }
  }

  function renderFilterSelects() {
    const categorySelect = document.getElementById('filter-category');
    categorySelect.innerHTML =
      '<option value="">All Categories</option>' + state.categories.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    categorySelect.value = state.filters.category_id;

    const prioritySelect = document.getElementById('filter-priority');
    prioritySelect.innerHTML =
      '<option value="">All Priorities</option>' + state.priorities.map((p) => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
    prioritySelect.value = state.filters.priority_id;

    const statusSelect = document.getElementById('filter-status');
    statusSelect.innerHTML =
      '<option value="">All Statuses</option>'
      + '<option value="pending">All pending</option>'
      + state.statuses.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    statusSelect.value = state.filters.status_id;
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

    const caps = state.caps || {};
    const fieldsEditable = canEditItemFields(item);
    const statusEditable = canEditItemStatus();
    const assignEditable = !!caps.can_assign_items;
    const showDelete = canDeleteItem(item);
    const reorderable = !!caps.can_reorder;

    tr.innerHTML = `
      <td class="col-sort">${item.sort_order}</td>
      <td class="col-category"><select data-field="category_id" ${fieldsEditable ? '' : 'disabled'}>${optionsHtml(state.categories, item.category_id)}</select></td>
      <td class="col-subsystem"><select data-field="subsystem_id" ${fieldsEditable ? '' : 'disabled'}>${optionsHtml(state.subsystems, item.subsystem_id)}</select></td>
      <td class="col-item"><textarea data-field="item_text" rows="1" ${fieldsEditable ? '' : 'disabled'}>${escapeHtml(item.item_text)}</textarea></td>
      <td class="col-url">
        <div class="url-cell">
          <input type="text" data-field="url" value="${escapeHtml(item.url)}" ${fieldsEditable ? '' : 'disabled'}>
          <button type="button" class="icon-btn url-open-btn hidden" title="Open URL" aria-label="Open URL">↗</button>
        </div>
      </td>
      <td class="col-project"><select data-field="project_id" ${fieldsEditable && caps.is_owner ? '' : 'disabled'}>${optionsHtml(state.projects, item.project_id, '')}</select></td>
      <td class="col-priority"><select data-field="priority_id" ${fieldsEditable ? '' : 'disabled'}>${optionsHtml(state.priorities, item.priority_id)}</select></td>
      <td class="col-order">${state.orderBy === 'priority' && item.priority_id && reorderable ? '<span class="drag-handle" title="Drag to reorder">⠿</span> ' : ''}${item.order_in_priority || ''}</td>
      <td class="col-status"><select data-field="status_id" ${statusEditable ? '' : 'disabled'}>${optionsHtml(state.statuses, item.status_id)}</select></td>
      <td class="col-assignee"><select data-field="assigned_user_id" ${assignEditable ? '' : 'disabled'}>${memberOptionsHtml(item)}</select></td>
      <td class="col-docs"><button class="link-btn detail-btn" data-tab="docs">Docs</button></td>
      <td class="col-notes"><button class="link-btn detail-btn" data-tab="notes">Notes</button></td>
      <td class="col-actions">${showDelete ? '<button class="icon-btn delete-item-btn" title="Delete">✕</button>' : ''}</td>
    `;

    bindRowEvents(tr, item);
    bindUrlCell(tr, item);
    autoResizeTextarea(tr.querySelector('textarea[data-field="item_text"]'));

    for (const [field, cfg] of Object.entries(FIELD_COLOR_CONFIG)) {
      const sel = tr.querySelector(`select[data-field="${field}"]`);
      paintColorSelect(sel, state[cfg.list], sel.value, cfg.mode);
      bindColoredSelectDropdown(sel);
    }
    return tr;
  }

  function paintColorSelect(selectEl, list, selectedId, mode) {
    if (!selectEl) return;
    const cell = selectEl.closest('td');
    const opt = list.find((o) => String(o.id) === String(selectedId));
    const bg = (opt && opt.bg_color) || '';
    const text = (opt && opt.text_color) || '';

    selectEl.style.backgroundColor = '';
    selectEl.style.color = '';
    selectEl.classList.remove('color-pill');

    if (!cell) return;

    cell.classList.remove('cell-colored', 'cell-fill', 'select-menu-open');
    if (bg || text) {
      cell.classList.add('cell-colored');
      if (mode === 'fill-cell') cell.classList.add('cell-fill');
      cell.style.setProperty('--cell-bg', bg || 'transparent');
      cell.style.setProperty('--cell-text', text || 'inherit');
    } else {
      cell.style.removeProperty('--cell-bg');
      cell.style.removeProperty('--cell-text');
    }
  }

  function bindColoredSelectDropdown(selectEl) {
    if (!selectEl || selectEl.dataset.dropdownNeutral) return;
    selectEl.dataset.dropdownNeutral = '1';

    const repaint = () => {
      const cfg = FIELD_COLOR_CONFIG[selectEl.dataset.field];
      if (cfg) paintColorSelect(selectEl, state[cfg.list], selectEl.value, cfg.mode);
    };

    const openMenu = () => {
      selectEl.closest('td')?.classList.add('select-menu-open');
    };
    const closeMenu = () => {
      selectEl.closest('td')?.classList.remove('select-menu-open');
      repaint();
    };

    selectEl.addEventListener('mousedown', openMenu);
    selectEl.addEventListener('focus', openMenu);
    selectEl.addEventListener('change', closeMenu);
    selectEl.addEventListener('blur', closeMenu);
  }

  function autoResizeTextarea(ta) {
    if (!ta) return;
    const resize = () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
    ta.addEventListener('input', resize);
    resize();
  }

  function normalizeExternalUrl(url) {
    const trimmed = String(url ?? '').trim();
    if (!trimmed) return '';
    return /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
  }

  function syncUrlOpenBtn(urlInput) {
    const btn = urlInput?.closest('.url-cell')?.querySelector('.url-open-btn');
    if (!btn) return;
    btn.classList.toggle('hidden', !String(urlInput.value ?? '').trim());
  }

  function bindUrlCell(tr, item) {
    const urlInput = tr.querySelector('input[data-field="url"]');
    if (!urlInput) return;

    syncUrlOpenBtn(urlInput);
    urlInput.addEventListener('input', () => syncUrlOpenBtn(urlInput));

    tr.querySelector('.url-open-btn')?.addEventListener('click', (e) => {
      e.preventDefault();
      const href = normalizeExternalUrl(urlInput.value);
      if (!href) return;
      const projectSelect = tr.querySelector('select[data-field="project_id"]');
      const project = findProject(projectSelect?.value || item.project_id || state.currentProjectId);
      window.open(href, projectSlug(project) || 'daybook');
    });
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

    tr.querySelector('.delete-item-btn')?.addEventListener('click', async () => {
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
    document.getElementById('project-strip-toggle').addEventListener('click', (e) => {
      e.stopPropagation();
      toggleProjectStripMenu();
    });
    document.getElementById('project-strip-menu').addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-project-id]');
      if (!btn) return;
      closeProjectStripMenu();
      await switchProject(btn.dataset.projectId);
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('#project-strip')) closeProjectStripMenu();
      if (!e.target.closest('#user-menu')) closeUserMenu();
      if (!e.target.closest('#filterbar-config')) closeFilterConfigMenu();
    });

    const userMenuToggle = document.getElementById('user-menu-toggle');
    if (userMenuToggle) {
      userMenuToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleUserMenu();
      });
    }
    const userMenuProjects = document.getElementById('user-menu-projects');
    if (userMenuProjects) {
      userMenuProjects.addEventListener('click', () => {
        closeUserMenu();
        openOptionModal('projects');
      });
    }

    window.addEventListener('popstate', async () => {
      const match = window.location.pathname.match(/^\/projects\/([a-z0-9-]+)\/?$/);
      const slug = match ? match[1] : '';
      const project = (slug && state.projects.find((p) => projectSlug(p) === slug)) || state.projects[0];
      if (!project || sameId(project.id, state.currentProjectId)) return;
      await switchProject(project.id);
    });

    document.getElementById('priority-sort-toggle').addEventListener('change', async (e) => {
      state.orderBy = e.target.checked ? 'priority' : 'sort_order';
      await loadItems();
    });

    document.getElementById('show-completed-toggle').addEventListener('change', async (e) => {
      state.showCompleted = e.target.checked;
      await loadItems();
    });

    document.getElementById('filter-config-toggle').addEventListener('click', (e) => {
      e.stopPropagation();
      toggleFilterConfigMenu();
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

    document.getElementById('manage-categories-btn').addEventListener('click', () => {
      closeFilterConfigMenu();
      openOptionModal('categories');
    });
    document.getElementById('manage-subsystems-btn').addEventListener('click', () => {
      closeFilterConfigMenu();
      openOptionModal('subsystems');
    });
    document.getElementById('manage-priorities-btn').addEventListener('click', () => {
      closeFilterConfigMenu();
      openOptionModal('priorities');
    });
    document.getElementById('manage-statuses-btn').addEventListener('click', () => {
      closeFilterConfigMenu();
      openOptionModal('statuses');
    });
    document.getElementById('manage-members-btn').addEventListener('click', () => {
      closeFilterConfigMenu();
      openMembersModal();
    });
    document.getElementById('export-btn').addEventListener('click', () => {
      window.location.href = `/api/export?project_id=${state.currentProjectId}`;
    });
    document.getElementById('export-tasklist-btn').addEventListener('click', copyTaskListExport);

    document.getElementById('members-modal-close').addEventListener('click', closeMembersModal);
    document.getElementById('invite-form').addEventListener('submit', submitInvite);
    document.getElementById('copy-invite-link').addEventListener('click', copyInviteLink);

    document.getElementById('option-modal-close').addEventListener('click', closeOptionModal);
    document.getElementById('option-modal-sync-palette').addEventListener('click', async () => {
      if (!confirm('Apply the current priority palette to all priorities in this project? Custom colors will be overwritten.')) return;
      try {
        await patch('/api/priorities', { action: 'sync_palette', project_id: state.currentProjectId });
        await refreshOptionList('priorities');
        renderOptionModalList();
        await loadItems();
        toast('Priority colors updated');
      } catch (err) {
        toast(err.message, true);
      }
    });
    document.getElementById('detail-modal-close').addEventListener('click', closeDetailModal);
  }

  // ---------- option list modals (categories / subsystems / priorities / statuses / projects) ----------

  const OPTION_CONFIG = {
    categories: { endpoint: '/api/categories', title: 'Categories', projectScoped: true, hasColor: false },
    subsystems: { endpoint: '/api/subsystems', title: 'Subsystems', projectScoped: true, hasColor: true },
    priorities: { endpoint: '/api/priorities', title: 'Priorities', projectScoped: true, hasColor: true },
    statuses: { endpoint: '/api/statuses', title: 'Manage Statuses', projectScoped: false, hasColor: true },
    projects: { endpoint: '/api/projects', title: 'Projects', projectScoped: false, hasColor: true },
  };
  let currentOptionType = null;

  function openOptionModal(type) {
    currentOptionType = type;
    const cfg = OPTION_CONFIG[type];
    document.getElementById('option-modal-title').textContent = cfg.title;
    const addForm = document.getElementById('option-modal-add-form');
    const canAdd = type === 'projects'
      ? (state.me?.is_daybookstaff || state.caps?.role === 'admin' || !state.projects.length)
      : type === 'statuses'
        ? !!state.me?.is_daybookstaff
        : !!state.caps?.can_edit_metadata;
    addForm.classList.toggle('hidden', !canAdd);
    const paletteRow = document.getElementById('option-modal-palette-row');
    if (paletteRow) {
      paletteRow.classList.toggle('hidden', type !== 'priorities' || !state.caps?.can_edit_metadata);
    }
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
    if (type === 'projects') renderProjectStrip();
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
        <input type="text" class="option-name-input" value="${escapeHtml(opt.name)}" ${canEditOptionRow(opt) ? '' : 'disabled'}>
        ${cfg.hasColor ? `
          <button type="button" class="icon-btn color-btn bucket-btn" title="Background color" style="background:${opt.bg_color || 'transparent'}" ${canEditOptionRow(opt) ? '' : 'disabled'}><i class="fa-solid fa-fill-drip"></i></button>
          <button type="button" class="icon-btn color-btn text-btn" title="Text color" style="color:${opt.text_color || 'inherit'}" ${canEditOptionRow(opt) ? '' : 'disabled'}><i class="fa-solid fa-font"></i></button>
        ` : ''}
        ${canDeleteOptionRow(opt) ? '<button class="icon-btn delete-option-btn" title="Delete">✕</button>' : ''}
      `;
      li.querySelector('.option-name-input').addEventListener('change', async (e) => {
        try {
          const updated = await put(cfg.endpoint, { id: opt.id, name: e.target.value.trim() });
          if (currentOptionType === 'projects' && updated && updated.slug) {
            const idx = state.projects.findIndex((p) => sameId(p.id, opt.id));
            if (idx !== -1) state.projects[idx] = { ...state.projects[idx], ...updated };
            if (sameId(state.currentProjectId, opt.id)) navigateToProject(updated, { replace: true });
          }
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
      li.querySelector('.delete-option-btn')?.addEventListener('click', async () => {
        const extra = currentOptionType === 'projects' ? ' and all its items' : '';
        if (!confirm(`Delete "${opt.name}"${extra}?`)) return;
        try {
          await del(`${cfg.endpoint}?id=${opt.id}`);
          if (currentOptionType === 'projects') {
            state.projects = state.projects.filter((p) => p.id !== opt.id);
            if (sameId(state.currentProjectId, opt.id)) {
              navigateToProject(state.projects[0], { reload: true });
            }
            renderProjectStrip();
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

  function canEditOptionRow(opt) {
    if (currentOptionType === 'statuses') return !!state.me?.is_daybookstaff;
    if (currentOptionType === 'projects') return !!state.caps?.can_edit_project || !!state.me?.is_daybookstaff;
    return !!state.caps?.can_edit_metadata;
  }

  function canDeleteOptionRow(opt) {
    if (currentOptionType === 'projects') {
      if (state.me?.is_daybookstaff) return true;
      return !!opt.is_owner && state.caps?.role === 'admin';
    }
    return canEditOptionRow(opt);
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

  // ---------- members modal ----------

  async function openMembersModal() {
    document.getElementById('members-modal').classList.remove('hidden');
    document.getElementById('invite-link-row').classList.add('hidden');
    await renderMembersModal();
  }

  function closeMembersModal() {
    document.getElementById('members-modal').classList.add('hidden');
  }

  async function renderMembersModal() {
    const [members, invites] = await Promise.all([
      get(`/api/members?project_id=${state.currentProjectId}`),
      get(`/api/invites?project_id=${state.currentProjectId}`),
    ]);
    state.projectMembers = members;
    await loadProjectAssignees();
    renderItems();

    const ul = document.getElementById('members-list');
    ul.innerHTML = '';
    for (const m of members) {
      const li = document.createElement('li');
      li.className = 'member-row';
      const roleSelect = ['admin', 'manager', 'contributor', 'viewer'].map((r) =>
        `<option value="${r}" ${m.role === r ? 'selected' : ''}>${r}</option>`
      ).join('');
      li.innerHTML = `
        <span>${escapeHtml(membersModalLabel(m))}${m.is_owner ? ' (owner)' : ''}</span>
        <select class="member-role-select" data-member-id="${m.id}" ${m.is_owner ? 'disabled' : ''}>${roleSelect}</select>
        ${!m.is_owner ? `<button type="button" class="link-btn remove-member-btn" data-member-id="${m.id}">Remove</button>` : ''}
        ${m.is_owner ? `<button type="button" class="link-btn transfer-owner-btn" data-user-id="${m.user_id}">Transfer ownership</button>` : ''}
      `;
      li.querySelector('.member-role-select')?.addEventListener('change', async (e) => {
        try {
          await put('/api/members', { id: Number(e.target.dataset.memberId), role: e.target.value });
          toast('Role updated');
        } catch (err) {
          toast(err.message, true);
        }
      });
      li.querySelector('.remove-member-btn')?.addEventListener('click', async () => {
        if (!confirm(`Remove ${membersModalLabel(m)} from this project?`)) return;
        try {
          await del(`/api/members?id=${m.id}`);
          await renderMembersModal();
        } catch (err) {
          toast(err.message, true);
        }
      });
      li.querySelector('.transfer-owner-btn')?.addEventListener('click', async () => {
        const target = members.find((x) => !x.is_owner && x.user_id !== m.user_id);
        if (!target) {
          toast('Add another member before transferring ownership', true);
          return;
        }
        const pick = prompt(`Transfer ownership to which user id? Other members:\n${
          members.filter((x) => !x.is_owner).map((x) => `${x.user_id}: ${membersModalLabel(x)}`).join('\n')
        }`);
        if (!pick) return;
        try {
          await patch('/api/members', { project_id: state.currentProjectId, new_owner_user_id: Number(pick) });
          toast('Ownership transferred');
          await renderMembersModal();
          await loadMeAndCaps();
        } catch (err) {
          toast(err.message, true);
        }
      });
      ul.appendChild(li);
    }

    const invUl = document.getElementById('invites-list');
    invUl.innerHTML = '';
    for (const inv of invites) {
      const li = document.createElement('li');
      li.innerHTML = `
        <span>${escapeHtml(inv.email)} (${inv.role})</span>
        <button type="button" class="link-btn copy-pending-invite" data-url="${escapeHtml(inv.invite_url)}">Copy link</button>
        <button type="button" class="link-btn revoke-invite-btn" data-id="${inv.id}">Revoke</button>
      `;
      li.querySelector('.copy-pending-invite').addEventListener('click', async () => {
        await navigator.clipboard.writeText(inv.invite_url);
        toast('Invite link copied');
      });
      li.querySelector('.revoke-invite-btn').addEventListener('click', async () => {
        try {
          await del(`/api/invites?id=${inv.id}`);
          await renderMembersModal();
        } catch (err) {
          toast(err.message, true);
        }
      });
      invUl.appendChild(li);
    }
  }

  async function submitInvite(e) {
    e.preventDefault();
    const email = document.getElementById('invite-email').value.trim();
    const role = document.getElementById('invite-role').value;
    try {
      const created = await post('/api/invites', {
        project_id: state.currentProjectId,
        email,
        role,
      });
      document.getElementById('invite-link-url').value = created.invite_url;
      document.getElementById('invite-link-row').classList.remove('hidden');
      document.getElementById('invite-email').value = '';
      await renderMembersModal();
      toast('Invite created — copy the link');
    } catch (err) {
      toast(err.message, true);
    }
  }

  async function copyInviteLink() {
    const url = document.getElementById('invite-link-url').value;
    if (!url) return;
    await navigator.clipboard.writeText(url);
    toast('Invite link copied');
  }

  document.addEventListener('DOMContentLoaded', init);
})();

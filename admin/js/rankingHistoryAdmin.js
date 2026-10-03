(() => {
  const script = document.currentScript;
  const api = script?.dataset.api;
  const token = script?.dataset.csrf || '';
  const panel = document.getElementById('rankingHistoryEditorPanel');
  const manager = document.getElementById('rankingHistoryManagerView');
  const form = document.getElementById('rankingHistoryAdminForm');
  const list = document.getElementById('rankingHistoryAdminList');
  const yearFilter = document.getElementById('rankingHistoryAdminYearFilter');
  const searchInput = document.getElementById('rankingHistoryAdminSearch');
  const chartDefaultOrganization = document.getElementById('rankingHistoryChartDefaultOrganization');
  const chartDefaultList = document.getElementById('rankingHistoryChartDefaultList');
  const chartDefaultsStatus = document.getElementById('rankingHistoryChartDefaultsStatus');
  const saveChartDefaults = document.getElementById('saveRankingHistoryChartDefaults');
  const formNotice = document.getElementById('rankingHistoryAdminNotice');
  const customFieldsHost = document.getElementById('rankingHistoryCustomFields');
  const customFieldsList = document.getElementById('rankingHistoryCustomFieldsList');
  if (!api || !panel || !manager || !form || !list || !yearFilter) return;

  let rankings = [];
  let customFieldDefinitions = {};
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

  function rankingGraphValue(organization, type) {
    return JSON.stringify([organization, type]);
  }

  function selectedRankingGraph() {
    if (!chartDefaultList.value) return null;
    try {
      const [organization, type] = JSON.parse(chartDefaultList.value);
      return typeof organization === 'string' && typeof type === 'string' ? { organization, type } : null;
    } catch (error) {
      return null;
    }
  }

  function updateChartDefaultLists(preferredOrganization = '', preferredList = '') {
    const graphs = new Map();
    for (const row of rankings) {
      if (row.organization_short_name === 'DEMO' || row.rank_value === null || row.rank_value === '' || !Number.isFinite(Number(row.rank_value))) continue;
      const organization = String(row.organization || '');
      const type = String(row.ranking_type || '');
      if (!organization || !type) continue;
      graphs.set(rankingGraphValue(organization, type), { organization, type });
    }
    const orderedGraphs = [...graphs.entries()];
    chartDefaultList.replaceChildren(
      new Option('All lists for selected organization', ''),
      ...orderedGraphs.map(([value, graph]) => new Option(`${graph.organization} · ${graph.type}`, value))
    );
    const preferredValue = preferredList ? rankingGraphValue(preferredOrganization, preferredList) : '';
    chartDefaultList.value = graphs.has(preferredValue) ? preferredValue : '';
    const selected = selectedRankingGraph();
    if (selected) chartDefaultOrganization.value = selected.organization;
  }

  function populateChartDefaults(payload) {
    const defaults = payload.chart_defaults || {};
    const organizations = [...new Set(rankings
      .filter(row => row.organization_short_name !== 'DEMO' && row.rank_value !== null && row.rank_value !== '' && Number.isFinite(Number(row.rank_value)))
      .map(row => String(row.organization || '')))]
      .filter(Boolean);
    chartDefaultOrganization.replaceChildren(new Option('All organizations', ''), ...organizations.map(name => new Option(name, name)));
    chartDefaultOrganization.value = organizations.includes(defaults.default_organization) ? defaults.default_organization : '';
    updateChartDefaultLists(defaults.default_organization || '', defaults.default_list || '');
  }

  function showList() {
    form.style.display = 'none';
    manager.style.display = 'block';
    document.getElementById('rankingHistoryEditorHeading').textContent = 'Manage Ranking History';
  }

  function showForm(isEdit) {
    manager.style.display = 'none';
    form.style.display = 'block';
    document.getElementById('rankingHistoryEditorHeading').textContent = isEdit ? 'Edit ranking' : 'Add ranking';
    document.getElementById('rankingHistoryAdminBody').focus();
  }

  function renderCustomFields(values = {}) {
    if (!customFieldsHost || !customFieldsList) return;
    const definitions = { ...customFieldDefinitions };
    for (const [key, field] of Object.entries(values)) {
      if (!Object.prototype.hasOwnProperty.call(definitions, key)) definitions[key] = field.label || key;
    }
    customFieldsList.replaceChildren();
    customFieldsList.style.display = 'grid';
    customFieldsList.style.gridTemplateColumns = 'repeat(auto-fit, minmax(190px, 1fr))';
    customFieldsList.style.gap = '1rem';
    for (const [key, label] of Object.entries(definitions)) {
      const fieldLabel = document.createElement('label');
      fieldLabel.className = 'form-label';
      fieldLabel.textContent = label;
      const input = document.createElement('textarea');
      input.className = 'form-input';
      input.rows = 2;
      input.dataset.rankingCustomField = key;
      input.value = values[key]?.value || '';
      input.setAttribute('aria-label', label);
      fieldLabel.append(input);
      customFieldsList.append(fieldLabel);
    }
  }

  function render() {
    const selectedYear = yearFilter.value || 'all';
    const years = [...new Set(rankings.map(row => String(row.year)))].sort((left, right) => Number(right) - Number(left));
    yearFilter.innerHTML = '<option value="all">All years</option>' + years.map(year => `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`).join('');
    yearFilter.value = years.includes(selectedYear) || selectedYear === 'all' ? selectedYear : 'all';
    const searchTerm = (searchInput?.value || '').trim().toLocaleLowerCase();
    const visible = rankings.filter(row => {
      if (yearFilter.value !== 'all' && String(row.year) !== yearFilter.value) return false;
      if (!searchTerm) return true;
      const searchable = [row.organization, row.ranking_type, row.year, row.global_rank, row.info_text]
        .filter(Boolean).join(' ').toLocaleLowerCase();
      return searchable.includes(searchTerm);
    });
    if (!visible.length) {
      list.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">${searchTerm ? 'No rankings match your search and filters.' : 'No ranking rows found.'}</div>`;
      bindBulkDelete(0);
      return;
    }
    list.innerHTML = visible.map(row => {
      const isPublished = Number(row.is_published ?? 1) !== 0;
      const badgeClass = isPublished
        ? 'inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-bold uppercase'
        : 'inline-flex items-center rounded-full bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 text-[10px] font-bold uppercase';
      return `
      <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800">
        <div class="flex items-start gap-3">
          <input type="checkbox" class="bulk-delete-checkbox mt-1 cursor-pointer" data-id="${Number(row.id)}" aria-label="Select for bulk delete" style="width:1rem;height:1rem;">
          <div class="flex-1">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-bold text-sm text-slate-900 dark:text-slate-100">${escapeHtml(row.organization)}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">${escapeHtml(row.ranking_type || '')} · Year: ${escapeHtml(row.year)}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Rank: ${escapeHtml(row.global_rank || '—')}</div>
                <div class="mt-1.5 flex flex-wrap gap-1.5 items-center">
                  <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-semibold">${escapeHtml(row.ranking_type || row.organization)}</span>
                  <span class="${badgeClass}">${isPublished ? 'Published' : 'Unpublished'}</span>
                </div>
                ${row.info_text ? `<div class="mt-2 text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-gray-700/60 p-2 rounded-md border border-slate-200 dark:border-gray-600">ⓘ ${escapeHtml(row.info_text)}</div>` : ''}
              </div>
            </div>
            <div class="flex gap-2 mt-3 flex-wrap">
              <button type="button" class="btn-studio-action" data-edit-ranking="${Number(row.id)}">Edit</button>
              <button type="button" class="btn-studio-action" data-history-ranking="${Number(row.id)}">History</button>
              <button type="button" class="btn-studio-action" data-toggle-ranking="${Number(row.id)}" data-published="${isPublished ? '1' : '0'}">${isPublished ? 'Unpublish' : 'Publish'}</button>
              <button type="button" class="archive-delete-button" data-delete-ranking="${Number(row.id)}">Delete</button>
            </div>
          </div>
        </div>
      </div>
    `;
    }).join('');
    bindBulkDelete(visible.length);
    list.querySelectorAll('[data-edit-ranking]').forEach(button => button.addEventListener('click', () => {
      const row = rankings.find(item => String(item.id) === button.dataset.editRanking);
      if (row) loadRankingIntoForm(row);
    }));
    list.querySelectorAll('[data-history-ranking]').forEach(button => button.addEventListener('click', () => {
      const row = rankings.find(item => String(item.id) === button.dataset.historyRanking);
      if (!row) return;
      const related = rankings.filter(item => item.organization === row.organization && item.ranking_type === row.ranking_type).sort((a, b) => Number(a.year) - Number(b.year));
      const existing = document.getElementById('rankingHistoryPopover');
      if (existing) existing.remove();
      const popover = document.createElement('div');
      popover.id = 'rankingHistoryPopover';
      popover.setAttribute('role', 'dialog');
      popover.setAttribute('aria-modal', 'true');
      popover.setAttribute('aria-label', `History for ${row.organization} ${row.ranking_type}`);
      popover.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:1rem;';
      const inner = document.createElement('div');
      inner.style.cssText = 'background:#fff;border-radius:12px;padding:1.5rem;max-width:520px;width:100%;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(15,23,42,.25);';
      inner.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
          <div>
            <h3 style="margin:0;color:#146C36;font-size:1.05rem;font-weight:800;">${escapeHtml(row.organization)}</h3>
            <p style="margin:.25rem 0 0;font-size:.8rem;color:#64748B;">${escapeHtml(row.ranking_type || '')}</p>
          </div>
          <button type="button" data-close-history style="background:none;border:none;cursor:pointer;font-size:1.25rem;color:#64748B;line-height:1;" aria-label="Close">&times;</button>
        </div>
        <div style="display:grid;gap:.5rem;">
          ${related.map(entry => {
            const isCurrent = String(entry.id) === button.dataset.historyRanking;
            const published = Number(entry.is_published ?? 1) !== 0;
            return `<div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .75rem;border-radius:8px;border:1px solid ${isCurrent ? '#146C36' : '#E2E8F0'};background:${isCurrent ? '#F0FDF4' : '#F8FAFC'};">
              <div>
                <span style="font-weight:700;font-size:.88rem;color:${isCurrent ? '#146C36' : '#0F172A'};">${escapeHtml(entry.year)}</span>
                <span style="margin-left:.5rem;font-size:.82rem;color:#64748B;">Rank: ${escapeHtml(entry.global_rank || '—')}</span>
              </div>
              <span style="font-size:.72rem;font-weight:700;text-transform:uppercase;padding:.2rem .5rem;border-radius:999px;background:${published ? '#D1FAE5' : '#E2E8F0'};color:${published ? '#065F46' : '#475569'};">${published ? 'Published' : 'Unpublished'}</span>
            </div>`;
          }).join('')}
        </div>`;
      popover.appendChild(inner);
      document.body.appendChild(popover);
      inner.querySelector('[data-close-history]').onclick = () => popover.remove();
      popover.addEventListener('click', e => { if (e.target === popover) popover.remove(); });
    }));
    list.querySelectorAll('[data-toggle-ranking]').forEach(button => button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const response = await fetch(`${api}?id=${encodeURIComponent(button.dataset.toggleRanking)}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
          body: JSON.stringify({ action: 'toggle-published' })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'Unable to update ranking.');
        await refresh();
      } catch (error) {
        window.alert(error.message || 'Unable to update ranking.');
        button.disabled = false;
      }
    }));
    list.querySelectorAll('[data-delete-ranking]').forEach(button => button.addEventListener('click', async () => {
      const row = rankings.find(item => String(item.id) === button.dataset.deleteRanking);
      if (!window.confirm(`Delete ${row?.organization || 'this'} ${row?.year || ''} ranking row?`)) return;
      button.disabled = true;
      try {
        const response = await fetch(`${api}?id=${encodeURIComponent(button.dataset.deleteRanking)}`, { method: 'DELETE', headers: { 'X-CSRF-Token': token, Accept: 'application/json' } });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'Unable to delete ranking.');
        await refresh();
      } catch (error) {
        window.alert(error.message || 'Unable to delete ranking.');
        button.disabled = false;
      }
    }));
  }

  function bindBulkDelete(visibleCount) {
    const selectAll = document.getElementById('selectAllRanking');
    const selectAllLabel = document.getElementById('selectAllRankingLabel');
    if (selectAllLabel) selectAllLabel.style.display = visibleCount ? 'inline-flex' : 'none';
    if (selectAll) {
      selectAll.checked = false;
      selectAll.onchange = () => {
        list.querySelectorAll('.bulk-delete-checkbox').forEach(checkbox => { checkbox.checked = selectAll.checked; });
        updateBulkButton();
      };
    }
    list.querySelectorAll('.bulk-delete-checkbox').forEach(checkbox => checkbox.addEventListener('change', updateBulkButton));
    const oldButton = document.getElementById('bulkDeleteRankingHistory');
    if (!oldButton) return;
    const button = oldButton.cloneNode(true);
    oldButton.replaceWith(button);
    button.addEventListener('click', async () => {
      const ids = [...list.querySelectorAll('.bulk-delete-checkbox:checked')].map(checkbox => checkbox.dataset.id);
      if (!ids.length || !window.confirm(`Delete ${ids.length} selected ranking history row(s)?`)) return;
      button.disabled = true;
      try {
        let failed = 0;
        for (const id of ids) {
          const response = await fetch(`${api}?id=${encodeURIComponent(id)}`, { method: 'DELETE', headers: { 'X-CSRF-Token': token, Accept: 'application/json' } });
          if (!response.ok) failed += 1;
        }
        if (failed) window.alert(`${failed} of ${ids.length} ranking history row(s) could not be deleted.`);
        await refresh();
      } catch (error) {
        window.alert(error.message || 'Unable to delete selected rankings.');
      } finally {
        button.disabled = false;
      }
    });
    updateBulkButton();
  }

  function updateBulkButton() {
    const button = document.getElementById('bulkDeleteRankingHistory');
    const count = list.querySelectorAll('.bulk-delete-checkbox:checked').length;
    if (button) {
      button.style.display = count ? 'inline-flex' : 'none';
      button.disabled = count === 0;
    }
    const countLabel = document.getElementById('bulkDeleteCount');
    if (countLabel) countLabel.textContent = String(count);
    const selectAll = document.getElementById('selectAllRanking');
    const checkboxes = [...list.querySelectorAll('.bulk-delete-checkbox')];
    if (selectAll) selectAll.checked = checkboxes.length > 0 && checkboxes.every(checkbox => checkbox.checked);
  }

  function loadRankingIntoForm(row) {
    document.getElementById('rankingHistoryAdminId').value = row.id;
    document.getElementById('rankingHistoryAdminBody').value = row.organization || '';
    document.getElementById('rankingHistoryAdminType').value = row.ranking_type || '';
    document.getElementById('rankingHistoryAdminYear').value = row.year;
    document.getElementById('rankingHistoryAdminGlobalRank').value = row.global_rank || '';
    document.getElementById('rankingHistoryAdminInfoText').value = row.info_text || '';
    renderCustomFields(row.custom_fields || {});
    document.getElementById('formDuplicateError').style.display = 'none';
    showForm(true);
  }

  async function refresh() {
    const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const payload = await response.json().catch(() => null);
    if (!response.ok) throw new Error(payload?.error || `Unable to load rankings (HTTP ${response.status}).`);
    if (!payload || !Array.isArray(payload.rankings)) throw new Error('The rankings response is invalid.');
    rankings = payload.rankings;
    customFieldDefinitions = payload.custom_field_definitions && typeof payload.custom_field_definitions === 'object'
      ? payload.custom_field_definitions
      : {};
    populateChartDefaults(payload);
    const bodies = payload.bodies || [];
    const bodyDatalist = document.getElementById('rankingBodySuggestions');
    if (bodyDatalist) bodyDatalist.innerHTML = [...new Set(bodies.map(body => body.name))].map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
    const typeDatalist = document.getElementById('rankingTypeSuggestions');
    if (typeDatalist) typeDatalist.innerHTML = [...new Set(rankings.map(row => row.ranking_type).filter(Boolean))].map(type => `<option value="${escapeHtml(type)}"></option>`).join('');
    render();
  }

  document.getElementById('toggleRankingHistoryEditor')?.addEventListener('click', () => {
    showList();
    panel.classList.add('active');
    panel.setAttribute('aria-hidden', 'false');
    document.getElementById('addRankingHistoryRow').focus();
  });
  document.getElementById('addRankingHistoryRow')?.addEventListener('click', async () => {
    const addButton = document.getElementById('addRankingHistoryRow');
    addButton.disabled = true;
    try {
      await refresh();
    } catch (error) {
      window.alert(error.message || 'Unable to refresh the configured template fields.');
      return;
    } finally {
      addButton.disabled = false;
    }
    form.reset();
    document.getElementById('rankingHistoryAdminId').value = '';
    document.getElementById('rankingHistoryAdminYear').value = new Date().getFullYear();
    renderCustomFields();
    document.getElementById('formDuplicateError').style.display = 'none';
    showForm(false);
  });
  yearFilter.addEventListener('change', render);
  searchInput?.addEventListener('input', render);
  chartDefaultOrganization.addEventListener('change', () => {
    const selected = selectedRankingGraph();
    if (selected && selected.organization !== chartDefaultOrganization.value) chartDefaultList.value = '';
  });
  chartDefaultList.addEventListener('change', () => {
    const selected = selectedRankingGraph();
    if (selected) chartDefaultOrganization.value = selected.organization;
  });
  saveChartDefaults.addEventListener('click', async () => {
    saveChartDefaults.disabled = true;
    chartDefaultsStatus.textContent = 'Saving…';
    chartDefaultsStatus.className = 'text-xs text-gray-500';
    try {
      const response = await fetch(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        body: JSON.stringify({
          action: 'save-chart-defaults',
          default_organization: selectedRankingGraph()?.organization || chartDefaultOrganization.value,
          default_list: selectedRankingGraph()?.type || ''
        })
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'Unable to save chart defaults.');
      const savedOrganization = result.chart_defaults?.default_organization || 'All organizations';
      const savedList = result.chart_defaults?.default_list || 'All lists';
      chartDefaultsStatus.textContent = `Saved: ${savedOrganization} · ${savedList}. Public users can change these filters.`;
      chartDefaultsStatus.className = 'text-xs font-semibold text-emerald-700 dark:text-emerald-300';
    } catch (error) {
      chartDefaultsStatus.textContent = error.message || 'Unable to save chart defaults.';
      chartDefaultsStatus.className = 'text-xs font-semibold text-red-700 dark:text-red-300';
    } finally {
      saveChartDefaults.disabled = false;
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton?.disabled) return;
    if (submitButton) submitButton.disabled = true;
    if (formNotice) {
      formNotice.hidden = true;
      formNotice.textContent = '';
    }
    const id = document.getElementById('rankingHistoryAdminId').value;
    const payload = {
      organization: document.getElementById('rankingHistoryAdminBody').value.trim(),
      ranking_type: document.getElementById('rankingHistoryAdminType').value.trim(),
      year: Number(document.getElementById('rankingHistoryAdminYear').value),
      global_rank: document.getElementById('rankingHistoryAdminGlobalRank').value.trim(),
      info_text: document.getElementById('rankingHistoryAdminInfoText').value.trim(),
      custom_fields: Object.fromEntries([...form.querySelectorAll('[data-ranking-custom-field]')]
        .map(input => [input.dataset.rankingCustomField, input.value]))
    };
    try {
      const response = await fetch(`${api}${id ? `?id=${encodeURIComponent(id)}` : ''}`, {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (response.status === 409 && result.duplicate_id) {
          document.getElementById('editDuplicateLink').onclick = () => {
            const duplicate = rankings.find(row => Number(row.id) === Number(result.duplicate_id));
            if (duplicate) loadRankingIntoForm(duplicate);
          };
          document.getElementById('formDuplicateError').style.display = 'block';
          return;
        }
        throw new Error(result.error || 'Unable to save ranking.');
      }
      form.reset();
      showList();
      await refresh();
    } catch (error) {
      if (formNotice) {
        formNotice.textContent = error.message || 'Unable to save ranking.';
        formNotice.hidden = false;
      } else window.alert(error.message || 'Unable to save ranking.');
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
  document.getElementById('cancelRankingHistoryAdminForm')?.addEventListener('click', () => { form.reset(); showList(); });
  const close = () => { panel.classList.remove('active'); panel.setAttribute('aria-hidden', 'true'); };
  document.getElementById('closeRankingHistoryEditor')?.addEventListener('click', close);
  panel.addEventListener('click', event => { if (event.target === panel) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel.classList.contains('active')) close(); });
  refresh().catch(error => {
    console.error('Unable to load ranking history:', error);
    list.innerHTML = `<div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-6 text-center text-sm text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300">${escapeHtml(error.message || 'Unable to load ranking history.')}</div>`;
    bindBulkDelete(0);
  });
})();
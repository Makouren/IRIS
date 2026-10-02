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
  if (!api || !panel || !manager || !form || !list || !yearFilter) return;

  let rankings = [];
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
    list.innerHTML = visible.map(row => `<article class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800"><div class="flex items-start gap-3"><input type="checkbox" class="bulk-delete-checkbox mt-1 cursor-pointer" data-id="${Number(row.id)}" aria-label="Select for bulk delete" style="width:1rem;height:1rem;"><div class="flex-1 flex flex-wrap items-start justify-between gap-3"><div><div class="font-bold text-sm text-slate-900">${escapeHtml(row.organization)}</div><div class="text-xs text-slate-600">${escapeHtml(row.ranking_type)} · ${escapeHtml(row.year)} · Rank: ${escapeHtml(row.global_rank || '—')}</div>${row.info_text ? `<div class="mt-1 text-xs text-slate-600 dark:text-slate-300">${escapeHtml(row.info_text)}</div>` : ''}</div><div class="flex gap-2"><button type="button" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600" data-edit-ranking="${Number(row.id)}">Edit</button><button type="button" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" data-delete-ranking="${Number(row.id)}">Delete</button></div></div></div></article>`).join('');
    bindBulkDelete(visible.length);
    list.querySelectorAll('[data-edit-ranking]').forEach(button => button.addEventListener('click', () => {
      const row = rankings.find(item => String(item.id) === button.dataset.editRanking);
      if (row) loadRankingIntoForm(row);
    }));
    list.querySelectorAll('[data-delete-ranking]').forEach(button => button.addEventListener('click', async () => {
      const row = rankings.find(item => String(item.id) === button.dataset.deleteRanking);
      if (!window.confirm(`Delete ${row?.organization || 'this'} ${row?.year || ''} ranking row?`)) return;
      const response = await fetch(`${api}?id=${encodeURIComponent(button.dataset.deleteRanking)}`, { method: 'DELETE', headers: { 'X-CSRF-Token': token, Accept: 'application/json' } });
      if (!response.ok) { window.alert('Unable to delete ranking.'); return; }
      await refresh();
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
      for (const id of ids) {
        const response = await fetch(`${api}?id=${encodeURIComponent(id)}`, { method: 'DELETE', headers: { 'X-CSRF-Token': token, Accept: 'application/json' } });
        if (!response.ok) window.alert('Some ranking history rows could not be deleted.');
      }
      await refresh();
    });
    updateBulkButton();
  }

  function updateBulkButton() {
    const button = document.getElementById('bulkDeleteRankingHistory');
    const count = list.querySelectorAll('.bulk-delete-checkbox:checked').length;
    if (button) button.style.display = count ? 'inline-flex' : 'none';
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
    document.getElementById('formDuplicateError').style.display = 'none';
    showForm(true);
  }

  async function refresh() {
    const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) return;
    const payload = await response.json();
    rankings = payload.rankings || [];
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
  document.getElementById('addRankingHistoryRow')?.addEventListener('click', () => {
    form.reset();
    document.getElementById('rankingHistoryAdminId').value = '';
    document.getElementById('rankingHistoryAdminYear').value = new Date().getFullYear();
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
    const id = document.getElementById('rankingHistoryAdminId').value;
    const payload = {
      organization: document.getElementById('rankingHistoryAdminBody').value.trim(),
      ranking_type: document.getElementById('rankingHistoryAdminType').value.trim(),
      year: Number(document.getElementById('rankingHistoryAdminYear').value),
      global_rank: document.getElementById('rankingHistoryAdminGlobalRank').value.trim(),
      info_text: document.getElementById('rankingHistoryAdminInfoText').value.trim()
    };
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
      } else window.alert(result.error || 'Unable to save ranking.');
      return;
    }
    form.reset();
    showList();
    await refresh();
  });
  document.getElementById('cancelRankingHistoryAdminForm')?.addEventListener('click', () => { form.reset(); showList(); });
  const close = () => { panel.classList.remove('active'); panel.setAttribute('aria-hidden', 'true'); };
  document.getElementById('closeRankingHistoryEditor')?.addEventListener('click', close);
  panel.addEventListener('click', event => { if (event.target === panel) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel.classList.contains('active')) close(); });
  refresh();
})();
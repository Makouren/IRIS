        (function () {
            if (false) {
            const apiUrl = window.IRIS_REVIEW_EDITOR_CONFIG.rankingApiUrl;
            const csrfToken = window.IRIS_REVIEW_EDITOR_CONFIG.csrfToken;
            const panel = document.getElementById('rankingHistoryEditorPanel');
            const manager = document.getElementById('rankingHistoryManagerView');
            const form = document.getElementById('rankingHistoryAdminForm');
            const list = document.getElementById('rankingHistoryAdminList');
            const bodySelect = document.getElementById('rankingHistoryAdminBody');
            const scopeSelect = document.getElementById('rankingHistoryAdminScope');
            const yearFilter = document.getElementById('rankingHistoryAdminYearFilter');
            const scopeFilter = document.getElementById('rankingHistoryAdminScopeFilter');
            const levelFilter = document.getElementById('rankingHistoryAdminLevelFilter');
            const scopeList = document.getElementById('rankingScopeAdminList');
            const levelList = document.getElementById('rankingLevelAdminList');
            const scopeApiUrl = apiUrl + '?resource=scopes';
            const levelApiUrl = apiUrl + '?resource=levels';
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            let rankings = [];
            let bodies = [];
            let scopes = [];
            let levels = [];

            const scopesModal = document.getElementById('manageScopesModalPanel');
            const levelsModal = document.getElementById('manageLevelsModalPanel');

            document.getElementById('openManageScopesModal')?.addEventListener('click', () => {
                if (scopesModal) { scopesModal.classList.add('active'); scopesModal.setAttribute('aria-hidden', 'false'); }
            });
            document.getElementById('closeManageScopesModal')?.addEventListener('click', () => {
                if (scopesModal) { scopesModal.classList.remove('active'); scopesModal.setAttribute('aria-hidden', 'true'); }
            });
            document.getElementById('openManageLevelsModal')?.addEventListener('click', () => {
                if (levelsModal) { levelsModal.classList.add('active'); levelsModal.setAttribute('aria-hidden', 'false'); }
            });
            document.getElementById('closeManageLevelsModal')?.addEventListener('click', () => {
                if (levelsModal) { levelsModal.classList.remove('active'); levelsModal.setAttribute('aria-hidden', 'true'); }
            });

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

            function renderScopes() {
                const selectAllScopes = document.getElementById('selectAllScopes');
                const selectAllScopesLabel = document.getElementById('selectAllScopesLabel');
                const bulkScopesBtn = document.getElementById('bulkDeleteScopes');
                const bulkScopesCount = document.getElementById('bulkDeleteScopesCount');

                if (selectAllScopesLabel) selectAllScopesLabel.style.display = scopes.length ? 'inline-flex' : 'none';
                if (selectAllScopes) selectAllScopes.checked = false;
                if (bulkScopesBtn) bulkScopesBtn.style.display = 'none';

                if (!scopes.length) {
                    scopeList.innerHTML = '<div class="text-xs text-gray-600 dark:text-gray-300">No ranking scopes yet. Add one below.</div>';
                    return;
                }

                scopeList.innerHTML = scopes.map(scope => `<div class="ranking-scope-row grid grid-cols-[auto_minmax(120px,1fr)_90px_auto_auto] items-center gap-2" data-id="${Number(scope.id)}"><input type="checkbox" class="bulk-delete-scope-checkbox cursor-pointer" data-id="${Number(scope.id)}" aria-label="Select scope for bulk delete" style="width:1rem;height:1rem;"><input class="form-input" data-scope-name maxlength="80" value="${escapeHtml(scope.name)}" aria-label="Scope name"><input class="form-input" data-scope-order type="number" step="1" value="${Number(scope.sort_order)}" aria-label="Scope order"><button type="button" data-save-scope class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100">Save</button><button type="button" data-delete-scope class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">Delete</button></div>`).join('');

                const updateBulkScopesBtn = () => {
                    const allCbs = Array.from(scopeList.querySelectorAll('.bulk-delete-scope-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (bulkScopesBtn) bulkScopesBtn.style.display = selected.length > 0 ? 'inline-flex' : 'none';
                    if (bulkScopesCount) bulkScopesCount.textContent = selected.length;
                    if (selectAllScopes) selectAllScopes.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                scopeList.querySelectorAll('.bulk-delete-scope-checkbox').forEach(cb => cb.addEventListener('change', updateBulkScopesBtn));

                if (selectAllScopes) {
                    selectAllScopes.onchange = () => {
                        scopeList.querySelectorAll('.bulk-delete-scope-checkbox').forEach(cb => { cb.checked = selectAllScopes.checked; });
                        updateBulkScopesBtn();
                    };
                }

                if (bulkScopesBtn) {
                    const newBulkBtn = bulkScopesBtn.cloneNode(true);
                    bulkScopesBtn.parentNode.replaceChild(newBulkBtn, bulkScopesBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(scopeList.querySelectorAll('.bulk-delete-scope-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected scope(s)? Their rankings will become unassigned.`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(scopeApiUrl + '&id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some scopes could not be deleted.');
                        await refresh();
                    });
                }

                scopeList.querySelectorAll('[data-save-scope]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-scope-row');
                    const response = await fetch(scopeApiUrl + '&id=' + encodeURIComponent(row.dataset.id), {
                        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                        body: JSON.stringify({ name: row.querySelector('[data-scope-name]').value.trim(), sort_order: Number(row.querySelector('[data-scope-order]').value || 0) })
                    });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to update scope.'); return; }
                    await refresh();
                }));
                scopeList.querySelectorAll('[data-delete-scope]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-scope-row');
                    const scope = scopes.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${scope?.name || 'this scope'}”? Its rankings will become unassigned.`)) return;
                    const response = await fetch(scopeApiUrl + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete scope.'); return; }
                    await refresh();
                }));
            }

            function renderLevels() {
                const selectAllLevels = document.getElementById('selectAllLevels');
                const selectAllLevelsLabel = document.getElementById('selectAllLevelsLabel');
                const bulkLevelsBtn = document.getElementById('bulkDeleteLevels');
                const bulkLevelsCount = document.getElementById('bulkDeleteLevelsCount');

                if (selectAllLevelsLabel) selectAllLevelsLabel.style.display = levels.length ? 'inline-flex' : 'none';
                if (selectAllLevels) selectAllLevels.checked = false;
                if (bulkLevelsBtn) bulkLevelsBtn.style.display = 'none';

                if (!levels.length) {
                    levelList.innerHTML = '<div class="text-xs text-gray-600 dark:text-gray-300">No ranking levels yet. Add one below.</div>';
                    return;
                }

                levelList.innerHTML = levels.map(level => `<div class="ranking-level-row grid grid-cols-[auto_minmax(120px,1fr)_90px_auto_auto] items-center gap-2" data-id="${Number(level.id)}"><input type="checkbox" class="bulk-delete-level-checkbox cursor-pointer" data-id="${Number(level.id)}" aria-label="Select level for bulk delete" style="width:1rem;height:1rem;"><input class="form-input" data-level-name maxlength="80" value="${escapeHtml(level.name)}" aria-label="Level name"><input class="form-input" data-level-order type="number" step="1" value="${Number(level.sort_order)}" aria-label="Level order"><button type="button" data-save-level class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100">Save</button><button type="button" data-delete-level class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">Delete</button></div>`).join('');

                const updateBulkLevelsBtn = () => {
                    const allCbs = Array.from(levelList.querySelectorAll('.bulk-delete-level-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (bulkLevelsBtn) bulkLevelsBtn.style.display = selected.length > 0 ? 'inline-flex' : 'none';
                    if (bulkLevelsCount) bulkLevelsCount.textContent = selected.length;
                    if (selectAllLevels) selectAllLevels.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                levelList.querySelectorAll('.bulk-delete-level-checkbox').forEach(cb => cb.addEventListener('change', updateBulkLevelsBtn));

                if (selectAllLevels) {
                    selectAllLevels.onchange = () => {
                        levelList.querySelectorAll('.bulk-delete-level-checkbox').forEach(cb => { cb.checked = selectAllLevels.checked; });
                        updateBulkLevelsBtn();
                    };
                }

                if (bulkLevelsBtn) {
                    const newBulkBtn = bulkLevelsBtn.cloneNode(true);
                    bulkLevelsBtn.parentNode.replaceChild(newBulkBtn, bulkLevelsBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(levelList.querySelectorAll('.bulk-delete-level-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected level(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(levelApiUrl + '&id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some levels could not be deleted.');
                        await refresh();
                    });
                }

                levelList.querySelectorAll('[data-save-level]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-level-row');
                    const response = await fetch(levelApiUrl + '&id=' + encodeURIComponent(row.dataset.id), {
                        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                        body: JSON.stringify({ name: row.querySelector('[data-level-name]').value.trim(), sort_order: Number(row.querySelector('[data-level-order]').value || 0) })
                    });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to update level.'); return; }
                    await refresh();
                }));
                levelList.querySelectorAll('[data-delete-level]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-level-row');
                    const levelItem = levels.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${levelItem?.name || 'this level'}”?`)) return;
                    const response = await fetch(levelApiUrl + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete level.'); return; }
                    await refresh();
                }));
            }

            function render() {
                const filterValue = yearFilter.value || 'all';
                const years = [...new Set(rankings.map(row => String(row.year)))].sort((a, b) => Number(b) - Number(a));
                yearFilter.innerHTML = '<option value="all">All years</option>' + years.map(year => `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`).join('');
                if ([...yearFilter.options].some(option => option.value === filterValue)) yearFilter.value = filterValue;
                const selectedScope = scopeFilter.value || 'all';
                const selectedLevel = levelFilter.value || 'all';
                const visible = rankings.filter(row => (yearFilter.value === 'all' || String(row.year) === yearFilter.value)
                    && (selectedScope === 'all' || (selectedScope === 'unassigned' ? !row.scope_id : String(row.scope_id) === selectedScope))
                    && (selectedLevel === 'all' || (selectedLevel === 'unassigned' ? !row.level : row.level === selectedLevel)));
                if (!visible.length) {
                    list.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">No ranking rows found.</div>';
                    return;
                }
                list.innerHTML = visible.map(row => `<article class="ranking-history-admin-card rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800"><div class="flex items-start gap-3"><input type="checkbox" class="bulk-delete-checkbox mt-1 cursor-pointer" data-id="${Number(row.id)}" aria-label="Select for bulk delete" style="width:1rem;height:1rem;"> <div class="flex-1 flex flex-wrap items-start justify-between gap-3"><div><div class="font-bold text-sm text-slate-900">${escapeHtml(row.ranking_type || row.body_name || row.body_short_name)}</div><div class="text-xs text-slate-600">${escapeHtml(row.year)} · ${escapeHtml(row.edition || 'Annual')} · ${escapeHtml(row.level || 'Unclassified')} · ${escapeHtml(row.scope_name || 'Unassigned')} · ${escapeHtml(row.category || 'Overall')} · Rank: ${escapeHtml(row.global_rank || '—')} · Bounds: ${escapeHtml(row.rank_low ?? '—')}–${escapeHtml(row.rank_high ?? '+')}</div><span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${row.verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : row.verification_status === 'conflicting' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'}">${escapeHtml(row.verification_status || 'unverified')}</span>${row.note ? `<div class="mt-1 text-xs text-slate-500">${escapeHtml(row.note)}</div>` : ''}${row.source ? `<div class="mt-1 break-all text-xs text-blue-700 dark:text-blue-300">${escapeHtml(row.source)}</div>` : ''}</div><div class="flex gap-2"><button type="button" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600" data-edit-ranking="${Number(row.id)}">Edit</button><button type="button" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100 dark:hover:bg-red-900/70" data-delete-ranking="${Number(row.id)}">Delete</button></div></div></div></article>`).join('');
                                                                const selectAllRanking = document.getElementById('selectAllRanking');
                const selectAllRankingLabel = document.getElementById('selectAllRankingLabel');

                if (selectAllRankingLabel) selectAllRankingLabel.style.display = visible.length ? 'inline-flex' : 'none';
                if (selectAllRanking) selectAllRanking.checked = false;

                const updateBulkBtn = () => {
                    const currentBulkBtn = document.getElementById('bulkDeleteRankingHistory');
                    const currentBulkCount = document.getElementById('bulkDeleteCount');
                    const allCbs = Array.from(list.querySelectorAll('.bulk-delete-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (currentBulkBtn) currentBulkBtn.style.display = selected.length > 0 ? 'inline-block' : 'none';
                    if (currentBulkCount) currentBulkCount.textContent = selected.length;
                    if (selectAllRanking) selectAllRanking.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                list.querySelectorAll('.bulk-delete-checkbox').forEach(cb => cb.addEventListener('change', updateBulkBtn));

                if (selectAllRanking) {
                    selectAllRanking.onchange = () => {
                        list.querySelectorAll('.bulk-delete-checkbox').forEach(cb => { cb.checked = selectAllRanking.checked; });
                        updateBulkBtn();
                    };
                }

                const bulkBtn = document.getElementById('bulkDeleteRankingHistory');
                if (bulkBtn) {
                    const newBulkBtn = bulkBtn.cloneNode(true);
                    bulkBtn.parentNode.replaceChild(newBulkBtn, bulkBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(list.querySelectorAll('.bulk-delete-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected ranking history row(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(apiUrl + '?id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some ranking history rows could not be deleted.');
                        await refresh();
                    });
                }
                updateBulkBtn();
                list.querySelectorAll('[data-edit-ranking]').forEach(button => button.addEventListener('click', () => {
                    const row = rankings.find(item => String(item.id) === button.dataset.editRanking);
                    if (row) loadRankingIntoForm(row);
                }));
                list.querySelectorAll('[data-delete-ranking]').forEach(button => button.addEventListener('click', async () => {
                    const row = rankings.find(item => String(item.id) === button.dataset.deleteRanking);
                    if (!confirm(`Delete ${row?.body_name || 'this'} ${row?.year || ''} ranking row?`)) return;
                    const response = await fetch(apiUrl + '?id=' + encodeURIComponent(button.dataset.deleteRanking), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete ranking.'); return; }
                    await refresh();
                }));
            }

            function loadRankingIntoForm(row) {
                document.getElementById('rankingHistoryAdminId').value = row.id;
                bodySelect.value = row.body_name || row.body_short_name || '';
                document.getElementById('rankingHistoryAdminType').value = row.ranking_type || '';
                document.getElementById('rankingHistoryAdminYear').value = row.year;
                rankDisplayInput.value = row.global_rank || '';
                document.getElementById('rankingHistoryAdminPhRank').value = row.ph_rank || '';
                document.getElementById('rankingHistoryAdminSource').value = row.source || '';
                const dupContainer = document.getElementById('formDuplicateError');
                if (dupContainer) dupContainer.classList.add('hidden');
                showForm(true);
            }

            async function refresh() {
                const response = await fetch(apiUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const payload = await response.json();
                rankings = payload.rankings || [];
                bodies = payload.bodies || [];
                scopes = payload.scopes || [];
                levels = payload.levels || [];

                const bodyDatalist = document.getElementById('rankingBodySuggestions');
                if (bodyDatalist) {
                    const bodyOptions = [...new Set(bodies.map(b => b.name))].filter(Boolean);
                    bodyDatalist.innerHTML = bodyOptions.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const scopeDatalist = document.getElementById('rankingScopeSuggestions');
                if (scopeDatalist) {
                    const scopeOptions = [...new Set(scopes.map(s => s.name))].filter(Boolean);
                    scopeDatalist.innerHTML = scopeOptions.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const levelDatalist = document.getElementById('rankingLevelSuggestions');
                if (levelDatalist) {
                    const dbLevels = levels.map(l => l.name);
                    const presetLevels = ['World', 'Asia', 'ASEAN', 'Local'];
                    const allLevels = [...new Set([...dbLevels, ...presetLevels, ...rankings.map(r => r.level)])].filter(Boolean);
                    levelDatalist.innerHTML = allLevels.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const typeDatalist = document.getElementById('rankingTypeSuggestions');
                if (typeDatalist) {
                    const allTypes = [...new Set(rankings.map(r => r.ranking_type))].filter(Boolean);
                    typeDatalist.innerHTML = allTypes.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const selectedScopeFilter = scopeFilter.value || 'all';
                scopeFilter.innerHTML = '<option value="all">All scopes</option><option value="unassigned">Unassigned</option>' + scopes.map(scope => `<option value="${escapeHtml(scope.name)}">${escapeHtml(scope.name)}</option>`).join('');
                if ([...scopeFilter.options].some(option => option.value === selectedScopeFilter)) scopeFilter.value = selectedScopeFilter;
                else scopeFilter.value = 'all';

                const levelOptions = [...new Set([...levels.map(l => l.name), 'World', 'Asia', 'ASEAN', 'Local', ...rankings.map(r => r.level)])].filter(Boolean);
                const selectedLevelFilter = levelFilter.value || 'all';
                levelFilter.innerHTML = '<option value="all">All levels</option><option value="unassigned">Unassigned</option>' + levelOptions.map(l => `<option value="${escapeHtml(l)}">${escapeHtml(l)}</option>`).join('');
                if ([...levelFilter.options].some(o => o.value === selectedLevelFilter)) levelFilter.value = selectedLevelFilter;
                else levelFilter.value = 'all';

                renderScopes();
                renderLevels();
                render();
            }

            const rankDisplayInput = document.getElementById('rankingHistoryAdminGlobalRank');

            document.getElementById('toggleRankingHistoryEditor').addEventListener('click', () => {
                showList(); panel.classList.add('active'); panel.setAttribute('aria-hidden', 'false');
                document.getElementById('addRankingHistoryRow').focus();
            });
            document.getElementById('addRankingHistoryRow').addEventListener('click', () => {
                form.reset();
                document.getElementById('rankingHistoryAdminId').value = '';
                document.getElementById('rankingHistoryAdminYear').value = new Date().getFullYear();
                const dupContainer = document.getElementById('formDuplicateError');
                if (dupContainer) dupContainer.classList.add('hidden');
                showForm(false);
            });
            yearFilter.addEventListener('change', render);
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const id = document.getElementById('rankingHistoryAdminId').value;
                const payload = {
                    organization: bodySelect.value.trim(),
                    ranking_type: document.getElementById('rankingHistoryAdminType').value.trim(),
                    year: Number(document.getElementById('rankingHistoryAdminYear').value),
                    global_rank: rankDisplayInput.value.trim(),
                    ph_rank: document.getElementById('rankingHistoryAdminPhRank').value.trim(),
                    source: document.getElementById('rankingHistoryAdminSource').value.trim()
                };

                const dupContainer = document.getElementById('formDuplicateError');
                if (dupContainer) dupContainer.classList.add('hidden');

                const response = await fetch(apiUrl + (id ? '?id=' + encodeURIComponent(id) : ''), {
                    method: id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    if (response.status === 409 && errorData.duplicate_id) {
                        const dupLink = document.getElementById('editDuplicateLink');
                        if (dupContainer && dupLink) {
                            dupLink.onclick = (e) => {
                                e.preventDefault();
                                const dupRow = rankings.find(r => r.id == errorData.duplicate_id);
                                if (dupRow) loadRankingIntoForm(dupRow);
                            };
                            dupContainer.classList.remove('hidden');
                        } else {
                            alert(errorData.error || 'This ranking already exists.');
                        }
                        return;
                    }
                    alert(errorData.error || 'Unable to save ranking.');
                    return;
                }
                form.reset();
                showList();
                await refresh();
            });
            document.getElementById('cancelRankingHistoryAdminForm').addEventListener('click', () => { form.reset(); showList(); });
            const close = () => { panel.classList.remove('active'); panel.setAttribute('aria-hidden', 'true'); };
            document.getElementById('closeRankingHistoryEditor').addEventListener('click', close);
            panel.addEventListener('click', event => { if (event.target === panel) close(); });
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel.classList.contains('active')) close(); });
            refresh();
            }
        })();

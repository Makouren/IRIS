/**
 * Purpose: Review Editor browser logic for summary cards; loaded by the Review Editor page.
 */
        (function () {
            const summaryCardApi = window.IRIS_REVIEW_EDITOR_CONFIG.irisApiUrl + '?resource=summary_cards';
            const categoryApi = window.IRIS_REVIEW_EDITOR_CONFIG.irisApiUrl + '?resource=summary_card_categories';
            const csrfToken = window.IRIS_REVIEW_EDITOR_CONFIG.csrfToken;
            const editorPanel = document.getElementById('summaryCardEditorPanel');
            const editorList = document.getElementById('summaryCardEditorList');
            const categoryList = document.getElementById('summaryCardCategoryList');
            const form = document.getElementById('summaryCardEditorForm');
            const managerView = document.getElementById('summaryCardManagerView');
            const editorHeading = document.getElementById('summaryCardEditorTitleHeading');
            const categorySelect = document.getElementById('summaryCardEditorCategory');
            const newCategoryInput = document.getElementById('summaryCardEditorNewCategory');
            const managerNewCategoryInput = document.getElementById('summaryCardManagerNewCategory');
            const managerCategoryAction = document.getElementById('summaryCardManagerCategoryAction');
            const managerCategoryMenuButton = document.getElementById('summaryCardManagerShowCategory');
            const managerAddCategoryButton = document.getElementById('summaryCardManagerAddCategory');
            const managerCancelCategoryButton = document.getElementById('summaryCardManagerCancelCategory');
            const managerActionsMenu = document.querySelector('.summary-card-manager-actions-menu');
            const managerCategoryStatus = document.getElementById('summaryCardManagerCategoryStatus');
            const categoryFilter = document.getElementById('summaryCardCategoryFilter');
            const publicDefaultCategory = document.getElementById('summaryCardPublicDefaultCategory');
            const publicDefaultStatus = document.getElementById('summaryCardPublicDefaultStatus');
            const savePublicDefaultButton = document.getElementById('saveSummaryCardPublicDefault');
            const cardSearch = document.getElementById('summaryCardSearch');
            let cards = [];
            let categories = [];
            let publicDefaultCategorySlug = '';
            const summaryCategoryOpenState = new Map();
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            const normalizeSearchText = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
            const searchValues = value => {
                if (value == null) return [];
                if (Array.isArray(value)) return value.flatMap(searchValues);
                if (typeof value === 'object') return Object.values(value).flatMap(searchValues);
                return [String(value)];
            };
            const mutationHeaders = () => ({ 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken });
            managerActionsMenu.addEventListener('click', event => {
                if (event.target.closest('button')) managerActionsMenu.open = false;
            });
            const closeEditor = () => {
                editorPanel.classList.remove('active');
                editorPanel.setAttribute('aria-hidden', 'true');
            };
            const openEditor = () => {
                editorPanel.classList.add('active');
                editorPanel.setAttribute('aria-hidden', 'false');
                const focusTarget = managerView.style.display === 'none'
                    ? document.getElementById('summaryCardEditorTitle')
                    : document.getElementById('addSummaryCardFromManager');
                focusTarget.focus();
            };
            const showCardForm = isEdit => {
                managerView.style.display = 'none';
                form.style.display = 'block';
                editorHeading.textContent = isEdit ? 'Edit summary card' : 'Add summary card';
                document.getElementById('summaryCardEditorTitle').focus();
            };
            const showCardList = () => {
                form.style.display = 'none';
                managerView.style.display = 'block';
                editorHeading.textContent = 'Manage summary cards';
            };

            function renderCategoryOptions() {
                const selected = selectedSummaryCategoryIds();
                categorySelect.innerHTML = categories.map(category => `
                    <label for="summary-card-category-${escapeHtml(category.id)}" style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input id="summary-card-category-${escapeHtml(category.id)}" type="checkbox" value="${escapeHtml(category.id)}" ${selected.includes(String(category.id)) ? 'checked' : ''}>
                        <span>${escapeHtml(category.name)}</span>
                    </label>
                `).join('');
                const filterValue = categoryFilter.value || 'all';
                categoryFilter.innerHTML = '<option value="all">All categories</option><option value="uncategorized">Uncategorized</option>' + categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                if ([...categoryFilter.options].some(option => option.value === filterValue)) categoryFilter.value = filterValue;
            }

            function selectedSummaryCategoryIds() {
                return [...categorySelect.querySelectorAll('input[type="checkbox"]:checked')].map(input => input.value);
            }

            function setSelectedSummaryCategoryIds(ids) {
                const selected = new Set(ids.map(String));
                categorySelect.querySelectorAll('input[type="checkbox"]').forEach(input => {
                    input.checked = selected.has(input.value);
                });
            }

            function summaryCardId(card) {
                return String(card.id ?? card.card_id ?? '');
            }

            function isSummaryCardPublished(card) {
                return card.is_published === true || card.is_published === 1 || card.is_published === '1';
            }

            function renderPublicDefaultCategory() {
                if (!publicDefaultCategory) return;
                const publishedCategoryIds = new Set(cards
                    .filter(isSummaryCardPublished)
                    .flatMap(card => card.category_ids || (card.category_id ? [card.category_id] : []))
                    .map(String));
                publicDefaultCategory.replaceChildren(
                    new Option('All categories', ''),
                    ...categories.map(category => {
                        const hasPublishedCards = publishedCategoryIds.has(String(category.id));
                        const option = new Option(
                            hasPublishedCards ? category.name : `${category.name} (no published cards)`,
                            category.slug
                        );
                        option.disabled = !hasPublishedCards;
                        return option;
                    })
                );
                publicDefaultCategory.value = categories.some(category =>
                    category.slug === publicDefaultCategorySlug
                    && publishedCategoryIds.has(String(category.id))
                )
                    ? publicDefaultCategorySlug
                    : '';
            }

            function renderCategoryList() {
                if (!categories.length) {
                    categoryList.innerHTML = '<div class="text-xs" style="color:var(--text-muted);">No categories yet. Create one from the card form.</div>';
                    return;
                }
                categoryList.innerHTML = categories.map(category => `
                    <div class="summary-card-category-row" data-id="${escapeHtml(category.id)}" style="display:grid; grid-template-columns:minmax(120px,1fr) 90px auto auto; align-items:center; gap:0.5rem;">
                        <input class="form-input" data-category-name value="${escapeHtml(category.name)}" maxlength="40" required aria-label="Category name">
                        <input class="form-input" data-category-order type="number" min="0" step="1" required value="${escapeHtml(category.sort_order)}" aria-label="Category sort order">
                        <button type="button" class="btn-studio-action summary-category-save">Save</button>
                        <button type="button" class="archive-delete-button summary-category-delete">Delete</button>
                    </div>`).join('');
                categoryList.querySelectorAll('.summary-category-save').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.summary-card-category-row');
                    const name = row.querySelector('[data-category-name]');
                    const order = row.querySelector('[data-category-order]');
                    const nameInvalid = !name.value.trim();
                    const orderInvalid = !order.validity.valid;
                    name.setCustomValidity(nameInvalid ? 'Enter a category name.' : '');
                    name.toggleAttribute('aria-invalid', nameInvalid);
                    order.toggleAttribute('aria-invalid', orderInvalid);
                    if (!name.reportValidity() || !order.reportValidity() || button.disabled) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(categoryApi + '&id=' + encodeURIComponent(row.dataset.id), {
                            method: 'PUT', headers: mutationHeaders(),
                            body: JSON.stringify({ name: name.value.trim(), sort_order: Number(order.value) })
                        });
                        if (!response.ok) {
                            const error = await response.json().catch(() => ({}));
                            throw new Error(error.error || 'Unable to update category.');
                        }
                        await refreshSummaryCardEditor();
                    } catch (error) {
                        alert(error.message || 'Unable to update category.');
                        button.disabled = false;
                    }
                }));
                categoryList.querySelectorAll('.summary-category-delete').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.summary-card-category-row');
                    const category = categories.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${category?.name || 'this category'}”? Its cards will move to Uncategorized.`)) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(categoryApi + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken } });
                        if (!response.ok) {
                            const error = await response.json().catch(() => ({}));
                            throw new Error(error.error || 'Unable to delete category.');
                        }
                        await refreshSummaryCardEditor();
                    } catch (error) {
                        alert(error.message || 'Unable to delete category.');
                        button.disabled = false;
                    }
                }));
            }

            function renderCards() {
                editorList.querySelectorAll('[data-summary-category-group]').forEach(group => {
                    summaryCategoryOpenState.set(group.dataset.summaryCategoryGroup, group.open);
                });
                const selectedCategory = categoryFilter.value || 'all';
                const searchTerms = normalizeSearchText(cardSearch.value).trim().split(/\s+/).filter(Boolean);
                const draftCards = cards.filter(card => !isSummaryCardPublished(card));
                const publishAllButton = document.getElementById('publishAllSummaryCards');
                const publishAllCount = document.getElementById('publishAllSummaryCardsCount');
                if (publishAllButton) {
                    publishAllButton.style.display = draftCards.length ? 'inline-block' : 'none';
                    publishAllButton.disabled = draftCards.length === 0;
                }
                if (publishAllCount) publishAllCount.textContent = String(draftCards.length);
                if (publishAllButton) {
                    publishAllButton.onclick = async () => {
                        const draftCards = cards.filter(card => !isSummaryCardPublished(card));
                        if (!draftCards.length || publishAllButton.disabled) return;
                        if (!confirm(`Publish all ${draftCards.length} draft Summary Card(s)?`)) return;
                        publishAllButton.disabled = true;
                        const count = document.getElementById('publishAllSummaryCardsCount');
                        if (count) count.textContent = '…';
                        let hasError = false;
                        try {
                            for (const card of draftCards) {
                                try {
                                    const response = await fetch(summaryCardApi + '&id=' + encodeURIComponent(summaryCardId(card)), {
                                        method: 'PUT',
                                        headers: mutationHeaders(),
                                        body: JSON.stringify({ is_published: true })
                                    });
                                    if (!response.ok) hasError = true;
                                } catch (error) { hasError = true; }
                            }
                            if (hasError) alert('Some summary cards could not be published.');
                            await refreshSummaryCardEditor();
                        } catch (error) {
                            alert(error.message || 'Unable to publish summary cards.');
                        } finally {
                            publishAllButton.disabled = false;
                        }
                    };
                }
                const visibleCards = cards.filter(card => {
                    const categoryMatches = selectedCategory === 'all'
                    || (selectedCategory === 'uncategorized'
                        ? !(card.category_ids || (card.category_id ? [card.category_id] : [])).length
                        : (card.category_ids || (card.category_id ? [card.category_id] : [])).some(categoryId => String(categoryId) === selectedCategory));
                    if (!categoryMatches) return false;
                    if (!searchTerms.length) return true;
                    const searchable = normalizeSearchText(searchValues([
                        card.title, card.import_key, card.main_value, card.main_label, card.year_date,
                        card.secondary_label, card.secondary_value, card.description,
                        card.secondary_description, card.info_text, card.current_public_period,
                        card.latest_imported_period, card.category_name, card.category_names,
                        card.custom_fields
                    ]).join(' '));
                    return searchTerms.every(term => searchable.includes(term));
                });
                const selectAllSummary = document.getElementById('selectAllSummaryCards');
                const selectAllSummaryLabel = document.getElementById('selectAllSummaryCardsLabel');
                if (!visibleCards.length) {
                    editorList.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 text-center">${searchTerms.length ? 'No summary cards match your search.' : 'No summary cards in this category.'}</div>`;
                    ['bulkDeleteSummaryCards', 'bulkPublishSummaryCards', 'bulkUnpublishSummaryCards'].forEach(id => {
                        const button = document.getElementById(id);
                        if (button) { button.style.display = 'none'; button.disabled = true; }
                    });
                    if (selectAllSummary) selectAllSummary.checked = false;
                    if (selectAllSummaryLabel) selectAllSummaryLabel.style.display = 'none';
                    return;
                }
                const categoryById = new Map(categories.map(category => [String(category.id), category]));
                const categoryGroups = new Map();
                visibleCards.forEach(card => {
                    const categoryIds = [...new Set((card.category_ids || (card.category_id ? [card.category_id] : [])).map(String))];
                    const memberships = selectedCategory !== 'all'
                        ? [selectedCategory === 'uncategorized'
                            ? { id: 'uncategorized', name: 'Uncategorized' }
                            : categoryById.get(selectedCategory) || { id: selectedCategory, name: card.category_name || 'Uncategorized' }]
                        : categoryIds.map(id => categoryById.get(id)).filter(Boolean);
                    if (!memberships.length) {
                        memberships.push({
                            id: 'uncategorized',
                            name: card.category_name || 'Uncategorized'
                        });
                    }
                    memberships.forEach(category => {
                        const key = String(category.id);
                        if (!categoryGroups.has(key)) categoryGroups.set(key, { category, cards: [] });
                        categoryGroups.get(key).cards.push(card);
                    });
                });
                const groupOrder = selectedCategory === 'all'
                    ? categories.map(category => String(category.id)).concat(['uncategorized'])
                    : [selectedCategory];
                const sortedGroups = [...categoryGroups.entries()].sort(([idA], [idB]) => {
                    const orderA = groupOrder.indexOf(idA);
                    const orderB = groupOrder.indexOf(idB);
                    return (orderA < 0 ? Number.MAX_SAFE_INTEGER : orderA) - (orderB < 0 ? Number.MAX_SAFE_INTEGER : orderB)
                        || categoryGroups.get(idA).category.name.localeCompare(categoryGroups.get(idB).category.name);
                });
                const renderCard = card => {
                    const id = summaryCardId(card);
                    const published = isSummaryCardPublished(card);
                    const categoryNames = (card.category_ids || (card.category_id ? [card.category_id] : []))
                        .map(categoryId => categoryById.get(String(categoryId))?.name)
                        .filter(Boolean);
                    return `
                        <div class="summary-card-editor-item">
                            <div class="summary-card-editor-item-content">
                                <input type="checkbox" class="bulk-delete-summary-checkbox mt-1 cursor-pointer" data-id="${escapeHtml(id)}" aria-label="Select ${escapeHtml(card.title || 'Summary Card')}" style="width:1rem;height:1rem;">
                                <div class="summary-card-editor-item-details">
                                    <div class="summary-card-editor-item-heading">
                                        <div>
                                            <div class="font-bold text-sm text-slate-900">${escapeHtml(card.title || 'Summary Card')}</div>
                                            <div class="text-xs text-slate-500">Global Label: ${escapeHtml(card.import_key || '')}</div>
                                            <div class="text-xs text-slate-500">${escapeHtml(card.main_value || '')} · ${escapeHtml(card.main_label || '')}</div>
                                            <div class="mt-1 text-xs text-slate-500">Public: ${escapeHtml(card.current_public_period || 'None')} · Latest imported: ${escapeHtml(card.latest_imported_period || 'None')} · Periods: ${Number(card.history_count || 0)}</div>
                                            ${categoryNames.length ? `<div class="summary-card-editor-category-badges">${categoryNames.map(name => `<span>${escapeHtml(name)}</span>`).join('')}</div>` : ''}
                                        </div>
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}">${published ? 'Published' : 'Draft'}</span>
                                    </div>
                                    <div class="flex gap-2 mt-3">
                                        <button type="button" class="summary-card-editor-edit btn-studio-action" data-id="${escapeHtml(id)}">Edit</button>
                                        <button type="button" class="btn-studio-action" data-summary-history-open data-id="${escapeHtml(id)}">History</button>
                                        <button type="button" class="summary-card-editor-toggle btn-studio-action" data-id="${escapeHtml(id)}" data-published="${published ? '1' : '0'}">${published ? 'Unpublish' : 'Publish'}</button>
                                        <button type="button" class="summary-card-editor-delete archive-delete-button" data-id="${escapeHtml(id)}">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                };
                editorList.innerHTML = sortedGroups.map(([, group]) => `
                    <details class="summary-card-category-group" data-summary-category-group="${escapeHtml(group.category.id)}" ${summaryCategoryOpenState.get(String(group.category.id)) ?? true ? 'open' : ''}>
                        <summary class="summary-card-category-heading">
                            <span>${escapeHtml(group.category.name)}</span>
                            <span>${group.cards.length} ${group.cards.length === 1 ? 'card' : 'cards'}</span>
                        </summary>
                        <div class="summary-card-category-items">
                            ${group.cards.slice().sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0)).map(renderCard).join('')}
                        </div>
                    </details>
                `).join('');
                editorList.querySelectorAll('[data-summary-category-group]').forEach(group => {
                    group.addEventListener('toggle', () => {
                        summaryCategoryOpenState.set(group.dataset.summaryCategoryGroup, group.open);
                    });
                });

                // Bulk delete wiring

                if (selectAllSummaryLabel) selectAllSummaryLabel.style.display = visibleCards.length ? 'inline-flex' : 'none';
                if (selectAllSummary) selectAllSummary.checked = false;

                const updateBulkBtn = () => {
                    const currentBulkBtn = document.getElementById('bulkDeleteSummaryCards');
                    const currentBulkCount = document.getElementById('bulkDeleteSummaryCardsCount');
                    const currentPublishBtn = document.getElementById('bulkPublishSummaryCards');
                    const currentPublishCount = document.getElementById('bulkPublishSummaryCardsCount');
                    const currentUnpublishBtn = document.getElementById('bulkUnpublishSummaryCards');
                    const currentUnpublishCount = document.getElementById('bulkUnpublishSummaryCardsCount');
                    const allCbs = Array.from(editorList.querySelectorAll('.bulk-delete-summary-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    const selectedIds = new Set(selected.map(cb => cb.dataset.id));
                    const selectedCards = cards.filter(card => selectedIds.has(summaryCardId(card)));
                    const selectedDrafts = selectedCards.filter(card => !isSummaryCardPublished(card));
                    const selectedPublished = selectedCards.filter(isSummaryCardPublished);
                    if (currentBulkBtn) {
                        currentBulkBtn.style.display = selectedIds.size > 0 ? 'inline-block' : 'none';
                        currentBulkBtn.disabled = selectedIds.size === 0;
                    }
                    if (currentBulkCount) currentBulkCount.textContent = String(selectedIds.size);
                    if (currentPublishBtn) {
                        currentPublishBtn.style.display = selectedDrafts.length > 0 ? 'inline-block' : 'none';
                        currentPublishBtn.disabled = selectedDrafts.length === 0;
                    }
                    if (currentPublishCount) currentPublishCount.textContent = selectedDrafts.length;
                    if (currentUnpublishBtn) {
                        currentUnpublishBtn.style.display = selectedPublished.length > 0 ? 'inline-block' : 'none';
                        currentUnpublishBtn.disabled = selectedPublished.length === 0;
                    }
                    if (currentUnpublishCount) currentUnpublishCount.textContent = selectedPublished.length;
                    if (selectAllSummary) {
                        const allIds = new Set(allCbs.map(cb => cb.dataset.id));
                        selectAllSummary.checked = allIds.size > 0 && selectedIds.size === allIds.size;
                    }
                };

                editorList.querySelectorAll('.bulk-delete-summary-checkbox').forEach(cb => cb.addEventListener('change', () => {
                    editorList.querySelectorAll('.bulk-delete-summary-checkbox').forEach(duplicate => {
                        if (duplicate.dataset.id === cb.dataset.id) duplicate.checked = cb.checked;
                    });
                    updateBulkBtn();
                }));

                if (selectAllSummary) {
                    selectAllSummary.onchange = () => {
                        editorList.querySelectorAll('.bulk-delete-summary-checkbox').forEach(cb => { cb.checked = selectAllSummary.checked; });
                        updateBulkBtn();
                    };
                }

                const bulkBtn = document.getElementById('bulkDeleteSummaryCards');
                if (bulkBtn) {
                    const newBulkBtn = bulkBtn.cloneNode(true);
                    bulkBtn.parentNode.replaceChild(newBulkBtn, bulkBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = [...new Set(Array.from(editorList.querySelectorAll('.bulk-delete-summary-checkbox:checked')).map(cb => cb.dataset.id))];
                        if (!selectedIds.length || newBulkBtn.disabled) return;
                        if (!confirm(`Delete ${selectedIds.length} selected summary card(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        try {
                            for (const id of selectedIds) {
                                try {
                                    const res = await fetch(summaryCardApi + '&id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken } });
                                    if (!res.ok) hasError = true;
                                } catch (error) { hasError = true; }
                            }
                            if (hasError) alert('Some summary cards could not be deleted.');
                            await refreshSummaryCardEditor();
                        } catch (error) {
                            alert(error.message || 'Unable to delete summary cards.');
                        } finally {
                            newBulkBtn.disabled = false;
                        }
                    });
                }

                const publishBtn = document.getElementById('bulkPublishSummaryCards');
                if (publishBtn) {
                    const newPublishBtn = publishBtn.cloneNode(true);
                    publishBtn.parentNode.replaceChild(newPublishBtn, publishBtn);
                    newPublishBtn.addEventListener('click', async () => {
                        const selectedIds = new Set(Array.from(editorList.querySelectorAll('.bulk-delete-summary-checkbox:checked')).map(cb => cb.dataset.id));
                        const selectedDrafts = cards.filter(card => selectedIds.has(summaryCardId(card)) && !isSummaryCardPublished(card));
                        if (!selectedDrafts.length || newPublishBtn.disabled) return;
                        if (!confirm(`Publish ${selectedDrafts.length} selected Summary Card(s)?`)) return;
                        newPublishBtn.disabled = true;
                        const count = newPublishBtn.querySelector('#bulkPublishSummaryCardsCount');
                        if (count) count.textContent = '…';
                        let hasError = false;
                        try {
                            for (const card of selectedDrafts) {
                                try {
                                    const response = await fetch(summaryCardApi + '&id=' + encodeURIComponent(summaryCardId(card)), {
                                        method: 'PUT',
                                        headers: mutationHeaders(),
                                        body: JSON.stringify({ is_published: true })
                                    });
                                    if (!response.ok) hasError = true;
                                } catch (error) { hasError = true; }
                            }
                            if (hasError) alert('Some selected summary cards could not be published.');
                            await refreshSummaryCardEditor();
                        } catch (error) {
                            alert(error.message || 'Unable to publish selected summary cards.');
                        } finally {
                            newPublishBtn.disabled = false;
                        }
                    });
                }

                const unpublishBtn = document.getElementById('bulkUnpublishSummaryCards');
                if (unpublishBtn) {
                    const newUnpublishBtn = unpublishBtn.cloneNode(true);
                    unpublishBtn.parentNode.replaceChild(newUnpublishBtn, unpublishBtn);
                    newUnpublishBtn.addEventListener('click', async () => {
                        const selectedIds = new Set(Array.from(editorList.querySelectorAll('.bulk-delete-summary-checkbox:checked')).map(cb => cb.dataset.id));
                        const selectedPublished = cards.filter(card => selectedIds.has(summaryCardId(card)) && isSummaryCardPublished(card));
                        if (!selectedPublished.length || newUnpublishBtn.disabled) return;
                        if (!confirm(`Unpublish ${selectedPublished.length} selected Summary Card(s)?`)) return;
                        newUnpublishBtn.disabled = true;
                        const count = newUnpublishBtn.querySelector('#bulkUnpublishSummaryCardsCount');
                        if (count) count.textContent = '…';
                        let hasError = false;
                        try {
                            for (const card of selectedPublished) {
                                try {
                                    const response = await fetch(summaryCardApi + '&id=' + encodeURIComponent(summaryCardId(card)), {
                                        method: 'PUT',
                                        headers: mutationHeaders(),
                                        body: JSON.stringify({ is_published: false })
                                    });
                                    if (!response.ok) hasError = true;
                                } catch (error) { hasError = true; }
                            }
                            if (hasError) alert('Some selected summary cards could not be unpublished.');
                            await refreshSummaryCardEditor();
                        } catch (error) {
                            alert(error.message || 'Unable to unpublish selected summary cards.');
                        } finally {
                            newUnpublishBtn.disabled = false;
                        }
                    });
                }
                updateBulkBtn();

                editorList.querySelectorAll('.summary-card-editor-edit').forEach(button => {
                    button.addEventListener('click', () => {
                        const id = String(button.dataset.id || '');
                        const card = cards.find(item => String(item.id ?? item.card_id ?? '') === id);
                        if (!card) {
                            managerCategoryStatus.textContent = 'This summary card could not be found. Refresh the list and try again.';
                            return;
                        }
                        document.getElementById('summaryCardEditorId').value = summaryCardId(card);
                        document.getElementById('summaryCardEditorImportKey').value = card.import_key || card.id || card.card_id || '';
                        document.getElementById('summaryCardEditorTitle').value = card.title || '';
                        document.getElementById('summaryCardEditorMainValue').value = card.main_value || '';
                        document.getElementById('summaryCardEditorMainLabel').value = card.main_label || '';
                        document.getElementById('summaryCardEditorYearDate').value = card.year_date || '';
                        document.getElementById('summaryCardEditorSecondaryLabel').value = card.secondary_label || '';
                        document.getElementById('summaryCardEditorSecondaryValue').value = card.secondary_value || '';
                        document.getElementById('summaryCardEditorDescription').value = card.description || '';
                        document.getElementById('summaryCardEditorSecondaryDescription').value = card.secondary_description || '';
                        document.getElementById('summaryCardEditorInfoText').value = card.info_text || '';
                        document.getElementById('summaryCardEditorDisplayOrder').value = card.display_order ?? 0;
                        const selectedIds = (card.category_ids || (card.category_id ? [card.category_id] : [])).map(String);
                        setSelectedSummaryCategoryIds(selectedIds);
                        newCategoryInput.value = '';
                        newCategoryInput.style.display = 'none';
                        document.getElementById('summaryCardEditorPrecision').value = String(card.display_precision ?? 2);
                        document.getElementById('summaryCardEditorPublished').checked = isSummaryCardPublished(card);
                        managerCategoryStatus.textContent = '';
                        showCardForm(true);
                        openEditor();
                    });
                });

                editorList.querySelectorAll('.summary-card-editor-toggle').forEach(button => {
                    button.addEventListener('click', async () => {
                        if (button.disabled) return;
                        const id = button.dataset.id;
                        const published = button.dataset.published === '1';
                        button.disabled = true;
                        try {
                            const response = await fetch(summaryCardApi + '&id=' + encodeURIComponent(id), {
                                method: 'PUT',
                                headers: mutationHeaders(),
                                body: JSON.stringify({ is_published: !published })
                            });
                            if (!response.ok) {
                                const error = await response.json().catch(() => ({}));
                                throw new Error(error.error || 'Unable to update publication.');
                            }
                            await refreshSummaryCardEditor();
                        } catch (error) {
                            alert(error.message || 'Unable to update publication.');
                            button.disabled = false;
                        }
                    });
                });

                editorList.querySelectorAll('.summary-card-editor-delete').forEach(button => {
                    button.addEventListener('click', async () => {
                        if (!confirm('Delete this summary card?')) return;
                        if (button.disabled) return;
                        button.disabled = true;
                        try {
                            const response = await fetch(summaryCardApi + '&id=' + encodeURIComponent(button.dataset.id), {
                                method: 'DELETE',
                                headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken }
                            });
                            if (!response.ok) {
                                const error = await response.json().catch(() => ({}));
                                throw new Error(error.error || 'Unable to delete summary card.');
                            }
                            await refreshSummaryCardEditor();
                        } catch (error) {
                            alert(error.message || 'Unable to delete summary card.');
                            button.disabled = false;
                        }
                    });
                });
            }

            async function refreshSummaryCardEditor() {
                const [cardResponse, categoryResponse, defaultResponse] = await Promise.all([
                    fetch(summaryCardApi, { headers: { Accept: 'application/json' } }),
                    fetch(categoryApi, { headers: { Accept: 'application/json' } }),
                    fetch(categoryApi + '&action=public-default', { headers: { Accept: 'application/json' } })
                ]);
                if (!cardResponse.ok || !categoryResponse.ok || !defaultResponse.ok) throw new Error('Unable to load Summary Cards and categories.');
                cards = await cardResponse.json();
                categories = await categoryResponse.json();
                publicDefaultCategorySlug = (await defaultResponse.json()).default_category_slug || '';
                renderCategoryOptions();
                renderCategoryList();
                renderPublicDefaultCategory();
                renderCards();
            }

            categoryFilter.addEventListener('change', renderCards);
            cardSearch.addEventListener('input', renderCards);
            savePublicDefaultButton?.addEventListener('click', async () => {
                if (savePublicDefaultButton.disabled) return;
                savePublicDefaultButton.disabled = true;
                publicDefaultStatus.textContent = 'Saving…';
                try {
                    const response = await fetch(categoryApi + '&action=save-public-default', {
                        method: 'POST',
                        headers: mutationHeaders(),
                        body: JSON.stringify({ default_category_slug: publicDefaultCategory.value })
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.error || 'Unable to save the public summary-card default.');
                    publicDefaultCategorySlug = result.default_category_slug || '';
                    const selected = categories.find(category => category.slug === publicDefaultCategorySlug);
                    publicDefaultStatus.textContent = `Saved: ${selected?.name || 'All categories'}. Public visitors can still change the category.`;
                } catch (error) {
                    publicDefaultStatus.textContent = error.message || 'Unable to save the public summary-card default.';
                } finally {
                    savePublicDefaultButton.disabled = false;
                }
            });
            document.getElementById('summaryCardEditorAddCategory').addEventListener('click', () => {
                newCategoryInput.style.display = newCategoryInput.style.display === 'none' ? 'block' : 'none';
                if (newCategoryInput.style.display === 'block') newCategoryInput.focus();
            });
            managerCategoryMenuButton?.addEventListener('click', () => {
                managerActionsMenu.open = false;
                managerCategoryAction.hidden = false;
                managerCategoryStatus.textContent = '';
                managerNewCategoryInput.focus();
            });
            managerCancelCategoryButton.addEventListener('click', () => {
                managerCategoryAction.hidden = true;
                managerNewCategoryInput.value = '';
                managerNewCategoryInput.setCustomValidity('');
                managerNewCategoryInput.removeAttribute('aria-invalid');
                managerCategoryStatus.textContent = '';
                managerCategoryMenuButton?.focus();
            });
            managerAddCategoryButton.addEventListener('click', async () => {
                const name = managerNewCategoryInput.value.trim();
                const invalid = !name || name.length > 40;
                managerNewCategoryInput.setCustomValidity(invalid
                    ? 'Enter a category name between 1 and 40 characters.'
                    : '');
                managerNewCategoryInput.setAttribute('aria-invalid', String(invalid));
                managerCategoryStatus.textContent = '';
                if (!managerNewCategoryInput.reportValidity() || managerAddCategoryButton.disabled) return;
                if (!confirm(`Add the category "${name}"?`)) return;
                managerAddCategoryButton.disabled = true;
                managerCategoryStatus.textContent = 'Adding category…';
                try {
                    const nextSortOrder = categories.length
                        ? Math.max(...categories.map(category => Number(category.sort_order) || 0)) + 1
                        : 0;
                    const response = await fetch(categoryApi, {
                        method: 'POST',
                        headers: mutationHeaders(),
                        body: JSON.stringify({ name, sort_order: nextSortOrder })
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.error || 'Unable to add category.');
                    managerNewCategoryInput.value = '';
                    managerNewCategoryInput.removeAttribute('aria-invalid');
                    managerCategoryStatus.textContent = `Category “${result.name || name}” added.`;
                    managerCategoryAction.hidden = true;
                    showCardList();
                    editorPanel.classList.add('active');
                    editorPanel.setAttribute('aria-hidden', 'false');
                    await refreshSummaryCardEditor();
                    managerCategoryStatus.textContent = `Category “${result.name || name}” added. It is now listed in the manager; it can be selected as the public default after a card in it is published.`;
                    categoryFilter.focus();
                } catch (error) {
                    managerCategoryStatus.textContent = error.message || 'Unable to add category.';
                } finally {
                    managerAddCategoryButton.disabled = false;
                }
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const importKeyInput = document.getElementById('summaryCardEditorImportKey');
                const titleInput = document.getElementById('summaryCardEditorTitle');
                const mainValueInput = document.getElementById('summaryCardEditorMainValue');
                const mainLabelInput = document.getElementById('summaryCardEditorMainLabel');
                const importKeyInvalid = importKeyInput.value.trim() !== importKeyInput.value || !importKeyInput.value.trim();
                const titleInvalid = !titleInput.value.trim();
                const mainValueInvalid = !mainValueInput.value.trim();
                const mainLabelInvalid = !mainLabelInput.value.trim();
                importKeyInput.setCustomValidity(importKeyInvalid ? 'Enter a Global Label without leading or trailing spaces.' : '');
                titleInput.setCustomValidity(titleInvalid ? 'Enter a title.' : '');
                mainValueInput.setCustomValidity(mainValueInvalid ? 'Enter a main value.' : '');
                mainLabelInput.setCustomValidity(mainLabelInvalid ? 'Enter a main label.' : '');
                importKeyInput.toggleAttribute('aria-invalid', importKeyInvalid);
                titleInput.toggleAttribute('aria-invalid', titleInvalid);
                mainValueInput.toggleAttribute('aria-invalid', mainValueInvalid);
                mainLabelInput.toggleAttribute('aria-invalid', mainLabelInvalid);
                if (!form.reportValidity()) return;
                const submitButton = form.querySelector('[type="submit"]');
                if (submitButton.disabled) return;
                submitButton.disabled = true;
                const payload = {
                    import_key: importKeyInput.value,
                    title: titleInput.value.trim(),
                    main_value: mainValueInput.value.trim(),
                    main_label: mainLabelInput.value.trim(),
                    year_date: document.getElementById('summaryCardEditorYearDate').value.trim(),
                    secondary_label: document.getElementById('summaryCardEditorSecondaryLabel').value.trim(),
                    secondary_value: document.getElementById('summaryCardEditorSecondaryValue').value.trim(),
                    description: document.getElementById('summaryCardEditorDescription').value.trim(),
                    secondary_description: document.getElementById('summaryCardEditorSecondaryDescription').value.trim(),
                    info_text: document.getElementById('summaryCardEditorInfoText').value.trim(),
                    display_order: Number(document.getElementById('summaryCardEditorDisplayOrder').value || 0),
                    display_precision: (() => {
                        const precisionValue = Number(document.getElementById('summaryCardEditorPrecision').value);
                        return Number.isFinite(precisionValue) ? precisionValue : 2;
                    })(),
                    is_published: document.getElementById('summaryCardEditorPublished').checked
                };
                payload.category_ids = selectedSummaryCategoryIds().map(Number);
                const newCategoryName = newCategoryInput.value.trim();
                if (newCategoryName) payload.category_names = [newCategoryName];
                const id = document.getElementById('summaryCardEditorId').value;
                try {
                    const response = await fetch(summaryCardApi + (id ? '&id=' + encodeURIComponent(id) : ''), {
                        method: id ? 'PUT' : 'POST',
                        headers: mutationHeaders(),
                        body: JSON.stringify(payload)
                    });
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.error || 'Unable to save summary card.');
                    }
                    form.reset();
                    document.getElementById('summaryCardEditorId').value = '';
                    newCategoryInput.style.display = 'none';
                    newCategoryInput.value = '';
                    showCardList();
                    closeEditor();
                    await refreshSummaryCardEditor();
                } catch (error) {
                    alert(error.message || 'Unable to save summary card.');
                } finally {
                    submitButton.disabled = false;
                }
            });

            document.getElementById('toggleSummaryCardEditor').addEventListener('click', () => {
                showCardList();
                openEditor();
            });
            document.getElementById('addSummaryCardFromManager').addEventListener('click', () => {
                form.reset();
                document.getElementById('summaryCardEditorId').value = '';
                setSelectedSummaryCategoryIds([]);
                newCategoryInput.style.display = 'none';
                newCategoryInput.value = '';
                document.getElementById('summaryCardEditorPrecision').value = '2';
                document.getElementById('summaryCardEditorPublished').checked = false;
                showCardForm(false);
            });

            document.getElementById('cancelSummaryCardEditor').addEventListener('click', () => {
                form.reset();
                document.getElementById('summaryCardEditorId').value = '';
                setSelectedSummaryCategoryIds([]);
                newCategoryInput.style.display = 'none';
                newCategoryInput.value = '';
                showCardList();
            });
            document.getElementById('closeSummaryCardEditor').addEventListener('click', closeEditor);
            editorPanel.addEventListener('click', event => {
                if (event.target === editorPanel) closeEditor();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && editorPanel.classList.contains('active')) closeEditor();
            });

            const refreshBtn = document.getElementById('refreshSummaryCardsBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', async () => {
                    refreshBtn.disabled = true;
                    refreshBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate fa-spin"></i>';
                    try {
                        await refreshSummaryCardEditor();
                    } catch (error) {
                        alert(error.message || 'Unable to refresh Summary Cards.');
                    } finally {
                        refreshBtn.disabled = false;
                        refreshBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i>';
                    }
                });
            }

            refreshSummaryCardEditor().catch(error => alert(error.message || 'Unable to load Summary Cards.'));
        })();

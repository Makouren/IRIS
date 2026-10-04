/**
 * Purpose: Review Editor browser logic for star rating cards; loaded by the Review Editor page.
 */
        (function () {
            const apiUrl = window.IRIS_REVIEW_EDITOR_CONFIG.starRatingApiUrl;
            const categoryApiUrl = apiUrl + '?resource=categories';
            const baseUrl = window.IRIS_REVIEW_EDITOR_CONFIG.baseUrl;
            const csrfToken = window.IRIS_REVIEW_EDITOR_CONFIG.csrfToken;
            const panel = document.getElementById('starRatingEditorPanel');
            const managerView = document.getElementById('starRatingManagerView');
            const form = document.getElementById('starRatingCardForm');
            const cardList = document.getElementById('starRatingCardList');
            const rowsHost = document.getElementById('starRatingRows');
            const preview = document.getElementById('starRatingPreview');
            const categoryFilter = document.getElementById('starRatingCategoryFilter');
            const categorySelect = document.getElementById('starRatingCategorySelect');
            const categoryList = document.getElementById('starRatingCategoryList');
            const newCategoryInput = document.getElementById('starRatingNewCategory');
            const logoInput = document.getElementById('starRatingLogo');
            const currentLogo = document.getElementById('starRatingCurrentLogo');
            const currentLogoImage = document.getElementById('starRatingCurrentLogoImage');
            const removeLogo = document.getElementById('starRatingRemoveLogo');
            const starPath = 'M12 2.5 14.9 8.4l6.6 1-4.75 4.62 1.12 6.53L12 17.47l-5.87 3.08 1.12-6.53L2.5 9.4l6.6-1L12 2.5Z';
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            let localLogoPreviewUrl = '';
            let cards = [];
            let categories = [];
            const starCategoryOpenState = new Map();

            function clearLocalLogoPreview() {
                if (localLogoPreviewUrl) URL.revokeObjectURL(localLogoPreviewUrl);
                localLogoPreviewUrl = '';
            }

            function starIcons(maxStars, score, prefix) {
                return Array.from({ length: maxStars }, (_, index) => {
                    const remaining = score - index;
                    const fill = remaining >= 1 ? '#E0A70D' : remaining >= 0.5 ? `url(#${prefix}-${index})` : 'none';
                    const half = remaining >= 0.5 && remaining < 1
                        ? `<defs><linearGradient id="${prefix}-${index}"><stop offset="50%" stop-color="#E0A70D"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs>`
                        : '';
                    return `<svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" aria-hidden="true">${half}<path d="${starPath}" fill="${fill}" stroke="#E0A70D" stroke-width="1.5" stroke-linejoin="round"/></svg>`;
                }).join('');
            }

            function rowValues() {
                return [...rowsHost.querySelectorAll('[data-star-row]')].map(row => ({
                    label: row.querySelector('[data-label]').value.trim(),
                    max_stars: Number(row.querySelector('[data-max-stars]').value),
                    score: Number(row.querySelector('[data-score]').value)
                }));
            }

            function renderPreview() {
                const title = document.getElementById('starRatingTitle').value.trim() || 'Star rating title';
                const year = document.getElementById('starRatingYear').value.trim();
                const logoSrc = localLogoPreviewUrl || (currentLogo.style.display !== 'none' && !removeLogo.checked ? currentLogoImage.src : '');
                const rows = rowValues();
                const rowsHtml = rows.length ? rows.map((row, index) => {
                    const maxStars = Math.max(1, Math.min(10, Number.isFinite(row.max_stars) ? row.max_stars : 10));
                    const score = Math.max(0, Math.min(maxStars, Number.isFinite(row.score) ? row.score : 0));
                    const label = row.label || 'Category';
                    return `<div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 py-2 dark:border-gray-700" aria-label="${escapeHtml(label)}: ${score} out of ${maxStars} stars" title="${escapeHtml(label)}: ${score} out of ${maxStars} stars"><div class="flex max-w-full flex-wrap items-center gap-0.5">${starIcons(maxStars, score, `preview-star-${index}`)}<span class="ml-1 text-xs text-gray-500 dark:text-gray-400">${score} / ${maxStars}</span></div><span class="text-sm font-semibold text-gray-800 dark:text-gray-100">${escapeHtml(label)}</span></div>`;
                }).join('') : '<div class="text-sm text-gray-500 dark:text-gray-400">Add a row to preview ratings.</div>';
                preview.innerHTML = `<div class="mx-auto max-w-lg rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex min-h-16 flex-col items-center justify-center gap-2 text-center">${logoSrc ? `<img src="${escapeHtml(logoSrc)}" alt="" class="max-h-12 max-w-36 object-contain">` : ''}<div class="font-bold text-gray-900 dark:text-white">${escapeHtml(title)}</div></div>${year ? `<div class="mt-2 border-y border-gray-200 py-1 text-center text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">${escapeHtml(year)}</div>` : ''}<div class="mt-2">${rowsHtml}</div></div>`;
            }

            function updateRowButtons() {
                const rows = [...rowsHost.querySelectorAll('[data-star-row]')];
                rows.forEach((row, index) => {
                    row.querySelector('[data-move-up]').disabled = index === 0;
                    row.querySelector('[data-move-down]').disabled = index === rows.length - 1;
                });
                document.getElementById('addStarRatingRow').disabled = rows.length >= 10;
            }

            function addRow(value = {}) {
                if (rowsHost.children.length >= 10) return;
                const row = document.createElement('div');
                row.dataset.starRow = '1';
                row.style.cssText = 'display:grid; grid-template-columns:minmax(130px,1.4fr) minmax(95px,0.8fr) minmax(95px,0.8fr) auto; align-items:end; gap:0.5rem; padding:0.65rem; border:1px solid var(--border-light); border-radius:var(--radius-md);';
                const options = Array.from({ length: 10 }, (_, index) => `<option value="${index + 1}" ${Number(value.max_stars || 10) === index + 1 ? 'selected' : ''}>${index + 1}</option>`).join('');
                row.innerHTML = `<label class="form-label" style="margin:0;">Label<input data-label type="text" class="form-input" maxlength="80" value="${escapeHtml(value.label || '')}" placeholder="Teaching" required></label><label class="form-label" style="margin:0;">Number of stars<select data-max-stars class="form-input">${options}</select></label><label class="form-label" style="margin:0;">Score<input data-score type="number" class="form-input" min="0" max="${Number(value.max_stars || 10)}" step="0.5" value="${Number.isFinite(Number(value.score)) ? Number(value.score) : 0}" required></label><div style="display:flex; gap:0.25rem;"><button type="button" data-move-up class="btn-studio-action" aria-label="Move row up" title="Move up">↑</button><button type="button" data-move-down class="btn-studio-action" aria-label="Move row down" title="Move down">↓</button><button type="button" data-remove-row class="archive-delete-button" aria-label="Remove row" title="Remove row">×</button></div>`;
                rowsHost.appendChild(row);
                row.querySelector('[data-max-stars]').addEventListener('change', event => {
                    const scoreInput = row.querySelector('[data-score]');
                    const maxStars = Number(event.target.value);
                    scoreInput.max = String(maxStars);
                    if (Number(scoreInput.value) > maxStars) scoreInput.value = String(maxStars);
                    renderPreview();
                });
                row.querySelectorAll('input').forEach(input => input.addEventListener('input', renderPreview));
                row.querySelector('[data-remove-row]').addEventListener('click', () => {
                    if (!confirm('Remove this rating row?')) return;
                    row.remove();
                    updateRowButtons();
                    renderPreview();
                });
                row.querySelector('[data-move-up]').addEventListener('click', () => { row.previousElementSibling && rowsHost.insertBefore(row, row.previousElementSibling); updateRowButtons(); });
                row.querySelector('[data-move-down]').addEventListener('click', () => { row.nextElementSibling && rowsHost.insertBefore(row.nextElementSibling, row); updateRowButtons(); });
                updateRowButtons();
                renderPreview();
            }

            function showList() {
                form.style.display = 'none';
                managerView.style.display = 'block';
                document.getElementById('starRatingEditorHeading').textContent = 'Manage star rating cards';
            }

            function showForm(isEdit) {
                managerView.style.display = 'none';
                form.style.display = 'block';
                document.getElementById('starRatingEditorHeading').textContent = isEdit ? 'Edit star rating card' : 'Add star rating card';
                document.getElementById('starRatingTitle').focus();
            }

            function openPanel() {
                panel.classList.add('active');
                panel.setAttribute('aria-hidden', 'false');
                (managerView.style.display === 'none' ? document.getElementById('starRatingTitle') : document.getElementById('addStarRatingCard')).focus();
            }

            function closePanel() {
                panel.classList.remove('active');
                panel.setAttribute('aria-hidden', 'true');
            }

            function renderCategoryOptions() {
                const selected = [...categorySelect.selectedOptions].map(option => option.value);
                categorySelect.innerHTML = categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                [...categorySelect.options].forEach(option => { option.selected = selected.includes(option.value); });
                const filterValue = categoryFilter.value || 'all';
                categoryFilter.innerHTML = '<option value="all">All categories</option><option value="uncategorized">Uncategorized</option>' + categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                if ([...categoryFilter.options].some(option => option.value === filterValue)) categoryFilter.value = filterValue;
            }

            function renderCategoryList() {
                if (!categories.length) {
                    categoryList.innerHTML = '<div class="text-xs" style="color:var(--text-muted);">No star rating categories yet. Create one from the card form.</div>';
                    return;
                }
                categoryList.innerHTML = categories.map(category => `<div class="star-rating-category-row" data-id="${escapeHtml(category.id)}" style="display:grid; grid-template-columns:minmax(120px,1fr) 90px auto auto; align-items:center; gap:0.5rem;"><input class="form-input" data-category-name value="${escapeHtml(category.name)}" maxlength="40" required aria-label="Star rating category name"><input class="form-input" data-category-order type="number" min="0" step="1" value="${escapeHtml(category.sort_order)}" required aria-label="Star rating category sort order"><button type="button" class="btn-studio-action" data-save-category>Save</button><button type="button" class="archive-delete-button" data-delete-category>Delete</button></div>`).join('');
                categoryList.querySelectorAll('[data-save-category]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.star-rating-category-row');
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
                        const response = await fetch(categoryApiUrl + '&id=' + encodeURIComponent(row.dataset.id), {
                            method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                            body: JSON.stringify({ name: name.value.trim(), sort_order: Number(order.value) })
                        });
                        if (!response.ok) { const error = await response.json().catch(() => ({})); throw new Error(error.error || 'Unable to update category.'); }
                        await refreshCards();
                    } catch (error) {
                        alert(error.message || 'Unable to update category.');
                        button.disabled = false;
                    }
                }));
                categoryList.querySelectorAll('[data-delete-category]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.star-rating-category-row');
                    const category = categories.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${category?.name || 'this category'}”? It will be removed from assigned star cards.`)) return;
                    if (button.disabled) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(categoryApiUrl + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                        if (!response.ok) { const error = await response.json().catch(() => ({})); throw new Error(error.error || 'Unable to delete category.'); }
                        await refreshCards();
                    } catch (error) {
                        alert(error.message || 'Unable to delete category.');
                        button.disabled = false;
                    }
                }));
            }

            function rowSummary(rows) {
                return rows.map(row => `${escapeHtml(row.label)} ${Number(row.score)} / ${Number(row.max_stars)}`).join(' · ');
            }

            async function refreshCards() {
                cardList.querySelectorAll('[data-star-category-group]').forEach(group => {
                    starCategoryOpenState.set(group.dataset.starCategoryGroup, group.open);
                });
                const [response, categoryResponse] = await Promise.all([
                    fetch(apiUrl, { headers: { Accept: 'application/json' } }),
                    fetch(categoryApiUrl, { headers: { Accept: 'application/json' } })
                ]);
                if (!response.ok || !categoryResponse.ok) throw new Error('Unable to load star rating cards and categories.');
                cards = await response.json();
                categories = await categoryResponse.json();
                renderCategoryOptions();
                renderCategoryList();
                const selectedCategory = categoryFilter.value || 'all';
                const visibleCards = cards.filter(card => selectedCategory === 'all'
                    || (selectedCategory === 'uncategorized'
                        ? !(card.category_ids || []).length
                        : (card.category_ids || []).some(categoryId => String(categoryId) === selectedCategory)));
                if (!visibleCards.length) {
                    const emptyText = cards.length ? 'No star rating cards in this category.' : 'No star rating cards yet.';
                    cardList.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 text-center">${emptyText}</div>`;
                    const bulkButton = document.getElementById('bulkDeleteStarCards');
                    const bulkCount = document.getElementById('bulkDeleteStarCardsCount');
                    const selectAll = document.getElementById('selectAllStarCards');
                    const selectAllLabel = document.getElementById('selectAllStarCardsLabel');
                    if (bulkButton) { bulkButton.style.display = 'none'; bulkButton.disabled = true; }
                    if (bulkCount) bulkCount.textContent = '0';
                    if (selectAll) selectAll.checked = false;
                    if (selectAllLabel) selectAllLabel.style.display = 'none';
                    return;
                }
                const categoryById = new Map(categories.map(category => [String(category.id), category]));
                const categoryGroups = new Map();
                visibleCards.forEach(card => {
                    const categoryIds = [...new Set((card.category_ids || []).map(String))];
                    const memberships = selectedCategory === 'all'
                        ? categoryIds.map(id => categoryById.get(id)).filter(Boolean)
                        : [selectedCategory === 'uncategorized'
                            ? { id: 'uncategorized', name: 'Uncategorized' }
                            : categoryById.get(selectedCategory) || { id: selectedCategory, name: 'Uncategorized' }];
                    if (!memberships.length) memberships.push({ id: 'uncategorized', name: 'Uncategorized' });
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
                const renderCard = card => `<article class="star-rating-card rounded-xl border border-gray-200 bg-gray-50 p-3"><div class="flex items-start gap-3"><input type="checkbox" class="bulk-delete-star-checkbox mt-1 cursor-pointer" data-id="${Number(card.id)}" aria-label="Select ${escapeHtml(card.title || 'star rating card')}" style="width:1rem;height:1rem;"> <div class="flex-1 flex flex-wrap items-start justify-between gap-3"><div><h4 class="font-bold text-sm text-slate-900">${escapeHtml(card.title)}</h4><p class="text-xs text-slate-500">${escapeHtml(card.year || 'No year')} · ${rowSummary(card.rows || [])}</p><span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">${escapeHtml((card.category_names || []).join(', ') || 'Uncategorized')}</span></div><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase ${card.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}">${card.is_published ? 'Published' : 'Draft'}</span></div></div><div class="mt-3 flex flex-wrap gap-2 pl-7"><button type="button" class="btn-studio-action" data-edit="${card.id}">Edit</button><button type="button" class="btn-studio-action" data-toggle="${card.id}">${card.is_published ? 'Unpublish' : 'Publish'}</button><button type="button" class="archive-delete-button" data-delete="${card.id}">Delete</button></div></article>`;
                cardList.innerHTML = sortedGroups.map(([, group]) => `
                    <details class="star-rating-category-group" data-star-category-group="${escapeHtml(group.category.id)}" ${starCategoryOpenState.get(String(group.category.id)) ?? true ? 'open' : ''}>
                        <summary class="star-rating-category-heading">
                            <span>${escapeHtml(group.category.name)}</span>
                            <span>${group.cards.length} ${group.cards.length === 1 ? 'card' : 'cards'}</span>
                        </summary>
                        <div class="star-rating-category-items">
                            ${group.cards.slice().sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0)).map(renderCard).join('')}
                        </div>
                    </details>
                `).join('');
                cardList.querySelectorAll('[data-star-category-group]').forEach(group => {
                    group.addEventListener('toggle', () => {
                        starCategoryOpenState.set(group.dataset.starCategoryGroup, group.open);
                    });
                });
                const selectAllStar = document.getElementById('selectAllStarCards');
                const selectAllStarLabel = document.getElementById('selectAllStarCardsLabel');

                if (selectAllStarLabel) selectAllStarLabel.style.display = visibleCards.length ? 'inline-flex' : 'none';
                if (selectAllStar) selectAllStar.checked = false;

                const updateBulkBtn = () => {
                    const currentBulkBtn = document.getElementById('bulkDeleteStarCards');
                    const currentBulkCount = document.getElementById('bulkDeleteStarCardsCount');
                    const allCbs = Array.from(cardList.querySelectorAll('.bulk-delete-star-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    const selectedIds = new Set(selected.map(cb => cb.dataset.id));
                    if (currentBulkBtn) {
                        currentBulkBtn.style.display = selectedIds.size > 0 ? 'inline-block' : 'none';
                        currentBulkBtn.disabled = selectedIds.size === 0;
                    }
                    if (currentBulkCount) currentBulkCount.textContent = String(selectedIds.size);
                    if (selectAllStar) {
                        const allIds = new Set(allCbs.map(cb => cb.dataset.id));
                        selectAllStar.checked = allIds.size > 0 && selectedIds.size === allIds.size;
                    }
                };

                cardList.querySelectorAll('.bulk-delete-star-checkbox').forEach(cb => cb.addEventListener('change', () => {
                    cardList.querySelectorAll('.bulk-delete-star-checkbox').forEach(duplicate => {
                        if (duplicate.dataset.id === cb.dataset.id) duplicate.checked = cb.checked;
                    });
                    updateBulkBtn();
                }));

                if (selectAllStar) {
                    selectAllStar.onchange = () => {
                        cardList.querySelectorAll('.bulk-delete-star-checkbox').forEach(cb => { cb.checked = selectAllStar.checked; });
                        updateBulkBtn();
                    };
                }

                const bulkBtn = document.getElementById('bulkDeleteStarCards');
                if (bulkBtn) {
                    const newBulkBtn = bulkBtn.cloneNode(true);
                    bulkBtn.parentNode.replaceChild(newBulkBtn, bulkBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = [...new Set(Array.from(cardList.querySelectorAll('.bulk-delete-star-checkbox:checked')).map(cb => cb.dataset.id))];
                        if (!selectedIds.length || newBulkBtn.disabled) return;
                        if (!confirm(`Delete ${selectedIds.length} selected star rating card(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        try {
                            for (const id of selectedIds) {
                                try {
                                    const response = await fetch(apiUrl + '?id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                                    if (!response.ok) hasError = true;
                                } catch (error) { hasError = true; }
                            }
                            if (hasError) alert('Some star rating cards could not be deleted.');
                            await refreshCards();
                        } catch (error) {
                            alert(error.message || 'Unable to delete star rating cards.');
                        } finally {
                            newBulkBtn.disabled = false;
                        }
                    });
                }
                updateBulkBtn();
                cardList.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => {
                    const card = cards.find(item => String(item.id) === button.dataset.edit);
                    if (!card) return;
                    clearLocalLogoPreview();
                    form.reset();
                    document.getElementById('starRatingCardId').value = card.id;
                    document.getElementById('starRatingTitle').value = card.title || '';
                    document.getElementById('starRatingYear').value = card.year || '';
                    document.getElementById('starRatingDisplayOrder').value = card.display_order ?? 0;
                    document.getElementById('starRatingPublished').checked = !!card.is_published;
                    const selectedCategoryIds = (card.category_ids || []).map(String);
                    [...categorySelect.options].forEach(option => { option.selected = selectedCategoryIds.includes(option.value); });
                    newCategoryInput.value = '';
                    newCategoryInput.style.display = 'none';
                    rowsHost.innerHTML = '';
                    (card.rows || []).forEach(addRow);
                    currentLogo.style.display = card.logo_path ? 'flex' : 'none';
                    currentLogoImage.src = card.logo_path ? baseUrl + card.logo_path : '';
                    removeLogo.checked = false;
                    showForm(true);
                    openPanel();
                }));
                cardList.querySelectorAll('[data-toggle]').forEach(button => button.addEventListener('click', async () => {
                    if (button.disabled) return;
                    const card = cards.find(item => String(item.id) === button.dataset.toggle);
                    if (!card) return;
                    const data = new FormData();
                    data.set('title', card.title);
                    data.set('year', card.year || '');
                    data.set('display_order', card.display_order);
                    data.set('is_published', card.is_published ? '0' : '1');
                    data.set('rows', JSON.stringify(card.rows || []));
                    data.set('category_ids', JSON.stringify(card.category_ids || []));
                    button.disabled = true;
                    try {
                        const response = await fetch(apiUrl + '?id=' + encodeURIComponent(card.id), { method: 'POST', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' }, body: data });
                        if (!response.ok) { const error = await response.json().catch(() => ({})); throw new Error(error.error || 'Unable to update publication.'); }
                        await refreshCards();
                    } catch (error) {
                        alert(error.message || 'Unable to update publication.');
                        button.disabled = false;
                    }
                }));
                cardList.querySelectorAll('[data-delete]').forEach(button => button.addEventListener('click', async () => {
                    if (!confirm('Delete this star rating card?')) return;
                    if (button.disabled) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(apiUrl + '?id=' + encodeURIComponent(button.dataset.delete), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                        if (!response.ok) { const error = await response.json().catch(() => ({})); throw new Error(error.error || 'Unable to delete star rating card.'); }
                        await refreshCards();
                    } catch (error) {
                        alert(error.message || 'Unable to delete star rating card.');
                        button.disabled = false;
                    }
                }));
            }

            document.getElementById('toggleStarRatingEditor').addEventListener('click', () => { showList(); openPanel(); });
            categoryFilter.addEventListener('change', () => refreshCards().catch(error => alert(error.message || 'Unable to load star rating cards.')));
            document.getElementById('starRatingAddCategory').addEventListener('click', () => {
                newCategoryInput.style.display = newCategoryInput.style.display === 'none' ? 'block' : 'none';
                if (newCategoryInput.style.display === 'block') newCategoryInput.focus();
            });
            document.getElementById('addStarRatingCard').addEventListener('click', () => {
                clearLocalLogoPreview();
                form.reset();
                [...categorySelect.options].forEach(option => { option.selected = false; });
                newCategoryInput.value = '';
                newCategoryInput.style.display = 'none';
                document.getElementById('starRatingCardId').value = '';
                document.getElementById('starRatingDisplayOrder').value = '0';
                document.getElementById('starRatingPublished').checked = true;
                rowsHost.innerHTML = '';
                currentLogo.style.display = 'none';
                currentLogoImage.removeAttribute('src');
                logoInput.value = '';
                removeLogo.checked = false;
                addRow({ max_stars: 10, score: 0 });
                showForm(false);
            });
            document.getElementById('addStarRatingRow').addEventListener('click', () => addRow({ max_stars: 10, score: 0 }));
            form.addEventListener('input', renderPreview);
            form.addEventListener('change', renderPreview);
            logoInput.addEventListener('change', () => {
                clearLocalLogoPreview();
                if (logoInput.files?.[0]) localLogoPreviewUrl = URL.createObjectURL(logoInput.files[0]);
                renderPreview();
            });
            removeLogo.addEventListener('change', renderPreview);
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const titleInput = document.getElementById('starRatingTitle');
                const titleInvalid = !titleInput.value.trim();
                titleInput.setCustomValidity(titleInvalid ? 'Enter a title.' : '');
                titleInput.toggleAttribute('aria-invalid', titleInvalid);
                rowsHost.querySelectorAll('[data-label]').forEach(input => {
                    const labelInvalid = !input.value.trim();
                    input.setCustomValidity(labelInvalid ? 'Enter a label for this rating row.' : '');
                    input.toggleAttribute('aria-invalid', labelInvalid);
                });
                if (!form.reportValidity()) return;
                const submitButton = form.querySelector('[type="submit"]');
                if (submitButton.disabled) return;
                const isPublished = document.getElementById('starRatingPublished').checked;
                if (isPublished && !rowsHost.querySelector('[data-star-row]')) {
                    alert('Add at least one rating row before publishing.');
                    document.getElementById('addStarRatingRow').focus();
                    return;
                }
                submitButton.disabled = true;
                const data = new FormData(form);
                data.set('rows', JSON.stringify(rowValues()));
                data.set('category_ids', JSON.stringify([...categorySelect.selectedOptions].map(option => Number(option.value))));
                data.set('new_category_name', newCategoryInput.value.trim());
                data.set('is_published', document.getElementById('starRatingPublished').checked ? '1' : '0');
                data.set('remove_logo', removeLogo.checked ? '1' : '0');
                const id = document.getElementById('starRatingCardId').value;
                try {
                    const response = await fetch(apiUrl + (id ? '?id=' + encodeURIComponent(id) : ''), { method: 'POST', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' }, body: data });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); throw new Error(error.error || 'Unable to save star rating card.'); }
                    form.reset();
                    clearLocalLogoPreview();
                    rowsHost.innerHTML = '';
                    showList();
                    closePanel();
                    await refreshCards();
                } catch (error) {
                    alert(error.message || 'Unable to save star rating card.');
                } finally {
                    submitButton.disabled = false;
                }
            });
            document.getElementById('cancelStarRatingEditor').addEventListener('click', () => { form.reset(); [...categorySelect.options].forEach(option => { option.selected = false; }); newCategoryInput.value = ''; newCategoryInput.style.display = 'none'; clearLocalLogoPreview(); rowsHost.innerHTML = ''; showList(); });
            document.getElementById('closeStarRatingEditor').addEventListener('click', closePanel);
            panel.addEventListener('click', event => { if (event.target === panel) closePanel(); });
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel.classList.contains('active')) closePanel(); });
            document.getElementById('starRatingTitle').addEventListener('input', renderPreview);
            document.getElementById('starRatingYear').addEventListener('input', renderPreview);
            refreshCards().catch(error => alert(error.message || 'Unable to load star rating cards.'));
        })();

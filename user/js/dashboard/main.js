        (function () {
            const loader = document.getElementById('page-loader');
            const hideLoader = () => {
                if (loader) {
                    loader.classList.add('hidden');
                }
            };

            document.querySelectorAll('.logo-refresh-trigger').forEach((link) => {
                link.addEventListener('click', function (event) {
                    const target = this.getAttribute('data-target') || this.href;
                    event.preventDefault();
                    loader && loader.classList.remove('hidden');
                    const currentUrl = window.location.href.split('#')[0];
                    if (target && target.split('#')[0] === currentUrl.split('#')[0]) {
                        window.location.reload();
                        return;
                    }
                    window.location.href = target;
                });
            });

            setTimeout(hideLoader, 90);
            window.addEventListener('load', hideLoader);
            window.addEventListener('beforeunload', () => { if (loader) loader.classList.remove('hidden'); });
            window.addEventListener('pagehide', () => { if (loader) loader.classList.add('hidden'); });
        })();

        // --- Dark Mode Logic ---
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
        const themeToggleBtn = document.getElementById('theme-toggle');

        if (document.documentElement.classList.contains('dark')) {
            themeToggleLightIcon.classList.remove('hidden');
        } else {
            themeToggleDarkIcon.classList.remove('hidden');
        }

        themeToggleBtn.addEventListener('click', function() {
            themeToggleDarkIcon.classList.toggle('hidden');
            themeToggleLightIcon.classList.toggle('hidden');

            const isDarkNow = document.documentElement.classList.contains('dark');
            const nextMode = isDarkNow ? 'light' : 'dark';

            document.documentElement.classList.toggle('dark', nextMode === 'dark');
            localStorage.setItem('color-theme', nextMode);
            localStorage.setItem('iris-theme', nextMode);
            window.IRISRankingHistory?.themeChanged?.();
            loadPublishedScannerGraphs();
        });

        function formatSummaryCardValue(value, precision) {
            const raw = String(value ?? '').trim();
            if (raw === '') return '';
            const normalized = raw.replace(/,/g, '');
            if (!/^-?(?:\d+|\d*\.\d+)$/.test(normalized)) {
                return raw;
            }
            const number = Number(normalized);
            if (!Number.isFinite(number)) {
                return raw;
            }
            const precisionValue = Number(precision);
            const digits = Number.isFinite(precisionValue)
                ? Math.max(0, Math.min(2, precisionValue))
                : 2;
            return Number(number).toLocaleString(undefined, {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            });
        }

        const dashboardBaseUrl = window.IRIS_DASHBOARD_CONFIG.baseUrl;
        const ratingStarPath = 'M12 2.5 14.9 8.4l6.6 1-4.75 4.62 1.12 6.53L12 17.47l-5.87 3.08 1.12-6.53L2.5 9.4l6.6-1L12 2.5Z';
        let starRatingCardsData = [];
        let starRatingCategories = [];
        let activeStarRatingCategorySlug = '';

        function renderRatingStars(maxStars, score, prefix) {
            return Array.from({ length: maxStars }, (_, index) => {
                const remaining = score - index;
                const gradientId = `${prefix}-half-${index}`;
                const isHalf = remaining >= 0.5 && remaining < 1;
                const fill = remaining >= 1 ? '#E0A70D' : isHalf ? `url(#${gradientId})` : 'none';
                const gradient = isHalf ? `<defs><linearGradient id="${gradientId}"><stop offset="50%" stop-color="#E0A70D"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs>` : '';
                const stroke = remaining >= 0.5 ? '#E0A70D' : '#9CA3AF';
                return `<svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" aria-hidden="true">${gradient}<path d="${ratingStarPath}" fill="${fill}" stroke="${stroke}" stroke-width="1.5" stroke-linejoin="round"/></svg>`;
            }).join('');
        }

        function updateStarRatingCategoryUrl(slug, replace = false) {
            const url = new URL(window.location.href);
            if (slug) url.searchParams.set('star_category', slug);
            else url.searchParams.delete('star_category');
            window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
        }

        function renderStarRatingCategoryChips() {
            const filter = document.getElementById('star-rating-category-filter');
            const chips = document.getElementById('star-rating-category-chips');
            if (!filter || !chips) return;
            if (!starRatingCategories.length) {
                filter.classList.add('hidden');
                chips.innerHTML = '';
                return;
            }
            filter.classList.remove('hidden');
            const options = [{ slug: '', name: 'All' }, ...starRatingCategories];
            chips.innerHTML = options.map(category => {
                const selected = activeStarRatingCategorySlug === category.slug;
                return `<button type="button" class="shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 ${selected ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 hover:bg-green-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'}" data-star-category-slug="${escapeHtmlDashboard(category.slug)}" aria-pressed="${selected ? 'true' : 'false'}">${escapeHtmlDashboard(category.name)}</button>`;
            }).join('');
            chips.querySelectorAll('button[data-star-category-slug]').forEach(button => button.addEventListener('click', () => {
                const slug = button.dataset.starCategorySlug || '';
                if (slug === activeStarRatingCategorySlug) return;
                activeStarRatingCategorySlug = slug;
                updateStarRatingCategoryUrl(slug);
                renderStarRatingCategoryChips();
                renderStarRatingCards(starRatingCardsData);
            }));
        }

        function restoreStarRatingCategoryFromUrl(replaceUnknown = false) {
            const requested = new URL(window.location.href).searchParams.get('star_category') || '';
            const match = starRatingCategories.find(category => category.slug === requested);
            activeStarRatingCategorySlug = match ? match.slug : '';
            if (requested && !match && replaceUnknown) updateStarRatingCategoryUrl('', true);
            renderStarRatingCategoryChips();
            renderStarRatingCards(starRatingCardsData);
        }

        window.addEventListener('popstate', () => restoreStarRatingCategoryFromUrl(true));

        function renderStarRatingCards(cards) {
            const section = document.getElementById('star-rating-cards-section');
            const grid = document.getElementById('star-rating-cards-grid');
            if (!section || !grid) return;
            const publishedCards = Array.isArray(cards) ? cards.filter(card => card && (card.is_published === true || card.is_published === 1 || card.is_published === '1')) : [];
            if (!publishedCards.length) {
                section.classList.add('hidden');
                grid.innerHTML = '';
                return;
            }
            section.classList.remove('hidden');
            const visibleCards = publishedCards.filter(card => !activeStarRatingCategorySlug || (Array.isArray(card.category_slugs) && card.category_slugs.includes(activeStarRatingCategorySlug)));
            if (!visibleCards.length) {
                grid.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">No published star rating cards are available in this category.</div>';
                return;
            }
            grid.innerHTML = visibleCards.map((card, cardIndex) => {
                const title = escapeHtmlDashboard(card.title || 'Star ratings');
                const logo = card.logo_path ? `<img src="${escapeHtmlDashboard(dashboardBaseUrl + card.logo_path)}" alt="${title} logo" class="mb-2 max-h-14 max-w-40 object-contain">` : '';
                const year = card.year ? `<div class="mt-3 border-y border-gray-200 py-1.5 text-center text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">${escapeHtmlDashboard(card.year)}</div>` : '';
                const rows = (Array.isArray(card.rows) ? card.rows : []).map((row, rowIndex) => {
                    const maxStars = Math.max(1, Math.min(10, Math.trunc(Number(row.max_stars) || 1)));
                    const rawScore = Number(row.score);
                    const score = Math.max(0, Math.min(maxStars, Number.isFinite(rawScore) ? rawScore : 0));
                    const scoreText = Number.isInteger(score) ? String(score) : score.toFixed(1);
                    const label = String(row.label || 'Category');
                    const accessibleLabel = `${label}: ${scoreText} out of ${maxStars} stars`;
                    return `<div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-gray-200 py-2.5 dark:border-gray-700" aria-label="${escapeHtmlDashboard(accessibleLabel)}" title="${escapeHtmlDashboard(accessibleLabel)}"><div class="flex max-w-full flex-wrap items-center gap-0.5">${renderRatingStars(maxStars, score, `rating-${cardIndex}-${rowIndex}`)}<span class="ml-1 whitespace-nowrap text-xs font-medium text-gray-600 dark:text-gray-300">${scoreText} / ${maxStars}</span></div><span class="min-w-0 break-words text-sm font-semibold text-gray-800 dark:text-gray-100">${escapeHtmlDashboard(label)}</span></div>`;
                }).join('');
                return `<article class="iris-hover-card rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"><header class="flex min-h-20 flex-col items-center justify-center text-center">${logo}<h3 class="text-base font-bold text-gray-900 dark:text-white">${title}</h3></header>${year}<div class="mt-2">${rows}</div></article>`;
            }).join('');
        }

        let summaryCardsData = [];
        let summaryCardCategories = [];
        let activeSummaryCategorySlug = '';
        let summaryCardDefaultCategorySlug = '';

        function updateSummaryCategoryUrl(slug, replace = false) {
            const url = new URL(window.location.href);
            if (slug) url.searchParams.set('category', slug);
            else url.searchParams.delete('category');
            const method = replace ? 'replaceState' : 'pushState';
            window.history[method]({}, '', url);
        }

        function renderSummaryCategoryChips() {
            const filter = document.getElementById('summaryCardCategoryFilter');
            const chips = document.getElementById('summaryCardCategoryChips');
            if (!filter || !chips) return;
            if (summaryCardCategories.length === 0) {
                filter.classList.add('hidden');
                chips.innerHTML = '';
                return;
            }
            filter.classList.remove('hidden');
            const options = [{ slug: '', name: 'All' }, ...summaryCardCategories];
            chips.innerHTML = options.map(category => {
                const selected = activeSummaryCategorySlug === category.slug;
                return `<button type="button" class="shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 ${selected ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 hover:bg-green-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'}" data-category-slug="${escapeHtmlDashboard(category.slug)}" aria-pressed="${selected ? 'true' : 'false'}">${escapeHtmlDashboard(category.name)}</button>`;
            }).join('');
            chips.querySelectorAll('button[data-category-slug]').forEach(button => button.addEventListener('click', () => {
                const slug = button.dataset.categorySlug || '';
                if (slug === activeSummaryCategorySlug) return;
                activeSummaryCategorySlug = slug;
                updateSummaryCategoryUrl(slug);
                renderSummaryCategoryChips();
                renderSummaryCards(summaryCardsData);
            }));
        }

        function restoreSummaryCategoryFromUrl(replaceUnknown = false) {
            const requested = new URL(window.location.href).searchParams.get('category') || '';
            const initialSlug = requested || summaryCardDefaultCategorySlug;
            const category = summaryCardCategories.find(item => item.slug === initialSlug);
            activeSummaryCategorySlug = category ? category.slug : '';
            if (requested && !category && replaceUnknown) updateSummaryCategoryUrl('', true);
            else if (!requested && activeSummaryCategorySlug && replaceUnknown) updateSummaryCategoryUrl(activeSummaryCategorySlug, true);
            renderSummaryCategoryChips();
            renderSummaryCards(summaryCardsData);
        }

        window.addEventListener('popstate', () => restoreSummaryCategoryFromUrl());

        let pinnedSummaryInfoControl = null;
        document.addEventListener('click', event => {
            if (!pinnedSummaryInfoControl || pinnedSummaryInfoControl.contains(event.target)) return;
            const trigger = pinnedSummaryInfoControl.querySelector('[data-info-trigger]');
            const panel = pinnedSummaryInfoControl.querySelector('[data-info-panel]');
            if (panel) panel.hidden = true;
            trigger?.setAttribute('aria-expanded', 'false');
            pinnedSummaryInfoControl = null;
        });

        function renderSummaryCards(cards) {
            const grid = document.getElementById('summaryCardsGrid');
            if (!grid) return;
            if (pinnedSummaryInfoControl) {
                const trigger = pinnedSummaryInfoControl.querySelector('[data-info-trigger]');
                const panel = pinnedSummaryInfoControl.querySelector('[data-info-panel]');
                if (panel) panel.hidden = true;
                trigger?.setAttribute('aria-expanded', 'false');
                pinnedSummaryInfoControl = null;
            }
            const publishedCards = Array.isArray(cards) ? cards.filter(card => card && (card.is_published === true || card.is_published === 1 || card.is_published === '1')) : [];
            const visibleCards = publishedCards.filter(card => !activeSummaryCategorySlug || (Array.isArray(card.category_slugs) && card.category_slugs.includes(activeSummaryCategorySlug)));
            if (!visibleCards.length) {
                const message = activeSummaryCategorySlug
                    ? 'There are no published summary cards in this category yet.'
                    : 'No performance snapshot cards are currently published.';
                grid.innerHTML = `<div class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 text-center text-sm text-gray-500 dark:text-gray-400 py-10">${message}</div>`;
                return;
            }

            grid.innerHTML = visibleCards
                .slice()
                .sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0))
                .map(card => {
                    const mainValue = formatSummaryCardValue(card.main_value, card.display_precision ?? 2);
                    const secondaryValue = card.secondary_value ? formatSummaryCardValue(card.secondary_value, card.display_precision ?? 2) : '';
                    const secondaryLabel = card.secondary_label ? escapeHtmlDashboard(card.secondary_label) : '';
                    const description = card.description ? escapeHtmlDashboard(card.description) : '';
                    const secondaryDescription = card.secondary_description ? escapeHtmlDashboard(card.secondary_description) : '';
                    const infoText = card.info_text ? escapeHtmlDashboard(card.info_text) : '';
                    const customFields = Object.values(card.custom_fields || {}).filter(field => field && field.value).map(field =>
                        `<p class="mt-2 text-sm text-gray-700 dark:text-gray-200"><span class="font-semibold">${escapeHtmlDashboard(field.label)}:</span> ${escapeHtmlDashboard(field.value)}</p>`
                    ).join('');
                    const mainLabel = escapeHtmlDashboard(card.main_label || 'Current snapshot');
                    const yearDate = escapeHtmlDashboard(card.year_date || '');
                    const infoLabel = escapeHtmlDashboard(`Information about ${card.title || 'this card'}`);
                    const historyControl = Number(card.history_count || 0) > 1 ? `
                        <div class="mt-3">
                            <button type="button" class="summary-card-history-toggle text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300" data-id="${escapeHtmlDashboard(card.id)}" aria-expanded="false">View Historical Data</button>
                            <div class="summary-card-history-panel mt-2 hidden rounded-lg border border-gray-200 p-3 dark:border-gray-700" data-id="${escapeHtmlDashboard(card.id)}" role="status" aria-live="polite"></div>
                        </div>` : '';
                    return `
                        <article class="iris-hover-card summary-card-shell relative overflow-visible rounded-2xl p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="summary-card-title text-[10px] font-bold uppercase tracking-[0.14em]">${escapeHtmlDashboard(card.title || 'Performance Snapshot')}</div>
                                    <div class="mt-2 whitespace-nowrap text-3xl font-extrabold text-gray-900 dark:text-white">${escapeHtmlDashboard(mainValue)}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    ${yearDate ? `<span class="summary-card-year-badge rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide">${yearDate}</span>` : ''}
                                    ${infoText ? `<div class="summary-card-info-control relative z-40"><button type="button" class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-gray-500/40 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" data-info-trigger aria-label="${infoLabel}" aria-controls="summary-card-info-${escapeHtmlDashboard(card.id)}" aria-expanded="false" title="More information"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></button><div id="summary-card-info-${escapeHtmlDashboard(card.id)}" data-info-panel role="tooltip" hidden class="absolute right-0 top-full z-40 mt-2 w-64 max-w-[75vw] rounded-lg border border-gray-200 bg-white p-3 text-left text-xs font-normal normal-case text-gray-700 shadow-xl dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">${infoText}</div></div>` : ''}
                                </div>
                            </div>
                            <div class="summary-card-label mt-2 text-xs font-semibold uppercase tracking-wide">${mainLabel}</div>
                            ${secondaryLabel || secondaryValue ? `<div class="summary-card-second-row mt-4 flex items-baseline justify-between gap-3 border-t pt-3 text-xs">
                                <span class="summary-card-label">${secondaryLabel}</span>
                                <span class="font-bold text-gray-900 dark:text-white">${escapeHtmlDashboard(secondaryValue)}</span>
                            </div>` : ''}
                            ${secondaryDescription ? `<p class="mt-2 text-sm italic text-gray-500 dark:text-gray-400">${secondaryDescription}</p>` : ''}
                            ${description ? `<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">${description}</p>` : ''}
                            ${customFields}
                            ${historyControl}
                        </article>`;
                }).join('');

            grid.querySelectorAll('.summary-card-info-control').forEach(wrapper => {
                const trigger = wrapper.querySelector('[data-info-trigger]');
                const panel = wrapper.querySelector('[data-info-panel]');
                if (!trigger || !panel) return;
                const open = () => { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); };
                const close = () => { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
                trigger.addEventListener('mouseenter', () => { if (!pinnedSummaryInfoControl) open(); });
                trigger.addEventListener('focus', () => { if (!pinnedSummaryInfoControl) open(); });
                wrapper.addEventListener('mouseleave', event => { if (pinnedSummaryInfoControl !== wrapper && !panel.contains(event.relatedTarget)) close(); });
                trigger.addEventListener('blur', () => { if (pinnedSummaryInfoControl !== wrapper) close(); });
                trigger.addEventListener('click', () => {
                    if (pinnedSummaryInfoControl === wrapper) {
                        pinnedSummaryInfoControl = null;
                        close();
                        return;
                    }
                    if (pinnedSummaryInfoControl) {
                        const previousTrigger = pinnedSummaryInfoControl.querySelector('[data-info-trigger]');
                        const previousPanel = pinnedSummaryInfoControl.querySelector('[data-info-panel]');
                        if (previousPanel) previousPanel.hidden = true;
                        previousTrigger?.setAttribute('aria-expanded', 'false');
                    }
                    pinnedSummaryInfoControl = wrapper;
                    open();
                });
                trigger.addEventListener('keydown', event => {
                    if (event.key !== 'Escape') return;
                    if (pinnedSummaryInfoControl === wrapper) pinnedSummaryInfoControl = null;
                    close();
                    trigger.blur();
                });
            });

            document.querySelectorAll('.summary-card-history-toggle').forEach(button => {
                button.addEventListener('click', async () => {
                    if (button.disabled) return;
                    const panel = [...document.querySelectorAll('.summary-card-history-panel')].find(item => item.dataset.id === button.dataset.id);
                    if (!panel) return;
                    if (!panel.classList.contains('hidden')) {
                        panel.classList.add('hidden');
                        button.setAttribute('aria-expanded', 'false');
                        return;
                    }
                    panel.classList.remove('hidden');
                    button.setAttribute('aria-expanded', 'true');
                    if (panel.dataset.loaded === 'true') return;
                    panel.textContent = 'Loading published history...';
                    button.disabled = true;
                    try {
                        const response = await fetch(window.IRIS_DASHBOARD_CONFIG.irisApiUrl + '?resource=summary_card_history&id=' + encodeURIComponent(button.dataset.id), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.error || 'Unable to load published history.');
                        const history = (result.periods || []).filter(period => !period.is_current_public);
                        panel.replaceChildren();
                        if (!history.length) {
                            panel.textContent = 'No published historical periods.';
                            panel.dataset.loaded = 'true';
                            return;
                        }
                        const label = document.createElement('label');
                        label.className = 'block text-xs font-semibold';
                        label.textContent = 'Period';
                        const select = document.createElement('select');
                        select.className = 'mt-1 block w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800';
                        history.forEach(period => select.add(new Option(period.period_label || period.period_key, period.period_key)));
                        const detail = document.createElement('div');
                        detail.className = 'mt-3 space-y-1 text-xs';
                        const renderPeriod = () => {
                            const period = history.find(item => item.period_key === select.value);
                            detail.replaceChildren();
                            for (const [name, value] of Object.entries({
                                Value: period?.main_value, Label: period?.main_label, 'Secondary label': period?.secondary_label,
                                'Secondary value': period?.secondary_value, 'Secondary description': period?.secondary_description,
                                Description: period?.description, Information: period?.info_text
                            })) {
                                if (value == null || value === '') continue;
                                const line = document.createElement('p');
                                line.textContent = `${name}: ${value}`;
                                detail.append(line);
                            }
                            for (const field of Object.values(period?.custom_fields || {})) {
                                if (!field?.value) continue;
                                const line = document.createElement('p');
                                line.textContent = `${field.label}: ${field.value}`;
                                detail.append(line);
                            }
                        };
                        select.addEventListener('change', renderPeriod);
                        label.append(select);
                        panel.append(label, detail);
                        renderPeriod();
                        panel.dataset.loaded = 'true';
                    } catch (error) {
                        panel.textContent = error.message;
                    } finally {
                        button.disabled = false;
                    }
                });
            });

        }

        async function loadSummaryCards() {
            try {
                const response = await fetch(window.IRIS_DASHBOARD_CONFIG.dashboardGraphsApiUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Unable to load summary cards');
                const payload = await response.json();
                summaryCardsData = payload.cards || [];
                summaryCardCategories = payload.categories || [];
                summaryCardDefaultCategorySlug = payload.summary_cards_default_category || '';
                restoreSummaryCategoryFromUrl(true);
                starRatingCardsData = payload.star_rating_cards || [];
                starRatingCategories = payload.star_rating_categories || [];
                restoreStarRatingCategoryFromUrl(true);
            } catch (error) {
                const grid = document.getElementById('summaryCardsGrid');
                if (grid) {
                    grid.innerHTML = '<div class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200 px-4 py-6 text-sm text-center">Summary cards could not be loaded.</div>';
                }
            }
        }

        window.addEventListener('iris:data-changed', () => { void loadSummaryCards(); });

        // --- Published Observatory Graphs ---
        let chartInstances = [];
        let publishedGraphLayout = 'side-by-side';
        const publishedGraphLayoutSelect = document.getElementById('publishedGraphLayout');

        publishedGraphLayoutSelect?.addEventListener('change', () => {
            publishedGraphLayout = publishedGraphLayoutSelect.value === 'one-per-row' ? 'one-per-row' : 'side-by-side';
            document.querySelectorAll('[data-scope-graphs]').forEach(scopeGrid => {
                scopeGrid.classList.toggle('lg:grid-cols-2', publishedGraphLayout === 'side-by-side');
                scopeGrid.classList.toggle('grid-cols-1', true);
            });
            requestAnimationFrame(() => chartInstances.forEach(chart => chart?.resize?.()));
        });

        // Live Filters
        const canManagePublishedGraphs = window.IRIS_DASHBOARD_CONFIG.canManagePublishedGraphs;

        function renderPublishedScannerGraphs(graphs) {
            const grid = document.getElementById('scannerPublishedGraphsGrid');
            const empty = document.getElementById('scannerPublishedGraphsEmpty');
            if (!grid) return;

            const replacedCharts = new Set();
            grid.querySelectorAll('.scanner-published-card').forEach(el => {
                el._chartResizeObserver?.disconnect?.();
                el._publishedChart?.dispose?.();
                if (el._publishedChart) replacedCharts.add(el._publishedChart);
                el.remove();
            });
            grid.querySelectorAll('.scanner-published-scope').forEach(el => el.remove());
            chartInstances = chartInstances.filter(chart => !replacedCharts.has(chart));
            if (!Array.isArray(graphs) || graphs.length === 0) {
                if (empty) empty.style.display = '';
                return;
            }
            if (empty) empty.style.display = 'none';

            const scopes = new Map();
            graphs.forEach(graph => {
                const scope = String(graph.scope || '').trim() || 'General';
                if (!scopes.has(scope)) scopes.set(scope, []);
                scopes.get(scope).push(graph);
            });
            const orderedScopes = [...scopes.entries()].sort(([left], [right]) => {
                if (left === 'General') return 1;
                if (right === 'General') return -1;
                return left.localeCompare(right);
            });
            let chartIndex = 0;
            orderedScopes.forEach(([scope, scopedGraphs]) => {
                const scopeCard = document.createElement('section');
                scopeCard.className = 'scanner-published-scope col-span-full rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800';
                scopeCard.innerHTML = `<header class="mb-4 flex items-center justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-700"><h3 class="text-base font-bold text-gray-900 dark:text-white">${escapeHtmlDashboard(scope)}</h3><span class="text-xs font-semibold text-gray-500 dark:text-gray-300">${scopedGraphs.length} graph${scopedGraphs.length === 1 ? '' : 's'}</span></header><div class="grid grid-cols-1 gap-6 ${publishedGraphLayout === 'side-by-side' ? 'lg:grid-cols-2' : ''}" data-scope-graphs></div>`;
                grid.appendChild(scopeCard);
                const scopeGrid = scopeCard.querySelector('[data-scope-graphs]');
                scopedGraphs.forEach(graph => {
                const card = document.createElement('div');
                card.className = 'iris-hover-card scanner-published-card bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm';
                const chartId = `scannerPublishedChart_${chartIndex++}`;
                card.innerHTML = `
                    <div class="flex items-start justify-between gap-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                                <i class="fa-solid fa-chart-line text-emerald-500 mr-2"></i>${escapeHtmlDashboard(graph.title || 'Published Observatory Chart')}
                            </h3>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">PUBLISHED</span>
                            ${canManagePublishedGraphs ? `<button type="button" class="published-graph-delete inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 dark:text-amber-300 dark:bg-amber-900/30 dark:border-amber-800" data-graph-id="${escapeHtmlDashboard(graph.id)}" title="Hide this published chart from the Observatory"><i class="fa-solid fa-eye-slash mr-1" aria-hidden="true"></i> Unpublish</button>` : ''}
                        </div>
                    </div>
                    <div id="${chartId}" class="w-full h-72 pt-4"></div>`;
                scopeGrid.appendChild(card);

                card.querySelector('.published-graph-delete')?.addEventListener('click', async event => {
                    const button = event.currentTarget;
                    if (!confirm(`Remove "${graph.title || 'this published chart'}" from the Observatory? It will remain saved and can be published again later.`)) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(window.IRIS_DASHBOARD_CONFIG.irisApiUrl + '?resource=graphs&id=' + encodeURIComponent(graph.id) + '&action=unpublish', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ published: false })
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(payload.error || 'Unable to unpublish chart.');
                        loadPublishedScannerGraphs();
                    } catch (error) {
                        button.disabled = false;
                        alert(error.message);
                    }
                });

                const elem = document.getElementById(chartId);
                if (!elem) return;
                const chart = echarts.init(elem);
                card._publishedChart = chart;
                card._publishedGraph = graph;
                if (typeof ResizeObserver !== 'undefined') {
                    card._chartResizeObserver = new ResizeObserver(() => chart.resize());
                    card._chartResizeObserver.observe(elem);
                }
                chartInstances.push(chart);
                chart.setOption(window.IRISChartBuilder.buildSavedGraphOption(graph, {
                    width: elem.clientWidth,
                    theme: { dark: document.documentElement.classList.contains('dark') }
                }));
                });
            });
        }

        function escapeHtmlDashboard(value) {
            return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
        }

        function loadPublishedScannerGraphs() {
            fetch(window.IRIS_DASHBOARD_CONFIG.dashboardGraphsApiUrl, { cache: 'no-store', headers: { 'Accept': 'application/json' } })
                .then(res => {
                    if (!res.ok) throw new Error('Published graph request failed');
                    return res.json();
                })
                .then(data => {
                    window.IRISFieldColors = data.field_colors || window.IRISFieldColors || {};
                    window.IRISFieldColorUpdatedAt = data.field_color_updated_at || window.IRISFieldColorUpdatedAt || {};
                    renderPublishedScannerGraphs(data.graphs || []);
                })
                .catch(() => {
                    const empty = document.getElementById('scannerPublishedGraphsEmpty');
                    if (empty) empty.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg mr-1"></i> Published scanner graphs could not be loaded.';
                });
        }

        new MutationObserver(() => {
            const theme = window.IRISChartBuilder.getChartTheme({ dark: document.documentElement.classList.contains('dark') });
            window.IRISRankingHistory?.themeChanged();
            document.querySelectorAll('.scanner-published-card').forEach(card => {
                const element = card.querySelector('[id^="scannerPublishedChart_"]');
                if (element && card._publishedGraph && card._publishedChart) {
                    card._publishedChart.setOption(window.IRISChartBuilder.buildSavedGraphOption(card._publishedGraph, {
                        width: element.clientWidth,
                        theme: { dark: document.documentElement.classList.contains('dark') }
                    }), true);
                }
            });
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        document.addEventListener('DOMContentLoaded', () => {
            loadSummaryCards();
            loadPublishedScannerGraphs();
            window.addEventListener('resize', () => {
                chartInstances.forEach(chart => chart?.resize?.());
                window.IRISRankingHistory?.resize();
            });
        });

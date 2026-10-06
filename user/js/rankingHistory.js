/**
 * Purpose: User-facing Observatory behavior for ranking history.
 */
(() => {
  const chartHost = document.getElementById('rankingChart');
  const displayModeSelect = document.getElementById('rankingDisplayMode');
  const graphLayoutSelect = document.getElementById('rankingGraphLayout');
  const graphLayoutControl = document.getElementById('rankingGraphLayoutControl');
  const organizationSelect = document.getElementById('rankingOrganizationFilter');
  const listControl = document.getElementById('rankingListControl');
  const listSelect = document.getElementById('rankingListFilter');
  const fromSelect = document.getElementById('rankingYearFrom');
  const toSelect = document.getElementById('rankingYearTo');
  const allYearsButton = document.getElementById('rankingAllYears');
  const resetDefaultsButton = document.getElementById('rankingResetDefaults');
  const filtersControl = document.getElementById('rankingFiltersControl');
  const filtersPopover = document.getElementById('rankingFiltersPopover');
  const api = document.currentScript?.dataset.api;
  if (!chartHost || !organizationSelect || !listControl || !listSelect || !fromSelect || !toSelect || !api) return;

  let rows = [];
  let charts = [];
  let pinnedRankingInfoControl = null;
  let allYearsActive = true;
  let defaultDisplayState = null;
  let colorOverrides = {};
  try {
    const stored = JSON.parse(localStorage.getItem('iris-ranking-series-colors') || '{}');
    if (stored && typeof stored === 'object' && !Array.isArray(stored)) colorOverrides = stored;
  } catch (error) {}

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
  const isNumeric = row => row.rank_value !== null && row.rank_value !== '' && Number.isFinite(Number(row.rank_value));
  const numericRows = () => rows.filter(isNumeric);
  const rankingRows = () => rows.filter(row => row.year !== null && row.year !== '' && Number.isFinite(Number(row.year)));
  const selectedOrganization = () => organizationSelect.value || 'all';
  const selectedList = () => listSelect.value || 'all';
  const selectedDisplayMode = () => displayModeSelect?.value === 'matrix' ? 'matrix' : 'charts';
  const selectedGraphLayout = () => graphLayoutSelect?.value === 'side-by-side' ? 'side-by-side' : 'one-per-row';

  document.addEventListener('click', event => {
    if (!pinnedRankingInfoControl || pinnedRankingInfoControl.contains(event.target)) return;
    const trigger = pinnedRankingInfoControl.querySelector('button');
    const panel = pinnedRankingInfoControl.querySelector('[role="tooltip"]');
    if (panel) panel.hidden = true;
    trigger?.setAttribute('aria-expanded', 'false');
    pinnedRankingInfoControl = null;
  });

  function selectedYears() {
    if (allYearsActive) return null;
    const from = Number(fromSelect.value);
    const to = Number(toSelect.value);
    return Number.isFinite(from) && Number.isFinite(to) ? { from, to } : null;
  }

  function inSelectedRange(row) {
    const range = selectedYears();
    return !range || (Number(row.year) >= range.from && Number(row.year) <= range.to);
  }

  function rowsForOrganization() {
    return numericRows().filter(row => selectedOrganization() === 'all' || row.organization === selectedOrganization());
  }

  function rankingRowsForOrganization() {
    return rankingRows().filter(row => selectedOrganization() === 'all' || row.organization === selectedOrganization());
  }

  function updateListOptions() {
    if (selectedOrganization() === 'all') {
      listSelect.value = 'all';
      listControl.classList.add('hidden');
      listControl.classList.remove('flex');
      return;
    }
    const types = [...new Set(rankingRowsForOrganization().filter(inSelectedRange).map(row => String(row.ranking_type || '')))]
      .filter(Boolean);
    const previous = selectedList();
    listSelect.replaceChildren(new Option('All lists', 'all'));
    for (const type of types) listSelect.add(new Option(type, type));
    listSelect.value = types.includes(previous) ? previous : 'all';
    listControl.classList.toggle('hidden', selectedOrganization() === 'all' || types.length <= 1);
    listControl.classList.toggle('flex', selectedOrganization() !== 'all' && types.length > 1);
  }

  function positionFiltersPopover() {
    if (!filtersControl?.open || !filtersPopover) return;
    const trigger = filtersControl.querySelector('summary');
    if (!trigger) return;
    const anchor = trigger.getBoundingClientRect();
    const margin = 12;
    const below = anchor.bottom + 8;
    // Clamp the popover's max-height to the available space below the anchor
    const availableBelow = window.innerHeight - below - margin;
    filtersPopover.style.maxHeight = `${Math.max(120, availableBelow)}px`;
    const panel = filtersPopover.getBoundingClientRect();
    const left = Math.max(margin, Math.min(anchor.right - panel.width, window.innerWidth - panel.width - margin));
    filtersPopover.style.left = `${Math.round(left)}px`;
    filtersPopover.style.top = `${Math.round(below)}px`;
  }

  filtersControl?.addEventListener('toggle', () => {
    if (!filtersControl.open) return;
    // Double rAF: first frame renders the popover, second frame measures it after layout
    requestAnimationFrame(() => requestAnimationFrame(positionFiltersPopover));
  });
  // Re-position when the inner Year range sub-details is expanded/collapsed (changes panel height)
  document.getElementById('rankingYearRangeControl')?.addEventListener('toggle', () => {
    requestAnimationFrame(positionFiltersPopover);
  });
  document.addEventListener('click', event => {
    if (filtersControl?.open && !filtersControl.contains(event.target)) filtersControl.open = false;
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && filtersControl?.open) filtersControl.open = false;
  });
  window.addEventListener('resize', positionFiltersPopover);
  window.addEventListener('scroll', positionFiltersPopover, true);
  // Close popover when the ranking history section's scroll container scrolls
  document.getElementById('ranking-history')?.addEventListener('scroll', () => {
    if (filtersControl?.open) filtersControl.open = false;
  }, { passive: true });


  function renderYearOptions() {
    const years = [...new Set(rankingRows().map(row => Number(row.year)))].filter(Number.isFinite).sort((left, right) => left - right);
    const options = years.map(year => new Option(String(year), String(year)));
    fromSelect.replaceChildren(...options.map(option => option.cloneNode(true)));
    toSelect.replaceChildren(...options.map(option => option.cloneNode(true)));
    fromSelect.disabled = years.length === 0;
    toSelect.disabled = years.length === 0;
    if (!years.length) {
      fromSelect.add(new Option('Unavailable', ''));
      toSelect.add(new Option('Unavailable', ''));
      return;
    }
    fromSelect.value = String(years[0]);
    toSelect.value = String(years.at(-1));
  }

  function chartTheme() {
    return window.IRISChartBuilder.getChartTheme({ dark: document.documentElement.classList.contains('dark') });
  }

  function seriesColor(name, index) {
    const colors = window.IRISChartColors?.DEFAULT_CHART_COLORS || ['#0F766E', '#D97706', '#2563EB', '#DB2777', '#65A30D', '#0891B2'];
    return colorOverrides[name] || colors[index % colors.length];
  }

  function rankingChartTitle(organization, rankingType) {
    const organizationName = String(organization || '').trim();
    const typeName = String(rankingType || '').trim();
    if (!organizationName) return typeName || 'Ranking';
    if (!typeName) return organizationName;
    const normalizedOrganization = organizationName.toLowerCase();
    const normalizedType = typeName.toLowerCase();
    return normalizedType === normalizedOrganization || normalizedType.startsWith(`${normalizedOrganization} `)
      ? typeName
      : `${organizationName} ${typeName}`;
  }

  function disposeCharts() {
    charts.forEach(chart => chart?.dispose?.());
    charts = [];
    if (pinnedRankingInfoControl) {
      const trigger = pinnedRankingInfoControl.querySelector('button');
      const panel = pinnedRankingInfoControl.querySelector('[role="tooltip"]');
      if (panel) panel.hidden = true;
      trigger?.setAttribute('aria-expanded', 'false');
      pinnedRankingInfoControl = null;
    }
  }

  function showEmpty(message = 'No numeric rankings found for the selected filters.') {
    disposeCharts();
    chartHost.className = rankingChartGridClass();
    chartHost.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-300"><i class="fa-solid fa-folder-open mb-2 text-2xl text-gray-400" aria-hidden="true"></i><p>${escapeHtml(message)}</p></div>`;
  }

  function rankingChartGridClass() {
    return selectedGraphLayout() === 'side-by-side'
      ? 'grid min-h-64 w-full grid-cols-1 gap-4 xl:grid-cols-2'
      : 'grid min-h-64 w-full grid-cols-1 gap-4';
  }

  function buildTrendMatrixTable(visible, includeOrganization) {
    const years = [...new Set(visible.map(row => Number(row.year)))]
      .filter(Number.isFinite)
      .sort((left, right) => right - left);
    const families = new Map();
    for (const row of visible) {
      const organization = String(row.organization || 'Organization');
      const rankingType = String(row.ranking_type || 'Ranking');
      const key = `${organization}\0${rankingType}`;
      if (!families.has(key)) families.set(key, { organization, rankingType, byYear: new Map() });
      families.get(key).byYear.set(Number(row.year), row);
    }

    const headers = years.map(year => `<th scope="col" class="min-w-32 border-b border-l border-amber-100 bg-amber-50/80 px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">${year}</th>`).join('');
    const body = [...families.values()].map(family => {
      const name = includeOrganization
        ? `<span class="block text-xs font-semibold text-green-800 dark:text-green-300">${escapeHtml(family.organization)}</span><span class="mt-1 block">${escapeHtml(family.rankingType)}</span>`
        : escapeHtml(family.rankingType);
      const recordedYears = family.byYear.size;
      const cells = years.map(year => {
        const row = family.byYear.get(year);
        if (!row) return '<td class="border-b border-l border-amber-100 px-3 py-3 text-center text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500"><span class="inline-block rounded-full bg-gray-100 px-2.5 py-1 font-semibold dark:bg-gray-700">N/A</span><span class="mt-1 block">No record</span></td>';
        const globalRank = String(row.global_rank ?? '').trim() || 'N/A';
        const phRank = String(row.ph_rank ?? '').trim() || 'N/A';
        return `<td class="border-b border-l border-amber-100 px-3 py-3 text-center dark:border-gray-700"><span class="inline-block max-w-full rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-green-900 dark:bg-amber-400/20 dark:text-amber-200">${escapeHtml(globalRank)}</span><span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Philippines: ${escapeHtml(phRank)}</span></td>`;
      }).join('');
      return `<tr><th scope="row" class="min-w-56 border-b border-amber-100 bg-amber-50/40 px-4 py-4 text-left text-sm font-semibold text-gray-800 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-100">${name}<span class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">${recordedYears} recorded ${recordedYears === 1 ? 'year' : 'years'}</span></th>${cells}</tr>`;
    }).join('');
    return `<div class="max-w-full overflow-x-auto"><table class="w-full min-w-max border-separate border-spacing-0 text-sm"><thead><tr><th scope="col" class="sticky left-0 z-10 min-w-56 border-b border-amber-100 bg-amber-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Ranking Name</th>${headers}</tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function renderTrendMatrix() {
    const visible = rankingRowsForOrganization()
      .filter(inSelectedRange)
      .filter(row => selectedList() === 'all' || row.ranking_type === selectedList());
    if (!visible.length) {
      disposeCharts();
      chartHost.className = rankingChartGridClass();
      chartHost.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-300">No rankings found for the selected filters.</div>';
      return;
    }

    disposeCharts();

    chartHost.className = 'min-h-64 w-full overflow-hidden rounded-xl border border-amber-200 bg-white dark:border-gray-700 dark:bg-gray-900';
    chartHost.innerHTML = `<div class="border-b border-amber-100 px-4 py-4 dark:border-gray-700"><h3 class="font-bold tracking-wide text-green-900 dark:text-green-200">Ranking Trend Matrix</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Each year stays in its own column, with the global rank and Philippine rank shown together for easier comparison.</p></div>${buildTrendMatrixTable(visible, selectedOrganization() === 'all')}`;
  }

  function openTrendMatrixDialog(title, matrixRows) {
    const dialog = document.createElement('dialog');
    dialog.className = 'm-auto max-h-[90vh] w-[min(96vw,1100px)] overflow-hidden rounded-2xl border border-amber-200 bg-white p-0 text-gray-800 shadow-2xl backdrop:bg-gray-950/50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
    const headingId = `ranking-matrix-dialog-title-${Date.now()}`;
    dialog.innerHTML = `<div class="flex items-center justify-between gap-4 border-b border-amber-100 px-5 py-4 dark:border-gray-700"><div><h2 id="${headingId}" class="font-bold text-green-900 dark:text-green-200">Ranking Trend Matrix</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(title)}</p></div><button type="button" class="ranking-matrix-dialog-close rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800" aria-label="Close Ranking Trend Matrix">Close</button></div><div class="max-h-[calc(90vh-76px)] overflow-auto">${buildTrendMatrixTable(matrixRows, false)}</div>`;
    dialog.setAttribute('aria-labelledby', headingId);
    dialog.querySelector('.ranking-matrix-dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    document.body.append(dialog);
    dialog.showModal();
  }

  function isRankingContextField(key, field) {
    return String(key || '').toLowerCase() === 'ranking_context'
      || String(field?.label || '').trim().toLowerCase() === 'ranking context';
  }

  function addRankingExplanation(content, rows) {
    const rankingContext = [...rows]
      .sort((left, right) => Number(right.year) - Number(left.year))
      .map(row => ({
        value: Object.entries(row.custom_fields || {})
          .find(([key, field]) => isRankingContextField(key, field) && String(field?.value || '').trim())?.[1]?.value
      }))
      .find(item => item.value);
    if (!rankingContext) return;

    const section = document.createElement('details');
    section.className = 'min-w-0 rounded-lg bg-gray-50 p-3 dark:bg-gray-900/70';
    const heading = document.createElement('summary');
    heading.className = 'cursor-pointer text-sm font-bold text-gray-800 dark:text-gray-100';
    heading.textContent = 'What this ranking means';
    const description = document.createElement('p');
    description.className = 'mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed text-gray-700 dark:text-gray-200';
    description.textContent = String(rankingContext.value).trim();
    section.append(heading, description);
    content.append(section);
  }

  function addInformationControl(wrapper, chartRows) {
    const infoRows = chartRows.filter(row =>
      String(row.info_text || '').trim() ||
      Object.entries(row.custom_fields || {}).some(([key, field]) =>
        !isRankingContextField(key, field) && String(field?.value || '').trim()
      )
    );
    if (!infoRows.length) return;
    const control = document.createElement('div');
    control.className = 'ranking-info-control absolute right-3 top-3 z-40';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-gray-500/40 bg-white/90 text-sm text-gray-500 shadow-sm hover:bg-gray-100 dark:bg-gray-800/90 dark:text-gray-300 dark:hover:bg-gray-700';
    trigger.setAttribute('aria-label', 'More ranking information');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.title = 'More information';
    trigger.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>';
    const panel = document.createElement('div');
    panel.hidden = true;
    panel.setAttribute('role', 'tooltip');
    panel.className = 'absolute right-0 top-full z-40 mt-2 max-h-56 w-72 max-w-[75vw] overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-700 shadow-xl dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200';
    for (const row of [...infoRows].sort((left, right) => Number(left.year) - Number(right.year))) {
      const entry = document.createElement('p');
      entry.className = 'whitespace-pre-wrap';
      const year = document.createElement('strong');
      year.textContent = `${row.year}: `;
      entry.append(year);
      if (String(row.info_text || '').trim()) entry.append(document.createTextNode(String(row.info_text).trim()));
      for (const [key, field] of Object.entries(row.custom_fields || {})) {
        if (isRankingContextField(key, field) || !String(field?.value || '').trim()) continue;
        const detail = document.createElement('span');
        detail.className = 'block pl-2';
        detail.textContent = `${field.label}: ${field.value}`;
        entry.append(detail);
      }
      panel.append(entry);
    }
    const open = () => { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); };
    const close = () => { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
    trigger.addEventListener('mouseenter', () => { if (!pinnedRankingInfoControl) open(); });
    trigger.addEventListener('focus', () => { if (!pinnedRankingInfoControl) open(); });
    control.addEventListener('mouseleave', () => { if (pinnedRankingInfoControl !== control) close(); });
    trigger.addEventListener('blur', () => { if (pinnedRankingInfoControl !== control) close(); });
    trigger.addEventListener('click', () => {
      if (pinnedRankingInfoControl === control) {
        pinnedRankingInfoControl = null;
        close();
        return;
      }
      if (pinnedRankingInfoControl) {
        const previousTrigger = pinnedRankingInfoControl.querySelector('button');
        const previousPanel = pinnedRankingInfoControl.querySelector('[role="tooltip"]');
        if (previousPanel) previousPanel.hidden = true;
        previousTrigger?.setAttribute('aria-expanded', 'false');
      }
      pinnedRankingInfoControl = control;
      open();
    });
    trigger.addEventListener('keydown', event => {
      if (event.key !== 'Escape') return;
      if (pinnedRankingInfoControl === control) pinnedRankingInfoControl = null;
      close();
      trigger.blur();
    });
    control.append(trigger, panel);
    wrapper.append(control);
  }

  function changeFor(row) {
    const previous = numericRows()
      .filter(item => item.organization === row.organization && item.ranking_type === row.ranking_type && Number(item.year) < Number(row.year))
      .sort((left, right) => Number(right.year) - Number(left.year))[0];
    if (!previous) return { label: 'New', color: '#64748B' };
    const currentValue = Number(row.rank_value);
    const previousValue = Number(previous.rank_value);
    if (currentValue < previousValue) return { label: `Improved (up) from ${previous.global_rank || previous.rank_value}`, color: '#15803D' };
    if (currentValue > previousValue) return { label: `Declined (down) from ${previous.global_rank || previous.rank_value}`, color: '#B91C1C' };
    return { label: 'No change', color: '#64748B' };
  }

  function makeChart(title, rowsForChart, color, index, showMatrixAction, hasSingleChart) {
    const wrapper = document.createElement('div');
    wrapper.className = 'iris-hover-card ranking-history-card relative min-w-0 overflow-visible rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    const header = document.createElement('div');
    header.className = 'mb-2 flex min-h-8 items-center justify-between gap-3 pr-10';
    const heading = document.createElement('h3');
    heading.className = 'min-w-0 text-sm font-bold text-gray-700 dark:text-gray-200';
    heading.textContent = title;
    header.append(heading);
    if (showMatrixAction) {
      const matrixButton = document.createElement('button');
      matrixButton.type = 'button';
      matrixButton.className = 'shrink-0 rounded-lg border border-green-800/30 px-2.5 py-1.5 text-xs font-semibold text-green-900 transition hover:border-green-800 hover:bg-green-50 dark:border-amber-500/40 dark:text-amber-200 dark:hover:bg-amber-400/10';
      matrixButton.textContent = 'Pop Ranking Trend Matrix';
      matrixButton.addEventListener('click', () => openTrendMatrixDialog(title, rowsForChart));
      header.append(matrixButton);
    }
    const content = document.createElement('div');
    content.className = 'grid min-w-0 grid-cols-1 gap-3';
    const surface = document.createElement('div');
    surface.className = 'min-w-0';
    surface.style.height = '320px';
    surface.style.width = '100%';
    content.append(surface);
    wrapper.append(header, content);
    addRankingExplanation(content, rowsForChart);
    addInformationControl(wrapper, rowsForChart);
    chartHost.append(wrapper);
    const chart = echarts.init(surface);
    charts.push(chart);

    const years = [...new Set(rowsForChart.map(row => String(row.year)))].sort((left, right) => Number(left) - Number(right));
    const byYear = new Map();
    for (const row of [...rowsForChart].sort((left, right) => Number(left.id) - Number(right.id))) byYear.set(String(row.year), row);
    const points = years.map(year => {
      const row = byYear.get(year);
      return { value: Number(row.rank_value), row, movement: changeFor(row) };
    });
    const theme = chartTheme();
    chart.setOption({
      color: [color],
      tooltip: {
        trigger: 'item',
        backgroundColor: theme.tooltipBackground,
        borderColor: theme.tooltipBorder,
        textStyle: { color: theme.labelColor, fontSize: 13 },
        formatter: params => {
          const point = params.data;
          if (!point?.row) return '';
          return `<b>${escapeHtml(title)}</b><br>Year: ${escapeHtml(point.row.year)}<br>Rank: <b>${escapeHtml(point.row.global_rank || point.row.rank_value)}</b><br>Change: <span style="color:${point.movement.color}">${escapeHtml(point.movement.label)}</span>`;
        }
      },
      grid: { left: '12%', right: '6%', top: '12%', bottom: '14%', containLabel: true },
      xAxis: {
        type: 'category', data: years,
        axisLabel: { color: theme.textColor, interval: 0, hideOverlap: true },
        axisLine: { lineStyle: { color: theme.gridColor } },
        splitLine: { show: false }
      },
      yAxis: {
        type: 'value', inverse: true, scale: true, minInterval: 1,
        axisLabel: { color: theme.textColor, formatter: value => String(Number.isInteger(value) ? value : Number(value.toFixed(1))) },
        axisLine: { lineStyle: { color: theme.gridColor } },
        splitLine: { lineStyle: { color: theme.gridColor } }
      },
      series: [{ type: 'line', data: points, connectNulls: false, symbol: 'circle', symbolSize: 9, lineStyle: { width: 3 }, itemStyle: { color } }]
    });
  }

  function makeImpactChart(organization, sdgRows, index, showMatrixAction, hasSingleChart) {
    const years = [...new Set(sdgRows.map(row => Number(row.year)))].sort((left, right) => left - right);
    const targetYear = years.at(-1);
    const selectedRows = sdgRows.filter(row => Number(row.year) === targetYear).sort((left, right) => Number(left.rank_value) - Number(right.rank_value));
    if (!selectedRows.length) return;
    const title = `${selectedOrganization() === 'all' ? `${organization} ` : ''}THE Impact SDG - ${targetYear}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'iris-hover-card ranking-history-card relative min-w-0 overflow-visible rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    const header = document.createElement('div');
    header.className = 'mb-2 flex min-h-8 items-center justify-between gap-3 pr-10';
    const heading = document.createElement('h3');
    heading.className = 'min-w-0 text-sm font-bold text-gray-700 dark:text-gray-200';
    heading.textContent = title;
    header.append(heading);
    if (showMatrixAction) {
      const matrixButton = document.createElement('button');
      matrixButton.type = 'button';
      matrixButton.className = 'shrink-0 rounded-lg border border-green-800/30 px-2.5 py-1.5 text-xs font-semibold text-green-900 transition hover:border-green-800 hover:bg-green-50 dark:border-amber-500/40 dark:text-amber-200 dark:hover:bg-amber-400/10';
      matrixButton.textContent = 'Pop Ranking Trend Matrix';
      matrixButton.addEventListener('click', () => openTrendMatrixDialog(title, sdgRows));
      header.append(matrixButton);
    }
    const content = document.createElement('div');
    content.className = 'grid min-w-0 grid-cols-1 gap-3';
    const surface = document.createElement('div');
    surface.className = 'min-w-0';
    surface.style.height = '320px';
    surface.style.width = '100%';
    content.append(surface);
    wrapper.append(header, content);
    addRankingExplanation(content, selectedRows);
    addInformationControl(wrapper, selectedRows);
    chartHost.append(wrapper);
    const chart = echarts.init(surface);
    charts.push(chart);
    const theme = chartTheme();
    chart.setOption({
      tooltip: {
        trigger: 'item', backgroundColor: theme.tooltipBackground, borderColor: theme.tooltipBorder,
        textStyle: { color: theme.labelColor },
        formatter: params => {
          const row = params.data.row;
          const movement = changeFor(row);
          return `<b>${escapeHtml(params.name)}</b><br>Year: ${escapeHtml(row.year)}<br>Rank: <b>${escapeHtml(row.global_rank || row.rank_value)}</b><br>Change: <span style="color:${movement.color}">${escapeHtml(movement.label)}</span>`;
        }
      },
      grid: { left: '18%', right: '12%', top: '12%', bottom: '10%', containLabel: true },
      xAxis: { type: 'value', inverse: true, show: false },
      yAxis: { type: 'category', data: selectedRows.map(row => row.ranking_type.split(' - ').at(-1)).reverse(), axisLabel: { color: theme.textColor } },
      series: [{
        type: 'bar',
        data: selectedRows.map(row => ({ value: Number(row.rank_value), row })).reverse(),
        itemStyle: { color: seriesColor(`${organization} THE Impact SDG`, index) },
        label: { show: true, position: 'right', formatter: params => params.data.row.global_rank || params.value, color: theme.labelColor }
      }]
    });
  }

  function render() {
    updateListOptions();
    if (selectedDisplayMode() === 'matrix') return renderTrendMatrix();
    const list = rowsForOrganization().filter(inSelectedRange);
    const selectedType = selectedList();
    const visible = selectedType === 'all' ? list : list.filter(row => row.ranking_type === selectedType);
    if (!visible.length) return showEmpty();
    disposeCharts();
    chartHost.replaceChildren();
    const families = new Map();
    const impact = new Map();
    for (const row of visible) {
      const organization = String(row.organization || 'Organization');
      const type = String(row.ranking_type || 'Ranking');
      if (selectedType === 'all' && type.startsWith('THE Impact SDG - ')) {
        if (!impact.has(organization)) impact.set(organization, []);
        impact.get(organization).push(row);
        continue;
      }
      const key = `${organization}\0${type}`;
      if (!families.has(key)) families.set(key, { organization, type, rows: [] });
      families.get(key).rows.push(row);
    }
    const chartCount = families.size + impact.size;
    chartHost.className = rankingChartGridClass();
    let index = 0;
    for (const family of families.values()) {
      const title = selectedOrganization() === 'all' ? rankingChartTitle(family.organization, family.type) : family.type;
      makeChart(title, family.rows, seriesColor(`${family.organization} ${family.type}`, index), index++, chartCount > 1, chartCount === 1);
    }
    for (const [organization, sdgRows] of impact) makeImpactChart(organization, sdgRows, index++, chartCount > 1, chartCount === 1);
    if (!families.size && !impact.size) showEmpty();
  }

  function updateAllYearsButton() {
    if (!allYearsButton) return;
    allYearsButton.setAttribute('aria-pressed', String(allYearsActive));
    allYearsButton.className = `w-auto justify-self-start self-center rounded-lg border px-2.5 py-1.5 text-xs ${allYearsActive ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200'}`;
  }

  function setRangeChanged() {
    allYearsActive = false;
    if (Number(fromSelect.value) > Number(toSelect.value)) toSelect.value = fromSelect.value;
    updateAllYearsButton();
    render();
  }

  function populate(payload) {
    rows = Array.isArray(payload.rankings) ? payload.rankings : [];
    const organizations = [...new Set(rows.map(row => String(row.organization || '')).filter(Boolean))];
    organizationSelect.replaceChildren(new Option('All organizations', 'all'));
    organizations.forEach(organization => organizationSelect.add(new Option(organization, organization)));
    const defaults = payload.chart_defaults || {};
    if (organizations.includes(defaults.default_organization)) organizationSelect.value = defaults.default_organization;
    renderYearOptions();
    updateListOptions();
    if ([...listSelect.options].some(option => option.value === defaults.default_list)) listSelect.value = defaults.default_list;
    defaultDisplayState = {
      mode: selectedDisplayMode(),
      layout: selectedGraphLayout(),
      organization: organizationSelect.value || 'all',
      list: listSelect.value || 'all'
    };
    updateAllYearsButton();
    render();
  }

  organizationSelect.addEventListener('change', () => {
    listSelect.value = 'all';
    render();
  });
  listSelect.addEventListener('change', render);
  displayModeSelect?.addEventListener('change', render);
  displayModeSelect?.addEventListener('change', () => {
    if (graphLayoutControl) graphLayoutControl.classList.toggle('hidden', selectedDisplayMode() !== 'charts');
  });
  graphLayoutSelect?.addEventListener('change', render);
  resetDefaultsButton?.addEventListener('click', () => {
    if (defaultDisplayState) {
      if ([...displayModeSelect.options].some(option => option.value === defaultDisplayState.mode)) displayModeSelect.value = defaultDisplayState.mode;
      if (graphLayoutSelect && [...graphLayoutSelect.options].some(option => option.value === defaultDisplayState.layout)) graphLayoutSelect.value = defaultDisplayState.layout;
      if ([...organizationSelect.options].some(option => option.value === defaultDisplayState.organization)) organizationSelect.value = defaultDisplayState.organization;
      updateListOptions();
      if ([...listSelect.options].some(option => option.value === defaultDisplayState.list)) listSelect.value = defaultDisplayState.list;
    }
    if (graphLayoutControl) graphLayoutControl.classList.toggle('hidden', selectedDisplayMode() !== 'charts');
    allYearsActive = true;
    const yearRangeControl = document.getElementById('rankingYearRangeControl');
    if (yearRangeControl) yearRangeControl.open = false;
    if (fromSelect.options.length) fromSelect.selectedIndex = 0;
    if (toSelect.options.length) toSelect.selectedIndex = toSelect.options.length - 1;
    updateAllYearsButton();
    render();
  });
  fromSelect.addEventListener('change', setRangeChanged);
  toSelect.addEventListener('change', setRangeChanged);
  allYearsButton?.addEventListener('click', () => {
    allYearsActive = true;
    const yearRangeControl = document.getElementById('rankingYearRangeControl');
    if (yearRangeControl) yearRangeControl.open = false;
    if (fromSelect.options.length) fromSelect.selectedIndex = 0;
    if (toSelect.options.length) toSelect.selectedIndex = toSelect.options.length - 1;
    updateAllYearsButton();
    render();
  });
  window.addEventListener('resize', () => charts.forEach(chart => chart?.resize?.()));
  const themeObserver = new MutationObserver(() => window.IRISRankingHistory?.themeChanged());
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

  window.IRISRankingHistory = {
    resize: () => charts.forEach(chart => chart?.resize?.()),
    themeChanged: () => {
      const theme = chartTheme();
      charts.forEach(chart => {
        chart?.setOption({
          textStyle: { color: theme.textColor },
          title: { textStyle: { color: theme.labelColor } },
          tooltip: { backgroundColor: theme.tooltipBackground, borderColor: theme.tooltipBorder, textStyle: { color: theme.labelColor } },
          xAxis: { axisLabel: { color: theme.textColor }, axisLine: { lineStyle: { color: theme.gridColor } }, splitLine: { lineStyle: { color: theme.gridColor } } },
          yAxis: { axisLabel: { color: theme.textColor }, axisLine: { lineStyle: { color: theme.gridColor } }, splitLine: { lineStyle: { color: theme.gridColor } } }
        });
        chart?.resize?.();
      });
    }
  };

  fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' })
    .then(response => { if (!response.ok) throw new Error('Ranking request failed.'); return response.json(); })
    .then(populate)
    .catch(() => showEmpty('Ranking data could not be loaded.'));
})();
(() => {
  const chartHost = document.getElementById('rankingChart');
  const organizationSelect = document.getElementById('rankingOrganizationFilter');
  const listControl = document.getElementById('rankingListControl');
  const listSelect = document.getElementById('rankingListFilter');
  const fromSelect = document.getElementById('rankingYearFrom');
  const toSelect = document.getElementById('rankingYearTo');
  const allYearsButton = document.getElementById('rankingAllYears');
  const api = document.currentScript?.dataset.api;
  if (!chartHost || !organizationSelect || !listControl || !listSelect || !fromSelect || !toSelect || !api) return;

  let rows = [];
  let charts = [];
  let allYearsActive = true;
  let colorOverrides = {};
  try {
    const stored = JSON.parse(localStorage.getItem('iris-ranking-series-colors') || '{}');
    if (stored && typeof stored === 'object' && !Array.isArray(stored)) colorOverrides = stored;
  } catch (error) {}

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
  const isNumeric = row => row.rank_value !== null && row.rank_value !== '' && Number.isFinite(Number(row.rank_value));
  const numericRows = () => rows.filter(isNumeric);
  const selectedOrganization = () => organizationSelect.value || 'all';
  const selectedList = () => listSelect.value || 'all';

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

  function updateListOptions() {
    if (selectedOrganization() === 'all') {
      listSelect.value = 'all';
      listControl.classList.add('hidden');
      listControl.classList.remove('inline-flex');
      return;
    }
    const types = [...new Set(rowsForOrganization().filter(inSelectedRange).map(row => String(row.ranking_type || '')))]
      .filter(Boolean);
    const previous = selectedList();
    listSelect.replaceChildren(new Option('All lists', 'all'));
    for (const type of types) listSelect.add(new Option(type, type));
    listSelect.value = types.includes(previous) ? previous : 'all';
    listControl.classList.toggle('hidden', selectedOrganization() === 'all' || types.length <= 1);
    listControl.classList.toggle('inline-flex', selectedOrganization() !== 'all' && types.length > 1);
  }

  function renderYearOptions() {
    const years = [...new Set(numericRows().map(row => Number(row.year)))].filter(Number.isFinite).sort((left, right) => left - right);
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

  function disposeCharts() {
    charts.forEach(chart => chart?.dispose?.());
    charts = [];
  }

  function showEmpty(message = 'No numeric rankings found for the selected filters.') {
    disposeCharts();
    chartHost.className = 'grid min-h-64 w-full grid-cols-1 gap-4';
    chartHost.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-300"><i class="fa-solid fa-folder-open mb-2 text-2xl text-gray-400" aria-hidden="true"></i><p>${escapeHtml(message)}</p></div>`;
  }

  function addInformationControl(wrapper, chartRows) {
    const infoRows = chartRows.filter(row => String(row.info_text || '').trim() || Object.keys(row.custom_fields || {}).length);
    if (!infoRows.length) return;
    const control = document.createElement('div');
    control.className = 'absolute right-3 top-3 z-40';
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
      for (const field of Object.values(row.custom_fields || {})) {
        if (!field?.value) continue;
        const detail = document.createElement('span');
        detail.className = 'block pl-2';
        detail.textContent = `${field.label}: ${field.value}`;
        entry.append(detail);
      }
      panel.append(entry);
    }
    const open = () => { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); };
    const close = () => { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
    let touchToggleHandled = false;
    trigger.addEventListener('mouseenter', open);
    trigger.addEventListener('focus', open);
    control.addEventListener('mouseleave', close);
    trigger.addEventListener('blur', close);
    trigger.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); trigger.blur(); } });
    trigger.addEventListener('pointerdown', event => {
      if (event.pointerType !== 'touch') return;
      event.preventDefault();
      touchToggleHandled = true;
      panel.hidden ? open() : close();
    });
    trigger.addEventListener('click', event => {
      if (touchToggleHandled) { touchToggleHandled = false; return; }
      if (event.detail === 0) return;
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

  function makeChart(title, rowsForChart, color, index) {
    const wrapper = document.createElement('div');
    wrapper.className = 'ranking-history-card relative min-w-0 overflow-visible rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    wrapper.style.position = 'relative';
    wrapper.style.minHeight = '360px';
    wrapper.style.width = '100%';
    const heading = document.createElement('h3');
    heading.className = 'mb-2 min-h-6 pr-10 text-sm font-bold text-gray-700 dark:text-gray-200';
    heading.textContent = title;
    const surface = document.createElement('div');
    surface.style.height = '320px';
    surface.style.width = '100%';
    wrapper.append(heading, surface);
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

  function makeImpactChart(organization, sdgRows, index) {
    const years = [...new Set(sdgRows.map(row => Number(row.year)))].sort((left, right) => left - right);
    const targetYear = years.at(-1);
    const selectedRows = sdgRows.filter(row => Number(row.year) === targetYear).sort((left, right) => Number(left.rank_value) - Number(right.rank_value));
    if (!selectedRows.length) return;
    const title = `${selectedOrganization() === 'all' ? `${organization} ` : ''}THE Impact SDG - ${targetYear}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'ranking-history-card relative min-w-0 overflow-visible rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    wrapper.style.position = 'relative';
    wrapper.style.minHeight = '360px';
    wrapper.style.width = '100%';
    const heading = document.createElement('h3');
    heading.className = 'mb-2 min-h-6 pr-10 text-sm font-bold text-gray-700 dark:text-gray-200';
    heading.textContent = title;
    const surface = document.createElement('div');
    surface.style.height = '320px';
    surface.style.width = '100%';
    wrapper.append(heading, surface);
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
    chartHost.className = chartCount === 1
      ? 'grid min-h-64 w-full grid-cols-1 gap-4'
      : 'grid min-h-64 w-full grid-cols-1 gap-4 xl:grid-cols-2';
    let index = 0;
    for (const family of families.values()) {
      const title = selectedOrganization() === 'all' ? `${family.organization} ${family.type}` : family.type;
      makeChart(title, family.rows, seriesColor(`${family.organization} ${family.type}`, index), index++);
    }
    for (const [organization, sdgRows] of impact) makeImpactChart(organization, sdgRows, index++);
    if (!families.size && !impact.size) showEmpty();
  }

  function updateAllYearsButton() {
    if (!allYearsButton) return;
    allYearsButton.setAttribute('aria-pressed', String(allYearsActive));
    allYearsButton.className = `rounded-lg border px-3 py-2 ${allYearsActive ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200'}`;
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
    updateAllYearsButton();
    render();
  }

  organizationSelect.addEventListener('change', () => {
    listSelect.value = 'all';
    render();
  });
  listSelect.addEventListener('change', render);
  fromSelect.addEventListener('change', setRangeChanged);
  toSelect.addEventListener('change', setRangeChanged);
  allYearsButton?.addEventListener('click', () => {
    allYearsActive = true;
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
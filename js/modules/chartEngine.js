export function createChart(ctx, type, chartData, { reverseOrder = false } = {}) {
  const data = JSON.parse(JSON.stringify(chartData));
  const labels = (data.labels || []).map((label, index) => label !== null && label !== undefined && String(label).trim() !== '' ? String(label) : `Item ${index + 1}`);
  data.labels = reverseOrder ? labels.slice().reverse() : labels;
  console.log('createChart labels:', JSON.stringify(data.labels));
  if (reverseOrder) data.datasets?.forEach(dataset => { dataset.data = (dataset.data || []).slice().reverse(); });
  const horizontal = chartData.orientation === 'horizontal' && type === 'bar';
  console.log('horizontal:', horizontal, 'chartType:', type);
  const rankSemantic = chartData.rankSemantic === true;
  if (rankSemantic) data.datasets?.forEach(dataset => { const values = dataset.data || []; const numericValues = values.map(value => window.ChartMapping.parseRankValue(value)).filter(value => value !== null); const maximum = Math.max(...numericValues); dataset.data = values.map(value => { const numeric = window.ChartMapping.parseRankValue(value); return numeric !== null ? maximum - numeric : value; }); });
  const rankTickLabel = value => String(chartData.rankValueMax !== undefined ? chartData.rankValueMax - value : value);
  const valueScale = { type: 'linear', reverse: rankSemantic ? false : chartData.valueAxisReversed === true, min: chartData.valueAxisMin, max: chartData.valueAxisMax, ticks: { color: '#94A3B8', callback: rankSemantic ? rankTickLabel : undefined, font: { family: 'Outfit, sans-serif' } }, grid: { color: 'rgba(255,255,255,0.05)' } };
  console.log('category axis config:', JSON.stringify(horizontal ? { type: 'category', ticks: valueScale.ticks, grid: valueScale.grid } : { type: 'category', ticks: valueScale.ticks, grid: valueScale.grid }));
  const categoryScale = { type: 'category', labels: data.labels, ticks: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } }, grid: valueScale.grid };
  return new window.Chart(ctx, { type, data, options: { responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x', plugins: { legend: { display: type === 'pie', labels: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } } } }, scales: type === 'pie' ? {} : { x: horizontal ? valueScale : categoryScale, y: horizontal ? { ...categoryScale, labels: data.labels } : valueScale } } });
}
export function renderStudioChart(arg1, arg2, arg3 = {}) {
  const legacyMode = arg1 && arg1.state && arg1.api && arg2 && typeof arg2 === 'object';
  const ctx = legacyMode ? arg1 : (arg2 && arg2.ctx ? arg2.ctx : null);
  const record = legacyMode ? arg2 : arg1;
  const options = legacyMode ? { ...arg3, ctx, state: arg1.state, elements: arg3.elements || {} } : (arg2 && typeof arg2 === 'object' && !Array.isArray(arg2) && !('getStudioActiveSheet' in arg2) ? arg2 : (arg3 || {}));
  const state = options.state || (ctx && ctx.state) || {};
  const elements = options.elements || {
    canvas: document.getElementById('studioChartCanvas'),
    emptyState: document.getElementById('studioChartEmptyState'),
    emptyMsg: document.getElementById('studioChartEmptyMsg'),
    warning: document.getElementById('studioFieldWarning'),
    typeSelect: document.getElementById('studioChartTypeSelect'),
    titleInput: document.getElementById('studioChartTitleInput'),
    subtitle: document.getElementById('studioChartSubtitleDisplay'),
    categorySelect: document.getElementById('studioCategoryCol'),
    valueSelect: document.getElementById('studioValueCol'),
    filterField: document.getElementById('studioFilterField'),
    filterOperator: document.getElementById('studioFilterOperator'),
    filterValue: document.getElementById('studioFilterValue'),
    filterUpperValue: document.getElementById('studioFilterUpperValue'),
    sortOrder: document.getElementById('studioSortOrder'),
    rowLimit: document.getElementById('studioRowLimit'),
    groupDuplicates: document.getElementById('studioGroupDuplicates'),
    reverseSortOrder: document.getElementById('studioReverseSortOrder'),
    reverseValueAxis: document.getElementById('studioReverseValueAxis')
  };
  const canvas = elements.canvas || document.getElementById('studioChartCanvas');
  const emptyState = elements.emptyState || document.getElementById('studioChartEmptyState');
  const emptyMsg = elements.emptyMsg || document.getElementById('studioChartEmptyMsg');
  const warning = elements.warning || document.getElementById('studioFieldWarning');
  const typeSelect = elements.typeSelect || document.getElementById('studioChartTypeSelect');
  const titleInput = elements.titleInput || document.getElementById('studioChartTitleInput');
  const subtitle = elements.subtitle || document.getElementById('studioChartSubtitleDisplay');
  if (!canvas || !typeSelect || !record) return;
  const getStudioActiveSheet = options.getStudioActiveSheet || (ctx && ctx.api && ctx.api.getStudioActiveSheet) || (() => null);
  const dispose = () => { state.studioChartInstance?.dispose?.(); state.studioChartInstance = null; };
  const empty = message => { dispose(); canvas.style.display = 'none'; if (emptyState) emptyState.style.display = 'flex'; if (emptyMsg) emptyMsg.textContent = message; };
  const show = () => { canvas.style.display = ''; if (emptyState) emptyState.style.display = 'none'; };
  const warn = message => { if (warning) { warning.textContent = message; warning.style.display = message ? 'block' : 'none'; } };
  warn(''); dispose();
  const info = getStudioActiveSheet(record);
  const sheet = info?.data;
  if (!sheet || !sheet.headers || !sheet.rows || !sheet.rows.length) return empty(sheet ? 'No rows to chart.' : 'No sheet data available.');
  const inferred = window.ChartMapping.inferColumns(sheet.headers, sheet.rows);
  const category = Number(elements.categorySelect?.value ?? document.getElementById('studioCategoryCol')?.value);
  const value = Number(elements.valueSelect?.value ?? document.getElementById('studioValueCol')?.value);
  const labelCol = Number.isInteger(category) && category >= 0 && category < sheet.headers.length ? category : inferred.labelColumn;
  const valueCol = Number.isInteger(value) && value >= 0 && value < sheet.headers.length ? value : inferred.valueColumn;
  if (labelCol === valueCol) { warn('Category and Value fields must be different columns.'); return empty('Category and Value fields must be different columns. Please adjust Field Mapping above.'); }
  const type = typeSelect.value || 'bar';
  const circular = ['pie', 'doughnut', 'polarArea'].includes(type);
  const isYearLike = value => window.ChartMapping.parseNumericValue(value) !== null && window.ChartMapping.parseNumericValue(value) >= 1900 && window.ChartMapping.parseNumericValue(value) <= 2100 && /^\s*\d{4}\s*$/.test(String(value));
  const valueIsYear = /year/i.test(String(sheet.headers[valueCol] || '')) || sheet.rows.some(row => row?.[valueCol] !== null && row?.[valueCol] !== undefined && isYearLike(row[valueCol]));
  const labelIsYear = /year/i.test(String(sheet.headers[labelCol] || '')) || sheet.rows.some(row => row?.[labelCol] !== null && row?.[labelCol] !== undefined && isYearLike(row[labelCol]));
  const rankSemantic = window.ChartMapping.isRankField(sheet.headers[valueCol]);
  const headerName = sheet.headers[valueCol] || 'Value';
  if (subtitle) subtitle.textContent = `Live interactive rendering from: ${info.name}`;
  if (titleInput && !titleInput.getAttribute('data-customized')) titleInput.value = `${headerName} — ${info.name}`;
  if (typeof window.echarts === 'undefined') return;
  const filterField = elements.filterField?.value || document.getElementById('studioFilterField')?.value || 'all';
  const operator = elements.filterOperator?.value || document.getElementById('studioFilterOperator')?.value || 'all';
  const filterValue = ((elements.filterValue?.value || document.getElementById('studioFilterValue')?.value) || '').trim();
  const upper = Number(elements.filterUpperValue?.value ?? document.getElementById('studioFilterUpperValue')?.value);
  const sortOrder = elements.sortOrder?.value || document.getElementById('studioSortOrder')?.value || 'source';
  const limit = Math.max(1, Math.min(100, Number(elements.rowLimit?.value ?? document.getElementById('studioRowLimit')?.value) || 30));
  const group = elements.groupDuplicates?.checked !== false;
  const rows = sheet.rows.map((row, index) => { const rawValue = row?.[valueIsYear && !labelIsYear ? labelCol : valueCol]; return { sourceIndex: index, row: row || [], label: String(row?.[valueIsYear ? valueCol : labelCol] ?? `Item ${index + 1}`).trim() || `Item ${index + 1}`, value: rankSemantic ? window.ChartMapping.parseRankValue(rawValue) : window.ChartMapping.parseNumericValue(rawValue), rawValue }; }).filter(item => item.value !== null);
  if (!rows.length) { warn(`The selected Value column "${headerName}" contains no numeric data. Choose a different Value field.`); return empty(`No numeric data found in column "${headerName}". Please select a numeric Value field above.`); }
  let chartRows = rows;
  if (filterValue && ['all', 'contains'].includes(operator)) {
    const selected = filterField.startsWith('column:') ? Number(filterField.slice(7)) : -1;
    const filterRows = rows.map(item => [selected >= 0 ? item.row[selected] : filterField === 'context' ? item.row[labelCol] : filterField === 'value' ? item.row[valueCol] : item.row]);
    const scope = `${info.name}:${filterField}:${operator}`;
    const same = state.studioFilterPreviousScope === scope;
    const filtered = window.TableFilter.filterRows([], filterRows, filterValue, { previousQuery: same ? state.studioFilterPreviousQuery : '', previousResults: same ? state.studioFilterPreviousResults : null, includeHeaders: false });
    state.studioFilterPreviousQuery = filterValue; state.studioFilterPreviousResults = filtered; state.studioFilterPreviousScope = scope;
    const matches = new Set(filtered.map(item => rows[item.origIdx]?.sourceIndex)); chartRows = rows.filter(item => matches.has(item.sourceIndex));
  } else if (operator !== 'all' && filterValue) {
    state.studioFilterPreviousQuery = ''; state.studioFilterPreviousResults = null; state.studioFilterPreviousScope = '';
    const numeric = Number(filterValue);
    chartRows = rows.filter(item => { const cells = filterField === 'context' ? [item.row[labelCol]] : filterField === 'value' ? [item.row[valueCol]] : item.row; const text = cells.map(cell => String(cell ?? '')).join(' ').toLowerCase(); const query = filterValue.toLowerCase(); if (operator === 'contains') return text.includes(query); if (operator === 'starts-with') return text.startsWith(query); if (operator === 'ends-with') return text.endsWith(query); if (operator === 'equals') return text === query; if (operator === 'not-equals') return text !== query; if (operator === 'greater-than') return Number.isFinite(numeric) && item.value > numeric; if (operator === 'less-than') return Number.isFinite(numeric) && item.value < numeric; if (operator === 'between') return Number.isFinite(numeric) && Number.isFinite(upper) && item.value >= numeric && item.value <= upper; return true; });
  } else { state.studioFilterPreviousQuery = ''; state.studioFilterPreviousResults = null; state.studioFilterPreviousScope = ''; }
  if (sortOrder === 'value-asc') chartRows.sort((a, b) => a.value - b.value);
  if (sortOrder === 'value-desc') chartRows.sort((a, b) => b.value - a.value);
  if (sortOrder === 'label-asc') chartRows.sort((a, b) => a.label.localeCompare(b.label));
  if (sortOrder === 'label-desc') chartRows.sort((a, b) => b.label.localeCompare(a.label));
  if (elements.reverseSortOrder?.getAttribute('aria-pressed') === 'true' || document.getElementById('studioReverseSortOrder')?.getAttribute('aria-pressed') === 'true') chartRows.reverse();
  chartRows = chartRows.slice(0, limit);
  if (!chartRows.length) return empty('No data matches the current filter. Try adjusting the filter criteria.');
  if (group) chartRows = circular ? window.ChartData.prepareCircularData(chartRows, true).rows : window.ChartData.groupAndAggregate(chartRows);
  const fullLabels = chartRows.map(row => row.label);
  const labels = fullLabels.slice();
  const reverseValueAxis = elements.reverseValueAxis?.getAttribute('aria-pressed') === 'true' || document.getElementById('studioReverseValueAxis')?.getAttribute('aria-pressed') === 'true';
  const rawValues = chartRows.map(row => row.value);
  const values = rankSemantic ? (() => { const maximum = Math.max(...rawValues); return rawValues.map(value => maximum - value); })() : rawValues;
  const yMin = Math.min(...values); const yMax = Math.max(...values); const axisMin = rankSemantic ? 0 : yMin >= 0 && yMin <= yMax * 0.8 ? 0 : Math.floor(yMin * 0.9);
  const yearOnValueAxis = valueIsYear && !labelIsYear;
  const horizontal = yearOnValueAxis && type === 'bar';
  const rankValueMin = rankSemantic ? 0 : undefined;
  const rankValueMax = rankSemantic ? Math.max(...rawValues) : undefined;
  state.studioChartConfig = { orientation: horizontal ? 'horizontal' : 'vertical', valueAxisReversed: reverseValueAxis, rankSemantic, rankValueMin, rankValueMax, valueAxisMin: axisMin, valueAxisMax: yMax, labels: fullLabels.slice() };
  show(); state.studioChartInstance = window.echarts.init(canvas);
  state.studioChartInstance.setOption({ animationDuration: 350, title: { text: titleInput?.value || `${headerName} — ${info.name}`, left: 'center', textStyle: { color: '#334155', fontSize: 13, fontWeight: 700 } }, tooltip: { trigger: circular ? 'item' : 'axis', formatter: circular ? '{b}: {c} ({d}%)' : params => { const point = Array.isArray(params) ? params[0] : params; return `<b>${fullLabels[point.dataIndex] || point.name}</b><br/>${headerName}: <b>${rankSemantic ? rawValues[point.dataIndex] : point.value}</b>`; } }, legend: { show: circular, data: [...new Set(fullLabels)], bottom: 0, type: 'scroll' }, grid: circular ? undefined : { left: 60, right: 20, top: 50, bottom: chartRows.length > 8 ? 90 : 60, containLabel: false }, xAxis: circular ? undefined : { type: horizontal ? 'value' : 'category', inverse: horizontal && reverseValueAxis && !rankSemantic, min: horizontal ? axisMin : undefined, max: horizontal ? yMax : undefined, data: horizontal ? undefined : labels, axisLabel: { rotate: chartRows.length > 6 ? 40 : 0, interval: 0, overflow: 'truncate', width: 100, fontSize: 11, formatter: horizontal && rankSemantic ? value => String(rankValueMax + rankValueMin - value) : undefined } }, yAxis: circular ? undefined : { type: horizontal ? 'category' : 'value', inverse: !horizontal && reverseValueAxis && !rankSemantic, data: horizontal ? labels : undefined, name: horizontal ? '' : headerName, nameTextStyle: { fontSize: 11, color: '#64748B' }, min: horizontal ? undefined : axisMin, max: horizontal ? undefined : yMax, axisLabel: { formatter: !horizontal && rankSemantic ? value => String(rankValueMax + rankValueMin - value) : undefined }, splitLine: { lineStyle: { type: 'dashed', color: '#E2E8F0' } } }, series: [circular ? { type: type === 'doughnut' ? 'pie' : type, radius: type === 'doughnut' ? ['45%', '72%'] : type === 'polarArea' ? ['15%', '72%'] : '68%', data: fullLabels.map((label, index) => ({ name: label, value: values[index] })) } : { type, smooth: type === 'line', data: rankSemantic ? values.map((value, index) => ({ value, rawValue: rawValues[index] })) : values, itemStyle: { color: '#146C36' }, label: { show: chartRows.length <= 15, position: horizontal ? 'right' : 'top', fontSize: 10, color: '#334155', formatter: '{c}' } }] });
  if (typeof ResizeObserver !== 'undefined') { canvas._studioResizeObserver?.disconnect?.(); canvas._studioResizeObserver = new ResizeObserver(() => state.studioChartInstance?.resize?.()); canvas._studioResizeObserver.observe(canvas); }
}

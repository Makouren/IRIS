export function createChart(ctx, type, chartData) {
  return new window.Chart(ctx, { type, data: JSON.parse(JSON.stringify(chartData)), options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type === 'pie', labels: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } } } }, scales: type === 'pie' ? {} : { x: { ticks: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } }, grid: { color: 'rgba(255,255,255,0.05)' } }, y: { ticks: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } }, grid: { color: 'rgba(255,255,255,0.05)' } } } } });
}
export function renderStudioChart(ctx, record) {
  const canvas = document.getElementById('studioChartCanvas');
  const emptyState = document.getElementById('studioChartEmptyState');
  const emptyMsg = document.getElementById('studioChartEmptyMsg');
  const warning = document.getElementById('studioFieldWarning');
  const typeSelect = document.getElementById('studioChartTypeSelect');
  const titleInput = document.getElementById('studioChartTitleInput');
  const subtitle = document.getElementById('studioChartSubtitleDisplay');
  if (!canvas || !typeSelect || !record) return;
  const dispose = () => { ctx.state.studioChartInstance?.dispose?.(); ctx.state.studioChartInstance = null; };
  const empty = message => { dispose(); canvas.style.display = 'none'; if (emptyState) emptyState.style.display = 'flex'; if (emptyMsg) emptyMsg.textContent = message; };
  const show = () => { canvas.style.display = ''; if (emptyState) emptyState.style.display = 'none'; };
  const warn = message => { if (warning) { warning.textContent = message; warning.style.display = message ? 'block' : 'none'; } };
  warn(''); dispose();
  const info = ctx.api.getStudioActiveSheet(record);
  const sheet = info?.data;
  if (!sheet || !sheet.headers || !sheet.rows || !sheet.rows.length) return empty(sheet ? 'No rows to chart.' : 'No sheet data available.');
  const inferred = window.ChartMapping.inferColumns(sheet.headers, sheet.rows);
  const category = Number(document.getElementById('studioCategoryCol')?.value);
  const value = Number(document.getElementById('studioValueCol')?.value);
  const labelCol = Number.isInteger(category) && category >= 0 && category < sheet.headers.length ? category : inferred.labelColumn;
  const valueCol = Number.isInteger(value) && value >= 0 && value < sheet.headers.length ? value : inferred.valueColumn;
  if (labelCol === valueCol) { warn('Category and Value fields must be different columns.'); return empty('Category and Value fields must be different columns. Please adjust Field Mapping above.'); }
  const type = typeSelect.value || 'bar';
  const circular = ['pie', 'doughnut', 'polarArea'].includes(type);
  const headerName = sheet.headers[valueCol] || 'Value';
  if (subtitle) subtitle.textContent = `Live interactive rendering from: ${info.name}`;
  if (titleInput && !titleInput.getAttribute('data-customized')) titleInput.value = `${headerName} — ${info.name}`;
  if (typeof window.echarts === 'undefined') return;
  const filterField = document.getElementById('studioFilterField')?.value || 'all';
  const operator = document.getElementById('studioFilterOperator')?.value || 'all';
  const filterValue = (document.getElementById('studioFilterValue')?.value || '').trim();
  const upper = Number(document.getElementById('studioFilterUpperValue')?.value);
  const sortOrder = document.getElementById('studioSortOrder')?.value || 'source';
  const limit = Math.max(1, Math.min(100, Number(document.getElementById('studioRowLimit')?.value) || 30));
  const group = document.getElementById('studioGroupDuplicates')?.checked !== false;
  const rows = sheet.rows.map((row, index) => ({ sourceIndex: index, row: row || [], label: String(row?.[labelCol] ?? `Item ${index + 1}`).trim() || `Item ${index + 1}`, value: window.ChartMapping.parseNumericValue(row?.[valueCol]), rawValue: row?.[valueCol] })).filter(item => item.value !== null);
  if (!rows.length) { warn(`The selected Value column "${headerName}" contains no numeric data. Choose a different Value field.`); return empty(`No numeric data found in column "${headerName}". Please select a numeric Value field above.`); }
  let chartRows = rows;
  if (filterValue && ['all', 'contains'].includes(operator)) {
    const selected = filterField.startsWith('column:') ? Number(filterField.slice(7)) : -1;
    const filterRows = rows.map(item => [selected >= 0 ? item.row[selected] : filterField === 'context' ? item.row[labelCol] : filterField === 'value' ? item.row[valueCol] : item.row]);
    const scope = `${info.name}:${filterField}:${operator}`;
    const same = ctx.state.studioFilterPreviousScope === scope;
    const filtered = window.TableFilter.filterRows([], filterRows, filterValue, { previousQuery: same ? ctx.state.studioFilterPreviousQuery : '', previousResults: same ? ctx.state.studioFilterPreviousResults : null, includeHeaders: false });
    ctx.state.studioFilterPreviousQuery = filterValue; ctx.state.studioFilterPreviousResults = filtered; ctx.state.studioFilterPreviousSheet = info.name; ctx.state.studioFilterPreviousScope = scope;
    const matches = new Set(filtered.map(item => rows[item.origIdx]?.sourceIndex)); chartRows = rows.filter(item => matches.has(item.sourceIndex));
  } else if (operator !== 'all' && filterValue) {
    ctx.state.studioFilterPreviousQuery = ''; ctx.state.studioFilterPreviousResults = null; ctx.state.studioFilterPreviousScope = '';
    const numeric = Number(filterValue);
    chartRows = rows.filter(item => { const cells = filterField === 'context' ? [item.row[labelCol]] : filterField === 'value' ? [item.row[valueCol]] : item.row; const text = cells.map(cell => String(cell ?? '')).join(' ').toLowerCase(); const query = filterValue.toLowerCase(); if (operator === 'contains') return text.includes(query); if (operator === 'starts-with') return text.startsWith(query); if (operator === 'ends-with') return text.endsWith(query); if (operator === 'equals') return text === query; if (operator === 'not-equals') return text !== query; if (operator === 'greater-than') return Number.isFinite(numeric) && item.value > numeric; if (operator === 'less-than') return Number.isFinite(numeric) && item.value < numeric; if (operator === 'between') return Number.isFinite(numeric) && Number.isFinite(upper) && item.value >= numeric && item.value <= upper; return true; });
  } else { ctx.state.studioFilterPreviousQuery = ''; ctx.state.studioFilterPreviousResults = null; ctx.state.studioFilterPreviousScope = ''; }
  if (sortOrder === 'value-asc') chartRows.sort((a, b) => a.value - b.value);
  if (sortOrder === 'value-desc') chartRows.sort((a, b) => b.value - a.value);
  if (sortOrder === 'label-asc') chartRows.sort((a, b) => a.label.localeCompare(b.label));
  if (sortOrder === 'label-desc') chartRows.sort((a, b) => b.label.localeCompare(a.label));
  chartRows = chartRows.slice(0, limit);
  if (!chartRows.length) return empty('No data matches the current filter. Try adjusting the filter criteria.');
  if (group) chartRows = circular ? window.ChartData.prepareCircularData(chartRows, true).rows : window.ChartData.groupAndAggregate(chartRows);
  const fullLabels = chartRows.map(row => row.label);
  const labels = fullLabels.map(label => label.length > 20 ? `${label.slice(0, 19)}…` : label);
  const values = chartRows.map(row => row.value);
  const yMin = Math.min(...values); const yMax = Math.max(...values); const axisMin = yMin >= 0 && yMin <= yMax * 0.8 ? 0 : Math.floor(yMin * 0.9);
  show(); ctx.state.studioChartInstance = window.echarts.init(canvas);
  ctx.state.studioChartInstance.setOption({ animationDuration: 350, title: { text: titleInput?.value || `${headerName} — ${info.name}`, left: 'center', textStyle: { color: '#334155', fontSize: 13, fontWeight: 700 } }, tooltip: { trigger: circular ? 'item' : 'axis', formatter: circular ? '{b}: {c} ({d}%)' : params => { const point = Array.isArray(params) ? params[0] : params; return `<b>${fullLabels[point.dataIndex] || point.name}</b><br/>${headerName}: <b>${point.value}</b>`; } }, legend: { show: circular, data: [...new Set(fullLabels)], bottom: 0, type: 'scroll' }, grid: circular ? undefined : { left: 60, right: 20, top: 50, bottom: chartRows.length > 8 ? 90 : 60, containLabel: false }, xAxis: circular ? undefined : { type: 'category', data: labels, axisLabel: { rotate: chartRows.length > 6 ? 40 : 0, interval: 0, overflow: 'truncate', width: 100, fontSize: 11 } }, yAxis: circular ? undefined : { type: 'value', name: headerName, nameTextStyle: { fontSize: 11, color: '#64748B' }, min: axisMin, splitLine: { lineStyle: { type: 'dashed', color: '#E2E8F0' } } }, series: [circular ? { type: type === 'doughnut' ? 'pie' : type, radius: type === 'doughnut' ? ['45%', '72%'] : type === 'polarArea' ? ['15%', '72%'] : '68%', data: fullLabels.map((label, index) => ({ name: label, value: values[index] })) } : { type, smooth: type === 'line', data: values, itemStyle: { color: '#146C36' }, label: { show: chartRows.length <= 15, position: 'top', fontSize: 10, color: '#334155', formatter: '{c}' } }] });
  if (typeof ResizeObserver !== 'undefined') { canvas._studioResizeObserver?.disconnect?.(); canvas._studioResizeObserver = new ResizeObserver(() => ctx.state.studioChartInstance?.resize?.()); canvas._studioResizeObserver.observe(canvas); }
}

export function formatChartValueForDisplay(value, precision = 2) {
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
  const precisionValue = Number.isFinite(Number(precision))
    ? Math.max(0, Math.min(2, Number(precision)))
    : 2;
  return Number(number).toLocaleString(undefined, {
    minimumFractionDigits: precisionValue,
    maximumFractionDigits: precisionValue
  });
}

export function createChart(ctx, type, chartData, { reverseOrder = false } = {}) {
  const source = chartData || {};
  const sourceSeries = source.series?.[0];
  const firstDataset = source.datasets?.[0];
  const axis = Array.isArray(source.xAxis) ? source.xAxis[0] : source.xAxis;
  const polarAxis = source.angleAxis;
  let labels = axis?.data || polarAxis?.data || source.labels || [];
  let values = sourceSeries?.data || firstDataset?.data || source.values || [];
  const seriesName = sourceSeries?.name || firstDataset?.label || source.seriesName || 'Value';
  if (sourceSeries?.type === 'pie' && Array.isArray(values)) {
    labels = values.map((point, index) => point?.name ?? labels[index] ?? `Item ${index + 1}`);
    values = values.map(point => point && typeof point === 'object' ? point.value : point);
  }
  labels = labels.map((label, index) => label !== null && label !== undefined && String(label).trim() !== '' ? String(label) : `Item ${index + 1}`);
  values = values.map(value => value && typeof value === 'object' ? value.value : value);
  if (reverseOrder) { labels.reverse(); values.reverse(); }

  const palette = window.IRISChartConfig || {};
  const colors = source.color || palette.colors || ['#1E6031', '#B7791F', '#0F766E', '#2563EB', '#C2410C', '#7C3AED'];
  const barFill = window.echarts?.graphic?.LinearGradient
    ? new window.echarts.graphic.LinearGradient(0, 0, 1, 0, [{ offset: 0, color: colors[0] }, { offset: 1, color: colors[1] || colors[0] }])
    : colors[0];
  const isDark = document.documentElement.classList.contains('dark');
  const textColor = isDark ? '#F8FAFC' : '#4B5563';
  const labelColor = isDark ? '#F8FAFC' : '#1F2937';
  const gridLineColor = isDark ? '#475569' : 'rgba(0, 0, 0, 0.12)';
  const option = {
    color: colors,
    textStyle: { color: textColor },
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      backgroundColor: isDark ? '#172033' : '#FFFFFF',
      borderColor: isDark ? '#475569' : '#E5E7EB',
      textStyle: { color: labelColor, fontSize: 13 }
    },
    legend: { data: [seriesName], textStyle: { color: textColor } },
    series: []
  };
  if (type === 'pie' || type === 'doughnut') {
    option.tooltip = {
      trigger: 'item',
      formatter: '{b}: {c} ({d}%)',
      backgroundColor: isDark ? '#172033' : '#FFFFFF',
      borderColor: isDark ? '#475569' : '#E5E7EB',
      textStyle: { color: labelColor }
    };
    option.legend = { data: labels, type: 'scroll', bottom: 0, textStyle: { color: textColor } };
    option.series = [{
      name: seriesName,
      type: 'pie',
      radius: type === 'doughnut' ? ['45%', '72%'] : '68%',
      data: labels.map((name, index) => ({ name, value: values[index] })),
      label: { show: true, color: labelColor, formatter: '{b}: {d}%' },
      itemStyle: { borderColor: '#FFFFFF', borderWidth: 2, borderRadius: 5 }
    }];
  } else if (type === 'polarArea') {
    option.polar = {};
    option.angleAxis = { type: 'category', data: labels, startAngle: 90, axisLabel: { color: textColor } };
    option.radiusAxis = { type: 'value', axisLabel: { color: textColor }, splitLine: { lineStyle: { color: gridLineColor } } };
    option.series = [{ name: seriesName, type: 'bar', coordinateSystem: 'polar', data: values }];
  } else {
    option.xAxis = { type: 'category', data: labels, axisLine: { lineStyle: { color: gridLineColor } }, axisLabel: { interval: 0, color: textColor } };
    option.yAxis = {
      type: 'value',
      name: seriesName,
      nameTextStyle: { color: labelColor },
      axisLine: { lineStyle: { color: gridLineColor } },
      axisLabel: { color: textColor },
      splitLine: { lineStyle: { color: gridLineColor } }
    };
    option.series = [{
      name: seriesName,
      type: type === 'line' ? 'line' : 'bar',
      data: values,
      smooth: type === 'line',
      itemStyle: { color: type === 'line' ? colors[0] : barFill, borderRadius: type === 'line' ? 0 : [0, 7, 7, 0] },
      lineStyle: type === 'line' ? { color: colors[0], width: 3 } : undefined
    }];
  }
  const chart = window.echarts.init(ctx);
  chart.setOption(option);
  return chart;
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
    rankedYearSelect: document.getElementById('studioRankedYearSelect'),
    filterField: document.getElementById('studioFilterField'),
    filterOperator: document.getElementById('studioFilterOperator'),
    filterValue: document.getElementById('studioFilterValue'),
    filterUpperValue: document.getElementById('studioFilterUpperValue'),
    sortOrder: document.getElementById('studioSortOrder'),
    rowLimit: document.getElementById('studioRowLimit'),
    groupDuplicates: document.getElementById('studioGroupDuplicates')
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
  const dispose = () => { canvas?._studioResizeObserver?.disconnect?.(); canvas._studioResizeObserver = null; state.studioChartInstance?.dispose?.(); state.studioChartInstance = null; };
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
  const type = typeSelect.value || 'bar';
  const rankedMode = type === 'rankedBar';
  const labelCol = Number.isInteger(category) && category >= 0 && category < sheet.headers.length ? category : inferred.labelColumn;
  const valueCol = Number.isInteger(value) && value >= 0 && value < sheet.headers.length ? value : inferred.valueColumn;
  const yearColumn = rankedMode ? window.ChartMapping.detectYearColumn(sheet.headers, sheet.rows) : null;
  if (rankedMode && yearColumn !== null && labelCol === yearColumn) {
    const fallbackLabel = sheet.headers.findIndex((header, idx) => idx !== yearColumn && idx !== valueCol && !/year/i.test(String(header || '')) && String(header || '').trim() !== '');
    const nextLabel = fallbackLabel >= 0 ? fallbackLabel : sheet.headers.findIndex((header, idx) => idx !== yearColumn && idx !== valueCol);
    if (nextLabel >= 0 && document.getElementById('studioCategoryCol')) {
      document.getElementById('studioCategoryCol').value = String(nextLabel);
    }
  }
  if (labelCol === valueCol) { warn('Category and Value fields must be different columns.'); return empty('Category and Value fields must be different columns. Please adjust Field Mapping above.'); }
  const circular = ['pie', 'doughnut'].includes(type);
  const polar = type === 'polarArea';
  const isYearLike = value => window.ChartMapping.parseNumericValue(value) !== null && window.ChartMapping.parseNumericValue(value) >= 1900 && window.ChartMapping.parseNumericValue(value) <= 2100 && /^\s*\d{4}\s*$/.test(String(value));
  const valueIsYear = /year/i.test(String(sheet.headers[valueCol] || '')) || sheet.rows.some(row => row?.[valueCol] !== null && row?.[valueCol] !== undefined && isYearLike(row[valueCol]));
  const labelIsYear = /year/i.test(String(sheet.headers[labelCol] || '')) || sheet.rows.some(row => row?.[labelCol] !== null && row?.[labelCol] !== undefined && isYearLike(row[labelCol]));
  const rankSemantic = window.ChartMapping.isRankField(sheet.headers[valueCol]);
  const headerName = sheet.headers[valueCol] || 'Value';
  if (subtitle) subtitle.textContent = `Live interactive rendering from: ${info.name}`;
  if (titleInput && !titleInput.getAttribute('data-customized')) titleInput.value = `${headerName} — ${info.name}`;
  if (typeof window.echarts === 'undefined') return;
  const rankedYearSelect = elements.rankedYearSelect || document.getElementById('studioRankedYearSelect');
  const rankedReverseOrder = elements.rankedReverseOrder || document.getElementById('studioRankedReverseOrder');
  const reverseOrder = rankedMode && Boolean(rankedReverseOrder?.checked ?? state.studioChartConfig?.reverseOrder ?? false);
  const yearConfiguration = yearColumn !== null ? window.ChartMapping.getYearOptions(sheet.rows, yearColumn) : { availableYears: [], selectedYear: null };
  if (rankedReverseOrder) {
    rankedReverseOrder.checked = reverseOrder;
  }
  if (rankedYearSelect) {
    const hasMultiYear = yearConfiguration.availableYears.length > 1;
    const currentSelection = state.studioChartConfig?.selectedYear ?? state.studioChartConfig?.rankedYear ?? rankedYearSelect.value ?? yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0] ?? 'all';
    rankedYearSelect.innerHTML = yearConfiguration.availableYears.length
      ? (hasMultiYear ? '<option value="all">Select all year</option>' : '') + yearConfiguration.availableYears.map(year => `<option value="${year}">${year}</option>`).join('')
      : '<option value="">No year data</option>';
    if (yearConfiguration.availableYears.length) {
      const isAllSelected = currentSelection === 'all' || (hasMultiYear && String(currentSelection) === 'all');
      const nextYear = isAllSelected ? 'all' : (yearConfiguration.availableYears.includes(Number(currentSelection)) ? Number(currentSelection) : (yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0]));
      rankedYearSelect.value = String(nextYear);
      state.studioChartConfig = { ...(state.studioChartConfig || {}), selectedYear: nextYear, rankedYear: nextYear, yearColumn };
    } else {
      rankedYearSelect.value = '';
    }
  }
  if (rankedMode && yearColumn === null) {
    warn('Ranked Bar Chart requires a Year column or year-like values to compare institutions over time.');
    return empty('Ranked Bar Chart requires a Year column or year-like values. Please map a Year field first.');
  }
  const filterField = elements.filterField?.value || document.getElementById('studioFilterField')?.value || 'all';
  const operator = elements.filterOperator?.value || document.getElementById('studioFilterOperator')?.value || 'all';
  const filterValue = ((elements.filterValue?.value || document.getElementById('studioFilterValue')?.value) || '').trim();
  const upper = Number(elements.filterUpperValue?.value ?? document.getElementById('studioFilterUpperValue')?.value);
  const sortOrder = elements.sortOrder?.value || document.getElementById('studioSortOrder')?.value || 'source';
  const displayPrecision = Number.isFinite(Number(elements.valuePrecision?.value ?? document.getElementById('studioValuePrecisionSelect')?.value))
    ? Math.max(0, Math.min(2, Number(elements.valuePrecision?.value ?? document.getElementById('studioValuePrecisionSelect')?.value)))
    : 2;
  const limit = Math.max(1, Math.min(100, Number(elements.rowLimit?.value ?? document.getElementById('studioRowLimit')?.value) || 30));
  const group = elements.groupDuplicates?.checked !== false;
  const rows = sheet.rows.map((row, index) => { const rawValue = row?.[valueIsYear && !labelIsYear ? labelCol : valueCol]; return { sourceIndex: index, row: row || [], label: String(row?.[valueIsYear ? valueCol : labelCol] ?? `Item ${index + 1}`).trim() || `Item ${index + 1}`, value: rankSemantic ? window.ChartMapping.parseRankValue(rawValue) : window.ChartMapping.parseNumericValue(rawValue), rawValue }; }).filter(item => item.value !== null);
  if (!rows.length) { warn(`The selected Value column "${headerName}" contains no numeric data. Choose a different Value field.`); return empty(`No numeric data found in column "${headerName}". Please select a numeric Value field above.`); }
  let chartRows = rows;
  if (rankedMode) {
    const selectedYearRaw = rankedYearSelect?.value ?? yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0] ?? 'all';
    const selectedYear = selectedYearRaw === 'all' ? 'all' : Number(selectedYearRaw);
    const ranked = window.ChartMapping.buildRankedBarRows(sheet.rows, { yearColumn, selectedYear, labelColumn: labelCol, valueColumn: valueCol, limit, reverseOrder });
    state.studioChartConfig = { ...(state.studioChartConfig || {}), reverseOrder, yearColumn, selectedYear, rankedYear: selectedYear, availableYears: ranked.options?.availableYears || yearConfiguration.availableYears || [] };
    chartRows = ranked.rows.map(row => ({
      sourceIndex: row.sourceIndex,
      row: row.row,
      label: row.label,
      value: Number(row.value),
      rawValue: Number(row.value),
      visualValue: Number(row.visualValue),
      year: row.year
    }));
    if (!chartRows.length) return empty('No ranked data available for the selected year. Try a different year.');
  } else {
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
    chartRows = chartRows.slice(0, limit);
    if (!chartRows.length) return empty('No data matches the current filter. Try adjusting the filter criteria.');
    if (group) chartRows = circular ? window.ChartData.prepareCircularData(chartRows, true).rows : window.ChartData.groupAndAggregate(chartRows);
  }
  const fullLabels = chartRows.map(row => row.label);
  const labels = fullLabels.slice();
  const rawValues = chartRows.map(row => rankedMode ? Number(row.rawValue ?? row.value ?? 0) : row.value);
  const values = rankedMode ? chartRows.map(row => Number(row.visualValue ?? row.value ?? 0)) : (rankSemantic ? (() => { const maximum = Math.max(...rawValues); return rawValues.map(value => maximum - value); })() : rawValues);
  const yMin = Math.min(...values); const yMax = Math.max(...values); const axisMin = rankedMode ? 0 : (rankSemantic ? 0 : yMin >= 0 && yMin <= yMax * 0.8 ? 0 : Math.floor(yMin * 0.9));
  const yearOnValueAxis = valueIsYear && !labelIsYear;
  const horizontal = rankedMode ? true : (yearOnValueAxis && type === 'bar');
  const rankValueMin = rankedMode ? 0 : (rankSemantic ? 0 : undefined);
  const rankValueMax = rankedMode ? Math.max(...rawValues) : (rankSemantic ? Math.max(...rawValues) : undefined);
  state.studioChartConfig = {
    ...(state.studioChartConfig || {}),
    orientation: horizontal ? 'horizontal' : 'vertical',
    rankSemantic: rankedMode || rankSemantic,
    rankValueMin,
    rankValueMax,
    valueAxisMin: axisMin,
    valueAxisMax: yMax,
    labels: fullLabels.slice(),
    selectedYear: rankedMode ? (rankedYearSelect?.value ?? yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0] ?? 'all') : undefined,
    rankedYear: rankedMode ? (rankedYearSelect?.value ?? yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0] ?? 'all') : undefined,
    yearColumn,
    reverseOrder: Boolean(rankedMode ? reverseOrder : false)
  };
  show();
  const isDark = document.documentElement.classList.contains('dark');
  const textColor = isDark ? '#E5E7EB' : '#1F2937';
  const labelColor = isDark ? '#F9FAFC' : '#111827';
  const subtextColor = isDark ? '#9CA3AF' : '#4B5563';
  const gridLineColor = isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)';

  const formatValue = value => formatChartValueForDisplay(value, displayPrecision);

  state.studioChartInstance = window.echarts.init(canvas);
  state.studioChartInstance.setOption({
    animationDuration: 350,
    title: {
      text: titleInput?.value || `${headerName} — ${info.name}`,
      left: 'center',
      textStyle: { color: labelColor, fontSize: 14, fontWeight: 700 }
    },
    tooltip: {
      trigger: circular ? 'item' : 'axis',
      backgroundColor: isDark ? '#1F2937' : '#FFFFFF',
      borderColor: isDark ? '#374151' : '#E5E7EB',
      textStyle: { color: labelColor },
      formatter: circular ? params => `${params.name}: ${formatValue(params.value)} (${params.percent}%)` : params => {
        const point = Array.isArray(params) ? params[0] : params;
        const rawValue = rankSemantic ? rawValues[point.dataIndex] : Number(point.value ?? 0);
        return `<b>${fullLabels[point.dataIndex] || point.name}</b><br/>${headerName}: <b>${formatValue(rawValue)}</b>`;
      }
    },
    legend: {
      show: circular || polar,
      data: [...new Set(fullLabels)],
      bottom: 0,
      type: 'scroll',
      textStyle: { color: textColor, fontSize: 11, fontWeight: 600 }
    },
    grid: circular || polar ? undefined : {
      left: '4%',
      right: '4%',
      top: 50,
      bottom: chartRows.length > 6 ? 80 : 50,
      containLabel: true
    },
    xAxis: circular || polar ? undefined : {
      type: horizontal ? 'value' : 'category',
      min: horizontal ? axisMin : undefined,
      max: horizontal ? yMax : undefined,
      data: horizontal ? undefined : labels,
      axisLine: { lineStyle: { color: gridLineColor } },
      axisLabel: {
        rotate: chartRows.length > 6 ? 35 : 0,
        interval: 0,
        overflow: 'none',
        fontSize: 11,
        fontWeight: 600,
        color: textColor,
        formatter: value => formatValue(value)
      },
      splitLine: horizontal ? { lineStyle: { type: 'dashed', color: gridLineColor, width: 1 } } : { show: false }
    },
    yAxis: circular || polar ? undefined : {
      type: horizontal ? 'category' : 'value',
      data: horizontal ? labels : undefined,
      name: horizontal ? '' : headerName,
      nameTextStyle: { fontSize: 12, fontWeight: 700, color: labelColor, padding: [0, 0, 8, 0] },
      min: horizontal ? undefined : axisMin,
      max: horizontal ? undefined : yMax,
      axisLine: { lineStyle: { color: gridLineColor } },
      axisLabel: {
        color: textColor,
        fontSize: 11,
        fontWeight: 600,
        formatter: value => formatValue(value)
      },
      splitLine: horizontal ? { show: false } : { lineStyle: { type: 'dashed', color: gridLineColor, width: 1 } }
    },
    polar: polar ? {} : undefined,
    angleAxis: polar ? { type: 'category', data: labels, startAngle: 90, axisLabel: { color: textColor } } : undefined,
    radiusAxis: polar ? { type: 'value', name: headerName, splitLine: { lineStyle: { color: gridLineColor } }, axisLabel: { color: textColor } } : undefined,
    series: [polar ? {
      name: headerName,
      type: 'bar',
      coordinateSystem: 'polar',
      data: values,
      itemStyle: { color: '#009639' },
      label: { show: chartRows.length <= 20, position: 'middle', color: '#FFFFFF', formatter: '{c}' }
    } : circular ? {
      type: 'pie',
      radius: type === 'doughnut' ? ['45%', '72%'] : '68%',
      center: ['50%', '45%'],
      data: fullLabels.map((label, index) => ({
        name: label,
        value: values[index],
        itemStyle: { color: ['#009639', '#1E6031', '#E0A70D', '#3B82F6', '#8B5CF6', '#F59E0B', '#10B981', '#EF4444', '#38BDF8', '#F97316'][index % 10] }
      })),
      label: { show: true, position: 'outside', color: textColor, fontSize: 11, fontWeight: 600, formatter: params => `${params.name}: ${formatValue(params.value)}`, distance: 12 },
      labelLine: { show: true, length: 12, length2: 8, lineStyle: { color: subtextColor, width: 1 } },
      itemStyle: { borderColor: isDark ? '#1F2937' : '#FFFFFF', borderWidth: 2 },
      emphasis: { itemStyle: { shadowBlur: 12, shadowColor: 'rgba(15, 23, 42, 0.38)' } }
    } : {
      type: rankedMode ? 'bar' : type,
      smooth: type === 'line',
      data: rankedMode ? values.map((value, index) => ({ value, rawValue: rawValues[index], name: labels[index] })) : (rankSemantic ? values.map((value, index) => ({ value, rawValue: rawValues[index] })) : values),
      itemStyle: { color: '#009639', borderRadius: type === 'bar' || rankedMode ? (horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]) : undefined },
      lineStyle: type === 'line' ? { width: 3, color: '#009639' } : undefined,
      label: {
        show: chartRows.length <= 20,
        position: horizontal ? 'right' : 'top',
        fontSize: 11,
        fontWeight: 700,
        color: labelColor,
        formatter: params => formatValue(rankedMode ? (params.data?.rawValue ?? rawValues[params.dataIndex] ?? params.value) : params.value)
      }
    }]
  });
  if (typeof ResizeObserver !== 'undefined') { canvas._studioResizeObserver?.disconnect?.(); canvas._studioResizeObserver = new ResizeObserver(() => state.studioChartInstance?.resize?.()); canvas._studioResizeObserver.observe(canvas); }
}

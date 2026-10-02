import '../chartColors.js?v=iris-chart-builder-20261001';
const { DEFAULT_CHART_COLORS, resolveFieldColors } = globalThis.IRISChartColors;

function sharedFieldColorIsNewer(field, irisConfig) {
  if (irisConfig.chartColorsOverrideShared) return false;
  const key = String(field ?? '').trim().toLowerCase().replace(/\s+/g, ' ');
  const fieldTimestamp = Date.parse(irisConfig.fieldColorUpdatedAt?.[key] || '');
  if (!Number.isFinite(fieldTimestamp)) return false;
  const graphTimestamp = Date.parse(irisConfig.chartUpdatedAt || '');
  return !Number.isFinite(graphTimestamp) || fieldTimestamp >= graphTimestamp;
}

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

const CHART_TYPES = new Set(['bar', 'line', 'pie', 'doughnut', 'rankedBar', 'nestedPie']);

function normalizeChartType(type) {
  const normalized = String(type || 'bar').trim();
  if (/^(?:polararea|polar-area|rose|nightingale)$/i.test(normalized)) return 'bar';
  if (/^rankedbar$/i.test(normalized)) return 'rankedBar';
  return CHART_TYPES.has(normalized) ? normalized : 'bar';
}

export function getChartTheme(theme = {}) {
  const dark = Boolean(theme.dark);
  return {
    dark,
    textColor: dark ? '#F8FAFC' : '#4B5563',
    labelColor: dark ? '#F8FAFC' : '#1F2937',
    gridColor: dark ? 'rgba(226,232,240,.24)' : '#E5E7EB',
    tooltipBackground: dark ? '#172033' : '#FFFFFF',
    tooltipBorder: dark ? '#475569' : '#E5E7EB'
  };
}

function shadeColor(color, amount) {
  const value = Number.parseInt(String(color).slice(1), 16);
  const channels = [value >> 16, (value >> 8) & 255, value & 255].map(channel => Math.max(0, Math.min(255, Math.round(channel + (amount >= 0 ? (255 - channel) * amount : channel * amount)))));
  return `#${channels.map(channel => channel.toString(16).padStart(2, '0')).join('').toUpperCase()}`;
}

export function buildChartOption({ type, labels = [], values = [], rawValues = values, series = [], config = {}, colors = [], theme = {}, precision = 2, context = {}, showTitle = false } = {}) {
  type = normalizeChartType(type || config.type);
  const irisConfig = config.irisConfig || config;
  const { dark: isDark, textColor, labelColor, gridColor, tooltipBackground, tooltipBorder } = getChartTheme(theme);
  const tooltip = { backgroundColor: tooltipBackground, borderColor: tooltipBorder, textStyle: { color: labelColor } };
  const safeLabels = labels.map((label, index) => String(label ?? `Item ${index + 1}`));
  const realValues = safeLabels.map((_, index) => Number(rawValues[index] ?? values[index] ?? 0));
  const seriesName = config.seriesName || config.valueLabel || 'Value';
  const format = value => formatChartValueForDisplay(value, precision);
  const rankValue = irisConfig.rankSemantic ?? config.rankSemantic;
  const rankSemantic = rankValue === true || rankValue === 1 || rankValue === '1';
  const reverseOrder = Boolean(irisConfig.reverseOrder ?? config.reverseOrder);
  const maxRank = Number(irisConfig.rankValueMax ?? config.rankValueMax ?? Math.max(0, ...realValues));
  const isRankedBar = type === 'rankedBar';
  const displayLabels = reverseOrder ? safeLabels.slice().reverse() : safeLabels.slice();
  const displayValues = reverseOrder ? realValues.slice().reverse() : realValues.slice();
  const resolvedColors = colors.length ? colors : DEFAULT_CHART_COLORS;
  const option = { color: resolvedColors, textStyle: { color: textColor }, tooltip: { ...tooltip, trigger: 'axis' }, animationDuration: context.animate === false ? 0 : 350 };
  if (showTitle && config.title) option.title = { text: config.title, left: 'center', textStyle: { color: labelColor, fontSize: 14, fontWeight: 700 } };

  if (type === 'pie' || type === 'doughnut') {
    const points = displayLabels.map((name, index) => ({ name, value: displayValues[index], rawValue: displayValues[index], itemStyle: { color: resolvedColors[index % resolvedColors.length] } }));
    const pieBase = { name: seriesName, type: 'pie', data: points, center: ['50%', '47%'], itemStyle: { borderColor: isDark ? '#111827' : '#FFFFFF', borderWidth: 2 }, emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0,0,0,0.5)' } } };
    option.tooltip = { ...tooltip, trigger: 'item', formatter: params => `${params.seriesName || seriesName}<br/>${params.name}: ${format(params.data?.rawValue ?? params.value)} (${params.percent}%)` };
    option.legend = { data: displayLabels, type: 'scroll', orient: 'vertical', left: 0, top: 'middle', textStyle: { color: textColor } };
    option.series = [{ ...pieBase, radius: type === 'doughnut' ? ['45%', '72%'] : '58%', label: { show: true, color: labelColor, formatter: params => `${params.name}: ${format(params.data?.rawValue ?? params.value)} (${params.percent}%)` } }];
    return option;
  }

  if (type === 'nestedPie') {
    const groups = Array.isArray(irisConfig.nestedGroups) ? irisConfig.nestedGroups : [];
    if (!groups.length) return { ...option, title: { ...(option.title || {}), text: 'Choose a Group (inner ring) field to render this chart', left: 'center', top: 'middle', textStyle: { color: textColor, fontSize: 14 } }, series: [] };
    const groupColors = groups.map((group, index) => {
      if (sharedFieldColorIsNewer(group.label, irisConfig)) return resolvedColors[index];
      const fieldKey = String(group.label).trim().toLowerCase().replace(/\s+/g, ' ');
      return irisConfig.groupColors?.[index]
        || irisConfig.colors?.[index]
        || irisConfig.fieldColors?.[fieldKey]
        || resolvedColors[index];
    });
    const childLabels = groups.flatMap(group => (group.children || []).map(child => child.label));
    const childColors = groups.flatMap((group, groupIndex) => (group.children || []).map((child, childIndex) => {
      const sliceIndex = groups.slice(0, groupIndex).reduce((count, item) => count + (item.children || []).length, 0) + childIndex;
      const fieldKey = String(child.label).trim().toLowerCase().replace(/\s+/g, ' ');
      const explicit = sharedFieldColorIsNewer(fieldKey, irisConfig)
        ? resolvedColors[groups.length + sliceIndex]
        : irisConfig.sliceColors?.[`${group.label}::${child.label}`]
          || irisConfig.colors?.[groups.length + sliceIndex]
          || irisConfig.fieldColors?.[fieldKey];
      return explicit || shadeColor(groupColors[groupIndex], (childIndex % 2 ? -1 : 1) * (0.12 + (childIndex % 4) * 0.06));
    }));
    const crowded = Number(context.width) < 560 || childLabels.length > 8;
    const outerData = groups.flatMap((group, groupIndex) => (group.children || []).map((child, childIndex) => ({ name: child.label, value: Number(child.value || 0), rawValue: Number(child.rawValue ?? child.value ?? 0), group: group.label, itemStyle: { color: childColors[groups.slice(0, groupIndex).reduce((count, item) => count + (item.children || []).length, 0) + childIndex] } })));
    option.tooltip = { ...tooltip, trigger: 'item', formatter: params => `${params.name}: ${format(params.data?.rawValue ?? params.value)} (${params.percent}%)` };
    option.legend = { data: crowded ? childLabels : groups.map(group => group.label), type: 'scroll', orient: 'vertical', right: 0, top: 'middle', textStyle: { color: textColor } };
    option.series = [
      { name: 'Groups', type: 'pie', radius: [0, '30%'], center: ['42%', '50%'], selectedMode: 'single', data: groups.map((group, index) => ({ name: group.label, value: group.value, rawValue: group.value, itemStyle: { color: groupColors[index] } })), label: { position: 'inside', formatter: '{b}', color: '#FFFFFF' }, labelLine: { show: false } },
      { name: seriesName, type: 'pie', radius: ['45%', '60%'], center: ['42%', '50%'], data: outerData, label: crowded ? { formatter: params => `${params.name}: ${params.percent}%`, color: labelColor } : { formatter: params => `{name|${params.name}}\n{value|${format(params.data?.rawValue ?? params.value)}}  {percent|${params.percent}%}`, color: labelColor, rich: { name: { fontWeight: 700, color: labelColor }, value: { color: labelColor }, percent: { color: textColor } } }, labelLine: { show: !crowded } }
    ];
    option.color = [...groupColors, ...childColors];
    return option;
  }

  const horizontal = isRankedBar && (irisConfig.orientation || config.orientation) === 'horizontal';
  const rankInverted = rankSemantic && !isRankedBar && type === 'bar';
  const labelsForAxis = horizontal ? displayLabels : safeLabels;
  const plottedValues = horizontal
    ? displayValues.map(value => Math.max(1, maxRank - value + 1))
    : rankInverted ? realValues.map(value => Math.max(1, maxRank - value + 1)) : realValues;
  const valueMin = irisConfig.valueAxisMin ?? config.valueAxisMin;
  const valueMax = irisConfig.valueAxisMax ?? config.valueAxisMax;
  option.grid = { left: '5%', right: '5%', top: showTitle ? 52 : '8%', bottom: safeLabels.length > 7 ? '16%' : '8%', containLabel: true };
  option.tooltip = { ...tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: params => {
    const items = Array.isArray(params) ? params : [params];
    return items.map(point => {
      const dataIndex = point.dataIndex ?? 0;
      const sourceIndex = reverseOrder ? safeLabels.length - dataIndex - 1 : dataIndex;
      const value = point.data?.rawValue ?? (rankInverted || isRankedBar ? displayValues[dataIndex] : point.value);
      const selectedYear = irisConfig.selectedYear ?? config.selectedYear;
      const year = selectedYear && selectedYear !== 'all' ? `<br/>Year: ${selectedYear}` : '';
      return `${point.seriesName ? `${point.seriesName}<br/>` : ''}${safeLabels[sourceIndex] || point.name}: ${format(value)}${year}`;
    }).join('<br/>');
  } };
  if (type === 'line') {
    const lineSeries = series.length ? series : [{ name: seriesName, data: realValues }];
    const rankLine = rankSemantic;
    option.legend = lineSeries.length > 1 ? { data: lineSeries.map((item, index) => item.name || `Series ${index + 1}`), textStyle: { color: textColor } } : undefined;
    option.xAxis = { type: 'category', data: safeLabels, axisLabel: { color: textColor }, axisLine: { lineStyle: { color: gridColor } }, splitLine: { show: false } };
    option.yAxis = { type: 'value', inverse: rankLine, min: valueMin, max: valueMax, axisLabel: { color: textColor }, axisLine: { lineStyle: { color: gridColor } }, splitLine: { lineStyle: { color: gridColor } } };
    option.series = lineSeries.map((item, index) => ({ name: item.name || seriesName, type: 'line', smooth: false, data: (item.data || realValues).map((value, valueIndex) => ({ value: Number(value?.value ?? value ?? 0), rawValue: Number(value?.rawValue ?? value?.value ?? value ?? 0), name: safeLabels[valueIndex] })), itemStyle: { color: resolvedColors[index] || resolvedColors[0] }, lineStyle: { color: resolvedColors[index] || resolvedColors[0], width: 3 } }));
  } else if (horizontal) {
    option.xAxis = { type: 'value', min: irisConfig.rankValueMin ?? 0, max: maxRank, axisLabel: { show: false, color: textColor }, axisLine: { lineStyle: { color: gridColor } }, splitLine: { lineStyle: { color: gridColor } } };
    option.yAxis = { type: 'category', data: displayLabels, axisLabel: { color: textColor }, inverse: false };
    option.series = [{ name: seriesName, type: 'bar', data: plottedValues.map((value, index) => ({ value, rawValue: displayValues[index], name: displayLabels[index], itemStyle: { color: resolvedColors[index] || resolvedColors[0] } })), itemStyle: { borderRadius: [0, 5, 5, 0] }, label: { show: true, position: 'right', formatter: params => format(params.data?.rawValue ?? 0), color: labelColor } }];
  } else {
    option.xAxis = { type: 'category', data: labelsForAxis, inverse: reverseOrder, axisLabel: { color: textColor, rotate: safeLabels.length > 6 ? 30 : 0 }, axisLine: { lineStyle: { color: gridColor } } };
    option.yAxis = { type: 'value', min: rankInverted ? 0 : valueMin, max: rankInverted ? maxRank : valueMax, inverse: rankSemantic && type === 'line', axisLabel: { show: !rankInverted, color: textColor }, axisLine: { lineStyle: { color: gridColor } }, splitLine: { lineStyle: { color: gridColor } } };
    option.series = [{ name: seriesName, type: type === 'line' ? 'line' : 'bar', smooth: false, data: plottedValues.map((value, index) => ({ value, rawValue: realValues[index], name: safeLabels[index], itemStyle: type === 'line' ? undefined : { color: resolvedColors[index] || resolvedColors[0] } })), itemStyle: type === 'line' ? { color: resolvedColors[0] } : { color: resolvedColors[0] }, lineStyle: type === 'line' ? { color: resolvedColors[0], width: 3 } : undefined, label: { show: type !== 'line' && safeLabels.length <= 20, position: 'top', formatter: params => format(params.data?.rawValue ?? params.value), color: labelColor } }];
  }
  return option;
}

export function buildSavedGraphOption(graphData, { width = 0, theme = null, colors: customColors = null } = {}) {
  const source = graphData || {};
  const config = source.irisConfig || source.chart_data?.irisConfig || source.chartData?.irisConfig || source.config || {};
  const legacyData = source.chart_data || source.chartData || source;
  const hasExplicitConfig = Boolean(source.irisConfig || source.chart_data?.irisConfig || source.chartData?.irisConfig);
  const labels = Array.isArray(source.labels) && (hasExplicitConfig || source.labels.length) ? source.labels : (config.labels || legacyData.labels || []);
  const values = Array.isArray(source.values_data) && (hasExplicitConfig || source.values_data.length)
    ? source.values_data
    : (config.rawValues || legacyData.datasets?.[0]?.data || legacyData.series?.[0]?.data?.map(point => point?.rawValue ?? point?.value ?? point) || []);
  const type = normalizeChartType(config.type || source.chart_type || source.chartType || 'bar');
  const legacyRanked = legacyData.rankedBar || source.rankedBar || {};
  const colorFields = type === 'nestedPie'
    ? [...(config.nestedGroups || []).map(group => group.label), ...(config.nestedGroups || []).flatMap(group => (group.children || []).map(child => child.label))]
    : type === 'line' && legacyData.series?.length > 1 ? legacyData.series.map(item => item.name || 'Series') : labels;
  const graphUpdatedAt = source.updated_at || source.updatedAt || null;
  const fieldColorUpdatedAt = globalThis.IRISFieldColorUpdatedAt || {};
  const resolvedColors = resolveFieldColors(colorFields, {
    chartColors: customColors || source.colors || config.colors,
    fieldColors: globalThis.IRISFieldColors || {},
    fieldColorUpdatedAt,
    chartUpdatedAt: graphUpdatedAt,
    legacyColors: DEFAULT_CHART_COLORS,
    defaultColors: DEFAULT_CHART_COLORS
  });
  const legacySeries = !config.type && !source.irisConfig && !source.chart_data?.irisConfig && !source.chartData?.irisConfig ? legacyData.series || [] : [];
  const graphConfig = {
    ...source,
    ...config,
    irisConfig: { ...config, colors: customColors || source.colors || config.colors, fieldColors: globalThis.IRISFieldColors || {}, fieldColorUpdatedAt, chartUpdatedAt: graphUpdatedAt },
    orientation: source.orientation || config.orientation,
    rankSemantic: source.rank_semantic ?? source.rankSemantic ?? config.rankSemantic,
    valueAxisMin: source.value_axis_min ?? config.valueAxisMin,
    valueAxisMax: source.value_axis_max ?? config.valueAxisMax,
    reverseOrder: config.reverseOrder ?? legacyRanked.reverseOrder ?? legacyRanked.reverse_order ?? source.reverse_order ?? source.reverseOrder,
    selectedYear: config.selectedYear ?? legacyRanked.selectedYear ?? legacyRanked.selected_year ?? source.selected_year ?? source.selectedYear,
    rankValueMin: source.rank_value_min ?? config.rankValueMin ?? legacyRanked.rankValueMin ?? legacyRanked.rank_value_min,
    rankValueMax: source.rank_value_max ?? config.rankValueMax ?? legacyRanked.rankValueMax ?? legacyRanked.rank_value_max,
    title: source.title,
    seriesName: source.title || legacyData.series?.[0]?.name || 'Value',
    fieldColors: globalThis.IRISFieldColors || {},
    colors: customColors || source.colors || config.colors,
    updated_at: graphUpdatedAt
  };
  return buildChartOption({ type, labels, values, rawValues: values, series: config.series || legacySeries, config: graphConfig, colors: resolvedColors, theme: theme || { dark: document.documentElement.classList.contains('dark') }, precision: config.precision ?? 2, context: { width }, showTitle: false });
}

export function createChart(element, type, graphData, { reverseOrder = false, colors: customColors = null, theme = null } = {}) {
  const source = graphData || {};
  const chartConfig = source.irisConfig || source.chart_data?.irisConfig || source.chartData?.irisConfig || {};
  const chart = window.echarts.init(element);
  const option = buildSavedGraphOption({ ...source, chart_type: chartConfig.type || type || source.chart_type }, { width: element.clientWidth, theme, colors: customColors });
  if (reverseOrder && !chartConfig.reverseOrder) option.xAxis && (option.xAxis.inverse = true);
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
    groupFieldSelect: document.getElementById('studioGroupField'),
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
  const circular = ['pie', 'doughnut', 'nestedPie'].includes(type);
  const groupFieldSelect = elements.groupFieldSelect || document.getElementById('studioGroupField');
  let labelCol = Number.isInteger(category) && category >= 0 && category < sheet.headers.length ? category : inferred.labelColumn;
  let valueCol = Number.isInteger(value) && value >= 0 && value < sheet.headers.length ? value : inferred.valueColumn;
  const yearColumn = typeof window.ChartMapping.detectYearColumn === 'function'
    ? window.ChartMapping.detectYearColumn(sheet.headers, sheet.rows)
    : null;
  if (yearColumn !== null && valueCol === yearColumn && labelCol !== yearColumn) {
    const alternativeValueColumn = inferred.numericColumns.find(column => column !== labelCol && column !== yearColumn);
    if (alternativeValueColumn !== undefined) {
      valueCol = alternativeValueColumn;
      const valueSelect = elements.valueSelect || document.getElementById('studioValueCol');
      if (valueSelect) valueSelect.value = String(valueCol);
    } else {
      warn('Year fields are categorical labels, not numeric chart values. Choose another Value field.');
      return empty('Year fields are categorical labels, not numeric chart values. Please select another Value field.');
    }
  }
  if (rankedMode && yearColumn !== null && labelCol === yearColumn) {
    const fallbackLabel = sheet.headers.findIndex((header, idx) => idx !== yearColumn && idx !== valueCol && !/year/i.test(String(header || '')) && String(header || '').trim() !== '');
    const nextLabel = fallbackLabel >= 0 ? fallbackLabel : sheet.headers.findIndex((header, idx) => idx !== yearColumn && idx !== valueCol);
    if (nextLabel >= 0 && document.getElementById('studioCategoryCol')) {
      document.getElementById('studioCategoryCol').value = String(nextLabel);
    }
  }
  if (labelCol === valueCol) { warn('Category and Value fields must be different columns.'); return empty('Category and Value fields must be different columns. Please adjust Field Mapping above.'); }
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
    const ranked = window.ChartMapping.buildRankedBarRows(sheet.rows, { yearColumn, selectedYear, labelColumn: labelCol, valueColumn: valueCol, limit, reverseOrder: false, valueIsRank: rankSemantic });
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
    if (group && type !== 'nestedPie') chartRows = circular ? window.ChartData.prepareCircularData(chartRows, true).rows : window.ChartData.groupAndAggregate(chartRows);
  }
  const fullLabels = chartRows.map(row => row.label);
  const labels = fullLabels.slice();
  const groupFieldRaw = groupFieldSelect?.value ?? state.studioChartConfig?.groupField ?? '';
  const groupField = groupFieldRaw === '' ? -1 : Number(groupFieldRaw);
  const nested = type === 'nestedPie' ? window.ChartData.prepareNestedPieData(chartRows, groupField) : null;
  if (type === 'nestedPie' && !nested.groups.length) return empty('Choose a Group (inner ring) field to render this chart.');
  const colorFields = type === 'nestedPie'
    ? [...nested.groups.map(item => item.label), ...nested.groups.flatMap(item => item.children.map(child => child.label))]
    : circular || type === 'bar' || rankedMode ? fullLabels : [headerName];
  const colorLabels = type === 'nestedPie'
    ? [...nested.groups.map(item => `Group: ${item.label}`), ...nested.groups.flatMap(item => item.children.map(child => `${item.label} / ${child.label}`))]
    : colorFields;
  const chartColors = resolveFieldColors(colorFields, {
    chartColors: state.studioChartOverrides,
    fieldColors: globalThis.IRISFieldColors || {},
    fieldColorUpdatedAt: globalThis.IRISFieldColorUpdatedAt || {},
    chartUpdatedAt: state.studioActiveGraphUpdatedAt,
    chartColorsOverrideShared: Array.isArray(state.studioChartOverrides),
    legacyColors: DEFAULT_CHART_COLORS,
    defaultColors: DEFAULT_CHART_COLORS
  });
  state.studioChartColors = [...chartColors];
  const rawValues = chartRows.map(row => rankedMode ? Number(row.rawValue ?? row.value ?? 0) : row.value);
  const values = rawValues;
  const yMin = Math.min(...values); const yMax = Math.max(...values); const axisMin = rankedMode ? 0 : (rankSemantic ? 0 : yMin >= 0 && yMin <= yMax * 0.8 ? 0 : Math.floor(yMin * 0.9));
  const horizontal = rankedMode;
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
    rawValues: rawValues.slice(),
    type,
    groupField: type === 'nestedPie' ? groupField : null,
    precision: displayPrecision,
    nestedGroups: nested?.groups || [],
    selectedYear: rankedMode ? (rankedYearSelect?.value ?? yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0] ?? 'all') : undefined,
    rankedYear: rankedMode ? (rankedYearSelect?.value ?? yearConfiguration.selectedYear ?? yearConfiguration.availableYears[0] ?? 'all') : undefined,
    yearColumn,
    reverseOrder: Boolean(rankedMode ? reverseOrder : false)
  };
  show();
  const chartConfig = { ...state.studioChartConfig, type, title: titleInput?.value || `${headerName} — ${info.name}`, seriesName: headerName, valueLabel: headerName, rankSemantic, nestedGroups: nested?.groups || [], colors: state.studioChartOverrides, fieldColors: globalThis.IRISFieldColors || {}, fieldColorUpdatedAt: globalThis.IRISFieldColorUpdatedAt || {}, chartUpdatedAt: state.studioActiveGraphUpdatedAt, chartColorsOverrideShared: Array.isArray(state.studioChartOverrides) };
  state.studioChartInstance = window.echarts.init(canvas);
  state.studioChartInstance.setOption(buildChartOption({ type, labels, values, rawValues, config: chartConfig, colors: chartColors, theme: { dark: document.documentElement.classList.contains('dark') }, precision: displayPrecision, context: { width: canvas.clientWidth }, showTitle: true }));
  ctx?.api?.renderStudioColorCustomizer?.({ chartType: type, labels: colorLabels, colorKeys: colorFields, colors: chartColors, overrides: state.studioChartOverrides });
  if (typeof ResizeObserver !== 'undefined') { canvas._studioResizeObserver?.disconnect?.(); canvas._studioResizeObserver = new ResizeObserver(() => state.studioChartInstance?.resize?.()); canvas._studioResizeObserver.observe(canvas); }
}

if (typeof window !== 'undefined') {
  window.IRISChartBuilder = Object.assign(window.IRISChartBuilder || {}, { buildChartOption, buildSavedGraphOption, getChartTheme, normalizeChartType });
}
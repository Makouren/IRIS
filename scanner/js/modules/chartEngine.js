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

const CHART_TYPES = new Set(['line', 'stackedArea', 'bar', 'pie', 'doughnut', 'nestedPie']);

function normalizeChartType(type) {
  const normalized = String(type || 'bar').trim();
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

function baseOption(config, colors, theme, context, showTitle, trigger = 'axis') {
  const { textColor, labelColor, tooltipBackground, tooltipBorder } = getChartTheme(theme);
  const option = {
    color: colors,
    textStyle: { color: textColor },
    tooltip: { trigger, backgroundColor: tooltipBackground, borderColor: tooltipBorder, textStyle: { color: labelColor } },
    animationDuration: context.animate === false ? 0 : 350
  };
  if (showTitle && config.title) option.title = { text: config.title, left: 'center', textStyle: { color: labelColor, fontSize: 14, fontWeight: 700 } };
  return option;
}

function buildLineOption(rows, config, colors, theme, context, showTitle, precision, series = []) {
  const { textColor, labelColor, gridColor } = getChartTheme(theme);
  const format = value => formatChartValueForDisplay(value, precision);
  const reverse = Boolean(config.reverseOrder);
  const orderedRows = reverse ? rows.slice().reverse() : rows;
  const labels = orderedRows.map(row => row.label);
  const sourceSeries = series.length ? series : [{ name: config.seriesName || 'Value', data: rows.map(row => row.value) }];
  const option = baseOption(config, colors, theme, context, showTitle);
  option.grid = { left: '5%', right: '5%', top: showTitle ? 52 : '8%', bottom: labels.length > 7 ? '16%' : '8%', containLabel: true };
  if (sourceSeries.length > 1) option.legend = { data: sourceSeries.map((item, index) => item.name || `Series ${index + 1}`), textStyle: { color: textColor } };
  option.tooltip.formatter = params => (Array.isArray(params) ? params : [params]).map(point => `${point.seriesName ? `${point.seriesName}<br/>` : ''}${point.name}: ${format(point.data?.rawValue ?? point.value)}`).join('<br/>');
  option.xAxis = { type: 'category', data: labels, axisLabel: { color: textColor }, axisLine: { lineStyle: { color: gridColor } } };
  const maxRankValue = Number(config.rankValueMax ?? Math.max(...rows.map(row => row.value), 0));
  option.yAxis = {
    type: 'value',
    min: config.rankSemantic ? config.valueAxisMin ?? 0 : config.valueAxisMin,
    max: config.rankSemantic ? config.valueAxisMax ?? Math.ceil(maxRankValue * 1.06) : config.valueAxisMax,
    inverse: config.rankSemantic ? true : undefined,
    axisLabel: { color: textColor, formatter: format },
    axisLine: { lineStyle: { color: gridColor } },
    splitLine: { lineStyle: { color: gridColor } }
  };
  option.series = sourceSeries.map((item, seriesIndex) => ({
    name: item.name || config.seriesName || 'Value',
    type: 'line',
    smooth: false,
    showSymbol: true,
    data: orderedRows.map((_, index) => {
      const point = item.data?.[reverse ? rows.length - index - 1 : index] ?? rows[reverse ? rows.length - index - 1 : index]?.value ?? 0;
      const value = Number(point?.rawValue ?? point?.value ?? point ?? 0);
      return { name: labels[index], value, rawValue: value };
    }),
    itemStyle: { color: colors[seriesIndex % colors.length] },
    lineStyle: { color: colors[seriesIndex % colors.length], width: 3 }
  }));
  return option;
}

function buildStackedAreaOption(rows, config, colors, theme, context, showTitle, precision, series = []) {
  const { textColor, gridColor } = getChartTheme(theme);
  const format = value => formatChartValueForDisplay(value, precision);
  const reverse = Boolean(config.reverseOrder);
  const orderedRows = reverse ? rows.slice().reverse() : rows;
  const labels = orderedRows.map(row => row.label);
  const sourceSeries = series.length ? series : [{ name: config.seriesName || 'Value', data: rows.map(row => row.value) }];
  const option = baseOption(config, colors, theme, context, showTitle);
  option.grid = { left: '5%', right: '5%', top: showTitle ? 52 : '8%', bottom: labels.length > 7 ? '16%' : '8%', containLabel: true };
  option.legend = { data: sourceSeries.map((item, index) => item.name || `Series ${index + 1}`), type: 'scroll', textStyle: { color: textColor } };
  option.tooltip = { ...option.tooltip, axisPointer: { type: 'cross', label: { backgroundColor: '#6a7985' } }, formatter: params => (Array.isArray(params) ? params : [params]).map(point => `${point.seriesName}<br/>${point.name}: ${format(point.data?.rawValue ?? point.value)}`).join('<br/>') };
  option.xAxis = { type: 'category', boundaryGap: false, data: labels, axisLabel: { color: textColor }, axisLine: { lineStyle: { color: gridColor } } };
  option.yAxis = { type: 'value', min: config.valueAxisMin, max: config.valueAxisMax, axisLabel: { color: textColor, formatter: format }, axisLine: { lineStyle: { color: gridColor } }, splitLine: { lineStyle: { color: gridColor } } };
  option.series = sourceSeries.map((item, seriesIndex) => ({
    name: item.name || config.seriesName || 'Value',
    type: 'line',
    stack: 'Total',
    areaStyle: {},
    emphasis: { focus: 'series' },
    data: orderedRows.map((_, index) => {
      const sourceIndex = reverse ? rows.length - index - 1 : index;
      const point = item.data?.[sourceIndex] ?? rows[sourceIndex]?.value ?? 0;
      const value = Number(point?.rawValue ?? point?.value ?? point ?? 0);
      return { value, rawValue: value };
    }),
    itemStyle: { color: colors[seriesIndex % colors.length] },
    lineStyle: { color: colors[seriesIndex % colors.length] }
  }));
  return option;
}

function buildBarOption(rows, config, colors, theme, context, showTitle, precision) {
  const { textColor, labelColor, gridColor } = getChartTheme(theme);
  const format = value => formatChartValueForDisplay(value, precision);
  const orderedRows = config.reverseOrder ? rows.slice().reverse() : rows;
  const labels = orderedRows.map(row => row.label);
  const option = baseOption(config, colors, theme, context, showTitle);
  option.grid = { left: '5%', right: '5%', top: showTitle ? 52 : '8%', bottom: labels.length > 7 ? '16%' : '8%', containLabel: true };
  option.legend = { data: [config.seriesName || 'Value'], textStyle: { color: textColor } };
  option.tooltip.formatter = params => (Array.isArray(params) ? params : [params]).map(point => `${point.name}: ${format(point.data?.rawValue ?? point.value)}`).join('<br/>');
  option.xAxis = { type: 'category', data: labels, axisLabel: { color: textColor, rotate: labels.length > 6 ? 30 : 0 }, axisLine: { lineStyle: { color: gridColor } } };
  option.yAxis = { type: 'value', min: config.valueAxisMin, max: config.valueAxisMax, axisLabel: { color: textColor, formatter: format }, axisLine: { lineStyle: { color: gridColor } }, splitLine: { lineStyle: { color: gridColor } } };
  option.series = [{
    name: config.seriesName || 'Value',
    type: 'bar',
    data: orderedRows.map((row, index) => ({ name: row.label, value: row.value, rawValue: row.rawValue ?? row.value, itemStyle: { color: colors[index % colors.length] } })),
    label: { show: labels.length <= 20, position: 'top', formatter: params => format(params.data?.rawValue ?? params.value), color: labelColor }
  }];
  return option;
}

function buildCircularOption(rows, config, colors, theme, context, showTitle, precision, doughnut) {
  const { textColor, labelColor, tooltipBackground, tooltipBorder } = getChartTheme(theme);
  const sliceLabelColor = theme.dark ? '#FFFFFF' : labelColor;
  const format = value => formatChartValueForDisplay(value, precision);
  const option = baseOption(config, colors, theme, context, showTitle, 'item');
  option.tooltip.formatter = params => `${params.seriesName || config.seriesName || 'Value'}<br/>${params.name}: ${format(params.data?.rawValue ?? params.value)} (${params.percent}%)`;
  option.legend = { data: rows.map(row => row.label), type: 'scroll', orient: 'horizontal', bottom: 0, left: 'center', textStyle: { color: sliceLabelColor } };
  option.series = [{
    name: config.seriesName || 'Value',
    type: 'pie',
    radius: doughnut ? ['38%', '65%'] : '55%',
    center: ['50%', '42%'],
    avoidLabelOverlap: false,
    itemStyle: { borderRadius: doughnut ? 6 : 0, borderColor: theme.dark ? '#111827' : '#FFFFFF', borderWidth: 2 },
    data: rows.map((row, index) => ({ name: row.label, value: row.value, rawValue: row.rawValue ?? row.value, itemStyle: { color: colors[index % colors.length] } })),
    label: { show: true, color: sliceLabelColor, textBorderWidth: 0, textBorderColor: 'transparent', formatter: params => `${params.name}: ${format(params.data?.rawValue ?? params.value)} (${params.percent}%)` },
    emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0,0,0,0.35)' } }
  }];
  option.tooltip = { ...option.tooltip, backgroundColor: tooltipBackground, borderColor: tooltipBorder, textStyle: { color: labelColor } };
  return option;
}

function buildPieOption(rows, config, colors, theme, context, showTitle, precision) {
  return buildCircularOption(rows, config, colors, theme, context, showTitle, precision, false);
}

function buildDoughnutOption(rows, config, colors, theme, context, showTitle, precision) {
  return buildCircularOption(rows, config, colors, theme, context, showTitle, precision, true);
}

function buildNestedPieOption(_rows, config, colors, theme, context, showTitle, precision) {
  const irisConfig = config.irisConfig || config;
  const { textColor, labelColor, tooltipBackground, tooltipBorder } = getChartTheme(theme);
  const format = value => formatChartValueForDisplay(value, precision);
  const groups = Array.isArray(irisConfig.nestedGroups) ? irisConfig.nestedGroups : [];
  const option = baseOption(config, colors, theme, context, showTitle, 'item');
  if (!groups.length) {
    option.title = { ...(option.title || {}), text: 'Choose a Group (inner ring) field to render this chart', left: 'center', top: 'middle', textStyle: { color: textColor, fontSize: 14 } };
    option.series = [];
    return option;
  }
  const groupColors = groups.map((group, index) => {
    if (sharedFieldColorIsNewer(group.label, irisConfig)) return colors[index % colors.length];
    const fieldKey = String(group.label).trim().toLowerCase().replace(/\s+/g, ' ');
    return irisConfig.groupColors?.[index] || irisConfig.colors?.[index] || irisConfig.fieldColors?.[fieldKey] || colors[index % colors.length];
  });
  const childLabels = groups.flatMap(group => (group.children || []).map(child => child.label));
  const childColors = groups.flatMap((group, groupIndex) => (group.children || []).map((child, childIndex) => {
    const sliceIndex = groups.slice(0, groupIndex).reduce((count, item) => count + (item.children || []).length, 0) + childIndex;
    const fieldKey = String(child.label).trim().toLowerCase().replace(/\s+/g, ' ');
    const explicit = sharedFieldColorIsNewer(fieldKey, irisConfig) ? colors[(groups.length + sliceIndex) % colors.length] : irisConfig.sliceColors?.[`${group.label}::${child.label}`] || irisConfig.colors?.[groups.length + sliceIndex] || irisConfig.fieldColors?.[fieldKey];
    return explicit || shadeColor(groupColors[groupIndex], (childIndex % 2 ? -1 : 1) * (0.12 + (childIndex % 4) * 0.06));
  }));
  const crowded = Number(context.width) < 560 || childLabels.length > 8;
  const outerData = groups.flatMap((group, groupIndex) => (group.children || []).map((child, childIndex) => {
    const colorIndex = groups.slice(0, groupIndex).reduce((count, item) => count + (item.children || []).length, 0) + childIndex;
    return { name: child.label, value: Number(child.value || 0), rawValue: Number(child.rawValue ?? child.value ?? 0), group: group.label, itemStyle: { color: childColors[colorIndex] } };
  }));
  option.tooltip = { ...option.tooltip, backgroundColor: tooltipBackground, borderColor: tooltipBorder, textStyle: { color: labelColor }, formatter: params => `${params.data?.group ? `${params.data.group} / ` : ''}${params.name}: ${format(params.data?.rawValue ?? params.value)} (${params.percent}%)` };
  option.legend = { data: crowded ? childLabels : groups.map(group => group.label), type: 'scroll', orient: 'horizontal', bottom: 0, left: 'center', textStyle: { color: textColor } };
  option.series = [
    { name: 'Groups', type: 'pie', radius: [0, '28%'], center: ['50%', '42%'], selectedMode: 'single', data: groups.map((group, index) => ({ name: group.label, value: group.value, rawValue: group.value, itemStyle: { color: groupColors[index] } })), label: { position: 'inside', formatter: '{b}', color: '#FFFFFF' }, labelLine: { show: false } },
    { name: config.seriesName || 'Value', type: 'pie', radius: ['42%', '58%'], center: ['50%', '42%'], data: outerData, label: crowded ? { formatter: params => `${params.name}: ${params.percent}%`, color: labelColor } : { formatter: params => `{name|${params.name}}\n{value|${format(params.data?.rawValue ?? params.value)}}  {percent|${params.percent}%}`, color: labelColor, rich: { name: { fontWeight: 700, color: labelColor }, value: { color: labelColor }, percent: { color: textColor } } }, labelLine: { show: !crowded } }
  ];
  option.color = [...groupColors, ...childColors];
  return option;
}

export function buildChartOption({ type, labels = [], values = [], rawValues = values, series = [], config = {}, colors = [], theme = {}, precision = 2, context = {}, showTitle = false } = {}) {
  type = normalizeChartType(type || config.type);
  const irisConfig = config.irisConfig || config;
  const resolvedColors = colors.length ? colors : DEFAULT_CHART_COLORS;
  const rows = labels.map((label, index) => ({ label: String(label ?? `Item ${index + 1}`), value: Number(rawValues[index] ?? values[index] ?? 0), rawValue: Number(rawValues[index] ?? values[index] ?? 0) }));
  const optionArgs = [config, resolvedColors, theme, context, showTitle, precision];
  if (type === 'line') return buildLineOption(rows, ...optionArgs, series);
  if (type === 'stackedArea') return buildStackedAreaOption(rows, ...optionArgs, series);
  if (type === 'bar') return buildBarOption(rows, ...optionArgs);
  if (type === 'pie') return buildPieOption(rows, ...optionArgs);
  if (type === 'doughnut') return buildDoughnutOption(rows, ...optionArgs);
  if (type === 'nestedPie') return buildNestedPieOption(rows, { ...config, irisConfig }, resolvedColors, theme, context, showTitle, precision);

  return buildBarOption(rows, config, resolvedColors, theme, context, showTitle, precision);
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
  const savedSeries = config.series?.length ? config.series : legacyData.series || [];
  const colorFields = type === 'nestedPie'
    ? [...(config.nestedGroups || []).map(group => group.label), ...(config.nestedGroups || []).flatMap(group => (group.children || []).map(child => child.label))]
    : ['line', 'stackedArea'].includes(type) && savedSeries.length ? savedSeries.map(item => item.name || 'Series') : labels;
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
    rankValueMax: source.rank_value_max ?? source.rankValueMax ?? config.rankValueMax,
    reverseOrder: config.reverseOrder ?? source.reverse_order ?? source.reverseOrder,
    selectedYear: config.selectedYear ?? source.selected_year ?? source.selectedYear,
    title: source.title,
    seriesName: source.title || legacyData.series?.[0]?.name || 'Value',
    fieldColors: globalThis.IRISFieldColors || {},
    colors: customColors || source.colors || config.colors,
    updated_at: graphUpdatedAt
  };
  return buildChartOption({ type, labels, values, rawValues: values, series: config.series || legacySeries, config: graphConfig, colors: resolvedColors, theme: theme || { dark: typeof document !== 'undefined' && document.documentElement.classList.contains('dark') }, precision: config.precision ?? 2, context: { width }, showTitle: false });
}

export function createChart(element, type, graphData, { reverseOrder = false, colors: customColors = null, theme = null } = {}) {
  const source = graphData || {};
  const chartConfig = source.irisConfig || source.chart_data?.irisConfig || source.chartData?.irisConfig || {};
  window.echarts.getInstanceByDom?.(element)?.dispose?.();
  const chart = window.echarts.init(element);
  const option = buildSavedGraphOption({ ...source, chart_type: chartConfig.type || type || source.chart_type }, { width: element.clientWidth, theme, colors: customColors });
  if (reverseOrder && !chartConfig.reverseOrder) option.xAxis && (option.xAxis.inverse = true);
  chart.setOption(option, true);
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
    seriesFieldSelect: document.getElementById('studioSeriesField'),
    yearSelect: document.getElementById('studioYearSelect'),
    reverseOrder: document.getElementById('studioReverseOrder'),
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
  const dispose = () => {
    const chart = state.studioChartInstance;
    chart?._studioResizeObserver?.disconnect?.();
    if (chart?._studioResizeHandler && typeof window.removeEventListener === 'function') window.removeEventListener('resize', chart._studioResizeHandler);
    chart?.dispose?.();
    state.studioChartInstance = null;
    state.studioChartCanvas = null;
  };
  const empty = message => { dispose(); canvas.style.display = 'none'; if (emptyState) emptyState.style.display = 'flex'; if (emptyMsg) emptyMsg.textContent = message; };
  const show = () => { canvas.style.display = ''; if (emptyState) emptyState.style.display = 'none'; };
  const warn = message => { if (warning) { warning.textContent = message; warning.style.display = message ? 'block' : 'none'; } };
  warn('');
  if (state.studioChartInstance && state.studioChartCanvas !== canvas) dispose();
  const info = getStudioActiveSheet(record);
  const sheet = info?.data;
  if (!sheet || !sheet.headers || !sheet.rows || !sheet.rows.length) return empty(sheet ? 'No rows to chart.' : 'No sheet data available.');
  const inferred = window.ChartMapping.inferColumns(sheet.headers, sheet.rows);
  const category = Number(elements.categorySelect?.value ?? document.getElementById('studioCategoryCol')?.value);
  const value = Number(elements.valueSelect?.value ?? document.getElementById('studioValueCol')?.value);
  const type = normalizeChartType(typeSelect.value);
  if (typeSelect.value !== type) typeSelect.value = type;
  const circular = ['pie', 'doughnut', 'nestedPie'].includes(type);
  const groupFieldSelect = elements.groupFieldSelect || document.getElementById('studioGroupField');
  const seriesFieldSelect = elements.seriesFieldSelect || document.getElementById('studioSeriesField');
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
  if (labelCol === valueCol) { warn('Category and Value fields must be different columns.'); return empty('Category and Value fields must be different columns. Please adjust Field Mapping above.'); }
  const isYearLike = value => window.ChartMapping.parseNumericValue(value) !== null && window.ChartMapping.parseNumericValue(value) >= 1900 && window.ChartMapping.parseNumericValue(value) <= 2100 && /^\s*\d{4}\s*$/.test(String(value));
  const valueIsYear = /year/i.test(String(sheet.headers[valueCol] || '')) || sheet.rows.some(row => row?.[valueCol] !== null && row?.[valueCol] !== undefined && isYearLike(row[valueCol]));
  const labelIsYear = /year/i.test(String(sheet.headers[labelCol] || '')) || sheet.rows.some(row => row?.[labelCol] !== null && row?.[labelCol] !== undefined && isYearLike(row[labelCol]));
  const rankSemantic = window.ChartMapping.isRankField(sheet.headers[valueCol]);
  const headerName = sheet.headers[valueCol] || 'Value';
  if (subtitle) subtitle.textContent = `Live interactive rendering from: ${info.name}`;
  if (titleInput && !titleInput.getAttribute('data-customized')) titleInput.value = `${headerName} — ${info.name}`;
  if (typeof window.echarts === 'undefined') return empty('The chart renderer is unavailable. Reload the page and try again.');
  const yearSelect = elements.yearSelect || document.getElementById('studioYearSelect');
  const reverseOrderInput = elements.reverseOrder || document.getElementById('studioReverseOrder');
  const reverseOrderEnabled = ['line', 'stackedArea', 'bar'].includes(type);
  const reverseOrder = reverseOrderEnabled && Boolean(reverseOrderInput?.checked ?? state.studioChartConfig?.reverseOrder ?? false);
  const yearConfiguration = yearColumn !== null ? window.ChartMapping.getYearOptions(sheet.rows, yearColumn) : { availableYears: [], selectedYear: null };
  const yearWrapper = document.getElementById('studioYearWrapper');
  if (yearWrapper) yearWrapper.style.display = yearConfiguration.availableYears.length ? 'flex' : 'none';
  const reverseWrapper = document.getElementById('studioReverseOrderWrapper');
  if (reverseWrapper) reverseWrapper.style.display = reverseOrderEnabled ? 'flex' : 'none';
  if (reverseOrderInput) reverseOrderInput.checked = Boolean(state.studioChartConfig?.reverseOrder ?? reverseOrderInput.checked);
  const currentYear = yearSelect?.options?.length
    ? yearSelect.value
    : state.studioChartConfig?.selectedYear ?? yearSelect?.value ?? 'all';
  if (yearSelect) {
    yearSelect.innerHTML = yearConfiguration.availableYears.length
      ? '<option value="all">All years</option>' + yearConfiguration.availableYears.map(year => `<option value="${year}">${year}</option>`).join('')
      : '<option value="">No year data</option>';
    const selectedYear = currentYear === 'all' || !yearConfiguration.availableYears.includes(Number(currentYear)) ? 'all' : String(currentYear);
    yearSelect.value = selectedYear;
    state.studioChartConfig = { ...(state.studioChartConfig || {}), selectedYear, yearColumn };
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
  const seriesFieldRaw = seriesFieldSelect?.value ?? state.studioChartConfig?.seriesField ?? '';
  const seriesField = seriesFieldRaw === '' ? -1 : Number(seriesFieldRaw);
  const rows = sheet.rows.map((row, index) => {
    const rawValue = row?.[valueIsYear && !labelIsYear ? labelCol : valueCol];
    return {
      sourceIndex: index,
      row: row || [],
      label: String(row?.[valueIsYear ? valueCol : labelCol] ?? `Item ${index + 1}`).trim() || `Item ${index + 1}`,
        seriesLabel: type === 'stackedArea' && seriesField >= 0 ? String(row?.[seriesField] ?? '').trim() || '(blank)' : headerName,
      value: rankSemantic ? window.ChartMapping.parseRankValue(rawValue) : window.ChartMapping.parseNumericValue(rawValue),
      rawValue
    };
  }).filter(item => item.value !== null);
  if (!rows.length) { warn(`The selected Value column "${headerName}" contains no numeric data. Choose a different Value field.`); return empty(`No numeric data found in column "${headerName}". Please select a numeric Value field above.`); }
  const selectedYear = yearSelect?.value || state.studioChartConfig?.selectedYear || 'all';
  const yearFilteredRows = yearColumn !== null && selectedYear !== 'all'
    ? rows.filter(item => window.ChartMapping.parseYearValue(item.row[yearColumn]) === Number(selectedYear))
    : rows;
  let chartRows = yearFilteredRows;
  if (!chartRows.length) return empty('No data matches the selected year. Try another year or select All years.');
  if (filterValue && ['all', 'contains'].includes(operator)) {
    const selected = filterField.startsWith('column:') ? Number(filterField.slice(7)) : -1;
    const filterRows = chartRows.map(item => [selected >= 0 ? item.row[selected] : filterField === 'context' ? item.row[labelCol] : filterField === 'value' ? item.row[valueCol] : item.row]);
    const scope = `${info.name}:${filterField}:${operator}:${selectedYear}`;
    const same = state.studioFilterPreviousScope === scope;
    const filtered = window.TableFilter.filterRows([], filterRows, filterValue, { previousQuery: same ? state.studioFilterPreviousQuery : '', previousResults: same ? state.studioFilterPreviousResults : null, includeHeaders: false });
    state.studioFilterPreviousQuery = filterValue;
    state.studioFilterPreviousResults = filtered;
    state.studioFilterPreviousScope = scope;
    const matches = new Set(filtered.map(item => chartRows[item.origIdx]?.sourceIndex));
    chartRows = chartRows.filter(item => matches.has(item.sourceIndex));
  } else if (operator !== 'all' && filterValue) {
    state.studioFilterPreviousQuery = '';
    state.studioFilterPreviousResults = null;
    state.studioFilterPreviousScope = '';
    const numeric = Number(filterValue);
    chartRows = chartRows.filter(item => {
      const cells = filterField === 'context' ? [item.row[labelCol]] : filterField === 'value' ? [item.row[valueCol]] : item.row;
      const text = cells.map(cell => String(cell ?? '')).join(' ').toLowerCase();
      const query = filterValue.toLowerCase();
      if (operator === 'contains') return text.includes(query);
      if (operator === 'starts-with') return text.startsWith(query);
      if (operator === 'ends-with') return text.endsWith(query);
      if (operator === 'equals') return text === query;
      if (operator === 'not-equals') return text !== query;
      if (operator === 'greater-than') return Number.isFinite(numeric) && item.value > numeric;
      if (operator === 'less-than') return Number.isFinite(numeric) && item.value < numeric;
      if (operator === 'between') return Number.isFinite(numeric) && Number.isFinite(upper) && item.value >= numeric && item.value <= upper;
      return true;
    });
  } else {
    state.studioFilterPreviousQuery = '';
    state.studioFilterPreviousResults = null;
    state.studioFilterPreviousScope = '';
  }
  if (sortOrder === 'value-asc') chartRows.sort((a, b) => a.value - b.value);
  if (sortOrder === 'value-desc') chartRows.sort((a, b) => b.value - a.value);
  if (sortOrder === 'label-asc') chartRows.sort((a, b) => a.label.localeCompare(b.label));
  if (sortOrder === 'label-desc') chartRows.sort((a, b) => b.label.localeCompare(a.label));
  chartRows = chartRows.slice(0, limit);
  if (!chartRows.length) return empty('No data matches the current filter. Try adjusting the filter criteria.');
  if (group && type === 'stackedArea' && seriesField >= 0) {
    const groupedSeriesRows = new Map();
    chartRows.forEach(row => {
      const key = JSON.stringify([row.label, row.seriesLabel]);
      const previous = groupedSeriesRows.get(key) || { ...row, value: 0, count: 0 };
      previous.value += row.value;
      previous.count += 1;
      groupedSeriesRows.set(key, previous);
    });
    chartRows = Array.from(groupedSeriesRows.values(), row => ({ ...row, value: Number((row.value / row.count).toFixed(4)), rawValue: Number((row.value / row.count).toFixed(4)) }));
  } else if (group && type !== 'nestedPie') {
    chartRows = circular ? window.ChartData.prepareCircularData(chartRows, true).rows : window.ChartData.groupAndAggregate(chartRows);
  }
  const fullLabels = chartRows.map(row => row.label);
  const labels = type === 'stackedArea' && seriesField >= 0 && group
    ? [...new Set(fullLabels)]
    : fullLabels.slice();
  const plottedRows = labels.length === chartRows.length
    ? chartRows
    : labels.map(label => chartRows.find(row => row.label === label)).filter(Boolean);
  const groupFieldRaw = groupFieldSelect?.value ?? state.studioChartConfig?.groupField ?? '';
  const groupField = groupFieldRaw === '' ? -1 : Number(groupFieldRaw);
  const nested = type === 'nestedPie' ? window.ChartData.prepareNestedPieData(chartRows, groupField) : null;
  if (type === 'nestedPie' && !nested.groups.length) return empty('Choose a Group (inner ring) field to render this chart.');
  const stackedSeries = type === 'stackedArea'
    ? seriesField >= 0
      ? [...new Set(chartRows.map(row => row.seriesLabel).filter(Boolean))].map(name => ({
        name,
        data: group
          ? [...new Set(chartRows.map(row => row.label))].map(label => chartRows.find(row => row.label === label && row.seriesLabel === name)?.value ?? 0)
          : chartRows.map(row => row.seriesLabel === name ? row.value : 0)
      }))
      : [{ name: headerName, data: chartRows.map(row => row.value) }]
    : [];
  const colorFields = type === 'nestedPie'
    ? [...nested.groups.map(item => item.label), ...nested.groups.flatMap(item => item.children.map(child => child.label))]
    : type === 'stackedArea' ? (stackedSeries.map(item => item.name))
      : circular || type === 'bar' ? fullLabels : [headerName];
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
  const rawValues = plottedRows.map(row => Number(row.value ?? 0));
  const values = rawValues;
  const yMin = Math.min(...values);
  const yMax = Math.max(...values);
  const axisMin = yMin >= 0 && yMin <= yMax * 0.8 ? 0 : Math.floor(yMin * 0.9);
  state.studioChartConfig = {
    ...(state.studioChartConfig || {}),
    valueAxisMin: axisMin,
    valueAxisMax: yMax,
    labels: labels.slice(),
    rawValues: rawValues.slice(),
    type,
    groupField: type === 'nestedPie' ? groupField : null,
    seriesField: type === 'stackedArea' ? seriesField : null,
    series: stackedSeries,
    precision: displayPrecision,
    nestedGroups: nested?.groups || [],
    selectedYear,
    yearColumn,
    reverseOrder
  };
  show();
  const chartConfig = { ...state.studioChartConfig, type, title: titleInput?.value || `${headerName} — ${info.name}`, seriesName: headerName, valueLabel: headerName, rankSemantic, nestedGroups: nested?.groups || [], colors: state.studioChartOverrides, fieldColors: globalThis.IRISFieldColors || {}, fieldColorUpdatedAt: globalThis.IRISFieldColorUpdatedAt || {}, chartUpdatedAt: state.studioActiveGraphUpdatedAt, chartColorsOverrideShared: Array.isArray(state.studioChartOverrides) };
  const option = buildChartOption({ type, labels, values, rawValues, series: stackedSeries, config: chartConfig, colors: chartColors, theme: { dark: document.documentElement.classList.contains('dark') }, precision: displayPrecision, context: { width: canvas.clientWidth }, showTitle: true });
  if (!state.studioChartInstance) {
    state.studioChartInstance = window.echarts.init(canvas);
    state.studioChartCanvas = canvas;
    const chart = state.studioChartInstance;
    chart._studioResizeHandler = () => chart.resize?.();
    if (typeof window.addEventListener === 'function') window.addEventListener('resize', chart._studioResizeHandler);
    if (typeof ResizeObserver !== 'undefined') {
      chart._studioResizeObserver = new ResizeObserver(() => chart.resize?.());
      chart._studioResizeObserver.observe(canvas);
    }
  }
  state.studioChartInstance.setOption(option, true);
  ctx?.api?.renderStudioColorCustomizer?.({ chartType: type, labels: colorLabels, colorKeys: colorFields, colors: chartColors, overrides: state.studioChartOverrides });
}

const chartBuilder = Object.assign(globalThis.IRISChartBuilder || {}, { buildChartOption, buildSavedGraphOption, getChartTheme, normalizeChartType });
globalThis.IRISChartBuilder = chartBuilder;
if (typeof window !== 'undefined') window.IRISChartBuilder = chartBuilder;
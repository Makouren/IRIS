const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const readIrisApiSource = () => [
  path.join(__dirname, '..', '..', 'api', 'iris.php'),
  path.join(__dirname, '..', '..', 'includes', 'api', 'common.php'),
  ...[
    'record_file_history',
    'field_colors',
    'summary_card_categories',
    'summary_card_history',
    'summary_cards',
    'records',
    'graphs'
  ].map(handler => path.join(__dirname, '..', '..', 'includes', 'api', 'handlers', `${handler}.php`))
].map(file => fs.readFileSync(file, 'utf8')).join('\n');
const { prepareCircularData, serializeChartState } = require('../js/charts/chartData');
const { pairSelectedText } = require('../js/ingestion/sourceIngestion');
const { normalizeGraphExportItem, buildPrintableGraphSheet, buildSavedChartOption } = require('../js/charts/graphExport');
const { buildChartOption, buildSavedGraphOption, createChart, renderStudioChart } = require('../js/modules/chartEngine');
const { buildColoredSeriesData, getChartColors, isValidChartColor, normalizeFieldKey, resolveFieldColors } = require('../js/charts/chartColors');
const stylesheetBundle = [
  'base.css',
  'ingestion.css',
  'layout.css',
  'studio.css',
  'viewer.css',
  'charts.css',
  'tables.css',
  'form-controls.css',
  'modals.css',
  'docx-viewer.css',
  'dark-overrides.css'
].map(file => fs.readFileSync(path.join(__dirname, '..', 'css', file), 'utf8')).join('\n');
const savedGraphsSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'savedGraphsTab.js'), 'utf8');
const studioColorCustomizerSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioColorCustomizer.js'), 'utf8');
const studioWorkbenchSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioWorkbench.js'), 'utf8');
const publicDashboardSource = [
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8'),
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'css', 'dashboard.css'), 'utf8'),
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'js', 'dashboard', 'main.js'), 'utf8'),
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'js', 'dashboard', 'chartBuilder.js'), 'utf8')
].join('\n');
const graphApiSource = readIrisApiSource();
const publicGraphApiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'dashboard_graphs.php'), 'utf8');
const reviewEditorSource = [
  fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'review_editor.php'), 'utf8'),
  ...[
    'summaryCards.php',
    'starRatingCards.php',
    'rankingHistory.php',
    'studioFieldColors.php',
    'manualDataset.php',
    'recordEdit.php'
  ].map(file => fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'reviewEditor', file), 'utf8')),
  ...[
    'summaryCards.js',
    'starRatingCards.js',
    'rankingHistory.js'
  ].map(file => fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'reviewEditor', file), 'utf8'))
].join('\n');
const chartEngineSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8');
const rankingHistoryControllerSource = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'js', 'rankingHistory.js'), 'utf8');

test('all six chart builders use live rows, precision formatting, and mapped colors', () => {
  const common = {
    labels: ['Alpha', 'Beta'],
    values: [1.234, 2.5],
    rawValues: [1.234, 2.5],
    colors: ['#112233', '#445566'],
    precision: 1,
    config: { title: 'Score — Sheet', seriesName: 'Score', reverseOrder: true },
    showTitle: true
  };
  const line = buildChartOption({ ...common, type: 'line' });
  const area = buildChartOption({
    ...common,
    type: 'stackedArea',
    series: [{ name: 'Group A', data: [1.234, 2.5] }, { name: 'Group B', data: [3, 4] }]
  });
  const bar = buildChartOption({ ...common, type: 'bar' });
  const pie = buildChartOption({ ...common, type: 'pie' });
  const doughnut = buildChartOption({ ...common, type: 'doughnut' });
  const nestedPie = buildChartOption({
    ...common,
    type: 'nestedPie',
    config: { ...common.config, nestedGroups: [{ label: 'Parent', value: 3.734, children: [{ label: 'Alpha', value: 1.234, rawValue: 1.234 }, { label: 'Beta', value: 2.5, rawValue: 2.5 }] }] }
  });

  assert.deepEqual(line.xAxis.data, ['Beta', 'Alpha']);
  assert.deepEqual(line.series[0].data.map(point => point.value), [2.5, 1.234]);
  assert.equal(line.yAxis.axisLabel.formatter(1.234), '1.2');
  assert.equal(area.series.length, 2);
  assert.deepEqual(area.xAxis.data, ['Beta', 'Alpha']);
  assert.deepEqual(area.series[0].data.map(point => point.value), [2.5, 1.234]);
  assert.equal(area.series[0].type, 'line');
  assert.equal(area.series[0].stack, 'Total');
  assert.deepEqual(bar.xAxis.data, ['Beta', 'Alpha']);
  assert.equal(bar.legend, undefined, 'single-series bar charts do not need a legend that can overlap the title');
  assert.deepEqual(bar.series[0].data.map(point => point.itemStyle.color), ['#112233', '#445566']);
  assert.equal(area.legend.top, 28, 'multi-series legends sit below the title');
  assert.ok(area.grid.top > area.legend.top, 'the chart plot starts below the legend');
  assert.equal(pie.series[0].radius, '55%');
  assert.deepEqual(pie.series[0].data.map(point => point.itemStyle.color), ['#112233', '#445566']);
  assert.deepEqual(doughnut.series[0].radius, ['38%', '65%']);
  assert.deepEqual(nestedPie.series.map(series => series.type), ['pie', 'pie']);
  assert.deepEqual(nestedPie.series.map(series => series.data.length), [1, 2]);
  assert.equal(buildChartOption({ ...common, type: 'retired-chart' }).series[0].type, 'bar');
});

test('newer saved field colors override graph colors without changing older graph snapshots', () => {
  const graphColor = '#112233';
  const sharedColor = '#AABBCC';
  assert.equal(resolveFieldColors(['Highlights'], {
    chartColors: [graphColor],
    fieldColors: { highlights: sharedColor },
    fieldColorUpdatedAt: { highlights: '2026-10-01T00:00:00Z' },
    chartUpdatedAt: '2026-10-02T00:00:00Z'
  })[0], graphColor);
  assert.equal(resolveFieldColors(['Highlights'], {
    chartColors: [graphColor],
    fieldColors: { highlights: sharedColor },
    fieldColorUpdatedAt: { highlights: '2026-10-03T00:00:00Z' },
    chartUpdatedAt: '2026-10-02T00:00:00Z'
  })[0], sharedColor);
  assert.equal(resolveFieldColors(['Highlights'], {
    chartColors: [graphColor],
    fieldColors: { highlights: sharedColor },
    fieldColorUpdatedAt: { highlights: '2026-10-03T00:00:00Z' },
    chartUpdatedAt: '2026-10-02T00:00:00Z',
    chartColorsOverrideShared: true
  })[0], graphColor);
});

test('nested-pie shared colors override explicit graph colors only when newly saved', () => {
  const originalWindow = global.window;
  const originalDocument = global.document;
  const originalFieldColors = global.IRISFieldColors;
  const originalFieldColorUpdatedAt = global.IRISFieldColorUpdatedAt;
  let option;
  global.window = { echarts: { init: () => ({ setOption: value => { option = value; } }) } };
  global.document = { documentElement: { classList: { contains: () => false } } };
  global.IRISFieldColors = { 'group a': '#AABBCC', item: '#DDEEFF' };
  global.IRISFieldColorUpdatedAt = { 'group a': '2026-10-03T00:00:00Z', item: '2026-10-03T00:00:00Z' };

  try {
    createChart({}, 'nestedPie', {
      updated_at: '2026-10-02T00:00:00Z',
      chart_type: 'nestedPie',
      irisConfig: {
        type: 'nestedPie',
        nestedGroups: [{ label: 'Group A', value: 10, children: [{ label: 'Item', value: 10 }] }],
        groupColors: ['#112233'],
        sliceColors: { 'Group A::Item': '#445566' }
      }
    });
    assert.equal(option.series[0].data[0].itemStyle.color, '#AABBCC');
    assert.equal(option.series[1].data[0].itemStyle.color, '#DDEEFF');

    global.IRISFieldColorUpdatedAt = { 'group a': '2026-10-01T00:00:00Z', item: '2026-10-01T00:00:00Z' };
    createChart({}, 'nestedPie', {
      updated_at: '2026-10-02T00:00:00Z',
      chart_type: 'nestedPie',
      irisConfig: {
        type: 'nestedPie',
        nestedGroups: [{ label: 'Group A', value: 10, children: [{ label: 'Item', value: 10 }] }],
        groupColors: ['#112233'],
        sliceColors: { 'Group A::Item': '#445566' }
      }
    });
    assert.equal(option.series[0].data[0].itemStyle.color, '#112233');
    assert.equal(option.series[1].data[0].itemStyle.color, '#445566');
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
    if (originalFieldColors === undefined) delete global.IRISFieldColors;
    else global.IRISFieldColors = originalFieldColors;
    if (originalFieldColorUpdatedAt === undefined) delete global.IRISFieldColorUpdatedAt;
    else global.IRISFieldColorUpdatedAt = originalFieldColorUpdatedAt;
  }
});

test('saved chart options use readable colors in dark mode', () => {
  let option;
  const originalWindow = global.window;
  const originalDocument = global.document;
  global.window = { echarts: { init: () => ({ setOption: value => { option = value; } }) } };
  global.document = { documentElement: { classList: { contains: className => className === 'dark' } } };

  try {
    createChart({}, 'bar', { labels: ['North'], datasets: [{ label: 'Score', data: [82] }] });
    assert.equal(option.legend.textStyle.color, '#F8FAFC');
    assert.equal(option.xAxis.axisLabel.color, '#F8FAFC');
    assert.equal(option.yAxis.axisLabel.color, '#F8FAFC');
    assert.equal(option.tooltip.textStyle.color, '#F8FAFC');
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('saved rank line charts invert the value axis and keep raw ranks', () => {
  let option;
  const originalWindow = global.window;
  const originalDocument = global.document;
  global.window = { echarts: { init: () => ({ setOption: value => { option = value; } }) } };
  global.document = { documentElement: { classList: { contains: () => true } } };

  try {
    const ranks = [50, 10, 5, 8, 1, 7];
    createChart({}, 'line', {
      rankSemantic: true,
      rankValueMax: 50,
      labels: ['2021', '2022', '2023', '2024', '2025', '2026'],
      datasets: [{ label: 'Rank', data: ranks }]
    });
    assert.equal(option.yAxis.inverse, true);
    assert.equal(option.yAxis.min, 0);
    assert.equal(option.yAxis.max, 53);
    assert.deepEqual(option.series[0].data.map(point => point.value), ranks);
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('saved rank metadata and reversed order match the Studio chart rendering', () => {
  const option = buildSavedGraphOption({
    chart_type: 'line',
    rank_semantic: 'rank',
    labels: ['First', 'Second', 'Third'],
    values_data: [132, 92, 48],
    chart_data: {
      irisConfig: {
        type: 'line',
        rankSemantic: true,
        reverseOrder: true,
        labels: ['First', 'Second', 'Third'],
        rawValues: [132, 92, 48],
        series: [{ name: 'Regional Rank', data: [132, 92, 48] }]
      }
    }
  });

  assert.equal(option.yAxis.inverse, true);
  assert.deepEqual(option.xAxis.data, ['Third', 'Second', 'First']);
  assert.deepEqual(option.series[0].data.map(point => point.value), [48, 92, 132]);
});

test('published pie labels are white with no text stroke in dark mode', () => {
  const originalDocument = global.document;
  global.document = { documentElement: { classList: { contains: className => className === 'dark' } } };

  try {
    const option = buildSavedGraphOption({
      chart_type: 'pie',
      labels: ['SDG 1', 'SDG 2'],
      values_data: [60, 40]
    });
    const label = option.series[0].label;
    assert.equal(option.legend.textStyle.color, '#FFFFFF');
    assert.equal(label.color, '#FFFFFF');
    assert.equal(label.textBorderWidth, 0);
    assert.equal(label.textBorderColor, 'transparent');
    assert.equal(label.formatter({ name: 'SDG 1', percent: 60, data: { rawValue: 60 } }), 'SDG 1: 60.00 (60%)');
  } finally {
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('chart colors validate strictly and fall back to the CLSU palette', () => {
  assert.equal(isValidChartColor('#FDB900'), true);
  assert.equal(isValidChartColor('#12ab'), false);
  assert.equal(isValidChartColor('red'), false);
  assert.deepEqual(getChartColors(['#FDB900', 'invalid'], 2), ['#FDB900', '#E0A70D']);
});

test('field colors normalize labels and resolve chart override before shared field color', () => {
  assert.equal(normalizeFieldKey('  Total   Students  '), 'total students');
  assert.deepEqual(resolveFieldColors(['Total Students', 'Faculty'], {
    chartColors: ['#123456'],
    fieldColors: { 'total students': '#FDB900', faculty: '#1E7A3C' },
    legacyColors: ['#6B7280', '#335C81']
  }), ['#123456', '#1E7A3C']);
  assert.deepEqual(resolveFieldColors(['Faculty'], {
    fieldColors: {},
    legacyColors: ['#335C81']
  }), ['#335C81']);
});

test('recoloring the Studio sample preserves every bar value, name, and tooltip value', () => {
  const labels = ['Total Students', 'International students', 'Total faculty staff'];
  const values = [14661, 12, 583];
  const defaultPoints = buildColoredSeriesData(values.map((value, index) => ({ name: labels[index], value })), labels, null);
  const coloredPoints = buildColoredSeriesData(defaultPoints, labels, ['#FDB900', '#1E7A3C', '#335C81']);
  assert.deepEqual(coloredPoints.map(point => [point.name, point.value]), labels.map((label, index) => [label, values[index]]));
  assert.deepEqual(coloredPoints.map(point => point.itemStyle.color), ['#FDB900', '#1E7A3C', '#335C81']);
  assert.match(studioColorCustomizerSource, /buildColoredSeriesData\(data, fields, currentColors\)/);
  assert.match(studioColorCustomizerSource, /setOption\(\{ color: \[\.\.\.currentColors\], series \}, \{ notMerge: false \}\)/);
});

test('initial bar chart options keep the three sample values through custom colors in both themes', () => {
  const labels = ['Total Students', 'International students', 'Total faculty staff'];
  const values = [14661, 12, 583];
  const colors = ['#FDB900', '#1E7A3C', '#335C81'];
  const originalWindow = global.window;
  const originalDocument = global.document;

  try {
    for (const dark of [false, true]) {
      let option;
      global.window = { IRISFieldColors: {}, IRISChartConfig: {}, echarts: { init: () => ({ setOption: value => { option = value; } }) } };
      global.document = { documentElement: { classList: { contains: name => name === 'dark' && dark } } };
      createChart({}, 'bar', { labels, datasets: [{ label: 'Enrollment', data: values }] }, { colors });
      assert.deepEqual(option.series[0].data.map(point => [point.name, point.value]), labels.map((label, index) => [label, values[index]]));
      assert.deepEqual(option.series[0].data.map(point => point.itemStyle.color), colors);
    }
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('Studio tooltip retains the original numeric value after colorization in both themes', () => {
  const names = ['Total Students', 'International students', 'Total faculty staff'];
  const rows = [['Total Students', '14,661'], ['International students', '12'], ['Total faculty staff', '583']];
  const previous = { window: global.window, document: global.document, chartMapping: global.ChartMapping };
  const captured = [];
  global.window = {
    IRISFieldColors: {},
    ChartMapping: {
      inferColumns: () => ({ labelColumn: 0, valueColumn: 1 }),
      isRankField: () => false,
      parseNumericValue: value => Number(String(value).replace(/,/g, ''))
    },
    ChartData: { groupAndAggregate: chartRows => chartRows },
    echarts: { init: () => ({ setOption: option => captured.push(option) }) }
  };
  global.document = {
    documentElement: { classList: { contains: name => name === 'dark' && global.__testDarkMode } },
    getElementById: () => null
  };
  const record = { id: 'sample', fileName: 'Sample', extractedData: {} };
  const state = { studioChartColors: ['#FDB900', '#1E7A3C', '#335C81'], studioChartOverrides: ['#FDB900', '#1E7A3C', '#335C81'] };
  const ctx = {
    state,
    api: {
      getStudioActiveSheet: () => ({ name: 'Sample', data: { headers: ['Field', 'Value'], rows } }),
      renderStudioColorCustomizer: () => {}
    }
  };
  const elements = {
    canvas: { style: {} }, typeSelect: { value: 'bar' }, titleInput: { value: 'Sample', getAttribute: () => 'customized' },
    subtitle: { textContent: '' }, warning: { style: {}, textContent: '' }, emptyState: { style: {} }, emptyMsg: { textContent: '' },
    categorySelect: { value: '0' }, valueSelect: { value: '1' }, valuePrecision: { value: '0' }, yearSelect: null,
    reverseOrder: null, filterField: { value: 'all' }, filterOperator: { value: 'all' }, filterValue: { value: '' },
    filterUpperValue: { value: '' }, sortOrder: { value: 'source' }, rowLimit: { value: '30' }, groupDuplicates: { checked: false }
  };

  try {
      for (const dark of [false, true]) {
        for (const groupDuplicates of [false, true]) {
          global.__testDarkMode = dark;
          elements.groupDuplicates.checked = groupDuplicates;
      renderStudioChart(ctx, record, { elements });
      const option = captured.at(-1);
      const point = option.series[0].data[0];
      assert.equal(point.name, names[0]);
      assert.equal(point.value, 14661);
      assert.equal(point.itemStyle.color, '#FDB900');
      assert.match(option.tooltip.formatter([{ ...point, dataIndex: 0 }]), /14,661/);
      assert.deepEqual(option.series[0].data.map(item => item.value), [14661, 12, 583]);
      }
    }
  } finally {
    if (previous.window === undefined) delete global.window; else global.window = previous.window;
    if (previous.document === undefined) delete global.document; else global.document = previous.document;
    if (previous.chartMapping === undefined) delete global.ChartMapping; else global.ChartMapping = previous.chartMapping;
    delete global.__testDarkMode;
  }
});

test('Studio color picker is local and exposes synced HEX, RGB, preset, and reset controls', () => {
  assert.match(studioColorCustomizerSource, /\.\.\/\.\.\/vendor\/vanilla-colorful\/hex-color-picker\.js/);
  assert.match(reviewEditorSource, /<hex-color-picker id="studioColorPicker"/);
  assert.match(reviewEditorSource, /id="studioColorHex"/);
  assert.match(reviewEditorSource, /id="studioColorR"/);
  assert.match(reviewEditorSource, /id="studioColorG"/);
  assert.match(reviewEditorSource, /id="studioColorB"/);
  assert.match(reviewEditorSource, /id="studioColorPresets"/);
  assert.match(reviewEditorSource, /id="studioColorReset"/);
  assert.match(studioWorkbenchSource, /colors: \$\('studioColorApplyAll'\)\?\.checked \? null : ctx\.state\.studioChartOverrides/);
  assert.doesNotMatch(studioWorkbenchSource, /persistStudioFieldColors/);
  assert.match(studioColorCustomizerSource, /ctx\.dbManager\.saveFieldColor\(fieldKey, label, color\)/);
  assert.match(studioColorCustomizerSource, /chartColorsOverrideShared: Array\.isArray\(overrides\)/);
});

test('Studio year filtering, stacked series grouping, duplicate aggregation, and reverse order use live rows', () => {
  const originalWindow = global.window;
  const originalDocument = global.document;
  const options = [];
  let initCount = 0;
  const yearSelect = {
    _value: '',
    _html: '',
    get options() { return [...this._html.matchAll(/<option value="([^"]+)"/g)]; },
    get value() { return this._value; },
    set value(value) { this._value = String(value); },
    set innerHTML(html) { this._html = html; }
  };
  const rows = [
    [2022, 'A', 'North', 100],
    [2023, 'A', 'North', 10],
    [2023, 'A', 'South', 5],
    [2023, 'A', 'North', 20],
    [2023, 'B', 'North', 20],
    [2023, 'B', 'South', 30]
  ];
  global.window = {
    IRISFieldColors: {},
    ChartMapping: {
      inferColumns: () => ({ labelColumn: 1, valueColumn: 3, numericColumns: [3] }),
      isRankField: () => false,
      parseNumericValue: value => Number(value),
      parseYearValue: value => Number(value),
      detectYearColumn: () => 0,
      getYearOptions: sourceRows => ({ availableYears: [...new Set(sourceRows.map(row => Number(row[0])))].sort((a, b) => b - a) })
    },
    ChartData: { groupAndAggregate: sourceRows => sourceRows },
    echarts: {
      init: () => {
        initCount += 1;
        return { setOption: (option, replace) => options.push({ option, replace }) };
      }
    }
  };
  global.document = {
    documentElement: { classList: { contains: () => false } },
    getElementById: () => null
  };
  const record = { id: 'series-test' };
  const state = { studioChartOverrides: null };
  const ctx = {
    state,
    api: {
      getStudioActiveSheet: () => ({ name: 'Sheet', data: { headers: ['Year', 'Category', 'Series', 'Value'], rows } }),
      renderStudioColorCustomizer: () => {}
    }
  };
  const elements = {
    canvas: { style: {}, clientWidth: 800 },
    typeSelect: { value: 'stackedArea' },
    titleInput: { value: 'Value — Sheet', getAttribute: () => 'customized' },
    subtitle: { textContent: '' },
    warning: { style: {}, textContent: '' },
    emptyState: { style: {} },
    emptyMsg: { textContent: '' },
    categorySelect: { value: '1' },
    valueSelect: { value: '3' },
    seriesFieldSelect: { value: '2' },
    valuePrecision: { value: '0' },
    yearSelect,
    reverseOrder: { checked: true },
    filterField: { value: 'all' },
    filterOperator: { value: 'all' },
    filterValue: { value: '' },
    filterUpperValue: { value: '' },
    sortOrder: { value: 'source' },
    rowLimit: { value: '30' },
    groupDuplicates: { checked: true }
  };

  try {
    renderStudioChart(ctx, record, { elements });
    yearSelect.value = '2023';
    renderStudioChart(ctx, record, { elements });
    const { option, replace } = options.at(-1);
    assert.equal(replace, true);
    assert.deepEqual(option.xAxis.data, ['B', 'A']);
    assert.deepEqual(option.series.map(series => series.name), ['North', 'South']);
    assert.deepEqual(option.series.map(series => series.data.map(point => point.value)), [[20, 15], [30, 5]]);
    assert.equal(state.studioChartConfig.selectedYear, '2023');
    elements.typeSelect.value = 'bar';
    renderStudioChart(ctx, record, { elements });
    assert.equal(initCount, 1);
    assert.equal(options.at(-1).replace, true);
    assert.equal(options.at(-1).option.series[0].type, 'bar');
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('saved pie and doughnut charts restore stored per-slice colors', () => {
  const originalDocument = global.document;
  const originalWindow = global.window;
  const charts = [];
  global.document = { documentElement: { classList: { contains: () => false } } };
  global.window = { IRISChartConfig: {}, echarts: { init: () => ({ setOption: option => charts.push(option) }) } };

  try {
    createChart({}, 'pie', { labels: ['North', 'South'], datasets: [{ data: [60, 40] }] }, { colors: ['#FDB900', '#1E7A3C'] });
    assert.deepEqual(charts[0].color, ['#FDB900', '#1E7A3C']);
    assert.deepEqual(charts[0].series[0].data.map(point => point.itemStyle.color), ['#FDB900', '#1E7A3C']);
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('public Observatory graph options use saved colors with safe defaults', () => {
  const option = buildSavedChartOption({ chart_type: 'pie', labels: ['North', 'South'], values_data: [6, 4], colors: ['#FDB900', 'bad'] });
  assert.equal(option.series[0].data[0].itemStyle.color, '#FDB900');
  assert.match(option.series[0].data[1].itemStyle.color, /^#[0-9A-F]{6}$/);
  assert.match(publicGraphApiSource, /sg\.colors/);
});

test('saved pie options use shared field colors when no chart override exists', () => {
  const originalFieldColors = global.IRISFieldColors;
  global.IRISFieldColors = { 'total students': '#FDB900' };
  try {
    const option = buildSavedChartOption({ chart_type: 'pie', labels: ['Total Students'], values_data: [100] });
    assert.equal(option.series[0].data[0].itemStyle.color, '#FDB900');
  } finally {
    if (originalFieldColors === undefined) delete global.IRISFieldColors;
    else global.IRISFieldColors = originalFieldColors;
  }
});

test('graph API validates and persists custom colors', () => {
  assert.match(graphApiSource, /function valid_graph_colors/);
  assert.ok(graphApiSource.includes("preg_match('/^#[0-9A-Fa-f]{6}$/', $color)"));
  assert.match(graphApiSource, /function save_graph_relations/);
  assert.match(graphApiSource, /INSERT INTO graph_colors \(graph_id, series_id, color\)/);
  assert.match(graphApiSource, /normalize_field_key/);
  assert.match(graphApiSource, /INSERT INTO field_colors \(field_name, color\) VALUES \(\?, \?\) ON DUPLICATE KEY UPDATE color = VALUES\(color\)/);
});

test('public Observatory receives the shared field-color map', () => {
  assert.match(publicGraphApiSource, /SELECT field_name AS field_key, color, updated_at FROM field_colors/);
  assert.match(publicGraphApiSource, /'field_colors' => \$fieldColors/);
  assert.match(publicDashboardSource, /window\.IRISFieldColors =/);
  assert.match(publicDashboardSource, /window\.IRISFieldColorUpdatedAt =/);
  assert.match(chartEngineSource, /resolveFieldColors\(colorFields/);
  assert.match(chartEngineSource, /fieldColorUpdatedAt/);
});

test('admin pie previews show precision-formatted values and percentages without hovering', () => {
  let option;
  const originalWindow = global.window;
  const originalDocument = global.document;
  global.window = { echarts: { init: () => ({ setOption: value => { option = value; } }) } };
  global.document = { documentElement: { classList: { contains: () => false } } };

  try {
    createChart({}, 'pie', { labels: ['North'], datasets: [{ label: 'Share', data: [60] }] });
    assert.equal(option.series[0].label.formatter({ name: 'North', value: 60, percent: 100, data: { rawValue: 60 } }), 'North: 60.00 (100%)');
  } finally {
    if (originalWindow === undefined) delete global.window;
    else global.window = originalWindow;
    if (originalDocument === undefined) delete global.document;
    else global.document = originalDocument;
  }
});

test('public Observatory college doughnut labels include percentages', () => {
  assert.match(publicDashboardSource, /return `\$\{displayName\}: \$\{params\.percent\}%`;/);
});

test('deduplicates circular chart legend labels while grouping remains optional', () => {
  const rows = [{ label: 'North', value: 2 }, { label: 'North', value: 3 }, { label: 'South', value: 4 }];
  const ungrouped = prepareCircularData(rows, false);
  const grouped = prepareCircularData(rows, true);
  assert.deepEqual(ungrouped.legendLabels, ['North', 'South']);
  assert.equal(ungrouped.rows.length, 3);
  assert.deepEqual(grouped.rows, [{ label: 'North', value: 5, rawValue: 5 }, { label: 'South', value: 4, rawValue: 4 }]);
});

test('serializes the current edited chart series after an entity is removed', () => {
  const original = { series: [{ data: [{ name: 'North', value: 2 }, { name: 'South', value: 4 }, { name: 'West', value: 6 }] }] };
  const edited = { series: [{ data: original.series[0].data.slice(1) }] };
  const exported = serializeChartState(edited, { labels: ['North', 'South', 'West'] });
  assert.equal(exported.labels.length, original.series[0].data.length - 1);
  assert.deepEqual(exported.labels, ['South', 'West']);
  assert.deepEqual(exported.values, [4, 6]);
});

test('pairs a selected label with its adjacent extracted value', () => {
  assert.equal(pairSelectedText('2022', '2022\n601-800'), '2022 601-800');
  assert.equal(pairSelectedText('Enrollment', 'Enrollment: 1,250'), 'Enrollment 1,250');
});

test('normalizes saved and draft graphs into a single export payload', () => {
  const draft = {
    title: 'Enrollment trend',
    primaryType: 'line',
    chartData: {
      labels: ['Q1', 'Q2'],
      datasets: [{ data: [120, 150] }]
    }
  };

  const payload = normalizeGraphExportItem(draft, 'rec_123');

  assert.equal(payload.record_id, 'rec_123');
  assert.equal(payload.chart_type, 'line');
  assert.deepEqual(payload.labels, ['Q1', 'Q2']);
  assert.deepEqual(payload.values_data, [120, 150]);
});

test('unsupported saved ECharts types fall back to Bar while preserving data', () => {
  const payload = normalizeGraphExportItem({
    title: 'Distribution',
    chartData: {
      type: 'polarArea',
      labels: ['North', 'South'],
      series: [{ data: [{ name: 'North', value: 65 }, { name: 'South', value: 35 }] }]
    }
  }, 'rec_456');

  assert.equal(payload.chart_type, 'bar');
  assert.deepEqual(payload.labels, ['North', 'South']);
  assert.deepEqual(payload.values_data, [65, 35]);
});

test('circled information controls can be pinned and dismissed by click', () => {
  assert.match(publicDashboardSource, /trigger\.addEventListener\('mouseenter', \(\) => \{ if \(!pinnedSummaryInfoControl\) open\(\); \}\)/);
  assert.match(publicDashboardSource, /if \(pinnedSummaryInfoControl === wrapper\)[\s\S]*?pinnedSummaryInfoControl = wrapper;\s+open\(\);/);
  assert.match(publicDashboardSource, /event\.key !== 'Escape'/);
  assert.match(rankingHistoryControllerSource, /trigger\.addEventListener\('mouseenter', \(\) => \{ if \(!pinnedRankingInfoControl\) open\(\); \}\)/);
  assert.match(rankingHistoryControllerSource, /if \(pinnedRankingInfoControl === control\)[\s\S]*?pinnedRankingInfoControl = control;\s+open\(\);/);
  assert.match(rankingHistoryControllerSource, /event\.key !== 'Escape'/);
});

test('removed chart types fall back to Bar for saved graph rendering', () => {
  const option = buildSavedGraphOption({
    title: 'SDG Rank',
    chart_type: 'unsupported-chart',
    labels: ['SDG 1', 'SDG 2'],
    values_data: [12, 45],
    chart_data: {
      series: [{ data: [{ name: 'SDG 1', value: 12, rawValue: 12 }, { name: 'SDG 2', value: 45, rawValue: 45 }] }]
    }
  });

  assert.equal(option.xAxis.type, 'category');
  assert.deepEqual(option.xAxis.data, ['SDG 1', 'SDG 2']);
  assert.deepEqual(option.series[0].data.map(point => point.value), [12, 45]);
});

test('saved stacked-area series use their configured field colors', () => {
  const previousColors = globalThis.IRISFieldColors;
  globalThis.IRISFieldColors = { north: '#112233', south: '#445566' };
  try {
    const option = buildSavedGraphOption({
      chart_type: 'stackedArea',
      labels: ['2024'],
      values_data: [5],
      chart_data: {
        irisConfig: {
          type: 'stackedArea',
          series: [
            { name: 'North', data: [2] },
            { name: 'South', data: [3] }
          ]
        }
      }
    });

    assert.deepEqual(option.series.map(series => series.itemStyle.color), ['#112233', '#445566']);
  } finally {
    if (previousColors === undefined) delete globalThis.IRISFieldColors;
    else globalThis.IRISFieldColors = previousColors;
  }
});

test('builds a printable graph sheet with row data and branding', () => {
  const html = buildPrintableGraphSheet({
    title: 'Quality score',
    chart_type: 'bar',
    labels: ['North', 'South'],
    values_data: [82, 90]
  }, { recordName: 'CLSU Scorecard' });

  assert.match(html, /CLSU Scorecard/);
  assert.match(html, /Quality score/);
  assert.match(html, /North/);
  assert.match(html, /82/);
  assert.match(html, /window\.print/);
  assert.match(html, /class="chart-preview"/);
  assert.match(html, /aria-label="Bar chart"/);
});

test('print graph sheets can embed the shared renderer output', () => {
  const chartImage = 'data:image/svg+xml;charset=UTF-8,%3Csvg%3E%3C%2Fsvg%3E';
  const html = buildPrintableGraphSheet({
    title: 'Rank trend',
    chart_type: 'line',
    labels: ['2025', '2026'],
    values_data: [12, 8]
  }, { chartImage });

  assert.match(html, /<img class="chart-preview" src="data:image\/svg\+xml;charset=UTF-8,/);
  assert.doesNotMatch(html, /<polyline/);
});

test('text export contains SQL statements and graph metadata comments', () => {
  assert.match(savedGraphsSource, /export function buildTextExport/);
  assert.match(savedGraphsSource, /CREATE TABLE IF NOT EXISTS/);
  assert.match(savedGraphsSource, /INSERT INTO/);
  assert.match(savedGraphsSource, /-- Title:/);
  assert.match(savedGraphsSource, /-- Source:/);
  assert.match(savedGraphsSource, /-- Chart Type:/);
});

test('SQL file export uses the shared SQL content and SQL download type', () => {
  assert.match(savedGraphsSource, /data-mode="sql" class="export-choice-button">Export as SQL File \(\.sql\)/);
  assert.match(savedGraphsSource, /text: buildTextExport\(graphs\), mimeType: 'application\/sql'/);
  assert.match(savedGraphsSource, /\.sql`/);
});

test('saved graph publish controls work for individual and bulk actions', () => {
  const dbManagerSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'database', 'dbManager.js'), 'utf8');
  assert.match(savedGraphsSource, /graph-action-button graph-action-publish/);
  assert.match(savedGraphsSource, /<span>Publish<\/span>/);
  assert.match(savedGraphsSource, /savedGraphsPublishSelected/);
  assert.match(savedGraphsSource, /publishSelectedGraphs\(selectedGraphs\)/);
  assert.match(savedGraphsSource, /ctx\.dbManager\.publishGraph\(graph\.id, true\)/);
  assert.match(dbManagerSource, /action=\$\{published \? 'publish' : 'unpublish'\}/);
  assert.doesNotMatch(savedGraphsSource, /approveRecords\(recordIds\)/);
  assert.match(savedGraphsSource, /Publish/);
});

test('public scanner graphs are gated by explicit graph publication only', () => {
  const dashboardApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'dashboard_graphs.php'), 'utf8');
  const graphApi = readIrisApiSource();
  const publicDashboard = publicDashboardSource;
  assert.match(dashboardApi, /WHERE sg\.is_published = 1/);
  assert.match(dashboardApi, /Cache-Control: no-store/);
  assert.match(publicDashboard, /cache: 'no-store'/);
  assert.doesNotMatch(dashboardApi, /r\.status\s*=\s*'Approved'/);
  assert.doesNotMatch(graphApi, /COALESCE\(saved_graphs\.is_published, CASE WHEN records\.status/);
});

test('Studio Publish approves the record and publishes only its active chart', () => {
  const studioSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioWorkbench.js'), 'utf8');
  assert.match(studioSource, /status: approve \? 'Approved'/);
  assert.match(studioSource, /savedChart\.is_published = approve === true/);
  assert.match(studioSource, /saveGraph\(savedChart\)/);
});

test('archive unpublish resets a record and unpublishes its associated charts', () => {
  const apiSource = readIrisApiSource();
  const adminPortalSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'adminPortal.js'), 'utf8');
  const dbManagerSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'database', 'dbManager.js'), 'utf8');
  const appSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'app.js'), 'utf8');
  const stylesSource = stylesheetBundle;
  assert.match(apiSource, /\$action === 'unpublish'[\s\S]*?UPDATE records SET status='Pending Review'[\s\S]*?UPDATE saved_graphs SET is_published=0 WHERE record_id=\?/);
  assert.match(adminPortalSource, /btn-table-unpublish/);
  assert.match(adminPortalSource, /class="admin-record-actions"/);
  assert.match(stylesSource, /\.admin-record-actions\s*\{[\s\S]*?grid-template-columns:\s*6rem 7\.5rem 5\.8rem/);
  assert.match(adminPortalSource, /ctx\.dbManager\.unpublishRecord\(id\)/);
  assert.match(adminPortalSource, /requestAnimationFrame\(\(\) => workbench\.scrollIntoView/);
  assert.match(dbManagerSource, /async unpublishRecord\(id\)/);
  assert.match(appSource, /modules\/adminPortal\.js\?v=archive-bulk-actions/);
  assert.match(adminPortalSource, /adminBulkPublish/);
  assert.match(adminPortalSource, /adminBulkUnpublish/);
  assert.match(dbManagerSource, /async setRecordsPublication\(ids, published\)/);
  assert.match(apiSource, /bulk-publish', 'bulk-unpublish/);
});

test('saved graph publish helpers are exposed globally for all files', () => {
  assert.match(savedGraphsSource, /window\.SavedGraphsTab|window\.GraphExport/);
  assert.match(savedGraphsSource, /publishSelectedGraphs\s*[:=]/);
  assert.match(savedGraphsSource, /publishSavedGraphs|publishSelectedGraphs/);
});

test('public dashboard includes a manual summary card snapshot section and admin card controls', () => {
  const dashboardSource = publicDashboardSource;
  const adminSource = reviewEditorSource;
  assert.match(dashboardSource, /Latest Performance Snapshot/i);
  assert.match(dashboardSource, /summary-card|snapshot-cards/i);
  assert.match(adminSource, /summary card|summary-card|summaryCards/i);
  assert.match(dashboardSource, /No decimals/);
  assert.match(dashboardSource, /1 decimal/);
  assert.match(dashboardSource, /2 decimals/);
  assert.doesNotMatch(dashboardSource, /0 decimals/);
  assert.match(adminSource, /No decimals/);
  assert.match(adminSource, /1 decimal/);
  assert.match(adminSource, /2 decimals/);
  assert.doesNotMatch(adminSource, /0 decimals/);
});

test('public star ratings are enclosed in a matching standalone section container', () => {
  assert.match(publicDashboardSource, /<section id="star-rating-cards-section" class="scanner-public-scroll-container hidden space-y-4 rounded-2xl border border-gray-200 bg-white\/70 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800\/50 sm:p-5">/);
  assert.match(publicDashboardSource, /Published institutional star ratings/);
  assert.match(publicDashboardSource, /id="star-rating-cards-grid"/);
});

test('public dashboard sections use bounded internal scrolling with sticky section headers', () => {
  assert.match(publicDashboardSource, /<section id="summary-card-section" class="scanner-public-scroll-container/);
  assert.match(publicDashboardSource, /<section id="ranking-history" class="scanner-public-scroll-container/);
  assert.match(publicDashboardSource, /<section id="star-rating-cards-section" class="scanner-public-scroll-container/);
  assert.match(publicDashboardSource, /class="scanner-public-scroll-header">[\s\S]*?id="summaryCardCategoryFilter"/);
  assert.match(publicDashboardSource, /class="scanner-public-scroll-header">[\s\S]*?id="rankingFiltersControl"/);
  assert.match(publicDashboardSource, /class="scanner-public-scroll-header">[\s\S]*?id="star-rating-category-filter"/);
  assert.match(publicDashboardSource, /class="scanner-public-scroll-header">[\s\S]*?aria-label="Ranking History controls"[\s\S]*?id="rankingChart"/);
  assert.match(publicDashboardSource, /\.scanner-published-scope,\s*\.scanner-public-scroll-container\s*\{[\s\S]*?max-height:\s*min\(75vh,\s*620px\)[\s\S]*?overflow-y:\s*auto[\s\S]*?overscroll-behavior:\s*contain/);
  assert.match(publicDashboardSource, /\.scanner-public-scroll-header\s*\{[\s\S]*?position:\s*sticky[\s\S]*?top:\s*-1rem[\s\S]*?margin:\s*-1rem -1rem 0[\s\S]*?padding:\s*1rem 1\.5rem \.65rem[\s\S]*?border-bottom:\s*1px solid #B8CCBD[\s\S]*?background-color:\s*#E8F2EA/);
  assert.match(publicDashboardSource, /html\.dark \.scanner-public-scroll-header\s*\{[\s\S]*?border-bottom-color:\s*#475569[\s\S]*?background-color:\s*#1E293B/);
  assert.match(publicDashboardSource, /\.scanner-public-scroll-container::-webkit-scrollbar-track\s*\{\s*background-color:\s*#E8F2EA/);
  assert.match(publicDashboardSource, /\.scanner-public-scroll-container\s*\{\s*scrollbar-color:\s*var\(--iris-green\) #E8F2EA/);
  assert.match(publicDashboardSource, /html\.dark \.scanner-public-scroll-container::-webkit-scrollbar-track\s*\{\s*background-color:\s*#1E293B/);
  assert.match(publicDashboardSource, /html\.dark \.scanner-public-scroll-container\s*\{\s*scrollbar-color:\s*var\(--iris-green\) #1E293B/);
});

test('public dashboard cards share the CLSU gradient hover treatment', () => {
  assert.match(publicDashboardSource, /\.iris-hover-card::after[\s\S]*?var\(--iris-green\)[\s\S]*?var\(--iris-gold\)/);
  assert.match(publicDashboardSource, /\.iris-hover-card:hover[\s\S]*?translateY\(-3px\)/);
  assert.match(publicDashboardSource, /class="iris-hover-card rounded-xl border border-gray-200 bg-white p-5/);
  assert.match(publicDashboardSource, /class="iris-hover-card summary-card-shell/);
  assert.match(publicDashboardSource, /className = 'iris-hover-card scanner-published-card/);
  assert.match(rankingHistoryControllerSource, /className = 'iris-hover-card ranking-history-card/g);
});

test('Ranking History can switch to a year-by-year matrix with Philippine ranks', () => {
  const rankingsApiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'rankings.php'), 'utf8');
  assert.match(publicDashboardSource, /id="rankingDisplayMode"[\s\S]*?<option value="matrix">Ranking Trend Matrix<\/option>/);
  assert.match(rankingHistoryControllerSource, /selectedDisplayMode\(\) === 'matrix'/);
  assert.match(rankingHistoryControllerSource, /function renderTrendMatrix\(\)/);
  assert.match(rankingHistoryControllerSource, /Ranking Name/);
  assert.match(rankingHistoryControllerSource, /Philippines: \$\{escapeHtml\(phRank\)\}/);
  assert.match(rankingsApiSource, /\{\$phRankDisplayColumn\} AS ph_rank/);
});

test('Ranking History year selectors are tucked into an expandable range control', () => {
  assert.match(publicDashboardSource, /<details id="rankingYearRangeControl" class="group min-w-0 sm:col-span-2 lg:col-span-3">[\s\S]*?<summary[^>]*>[\s\S]*?Year range[\s\S]*?<\/summary>[\s\S]*?<div class="mt-2 grid grid-cols-1 gap-3[\s\S]*?id="rankingYearFrom"[\s\S]*?id="rankingYearTo"[\s\S]*?<\/details>/);
  const yearRangeMarkup = publicDashboardSource.match(/<details id="rankingYearRangeControl"[\s\S]*?<\/details>/)?.[0] || '';
  assert.ok(yearRangeMarkup);
  assert.match(publicDashboardSource, /<details id="rankingFiltersControl" class="group">[\s\S]*?<summary[^>]*>[\s\S]*?Filters[\s\S]*?<\/summary>[\s\S]*?<div id="rankingFiltersPopover" class="fixed z-\[100\] grid max-h-\[min\(75vh,560px\)\] w-\[min\(92vw,760px\)\][\s\S]*?id="rankingOrganizationFilter"[\s\S]*?id="rankingListFilter"[\s\S]*?id="rankingAllYears"[\s\S]*?id="rankingYearRangeControl"/);
  assert.match(rankingHistoryControllerSource, /function positionFiltersPopover\(\)[\s\S]*?window\.innerWidth[\s\S]*?window\.innerHeight/);
  assert.match(rankingHistoryControllerSource, /filtersControl\?\.addEventListener\('toggle'/);
  assert.match(rankingHistoryControllerSource, /document\.addEventListener\('click', event => \{\s*if \(filtersControl\?\.open && !filtersControl\.contains\(event\.target\)\) filtersControl\.open = false/);
  assert.match(rankingHistoryControllerSource, /event\.key === 'Escape' && filtersControl\?\.open\) filtersControl\.open = false/);
  assert.match(rankingHistoryControllerSource, /yearRangeControl\.open = false/);
});

test('Ranking History All years button keeps a compact natural width inside the filter grid', () => {
  const filterPopover = publicDashboardSource.match(/<div id="rankingFiltersPopover"[\s\S]*?<\/div>\s*<\/details>/)?.[0] || '';
  assert.match(filterPopover, /id="rankingAllYears"[^>]*class="w-auto justify-self-start self-center/);
  assert.doesNotMatch(filterPopover, /id="rankingAllYears"[^>]*class="[^"]*\bw-full\b/);
  assert.match(rankingHistoryControllerSource, /allYearsButton\.className = `w-auto justify-self-start self-center rounded-lg border px-2\.5 py-1\.5 text-xs/);
});

test('Ranking History reset restores the initial highlighted display and all-years state', () => {
  assert.match(publicDashboardSource, /id="rankingResetDefaults"[^>]*>Reset to default<\/button>/);
  assert.match(rankingHistoryControllerSource, /defaultDisplayState = \{\s*mode: selectedDisplayMode\(\),\s*layout: selectedGraphLayout\(\),\s*organization: organizationSelect\.value \|\| 'all',\s*list: listSelect\.value \|\| 'all'/);
  assert.match(rankingHistoryControllerSource, /resetDefaultsButton\?\.addEventListener\('click', \(\) => \{[\s\S]*?defaultDisplayState\.mode[\s\S]*?defaultDisplayState\.organization[\s\S]*?defaultDisplayState\.list[\s\S]*?allYearsActive = true/);
});

test('Ranking History graph layout switches between side-by-side and one-per-row', () => {
  assert.match(publicDashboardSource, /id="rankingGraphLayout"[\s\S]*?<option value="one-per-row">One per row<\/option>[\s\S]*?<option value="side-by-side">Side by side<\/option>/);
  assert.match(rankingHistoryControllerSource, /function rankingChartGridClass\(\)[\s\S]*?xl:grid-cols-2[\s\S]*?grid-cols-1 gap-4/);
  assert.match(rankingHistoryControllerSource, /graphLayoutSelect\?\.addEventListener\('change', render\)/);
  assert.match(rankingHistoryControllerSource, /const selectedGraphLayout = \(\) => graphLayoutSelect\?\.value === 'side-by-side' \? 'side-by-side' : 'one-per-row'/);
  assert.match(rankingHistoryControllerSource, /layout: selectedGraphLayout\(\)/);
});

test('Ranking History chart titles do not repeat an organization name already in the ranking type', () => {
  assert.match(rankingHistoryControllerSource, /function rankingChartTitle\(organization, rankingType\) \{[\s\S]*?normalizedType === normalizedOrganization \|\| normalizedType\.startsWith\(`\$\{normalizedOrganization\} `\)/);
  assert.match(rankingHistoryControllerSource, /selectedOrganization\(\) === 'all' \? rankingChartTitle\(family\.organization, family\.type\) : family\.type/);
});

test('Ranking History chart cards avoid duplicating the ranking matrix and retain per-card matrix popouts', () => {
  assert.match(rankingHistoryControllerSource, /content\.className = 'grid min-w-0 grid-cols-1 gap-3'/);
  assert.doesNotMatch(rankingHistoryControllerSource, /function addRankingContextToggle|function rankingContextMarkup|Show ranking context|Latest global|Ranking context ·/);
  assert.doesNotMatch(rankingHistoryControllerSource, /rankingContextMarkup\(|addRankingContextToggle\(/);
  assert.match(rankingHistoryControllerSource, /function addRankingExplanation\(content, rows\)/);
  assert.match(rankingHistoryControllerSource, /const section = document\.createElement\('details'\)/);
  assert.match(rankingHistoryControllerSource, /const heading = document\.createElement\('summary'\)/);
  assert.match(rankingHistoryControllerSource, /heading\.textContent = 'What this ranking means'/);
  assert.match(rankingHistoryControllerSource, /function isRankingContextField\(key, field\)/);
  assert.match(rankingHistoryControllerSource, /String\(key \|\| ''\)\.toLowerCase\(\) === 'ranking_context'/);
  assert.match(rankingHistoryControllerSource, /description\.textContent = String\(rankingContext\.value\)\.trim\(\)/);
  assert.match(rankingHistoryControllerSource, /!isRankingContextField\(key, field\) && String\(field\?\.value \|\| ''\)\.trim\(\)/);
  assert.match(rankingHistoryControllerSource, /if \(isRankingContextField\(key, field\) \|\| !String\(field\?\.value \|\| ''\)\.trim\(\)\) continue/);
  assert.match(rankingHistoryControllerSource, /addRankingExplanation\(content, rowsForChart\)/);
  assert.match(rankingHistoryControllerSource, /addRankingExplanation\(content, selectedRows\)/);
  assert.match(rankingHistoryControllerSource, /addInformationControl\(wrapper, rowsForChart\)/);
  assert.match(rankingHistoryControllerSource, /addInformationControl\(wrapper, selectedRows\)/);
  assert.match(rankingHistoryControllerSource, /chartCount === 1/);
  assert.match(rankingHistoryControllerSource, /matrixButton\.textContent = 'Pop Ranking Trend Matrix'/);
  assert.match(rankingHistoryControllerSource, /openTrendMatrixDialog\(title, rowsForChart\)/);
  assert.match(rankingHistoryControllerSource, /chartCount > 1/);
  assert.match(rankingHistoryControllerSource, /dialog\.showModal\(\)/);
});

test('public removal hides charts without deleting the saved graph record', () => {
  const dashboardSource = publicDashboardSource;
  const apiSource = readIrisApiSource();
  assert.match(dashboardSource, /Unpublish|Hide this published chart from the Observatory/);
  assert.match(dashboardSource, /action=unpublish/);
  assert.match(apiSource, /action\s*===\s*'unpublish'|action\s*===\s*"unpublish"/);
});

test('published graph layout can switch between full-width and side-by-side', () => {
  assert.match(publicDashboardSource, /id="publishedGraphLayout"/);
  assert.match(publicDashboardSource, /<option value="side-by-side">Side by side<\/option>/);
  assert.match(publicDashboardSource, /<option value="one-per-row">One per row<\/option>/);
  assert.doesNotMatch(publicDashboardSource, /publishedGraphCount|Loading published graphs|\d+ published graphs/);
  assert.match(publicDashboardSource, /scopeGrid\.classList\.toggle\('lg:grid-cols-2', publishedGraphLayout === 'side-by-side'\)/);
  assert.match(publicDashboardSource, /chartInstances\.forEach\(chart => chart\?\.resize\?\.\(\)\)/);
  assert.match(publicDashboardSource, /\$\{publishedGraphLayout === 'side-by-side' \? 'lg:grid-cols-2' : ''\}/);
});

test('each published graph container has its own bounded vertical scroll area', () => {
  assert.match(publicDashboardSource, /\.scanner-published-scope,\s*\.scanner-public-scroll-container\s*\{[\s\S]*?max-height:\s*min\(75vh,\s*620px\)[\s\S]*?overflow-y:\s*auto[\s\S]*?overscroll-behavior:\s*contain/);
  assert.match(publicDashboardSource, /scopeCard\.className = 'scanner-published-scope/);
  assert.match(publicDashboardSource, /\.scanner-published-scope > header,\s*\.scanner-public-scroll-header\s*\{[\s\S]*?position:\s*sticky[\s\S]*?top:\s*-1rem[\s\S]*?margin:\s*-1rem -1rem 0[\s\S]*?padding:\s*1rem 1\.5rem \.65rem[\s\S]*?border-bottom:\s*1px solid #B8CCBD[\s\S]*?background-color:\s*#E8F2EA/);
  assert.match(publicDashboardSource, /html\.dark \.scanner-published-scope > header,[\s\S]*?html\.dark \.scanner-public-scroll-header\s*\{[\s\S]*?background-color:\s*#1E293B/);
  assert.match(publicDashboardSource, /@media \(min-width:\s*640px\)\s*\{[\s\S]*?\.scanner-public-scroll-header\s*\{[\s\S]*?top:\s*-1\.25rem[\s\S]*?margin:\s*-1\.25rem -1\.25rem 0/);
});

test('record approval does not auto-publish every saved graph for that record', () => {
  const apiSource = readIrisApiSource();
  const dbSource = fs.readFileSync(path.join(__dirname, '..', '..', 'config', 'db.php'), 'utf8');
  assert.doesNotMatch(apiSource, /UPDATE saved_graphs SET is_published = 1 WHERE record_id IN/);
  assert.doesNotMatch(apiSource, /UPDATE saved_graphs SET is_published = \? WHERE record_id = \?/);
  assert.doesNotMatch(dbSource, /UPDATE saved_graphs sg INNER JOIN records r ON r.id = sg.record_id SET sg.is_published/);
});

test('builds a printable pie chart preview before the data table', () => {
  const html = buildPrintableGraphSheet({
    title: 'Distribution',
    chart_type: 'pie',
    labels: ['North', 'South'],
    values_data: [60, 40]
  });

  assert.match(html, /aria-label="pie chart"/);
  assert.ok(html.indexOf('chart-preview') < html.indexOf('<table>'));
});

test('unsupported chart types render a Bar chart in printable sheets', () => {
  const html = buildPrintableGraphSheet({
    title: 'Regional spread',
    chart_type: 'polarArea',
    labels: ['North', 'South', 'West'],
    values_data: [18, 42, 30]
  });

  assert.match(html, /Chart Type: BAR/);
  assert.match(html, /aria-label="Bar chart"/);
  assert.doesNotMatch(html, /aria-label="pie chart"/);
});

test('studio chart previews use the same decimal precision control as summary cards', () => {
  const html = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
  const engine = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8');
  assert.match(html, /studioValuePrecisionSelect|Display Precision/i);
  assert.match(html, /No decimals|1 decimal|2 decimals/i);
  assert.match(engine, /formatChartValueForDisplay|displayPrecision/);
});

test('upload widgets use unique file input IDs so the browser chooses the correct file picker', () => {
  const scannerHtml = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
  const adminDashboardHtml = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'dashboard.php'), 'utf8');
  const adminHeaderHtml = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'header.php'), 'utf8');

  assert.equal((scannerHtml.match(/id="fileInput"/g) || []).length, 0);
  assert.equal((adminDashboardHtml.match(/id="fileInput"/g) || []).length, 0);
  assert.equal((adminHeaderHtml.match(/id="fileInput"/g) || []).length, 0);
  assert.match(scannerHtml, /id="scannerUploadFileInput"/);
  assert.match(adminDashboardHtml, /id="adminInlineFileInput"/);
  assert.match(adminHeaderHtml, /id="adminWidgetFileInput"/);
});

test('chart engine detects the year column once for the year filter', () => {
  const engineSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8');
  assert.equal((engineSource.match(/const yearColumn = /g) || []).length, 1);
});

test('axis controls are structural and no longer user-facing', () => {
  const app = fs.readFileSync(path.join(__dirname, '..', 'js', 'app.js'), 'utf8');
  const html = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
  assert.equal(app.includes('studioBtnSwapAxes'), false);
  assert.equal(html.includes('studioBtnSwapAxes'), false);
  assert.equal(app.includes('axis-toggle-btn'), false);
  assert.equal(html.includes('studioLabelColSelect'), false);
  assert.equal(html.includes('studioValueColSelect'), false);
  assert.match(app, /ChartMapping\.inferColumns/);
  assert.match(app, /xAxis: isCircular \? undefined : \{ type: 'category', name: 'Rows'/);
  assert.match(app, /yAxis: isCircular \? undefined : \{ type: 'value', name: headerName/);
});

test('public Ranking History uses Organization and List chart filters without the old table', () => {
  assert.match(publicDashboardSource, /id="rankingOrganizationFilter"/);
  assert.match(publicDashboardSource, /id="rankingListFilter"/);
  assert.doesNotMatch(publicDashboardSource, /id="rankingTable"|id="rankingTableStatus"|id="rankingLevelFilter"|id="rankingScopeFilter"|id="rankingTypeFilter"/);
  assert.match(rankingHistoryControllerSource, /inverse: true/);
  assert.match(rankingHistoryControllerSource, /Improved \(up\)/);
  assert.match(rankingHistoryControllerSource, /Declined \(down\)/);
  assert.match(rankingHistoryControllerSource, /addInformationControl\(wrapper, rowsForChart\)/);
});

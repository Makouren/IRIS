const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { prepareCircularData, serializeChartState } = require('../js/chartData');
const { pairSelectedText } = require('../js/sourceIngestion');
const { normalizeGraphExportItem, buildPrintableGraphSheet, buildSavedChartOption } = require('../js/graphExport');
const { createChart, renderStudioChart } = require('../js/modules/chartEngine');
const { buildColoredSeriesData, getChartColors, isValidChartColor, normalizeFieldKey, resolveFieldColors } = require('../js/chartColors');
const savedGraphsSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'savedGraphsTab.js'), 'utf8');
const studioColorCustomizerSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioColorCustomizer.js'), 'utf8');
const studioWorkbenchSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioWorkbench.js'), 'utf8');
const publicDashboardSource = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8');
const graphApiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'iris.php'), 'utf8');
const publicGraphApiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'dashboard_graphs.php'), 'utf8');
const reviewEditorSource = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'review_editor.php'), 'utf8');

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

test('published pie labels are white with no text stroke in dark mode', () => {
  const originalDocument = global.document;
  global.document = { documentElement: { classList: { contains: className => className === 'dark' } } };

  try {
    const option = buildSavedChartOption({
      chart_type: 'pie',
      labels: ['SDG 1', 'SDG 2'],
      values_data: [60, 40]
    });
    const label = option.series[0].label;
    assert.equal(option.legend.textStyle.color, '#FFFFFF');
    assert.equal(label.color, '#FFFFFF');
    assert.equal(label.textBorderWidth, 0);
    assert.equal(label.textBorderColor, 'transparent');
    assert.equal(label.formatter({ name: 'SDG 1', percent: 60 }), 'SDG 1: 60%');
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
    categorySelect: { value: '0' }, valueSelect: { value: '1' }, valuePrecision: { value: '0' }, rankedYearSelect: null,
    rankedReverseOrder: null, filterField: { value: 'all' }, filterOperator: { value: 'all' }, filterValue: { value: '' },
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
  assert.match(studioWorkbenchSource, /persistStudioFieldColors/);
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
  assert.equal(option.series[0].data[1].itemStyle.color, '#B7791F');
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
  assert.match(graphApiSource, /colors,chart_data|chart_data,colors,is_published/);
  assert.match(graphApiSource, /normalize_field_key/);
  assert.match(graphApiSource, /ON DUPLICATE KEY UPDATE label = VALUES\(label\), color = VALUES\(color\)/);
});

test('public Observatory receives the shared field-color map', () => {
  assert.match(publicGraphApiSource, /SELECT field_key, color FROM field_colors/);
  assert.match(publicGraphApiSource, /'field_colors' => \$fieldColors/);
  assert.match(publicDashboardSource, /window\.IRISFieldColors =/);
  assert.match(publicDashboardSource, /IRISChartColors\.resolveFieldColors/);
});

test('admin pie previews show slice percentages without hovering', () => {
  let option;
  const originalWindow = global.window;
  const originalDocument = global.document;
  global.window = { echarts: { init: () => ({ setOption: value => { option = value; } }) } };
  global.document = { documentElement: { classList: { contains: () => false } } };

  try {
    createChart({}, 'pie', { labels: ['North'], datasets: [{ label: 'Share', data: [60] }] });
    assert.equal(option.series[0].label.formatter, '{b}: {d}%');
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
  assert.deepEqual(grouped.rows, [{ label: 'North', value: 5 }, { label: 'South', value: 4 }]);
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

test('preserves the exact saved ECharts chart type when the type is nested inside chartData', () => {
  const payload = normalizeGraphExportItem({
    title: 'Distribution',
    chartData: {
      type: 'polarArea',
      labels: ['North', 'South'],
      series: [{ data: [{ name: 'North', value: 65 }, { name: 'South', value: 35 }] }]
    }
  }, 'rec_456');

  assert.equal(payload.chart_type, 'polarArea');
  assert.deepEqual(payload.labels, ['North', 'South']);
  assert.deepEqual(payload.values_data, [65, 35]);
});

test('ranked-bar exports keep raw ranks and horizontal orientation for the published dashboard', () => {
  const option = buildSavedChartOption({
    title: 'SDG Rank',
    chart_type: 'rankedBar',
    labels: ['SDG 1', 'SDG 2'],
    values_data: [12, 45],
    chart_data: {
      rankedBar: { selectedYear: 2025, reverseOrder: true },
      series: [{ data: [{ name: 'SDG 1', value: 12, rawValue: 12 }, { name: 'SDG 2', value: 45, rawValue: 45 }] }]
    }
  });

  assert.equal(option.xAxis.type, 'value');
  assert.equal(option.yAxis.type, 'category');
  assert.deepEqual(option.yAxis.data, ['SDG 2', 'SDG 1']);
  assert.deepEqual(option.series[0].data.map(point => point.value), [45, 12]);
  assert.deepEqual(option.series[0].data.map(point => point.rawValue), [45, 12]);
  assert.equal(option.yAxis.inverse, false);
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
  const graphApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'iris.php'), 'utf8');
  const publicDashboard = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8');
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
  const apiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'iris.php'), 'utf8');
  const adminPortalSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'adminPortal.js'), 'utf8');
  const dbManagerSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'database', 'dbManager.js'), 'utf8');
  const appSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'app.js'), 'utf8');
  const stylesSource = fs.readFileSync(path.join(__dirname, '..', 'css', 'styles.css'), 'utf8');
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
  const dashboardSource = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8');
  const adminSource = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'review_editor.php'), 'utf8');
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

test('public removal hides charts without deleting the saved graph record', () => {
  const dashboardSource = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8');
  const apiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'iris.php'), 'utf8');
  assert.match(dashboardSource, /Unpublish|Hide this published chart from the Observatory/);
  assert.match(dashboardSource, /action=unpublish/);
  assert.match(apiSource, /action\s*===\s*'unpublish'|action\s*===\s*"unpublish"/);
});

test('record approval does not auto-publish every saved graph for that record', () => {
  const apiSource = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'iris.php'), 'utf8');
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

test('renders polar-area print sheets as a dedicated polar chart instead of a pie slice layout', () => {
  const html = buildPrintableGraphSheet({
    title: 'Regional spread',
    chart_type: 'polarArea',
    labels: ['North', 'South', 'West'],
    values_data: [18, 42, 30]
  });

  assert.match(html, /Chart Type: POLARAREA/);
  assert.match(html, /aria-label="Polar Area chart"/);
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

test('chart engine does not redeclare yearColumn during ranked-bar rendering', () => {
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

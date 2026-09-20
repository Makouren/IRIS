const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { prepareCircularData } = require('../js/chartData');
const { pairSelectedText } = require('../js/sourceIngestion');
const { normalizeGraphExportItem, buildPrintableGraphSheet } = require('../js/graphExport');
const savedGraphsSource = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'savedGraphsTab.js'), 'utf8');

test('deduplicates circular chart legend labels while grouping remains optional', () => {
  const rows = [{ label: 'North', value: 2 }, { label: 'North', value: 3 }, { label: 'South', value: 4 }];
  const ungrouped = prepareCircularData(rows, false);
  const grouped = prepareCircularData(rows, true);
  assert.deepEqual(ungrouped.legendLabels, ['North', 'South']);
  assert.equal(ungrouped.rows.length, 3);
  assert.deepEqual(grouped.rows, [{ label: 'North', value: 5 }, { label: 'South', value: 4 }]);
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

test('axis controls are structural and no longer user-facing', () => {
  const app = fs.readFileSync(path.join(__dirname, '..', 'js', 'app.js'), 'utf8');
  const html = fs.readFileSync(path.join(__dirname, '..', 'index.html'), 'utf8');
  assert.equal(app.includes('studioBtnSwapAxes'), false);
  assert.equal(html.includes('studioBtnSwapAxes'), false);
  assert.equal(app.includes('axis-toggle-btn'), false);
  assert.equal(html.includes('studioLabelColSelect'), false);
  assert.equal(html.includes('studioValueColSelect'), false);
  assert.match(app, /ChartMapping\.inferColumns/);
  assert.match(app, /xAxis: isCircular \? undefined : \{ type: 'category', name: 'Rows'/);
  assert.match(app, /yAxis: isCircular \? undefined : \{ type: 'value', name: headerName/);
});

const test = require('node:test');
const assert = require('node:assert/strict');
const { inferColumns, isRankField, parseNumericValue, parseRankValue, detectYearColumn, getYearOptions } = require('../js/charts/chartMapping');

test('rank detection requires an explicit rank field name', () => {
  assert.equal(isRankField('Overall Rank'), true);
  assert.equal(isRankField('Score'), false);
  assert.equal(isRankField('Value'), false);
});

test('structural chart mapping uses row labels on X and numeric column values on Y', () => {
  const mapping = inferColumns(['Year', 'Category', 'Overall Rank'], [
    ['2020', 'Teaching', '123'],
    ['2021', 'Research', '111']
  ]);
  assert.equal(mapping.labelColumn, 1);
  assert.equal(mapping.valueColumn, 2);
});

test('year columns stay categorical strings and are excluded from numeric value choices', () => {
  const mapping = inferColumns(['Year', 'Institution', 'Rank'], [
    [2021, 'Institution A', 10],
    [2022, 'Institution B', 9]
  ]);
  assert.equal(mapping.columnTypes[0], 'text');
  assert.equal(mapping.numericColumns.includes(0), false);
  assert.equal(mapping.labelColumn, 1);
  assert.equal(mapping.valueColumn, 2);
});

test('QS Stars formatted ratings remain plottable values', () => {
  const headers = ['Year', 'Category', 'Star Rating', 'Score Rating', 'Validity'];
  const rows = [
    [2020, 'Teaching', '5 stars', '123/150', '2020-2023'],
    [2020, 'Employability', '5 stars', '111/150', '2020-2023']
  ];
  const mapping = inferColumns(headers, rows);
  assert.equal(mapping.labelColumn, 1);
  assert.equal(mapping.valueColumn, 3);
  assert.equal(parseNumericValue('123/150'), 123);
  assert.equal(parseNumericValue('5 stars'), 5);
});

test('rank ranges use their representative midpoint', () => {
  assert.equal(parseRankValue('801-1000'), 900.5);
  assert.equal(parseRankValue('601-800'), 700.5);
  assert.equal(parseRankValue('200'), 200);
});

test('year controls detect the year column and return available years in descending order', () => {
  const headers = ['Year', 'Institution', 'Overall Rank'];
  const rows = [
    [2022, 'Institution A', 5],
    [2023, 'Institution B', 2],
    [2023, 'Institution C', 1],
    [2024, 'Institution D', 4]
  ];
  const yearColumn = detectYearColumn(headers, rows);
  const options = getYearOptions(rows, yearColumn);
  assert.equal(yearColumn, 0);
  assert.deepEqual(options.availableYears, [2024, 2023, 2022]);
  assert.equal(options.selectedYear, 2024);
});

test('chart renderer uses ECharts option replacement and no retired chart-type renderer', () => {
  const source = require('node:fs').readFileSync(require('node:path').join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8');
  assert.match(source, /const chart = window\.echarts\.init\(/);
  assert.match(source, /studioChartInstance\.setOption\(option, true\)/);
  assert.match(source, /type: 'category'/);
  assert.doesNotMatch(source, new RegExp(['ranked', 'Bar'].join('')));
});
const test = require('node:test');
const assert = require('node:assert/strict');
const { inferColumns, isRankField, parseNumericValue, parseRankValue, detectYearColumn, buildRankedBarRows } = require('../js/chartMapping');

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

test('ranked bar charts detect the year column and use the newest year by default', () => {
  const headers = ['Year', 'Institution', 'Overall Rank'];
  const rows = [
    [2022, 'Institution A', 5],
    [2023, 'Institution B', 2],
    [2023, 'Institution C', 1],
    [2024, 'Institution D', 4]
  ];
  const yearColumn = detectYearColumn(headers, rows);
  const { options, rows: rankedRows } = buildRankedBarRows(rows, { yearColumn, selectedYear: 2023, limit: 10, labelColumn: 1, valueColumn: 2, rankMode: true });
  assert.equal(yearColumn, 0);
  assert.equal(options.availableYears.includes(2024), true);
  assert.equal(rankedRows[0].label, 'Institution C');
  assert.equal(rankedRows[0].value, 1);
  assert.equal(rankedRows[0].visualValue, 2);
});

test('ranked bar charts reverse only display order without changing rank values', () => {
  const rows = [
    [2024, 'Institution A', 12],
    [2024, 'Institution B', 34],
    [2024, 'Institution C', 45],
    [2024, 'Institution D', 89],
    [2024, 'Institution E', 125]
  ];
  const { rows: rankedRows, options } = buildRankedBarRows(rows, {
    yearColumn: 0,
    selectedYear: 2024,
    labelColumn: 1,
    valueColumn: 2,
    limit: 10,
    reverseOrder: true
  });
  assert.equal(options.reverseOrder, true);
  assert.deepEqual(rankedRows.map(row => row.label), ['Institution E', 'Institution D', 'Institution C', 'Institution B', 'Institution A']);
  assert.deepEqual(rankedRows.map(row => row.value), [125, 89, 45, 34, 12]);
});

test('ranked bar charts can include all years without filtering the ranking values', () => {
  const rows = [
    [2022, 'Institution A', 85],
    [2023, 'Institution B', 12],
    [2024, 'Institution C', 45],
    [2024, 'Institution D', 80],
    [2023, 'Institution E', 70]
  ];
  const { rows: rankedRows } = buildRankedBarRows(rows, {
    yearColumn: 0,
    selectedYear: 'all',
    labelColumn: 1,
    valueColumn: 2,
    limit: 10
  });
  assert.deepEqual(rankedRows.map(row => row.value), [12, 45, 70, 80, 85]);
  assert.deepEqual(rankedRows.map(row => row.label), ['Institution B', 'Institution C', 'Institution E', 'Institution D', 'Institution A']);
});

test('chart renderer initializes Apache ECharts with normalized category data', () => {
  const source = require('node:fs').readFileSync(require('node:path').join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8');
  assert.match(source, /const chart = window\.echarts\.init\(ctx\)/);
  assert.match(source, /chart\.setOption\(option\)/);
  assert.match(source, /option\.xAxis = \{ type: 'category', data: labels/);
  assert.match(source, /rankedBar/);
});
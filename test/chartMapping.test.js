const test = require('node:test');
const assert = require('node:assert/strict');
const { inferColumns, isRankField, parseNumericValue, parseRankValue } = require('../js/chartMapping');

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

test('chart adapters bind line and bar categories to source labels', () => {
  const source = require('node:fs').readFileSync(require('node:path').join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8');
  assert.match(source, /const categoryScale = \{ type: 'category', labels: data\.labels/);
  assert.match(source, /x: horizontal \? valueScale : categoryScale/);
  assert.match(source, /y: horizontal \? \{ \.\.\.categoryScale, labels: data\.labels \} : valueScale/);
});
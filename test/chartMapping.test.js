const test = require('node:test');
const assert = require('node:assert/strict');
const { inferColumns, parseNumericValue } = require('../js/chartMapping');

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
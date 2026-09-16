const test = require('node:test');
const assert = require('node:assert/strict');
const { filterRows } = require('../js/tableFilter');

const headers = ['Product', 'Status', 'Owner'];
const rows = [
  ['Alpha', 'Approved', 'Mina'],
  ['Beta', 'Pending', 'Noah'],
  ['Gamma', 'Approved', 'Mina']
];

test('matches values in non-header cells', () => {
  const results = filterRows(headers, rows, 'Pending');
  assert.deepEqual(results.map(result => result.origIdx), [1]);
});

test('progressively narrows the prior result set', () => {
  const broad = filterRows(headers, rows, 'Approved');
  const narrow = filterRows(headers, rows, 'Approved Mina', {
    previousQuery: 'Approved',
    previousResults: broad
  });
  assert.deepEqual(narrow.map(result => result.origIdx), [0, 2]);
  assert.equal(narrow.every(result => result.matchedTerms === 2), true);
});

test('matches common OCR substitutions and ranks exact text first', () => {
    const ocrRows = [['Total Sales', '1,250'], ['Tota1 Sa1es', '1,200']];
    const results = filterRows(['Metric', 'Amount'], ocrRows, 'Total Sales');
  assert.equal(results.length, 2);
    assert.equal(results[0].origIdx, 0);
    assert.equal(results[0].exactMatches, 2);
});

test('column-scoped filtering ignores the same value in another column', () => {
  const scopedRows = rows.map(row => [row[0], row[1]]);
  const results = filterRows([], scopedRows.map(row => [row[0]]), 'Approved', { includeHeaders: false });
  assert.deepEqual(results.map(result => result.origIdx), []);
  const statusResults = filterRows([], scopedRows.map(row => [row[1]]), 'Approved', { includeHeaders: false });
  assert.deepEqual(statusResults.map(result => result.origIdx), [0, 2]);
});

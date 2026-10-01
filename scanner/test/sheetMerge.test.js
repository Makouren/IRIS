const test = require('node:test');
const assert = require('node:assert/strict');
const { mergeSheet, defaultKeyColumns, validateSheet, isRecentMerge } = require('../js/sheetMerge.js');
const ChartMapping = require('../js/chartMapping.js');
const ChartData = require('../js/chartData.js');

function makeSheet(headers, rows, extra = {}) {
  return {
    name: 'Data',
    rowCount: rows.length + 1,
    colCount: headers.length,
    headers,
    rows,
    numericStats: {},
    ...extra
  };
}

test('inserts an incoming key and preserves existing rows', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 5], ['B', 2]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.stats.inserted, 1);
  assert.deepEqual(result.stats.rowStatus, ['unchanged', 'inserted']);
  assert.deepEqual(result.sheet.rows, [['A', 5], ['B', 2]]);
  assert.equal(result.sheet.rowCount, 3);
});

test('updates changed non-blank fields and leaves unchanged rows alone', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5], ['B', 3]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4], ['B', 3]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.sheet.rows, [['A', 4], ['B', 3]]);
  assert.equal(result.stats.updated, 1);
  assert.equal(result.stats.unchanged, 1);
  assert.deepEqual(result.stats.updatedByColumn, { Rank: 1 });
  assert.deepEqual(result.stats.rowStatus, ['updated', 'unchanged']);
});

test('blank new values do not overwrite existing data', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', '  ']]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.sheet.rows, [['A', 5]]);
  assert.equal(result.stats.unchanged, 1);
});

test('numeric zero overwrites an existing value', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 0]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.sheet.rows, [['A', 0]]);
  assert.deepEqual(result.stats.updatedByColumn, { Rank: 1 });
});

test('maps reordered incoming columns by normalized header name', () => {
  const existing = makeSheet(['Category', 'Rank', 'Year'], [['A', 5, 2025]]);
  const incoming = makeSheet(['Year', ' Rank ', 'Category'], [[2025, 4, 'A']]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.sheet.headers, ['Category', 'Rank', 'Year']);
  assert.deepEqual(result.sheet.rows, [['A', 4, 2025]]);
});

test('rejects a missing selected key column without mutating either input', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Rank'], [[4]]);
  const oldCopy = structuredClone(existing);
  const newCopy = structuredClone(incoming);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.match(result.error, /key column/i);
  assert.deepEqual(existing, oldCopy);
  assert.deepEqual(incoming, newCopy);
});

test('keeps existing keys absent from the incoming sheet', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5], ['B', 3]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.sheet.rows, [['A', 4], ['B', 3]]);
});

test('recomputes ExcelParser numeric statistics and headed rowCount', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5], ['B', 8]], {
    numericStats: { Rank: { count: 2, sum: 13, min: 5, max: 8, avg: 6.5 } }
  });
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4], ['C', 2]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.sheet.rowCount, result.sheet.rows.length + 1);
  assert.deepEqual(result.sheet.numericStats.Rank, { count: 3, sum: 14, min: 2, max: 8, avg: 4.67 });
});

test('matches Date objects with ISO and Office YYYY-MM-DD date strings', () => {
  const existing = makeSheet(['Date', 'Rank'], [[new Date('2025-02-03T00:00:00.000Z'), 5]]);
  const incoming = makeSheet(['Date', 'Rank'], [['2025-02-03', 4]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.stats.updated, 1);
  assert.deepEqual(result.sheet.rows[0], [new Date('2025-02-03T00:00:00.000Z'), 4]);
});

test('matches numeric strings with numeric values for keys', () => {
  const existing = makeSheet(['ID', 'Value'], [[5, 'old']]);
  const incoming = makeSheet(['ID', 'Value'], [['5.0', 'new']]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.stats.updated, 1);
  assert.deepEqual(result.sheet.rows, [[5, 'new']]);
});

test('updates text values when case changes but treats equivalent numeric values as unchanged', () => {
  const existing = makeSheet(['Category', 'Status', 'Rank'], [['A', 'Approved', 5]]);
  const incoming = makeSheet(['Category', 'Status', 'Rank'], [['A', 'approved', '5.0']]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.sheet.rows, [['A', 'approved', 5]]);
  assert.equal(result.stats.updated, 1);
  assert.deepEqual(result.stats.updatedByColumn, { Status: 1 });
});

test('skips blank keys', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank'], [['', 1], [null, 2], ['B', 3]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.stats.skipped, 2);
  assert.equal(result.stats.inserted, 1);
  assert.deepEqual(result.stats.rowStatus, ['skipped', 'skipped', 'inserted']);
});

test('last duplicate incoming key wins and earlier duplicates are skipped', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4], ['A', 3]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.stats.duplicateIncoming, 1);
  assert.equal(result.stats.skipped, 1);
  assert.deepEqual(result.sheet.rows, [['A', 3]]);
  assert.deepEqual(result.stats.rowStatus, ['skipped', 'updated']);
});

test('updates only the first duplicate key in existing rows', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5], ['A', 6]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.equal(result.stats.duplicateExisting, 1);
  assert.deepEqual(result.sheet.rows, [['A', 4], ['A', 6]]);
});

test('ignores incoming columns that do not exist in the existing sheet', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank', 'New Field'], [['A', 4, 'ignored'], ['B', 2, 'ignored']]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.stats.ignoredColumns, ['New Field']);
  assert.deepEqual(result.sheet.headers, ['Category', 'Rank']);
  assert.deepEqual(result.sheet.rows, [['A', 4], ['B', 2]]);
});

test('does not mutate the input sheets or their row arrays', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]]);
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4], ['B', 2]]);
  const oldCopy = structuredClone(existing);
  const newCopy = structuredClone(incoming);
  mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(existing, oldCopy);
  assert.deepEqual(incoming, newCopy);
});

test('default key uses ChartMapping label columns and falls back to column zero', () => {
  assert.deepEqual(defaultKeyColumns(['Name', 'Rank'], [['A', 5], ['B', 3]]), [0]);
  assert.deepEqual(defaultKeyColumns(['A', 'B'], [[1, 2], [3, 4]]), [0]);
});

test('preserves header order, column indexes, and existing row order', () => {
  const existing = makeSheet(['Year', 'Category', 'Rank'], [[2024, 'A', 5], [2025, 'B', 3]]);
  const incoming = makeSheet(['Rank', 'Category', 'Year'], [[4, 'A', 2024], [2, 'C', 2026]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0, 1] });
  assert.deepEqual(result.sheet.headers, ['Year', 'Category', 'Rank']);
  assert.deepEqual(result.sheet.rows, [[2024, 'A', 4], [2025, 'B', 3], [2026, 'C', 2]]);
});

test('reports per-row statuses and per-column update counts', () => {
  const existing = makeSheet(['Category', 'Rank', 'Score'], [['A', 5, 10], ['B', 3, 7]]);
  const incoming = makeSheet(['Category', 'Rank', 'Score'], [['A', 4, 10], ['B', 3, 7], ['C', 1, 9], ['', 0, 0]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0] });
  assert.deepEqual(result.stats.rowStatus, ['updated', 'unchanged', 'inserted', 'skipped']);
  assert.deepEqual(result.stats.updatedByColumn, { Rank: 1 });
});

test('validateSheet flags missing, blank, duplicate, empty, malformed, and truncated sheets', () => {
  assert.equal(validateSheet(null).ok, false);
  assert.ok(validateSheet(makeSheet([], [])).problems.some(problem => /headers/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['A', 'a'], [['x', 'y']])).problems.some(problem => /duplicate/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['A'], [])).problems.some(problem => /zero/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['A'], [['']])).problems.some(problem => /blank/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['A'], [['x', 'extra']])).problems.some(problem => /more values/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['A'], [['x']], { rowCount: 10 })).problems.some(problem => /row count/i.test(problem)));
});

test('validateSheet rejects missing keys, weak header overlap, and numeric columns turned into text', () => {
  const existing = makeSheet(['Category', 'Rank', 'Score'], [['A', 5, 10], ['B', 3, 7]], {
    numericStats: { Rank: { count: 2 }, Score: { count: 2 } }
  });
  assert.ok(validateSheet(makeSheet(['Rank'], [[4]]), existing).problems.some(problem => /key column/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['Other'], [['x']]), existing).problems.some(problem => /half/i.test(problem)));
  assert.ok(validateSheet(makeSheet(['Category', 'Rank', 'Score'], [['A', 'low', 'ten']]), existing).problems.some(problem => /mostly text/i.test(problem)));
});

test('validateSheet accepts complete compatible sheets', () => {
  const existing = makeSheet(['Category', 'Rank'], [['A', 5]], { numericStats: { Rank: { count: 1 } } });
  const incoming = makeSheet(['Category', 'Rank'], [['A', 4], ['B', 2]], { numericStats: { Rank: { count: 2 } } });
  assert.deepEqual(validateSheet(incoming, existing), { ok: true, problems: [] });
});

test('isRecentMerge accepts seven days and rejects eight days or invalid dates', () => {
  const now = new Date('2026-10-01T12:00:00.000Z');
  assert.equal(isRecentMerge({ merge: { merged_at: '2026-09-24T12:00:00.000Z' } }, now), true);
  assert.equal(isRecentMerge({ merge: { merged_at: '2026-09-23T12:00:00.000Z' } }, now), false);
  assert.equal(isRecentMerge({}, now), false);
  assert.equal(isRecentMerge({ merge: { merged_at: 'not-a-date' } }, now), false);
});

test('merged rows preserve inferred field indexes and add the new year to ranking helpers', () => {
  const existing = makeSheet(['Year', 'Category', 'Rank'], [[2024, 'A', 5], [2025, 'B', 3]]);
  const incoming = makeSheet(['Rank', 'Year', 'Category'], [[4, 2024, 'A'], [3, 2025, 'B'], [1, 2026, 'C']]);
  const before = ChartMapping.inferColumns(existing.headers, existing.rows);
  const result = mergeSheet(existing, incoming, { keyColumns: [0, 1] });
  const after = ChartMapping.inferColumns(result.sheet.headers, result.sheet.rows);
  assert.equal(after.labelColumn, before.labelColumn);
  assert.equal(after.valueColumn, before.valueColumn);
  assert.deepEqual(ChartMapping.getYearOptions(result.sheet.rows, 0).availableYears, [2026, 2025, 2024]);
  const ranked = ChartMapping.buildRankedBarRows(result.sheet.rows, {
    yearColumn: 0,
    selectedYear: 2026,
    labelColumn: after.labelColumn,
    valueColumn: after.valueColumn
  });
  assert.deepEqual(ranked.rows.map(row => row.label), ['C']);
  assert.equal(ranked.rows[0].value, 1);
});

test('merged rows flow through chart aggregators and survive JSON serialization', () => {
  const existing = makeSheet(['Year', 'Category', 'Rank'], [[2024, 'A', 5], [2025, 'B', 3]]);
  const incoming = makeSheet(['Year', 'Category', 'Rank'], [[2024, 'A', 4], [2025, 'B', 3], [2026, 'C', 1]]);
  const result = mergeSheet(existing, incoming, { keyColumns: [0, 1] });
  const chartRows = result.sheet.rows.map(row => ({ label: row[1], value: row[2], rawValue: row[2], row }));
  assert.deepEqual(ChartData.groupAndAggregate(chartRows).map(row => row.label), ['A', 'B', 'C']);
  assert.deepEqual(ChartData.prepareCircularData(chartRows, true).rows.map(row => row.label), ['A', 'B', 'C']);
  assert.deepEqual(ChartData.prepareNestedPieData(chartRows, 0).groups.map(group => group.label), ['2024', '2025', '2026']);
  const roundTripped = JSON.parse(JSON.stringify(result.sheet));
  assert.deepEqual(roundTripped.headers, result.sheet.headers);
  assert.deepEqual(roundTripped.rows, result.sheet.rows);
  assert.deepEqual(roundTripped.numericStats, result.sheet.numericStats);
});

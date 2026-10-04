/**
 * Purpose: Shared chart utility for chart mapping; used by Scanner chart views and exports.
 * Loaded by: scanner/index.php before sheet merging and chart modules; required by Node tests.
 * Inputs/outputs: Accepts headers and row arrays; exports ChartMapping in browser/CommonJS.
 * Dependencies: None.
 * Load order: Load before sheetMerge.js and Studio modules that use ChartMapping.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.ChartMapping = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  function isNumeric(value) {
    return parseNumericValue(value) !== null;
  }

  function isRankField(header) {
    return /\brank\b/i.test(String(header || ''));
  }

  function parseRankValue(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    const text = String(value ?? '').trim().replace(/,/g, '');
    const range = text.match(/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/);
    if (range) return (Number(range[1]) + Number(range[2])) / 2;
    return parseNumericValue(text);
  }

  function parseNumericValue(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    if (value === null || value === undefined) return null;
    const text = String(value).trim().replace(/,/g, '');
    if (!text) return null;
    const match = text.match(/^[-+]?\d+(?:\.\d+)?/);
    if (!match) return null;
    const parsed = Number(match[0]);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function parseYearValue(value) {
    if (value === null || value === undefined) return null;
    const numeric = Number(String(value).trim().replace(/,/g, ''));
    if (!Number.isFinite(numeric) || numeric < 1900 || numeric > 2100) return null;
    return Math.round(numeric);
  }

  /** Detect the year column from its header or, failing that, the values in a column. */
  function detectYearColumn(headers, rows) {
    const safeHeaders = Array.isArray(headers) ? headers : [];
    const safeRows = Array.isArray(rows) ? rows : [];
    const byHeader = safeHeaders.findIndex(header => /year/i.test(String(header || '')));
    if (byHeader >= 0) return byHeader;
    for (let colIndex = 0; colIndex < safeHeaders.length; colIndex++) {
      const values = safeRows.map(row => row?.[colIndex]).filter(value => value !== null && value !== undefined && String(value).trim() !== '');
      if (!values.length) continue;
      const yearLike = values.filter(value => parseYearValue(value) !== null);
      if (yearLike.length === values.length) return colIndex;
    }
    return null;
  }

  function getYearOptions(rows, yearColumn) {
    const safeRows = Array.isArray(rows) ? rows : [];
    const values = safeRows
      .map(row => parseYearValue(row?.[yearColumn]))
      .filter(value => value !== null && value !== undefined);
    const unique = [...new Set(values)].sort((a, b) => Number(b) - Number(a));
    return { availableYears: unique, selectedYear: unique[0] ?? null };
  }

  /**
   * Classify each column and return best default label/value columns plus full metadata.
   * Returns { labelColumn, valueColumn, numericColumns, labelColumns, columnTypes }
   */
  /** Infer category/value columns from header semantics and the observed cell values. */
  function inferColumns(headers, rows) {
    const safeHeaders = headers || [];
    const safeRows = rows || [];
    let labelColumn = safeHeaders.length > 0 ? 0 : -1;
    let valueColumn = safeHeaders.length > 1 ? 1 : 0;
    const numericColumns = [];
    const labelColumns = [];
    const columnTypes = {}; // colIdx -> 'numeric' | 'text' | 'mixed'
    const yearColumn = detectYearColumn(safeHeaders, safeRows);

    for (let column = 0; column < safeHeaders.length; column++) {
      if (column === yearColumn) {
        labelColumns.push(column);
        columnTypes[column] = 'text';
        continue;
      }
      const values = safeRows
        .map(row => row && row[column])
        .filter(value => value !== null && value !== undefined && String(value).trim() !== '');
      if (values.length === 0) { columnTypes[column] = 'mixed'; continue; }
      const numericCount = values.filter(isNumeric).length;
      const ratio = numericCount / values.length;
      if (ratio >= 0.7) {
        numericColumns.push(column);
        columnTypes[column] = 'numeric';
      } else if (ratio <= 0.3) {
        labelColumns.push(column);
        columnTypes[column] = 'text';
      } else {
        columnTypes[column] = 'mixed';
      }
    }

    const metricHeader = /rank|score|value|amount|count|total|rate|percent|percentage|points|metric/i;
    valueColumn =
      numericColumns.find(column => metricHeader.test(String(safeHeaders[column]))) ||
      numericColumns[numericColumns.length - 1] ||
      valueColumn;

    // Prefer text columns as label
    for (let column = 0; column < safeHeaders.length; column++) {
      if (column !== valueColumn && column !== yearColumn && columnTypes[column] === 'text') {
        labelColumn = column;
        break;
      }
    }
    if (labelColumn === valueColumn && yearColumn !== null && yearColumn !== valueColumn) labelColumn = yearColumn;
    // fallback: first column that isn't valueColumn
    if (labelColumn === valueColumn) {
      for (let column = 0; column < safeHeaders.length; column++) {
        if (column !== valueColumn) { labelColumn = column; break; }
      }
    }

    return { labelColumn, valueColumn, numericColumns, labelColumns, columnTypes };
  }

  return { inferColumns, isNumeric, isRankField, parseRankValue, parseNumericValue, parseYearValue, detectYearColumn, getYearOptions };
});
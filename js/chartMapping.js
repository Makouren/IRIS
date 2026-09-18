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

  /**
   * Classify each column and return best default label/value columns plus full metadata.
   * Returns { labelColumn, valueColumn, numericColumns, labelColumns, columnTypes }
   */
  function inferColumns(headers, rows) {
    const safeHeaders = headers || [];
    const safeRows = rows || [];
    let labelColumn = safeHeaders.length > 0 ? 0 : -1;
    let valueColumn = safeHeaders.length > 1 ? 1 : 0;
    const numericColumns = [];
    const labelColumns = [];
    const columnTypes = {}; // colIdx -> 'numeric' | 'text' | 'mixed'

    for (let column = 0; column < safeHeaders.length; column++) {
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
      if (column !== valueColumn && columnTypes[column] === 'text') {
        labelColumn = column;
        break;
      }
    }
    // fallback: first column that isn't valueColumn
    if (labelColumn === valueColumn) {
      for (let column = 0; column < safeHeaders.length; column++) {
        if (column !== valueColumn) { labelColumn = column; break; }
      }
    }

    return { labelColumn, valueColumn, numericColumns, labelColumns, columnTypes };
  }

  return { inferColumns, isNumeric, isRankField, parseRankValue, parseNumericValue };
});

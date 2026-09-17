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

  function inferColumns(headers, rows) {
    const safeHeaders = headers || [];
    const safeRows = rows || [];
    let labelColumn = safeHeaders.length > 0 ? 0 : -1;
    let valueColumn = safeHeaders.length > 1 ? 1 : 0;
    const numericColumns = [];

    for (let column = 0; column < safeHeaders.length; column++) {
      const values = safeRows.map(row => row && row[column]).filter(value => value !== null && value !== undefined && String(value).trim() !== '');
      const numericCount = values.filter(isNumeric).length;
      if (values.length > 0 && numericCount / values.length >= 0.5) {
        numericColumns.push(column);
      }
    }

    const metricHeader = /rank|score|value|amount|count|total|rate|percent|percentage|points|metric/i;
    valueColumn = numericColumns.find(column => metricHeader.test(String(safeHeaders[column]))) || numericColumns[numericColumns.length - 1] || valueColumn;

    for (let column = 0; column < safeHeaders.length; column++) {
      if (column !== valueColumn) {
        const values = safeRows.map(row => row && row[column]).filter(value => value !== null && value !== undefined && String(value).trim() !== '');
        const numericCount = values.filter(isNumeric).length;
        if (values.length === 0 || numericCount / values.length < 0.5) {
          labelColumn = column;
          break;
        }
      }
    }

    return { labelColumn, valueColumn };
  }

  return { inferColumns, isNumeric, parseNumericValue };
});

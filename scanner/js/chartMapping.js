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

  function buildRankedBarRows(rows, options = {}) {
    const safeRows = Array.isArray(rows) ? rows : [];
    const yearColumn = Number.isInteger(options.yearColumn) ? options.yearColumn : null;
    const allYears = yearColumn !== null
      ? [...new Set(safeRows.map(row => parseYearValue(row?.[yearColumn])).filter(value => value !== null))].sort((a, b) => Number(b) - Number(a))
      : [];
    const selectedRaw = options.selectedYear;
    const selectedYear = yearColumn !== null && (selectedRaw === null || selectedRaw === undefined)
      ? (allYears[0] ?? null)
      : (selectedRaw !== null && selectedRaw !== undefined ? (selectedRaw === 'all' ? 'all' : Number(selectedRaw)) : null);
    const labelColumn = Number.isInteger(options.labelColumn) ? options.labelColumn : 0;
    const valueColumn = Number.isInteger(options.valueColumn) ? options.valueColumn : 1;
    const limit = Number.isFinite(Number(options.limit)) ? Math.max(1, Number(options.limit)) : safeRows.length || 30;
    const reverseOrder = Boolean(options.reverseOrder);
    const yearFilter = yearColumn !== null && selectedYear !== null && selectedYear !== 'all';
    const filtered = yearFilter
      ? safeRows.filter(row => parseYearValue(row?.[yearColumn]) === Number(selectedYear))
      : safeRows.slice();
    const prepared = filtered.map((row, index) => {
      const value = parseNumericValue(row?.[valueColumn]);
      const label = String(row?.[labelColumn] ?? `Item ${index + 1}`).trim() || `Item ${index + 1}`;
      return {
        label,
        value: value !== null ? value : 0,
        rawValue: value !== null ? value : 0,
        row,
        year: parseYearValue(row?.[yearColumn]),
        sourceIndex: index
      };
    }).filter(item => Number.isFinite(item.value));
    const sorted = prepared.slice().sort((a, b) => Number(a.value) - Number(b.value));
    const top = sorted.slice(0, Math.min(limit, sorted.length || limit));
    const displayRows = reverseOrder ? top.slice().reverse() : top;
    const maxValue = displayRows.length ? Math.max(...displayRows.map(item => Number(item.value) || 0)) : 0;
    return {
      rows: displayRows.map((item, index) => ({
        ...item,
        rank: index + 1,
        visualValue: maxValue > 0 ? Math.max(1, maxValue - Number(item.value) + 1) : Number(item.value) || 1,
        labelValue: Number(item.value)
      })),
      options: {
        yearColumn,
        selectedYear,
        availableYears: allYears,
        limit,
        reverseOrder
      }
    };
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

  return { inferColumns, isNumeric, isRankField, parseRankValue, parseNumericValue, parseYearValue, detectYearColumn, getYearOptions, buildRankedBarRows };
});

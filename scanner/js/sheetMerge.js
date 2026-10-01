(function (root, factory) {
  const chartMapping = root.ChartMapping || (typeof require === 'function' ? require('./chartMapping.js') : null);
  const tableFilter = root.TableFilter || (typeof require === 'function' ? require('./tableFilter.js') : null);
  const api = factory(chartMapping, tableFilter);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.SheetMerge = api;
})(typeof self !== 'undefined' ? self : globalThis, function (ChartMapping, TableFilter) {
  function normalizeText(value) {
    if (TableFilter?.normalize) return TableFilter.normalize(value);
    return String(value ?? '').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
  }

  function normalizeHeader(value) {
    return normalizeText(value);
  }

  function isBlank(value) {
    return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
  }

  function normalizedDate(value) {
    if (value instanceof Date && Number.isFinite(value.getTime())) return value.toISOString().slice(0, 10);
    if (typeof value !== 'string') return null;
    const match = value.trim().match(/^(\d{4}-\d{2}-\d{2})(?:$|T)/);
    if (!match) return null;
    const date = new Date(`${match[1]}T00:00:00.000Z`);
    return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === match[1] ? match[1] : null;
  }

  function normalizedComparable(value) {
    if (value instanceof Date) return normalizedDate(value) ?? '';
    const date = normalizedDate(value);
    if (date !== null) return date;
    if (typeof value === 'number' && Number.isFinite(value)) return String(Object.is(value, -0) ? 0 : value);
    if (typeof value === 'string') {
      const trimmed = value.trim().replace(/,/g, '');
      if (/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(trimmed)) {
        const number = Number(trimmed);
        if (Number.isFinite(number)) return String(Object.is(number, -0) ? 0 : number);
      }
    }
    return normalizeText(value);
  }

  function sameValue(left, right) {
    if (isBlank(left) && isBlank(right)) return true;
    const leftDate = normalizedDate(left);
    const rightDate = normalizedDate(right);
    if (leftDate !== null || rightDate !== null) return leftDate === rightDate;
    const isNumeric = value => typeof value === 'number' && Number.isFinite(value)
      || typeof value === 'string' && value.trim() !== '' && /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim().replace(/,/g, ''));
    if (isNumeric(left) && isNumeric(right)) return Number(String(left).replace(/,/g, '')) === Number(String(right).replace(/,/g, ''));
    return String(left).trim() === String(right).trim();
  }

  function defaultKeyColumns(headers, rows) {
    const safeHeaders = Array.isArray(headers) ? headers : [];
    const inferred = ChartMapping?.inferColumns?.(safeHeaders, Array.isArray(rows) ? rows : []);
    const keys = Array.isArray(inferred?.labelColumns)
      ? inferred.labelColumns.filter(index => Number.isInteger(index) && index >= 0 && index < safeHeaders.length)
      : [];
    return keys.length ? keys : (safeHeaders.length ? [0] : []);
  }

  function headerIndex(headers) {
    const index = new Map();
    headers.forEach((header, column) => {
      const key = normalizeHeader(header);
      if (key && !index.has(key)) index.set(key, column);
    });
    return index;
  }

  function hasDuplicateHeaders(headers) {
    const normalized = headers.map(normalizeHeader).filter(Boolean);
    return new Set(normalized).size !== normalized.length;
  }

  function isMostlyNumeric(values) {
    const occupied = values.filter(value => !isBlank(value));
    if (!occupied.length) return false;
    const numeric = occupied.filter(value => {
      if (typeof value === 'number') return Number.isFinite(value);
      return typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value.replace(/,/g, '')));
    }).length;
    return numeric / occupied.length >= 0.5;
  }

  function validateSheet(sheet, existing = null, selectedKeyColumns = null) {
    const problems = [];
    if (!sheet || typeof sheet !== 'object' || Array.isArray(sheet)) return { ok: false, problems: ['No readable worksheet was selected.'] };
    const headers = Array.isArray(sheet.headers) ? sheet.headers : [];
    const rows = Array.isArray(sheet.rows) ? sheet.rows : [];
    if (!headers.length || headers.every(isBlank)) problems.push('The worksheet has no usable headers.');
    if (headers.some(isBlank)) problems.push('The worksheet contains a blank header.');
    if (hasDuplicateHeaders(headers)) problems.push('The worksheet contains duplicate headers after normalization.');
    if (!rows.length) problems.push('The worksheet contains zero data rows.');
    if (rows.length && rows.every(row => !Array.isArray(row) || row.every(isBlank))) problems.push('All worksheet rows are blank.');
    if (!Number.isInteger(sheet.rowCount) || (sheet.rowCount !== rows.length && sheet.rowCount !== rows.length + 1)) {
      problems.push('The worksheet row count does not match its rows. It may be truncated or incomplete.');
    }
    if (rows.some(row => Array.isArray(row) && row.length > headers.length)) problems.push('A worksheet row contains more values than there are headers.');

    if (existing && Array.isArray(existing.headers) && existing.headers.length) {
      const incomingHeaders = headerIndex(headers);
      const existingHeaders = headerIndex(existing.headers);
      const overlap = [...existingHeaders.keys()].filter(key => incomingHeaders.has(key)).length;
      if (overlap / existingHeaders.size < 0.5) problems.push('Fewer than half of the existing headers match the incoming worksheet.');
      const keyColumns = Array.isArray(selectedKeyColumns) ? selectedKeyColumns : defaultKeyColumns(existing.headers, existing.rows);
      keyColumns.forEach(column => {
        const key = normalizeHeader(existing.headers[column]);
        if (!key || !incomingHeaders.has(key)) problems.push(`The incoming worksheet is missing key column "${existing.headers[column] || `Column ${column + 1}`}".`);
      });
      Object.keys(existing.numericStats || {}).forEach(header => {
        const oldColumn = existingHeaders.get(normalizeHeader(header));
        const newColumn = incomingHeaders.get(normalizeHeader(header));
        if (oldColumn === undefined || newColumn === undefined) return;
        const previousValues = (existing.rows || []).map(row => row?.[oldColumn]);
        const incomingValues = rows.map(row => row?.[newColumn]);
        if (isMostlyNumeric(previousValues) && !isMostlyNumeric(incomingValues)) {
          problems.push(`Previously numeric column "${header}" is mostly text in the incoming worksheet.`);
        }
      });
    }

    return { ok: problems.length === 0, problems };
  }

  function numericStats(headers, rows) {
    const stats = {};
    headers.forEach((header, column) => {
      const values = rows.map(row => row[column]).filter(value => typeof value === 'number' && !Number.isNaN(value));
      if (values.length && values.length >= rows.length * 0.4) {
        const sum = values.reduce((total, value) => total + value, 0);
        stats[header] = {
          count: values.length,
          sum: Number(sum.toFixed(2)),
          min: Number(Math.min(...values).toFixed(2)),
          max: Number(Math.max(...values).toFixed(2)),
          avg: Number((sum / values.length).toFixed(2))
        };
      }
    });
    return stats;
  }

  function mergeSheet(existing, incoming, options = {}) {
    const existingCheck = validateSheet(existing);
    if (!existingCheck.ok) return { error: existingCheck.problems[0], problems: existingCheck.problems };
    const selectedKeyColumns = Array.isArray(options.keyColumns) ? options.keyColumns : null;
    const incomingCheck = validateSheet(incoming, existing, selectedKeyColumns);
    if (!incomingCheck.ok) return { error: incomingCheck.problems[0], problems: incomingCheck.problems };

    const oldHeaders = existing.headers;
    const newHeaders = incoming.headers;
    const oldHeaderIndex = headerIndex(oldHeaders);
    const newHeaderIndex = headerIndex(newHeaders);
    const keyColumns = Array.isArray(options.keyColumns) ? [...new Set(options.keyColumns)] : defaultKeyColumns(oldHeaders, existing.rows);
    if (!keyColumns.length || keyColumns.some(column => !Number.isInteger(column) || column < 0 || column >= oldHeaders.length)) {
      return { error: 'Select at least one valid key column.', problems: ['Select at least one valid key column.'] };
    }
    const newKeyColumns = keyColumns.map(column => newHeaderIndex.get(normalizeHeader(oldHeaders[column])));
    if (newKeyColumns.some(column => column === undefined)) {
      return { error: 'The incoming worksheet is missing a selected key column.', problems: ['The incoming worksheet is missing a selected key column.'] };
    }

    const ignoredColumns = newHeaders.filter(header => !oldHeaderIndex.has(normalizeHeader(header)));
    const stats = {
      inserted: 0,
      updated: 0,
      unchanged: 0,
      skipped: 0,
      duplicateIncoming: 0,
      duplicateExisting: 0,
      ignoredColumns,
      updatedByColumn: {},
      rowStatus: incoming.rows.map(() => 'skipped')
    };

    const keyForRow = (row, columns) => {
      const values = columns.map(column => row?.[column]);
      if (values.some(isBlank)) return null;
      return JSON.stringify(values.map(normalizedComparable));
    };
    const existingMatches = new Map();
    existing.rows.forEach((row, rowIndex) => {
      const key = keyForRow(row, keyColumns);
      if (key === null) return;
      if (!existingMatches.has(key)) existingMatches.set(key, []);
      existingMatches.get(key).push(rowIndex);
    });

    const lastIncomingIndex = new Map();
    incoming.rows.forEach((row, rowIndex) => {
      const key = keyForRow(row, newKeyColumns);
      if (key === null) return;
      if (lastIncomingIndex.has(key)) stats.duplicateIncoming++;
      lastIncomingIndex.set(key, rowIndex);
    });

    const mergedRows = existing.rows.map(row => oldHeaders.map((_, column) => row?.[column] ?? ''));
    incoming.rows.forEach((newRow, rowIndex) => {
      const key = keyForRow(newRow, newKeyColumns);
      if (key === null) {
        stats.skipped++;
        return;
      }
      if (lastIncomingIndex.get(key) !== rowIndex) {
        stats.skipped++;
        return;
      }

      const matches = existingMatches.get(key) || [];
      if (matches.length) {
        if (matches.length > 1) stats.duplicateExisting += matches.length - 1;
        const target = mergedRows[matches[0]];
        let changed = false;
        oldHeaders.forEach((header, column) => {
          if (keyColumns.includes(column)) return;
          const incomingColumn = newHeaderIndex.get(normalizeHeader(header));
          if (incomingColumn === undefined) return;
          const value = newRow?.[incomingColumn];
          if (isBlank(value) || sameValue(target[column], value)) return;
          target[column] = value;
          stats.updatedByColumn[header] = (stats.updatedByColumn[header] || 0) + 1;
          changed = true;
        });
        stats.rowStatus[rowIndex] = changed ? 'updated' : 'unchanged';
        stats[changed ? 'updated' : 'unchanged']++;
        return;
      }

      const added = oldHeaders.map(header => {
        const incomingColumn = newHeaderIndex.get(normalizeHeader(header));
        return incomingColumn === undefined ? '' : (newRow?.[incomingColumn] ?? '');
      });
      mergedRows.push(added);
      stats.rowStatus[rowIndex] = 'inserted';
      stats.inserted++;
    });

    const headerless = existing.rowCount === existing.rows.length;
    return {
      sheet: {
        ...existing,
        headers: [...oldHeaders],
        rows: mergedRows,
        rowCount: mergedRows.length + (headerless ? 0 : 1),
        numericStats: numericStats(oldHeaders, mergedRows)
      },
      stats
    };
  }

  function isRecentMerge(metadata, now = new Date()) {
    const mergedAt = metadata?.merge?.merged_at;
    const mergedTime = typeof mergedAt === 'string' || mergedAt instanceof Date ? new Date(mergedAt).getTime() : NaN;
    const currentTime = now instanceof Date ? now.getTime() : new Date(now).getTime();
    const age = currentTime - mergedTime;
    return Number.isFinite(mergedTime) && Number.isFinite(currentTime) && age >= 0 && age <= 7 * 24 * 60 * 60 * 1000;
  }

  return { mergeSheet, defaultKeyColumns, validateSheet, isRecentMerge };
});
/**
 * Search spreadsheet rows using headers and cell values.
 * The optional previous result set lets callers progressively narrow results.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.TableFilter = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  const OCR_REPLACEMENTS = [
    [/0/g, 'o'],
    [/1/g, 'l'],
    [/i/g, 'l'],
    [/5/g, 's'],
    [/rn/g, 'm']
  ];

  function normalize(value) {
    return String(value === null || value === undefined ? '' : value)
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .trim()
      .replace(/\s+/g, ' ');
  }

  function ocrNormalize(value) {
    let result = normalize(value).replace(/\s+/g, '');
    OCR_REPLACEMENTS.forEach(([pattern, replacement]) => {
      result = result.replace(pattern, replacement);
    });
    return result;
  }

  function levenshtein(left, right) {
    const previous = Array.from({ length: right.length + 1 }, (_, index) => index);
    for (let leftIndex = 0; leftIndex < left.length; leftIndex++) {
      const current = [leftIndex + 1];
      for (let rightIndex = 0; rightIndex < right.length; rightIndex++) {
        current.push(Math.min(
          current[rightIndex] + 1,
          previous[rightIndex + 1] + 1,
          previous[rightIndex] + (left[leftIndex] === right[rightIndex] ? 0 : 1)
        ));
      }
      for (let index = 0; index < current.length; index++) previous[index] = current[index];
    }
    return previous[right.length];
  }

  function fuzzyMatch(term, cellText) {
    const normalizedTerm = normalize(term);
    const words = normalize(cellText).split(' ').filter(Boolean);
    if (!normalizedTerm || words.length === 0) return 0;

    const maxDistance = Math.max(1, Math.floor(normalizedTerm.length * 0.25));
    let best = 0;
    words.forEach(word => {
      const distance = levenshtein(normalizedTerm, word);
      if (distance <= maxDistance) {
        best = Math.max(best, 40 - distance * 5);
      }
    });
    return best;
  }

  function matchTerm(term, cells) {
    const normalizedTerm = normalize(term);
    const compactTerm = ocrNormalize(term);
    let best = { score: 0, column: -1 };

    cells.forEach((cell, column) => {
      const normalizedCell = normalize(cell);
      const compactCell = ocrNormalize(cell);
      let score = 0;
      if (normalizedCell.includes(normalizedTerm) || compactCell.includes(compactTerm)) {
        score = normalizedCell.includes(normalizedTerm) ? 100 : 80;
      } else {
        score = fuzzyMatch(term, cell);
      }
      if (score > best.score) best = { score, column };
    });
    return best;
  }

  function queryTerms(query) {
    return normalize(query).split(' ').filter(Boolean);
  }

  function isProgressiveQuery(previousQuery, query) {
    const previousTerms = queryTerms(previousQuery);
    const terms = queryTerms(query);
    return terms.length > previousTerms.length && previousTerms.every(term => terms.includes(term));
  }

  function filterRows(headers, rows, query, options = {}) {
    const terms = queryTerms(query);
    const source = terms.length > 0 && isProgressiveQuery(options.previousQuery || '', query) && Array.isArray(options.previousResults)
      ? options.previousResults
      : (rows || []).map((row, origIdx) => ({ row: row || [], origIdx }));

    if (terms.length === 0) return source;

    return source.map(item => {
      const cells = options.includeHeaders === false ? (item.row || []) : (headers || []).map(header => header).concat(item.row || []);
      const matches = terms.map(term => matchTerm(term, cells));
      if (matches.some(match => match.score === 0)) return null;
      const matchedColumns = new Set(matches.map(match => match.column)).size;
        const exactMatches = matches.filter(match => match.score >= 100).length;
      return {
        ...item,
        score: matches.reduce((total, match) => total + match.score, 0) + matchedColumns * 5,
        matchedTerms: terms.length,
        matchedColumns,
        exactMatches
      };
    }).filter(Boolean).sort((left, right) => right.score - left.score || left.origIdx - right.origIdx);
  }

  return {
    filterRows,
    normalize,
    levenshtein
  };
});

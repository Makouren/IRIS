/**
 * Purpose: Pair selected text with a likely corresponding source line/value.
 * Loaded by: scanner/index.php as a classic script before document viewer modules.
 * Inputs/outputs: Accepts selected text and source text; exposes SourceIngestion globally.
 * Dependencies: None.
 * Load order: Load before documentViewer.js consumes pairSelectedText().
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.SourceIngestion = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  function looksLikeValue(text) {
    return /\d/.test(text) || /[$%]/.test(text) || /^[-+]?\d[\d,.]*(?:\s*[-/]\s*\d[\d,.]*)?$/.test(text);
  }

  /**
   * Add a neighboring value to a selected label when source text suggests a pair.
   * @param {string} selection Text selected in the document viewer.
   * @param {string} sourceText Extracted record text.
   * @returns {string} Paired selection, or the original selection when no safe pair exists.
   */
  function pairSelectedText(selection, sourceText) {
    const selected = String(selection || '').trim();
    if (!selected) return '';
    if (/\s/.test(selected) && looksLikeValue(selected)) return selected;

    const lines = String(sourceText || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean);
    const selectedIndex = lines.findIndex(line => line === selected || line.includes(selected));
    if (selectedIndex < 0) return selected;

    const line = lines[selectedIndex];
    const inlineParts = line.split(/\s*(?::|=|\|)\s*/).filter(Boolean);
    if (inlineParts.length === 2 && (inlineParts[0].includes(selected) || inlineParts[1].includes(selected))) {
      return inlineParts.join(' ');
    }

    const nearby = [line, lines[selectedIndex + 1], lines[selectedIndex - 1]
      ].filter(Boolean);
    const valueLine = nearby.find(candidate => candidate !== line && looksLikeValue(candidate));
    if (valueLine) return selectedIndex + 1 < lines.length ? `${line} ${valueLine}` : `${valueLine} ${line}`;

    if (line.split(/\s+/).length <= 4 && line.split(/\s+/).some(looksLikeValue)) return line;
    return selected;
  }

  return { pairSelectedText };
});

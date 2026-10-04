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

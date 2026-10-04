/**
 * Purpose: Scanner browser logic for document pagination; loaded by the Scanner page.
 * Loaded by: scanner/index.php before document viewer modules; required by Node tests.
 * Inputs/outputs: Accepts text and a word limit; exports DocumentPagination in browser/CommonJS.
 * Dependencies: None.
 * Load order: Load before documentViewer.js calls paginateText().
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.DocumentPagination = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  /**
   * Split extracted text into pages while preferring paragraph boundaries.
   * @param {string} rawText Extracted source text.
   * @param {number} maxWords Approximate word target per page.
   * @returns {string[]} Non-empty text pages.
   */
  function paginateText(rawText, maxWords = 230) {
    const paragraphs = String(rawText || '').split(/\n\s*\n/).map(text => text.trim()).filter(Boolean);
    const pages = [];
    let page = [];
    let wordCount = 0;

    paragraphs.forEach(paragraph => {
      const paragraphWords = paragraph.split(/\s+/).filter(Boolean).length;
      if (page.length > 0 && wordCount + paragraphWords > maxWords) {
        pages.push(page);
        page = [];
        wordCount = 0;
      }
      page.push(paragraph);
      wordCount += paragraphWords;
    });

    if (page.length > 0) pages.push(page);
    return pages.length > 0 ? pages : [['No content available.']];
  }

  return { paginateText };
});

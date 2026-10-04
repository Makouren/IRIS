(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.DocumentPagination = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
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

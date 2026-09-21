const test = require('node:test');
const assert = require('node:assert/strict');
const { paginateText } = require('../js/documentPagination');

test('pagination keeps page two distinct for the page navigator', () => {
  const pages = paginateText('Page one content.\n\nPage two content.', 3);
  assert.equal(pages.length, 2);
  assert.deepEqual(pages[0], ['Page one content.']);
  assert.deepEqual(pages[1], ['Page two content.']);
});
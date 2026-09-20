const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(path.join(__dirname, '..', 'index.html'), 'utf8');

test('review flow removes the admin destination and fake auth affordances', () => {
  assert.equal(html.includes('id="navAdminBtn"'), false, 'Admin navigation destination should be retired');
  assert.equal(html.includes('Welcome, Admin'), false, 'Fake admin identity should be removed');
  assert.equal(html.includes('Logout'), false, 'Fake logout affordance should be removed');
  assert.ok(html.includes('studioContainer'), 'Review studio should remain available in the main app');
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(path.join(__dirname, '..', 'index.html'), 'utf8');
const styles = fs.readFileSync(path.join(__dirname, '..', 'css', 'styles.css'), 'utf8');

test('review flow removes the admin destination and fake auth affordances', () => {
  assert.equal(html.includes('id="navAdminBtn"'), false, 'Admin navigation destination should be retired');
  assert.equal(html.includes('Welcome, Admin'), false, 'Fake admin identity should be removed');
  assert.equal(html.includes('Logout'), false, 'Fake logout affordance should be removed');
  assert.ok(html.includes('studioContainer'), 'Review studio should remain available in the main app');
  assert.equal(html.includes('id="recordEditModal"'), false, 'Legacy review editor modal should be removed');
});

test('review editor action opens the dashboard studio directly', () => {
  const overview = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'overviewTab.js'), 'utf8');
  const app = fs.readFileSync(path.join(__dirname, '..', 'js', 'app.js'), 'utf8');
  assert.match(overview, /openReviewStudio\?\.\(ctx\.state\.activeScan\.id\)/);
  assert.equal(overview.includes('openRecordEditModal'), false);
  assert.equal(app.includes('initRecordEditModal'), false);
});

test('review workspace navigation restores the scanner view and scroll position', () => {
  const navigation = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'navigation.js'), 'utf8');
  assert.match(navigation, /if \(adminView\) adminView\.style\.display = 'none';/);
  assert.match(navigation, /scannerView\?\.scrollIntoView\(\{ behavior: 'smooth', block: 'start' \}\)/);
  assert.match(navigation, /event\.preventDefault\(\)/);
});

test('review and dashboard layouts have mobile overflow protections', () => {
  assert.match(styles, /grid-template-columns: minmax\(220px, 290px\) minmax\(0, 1fr\)/);
  assert.match(styles, /grid-template-columns: minmax\(0, 480px\) minmax\(0, 1fr\)/);
  assert.match(styles, /@media \(max-width: 700px\)/);
  assert.match(styles, /\.table-container\s*\{[\s\S]*overflow-x: auto/);
  assert.match(styles, /\.modal-card\s*\{[\s\S]*max-height: calc\(100vh - 2rem\)/);
});

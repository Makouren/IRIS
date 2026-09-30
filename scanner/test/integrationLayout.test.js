const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const styles = fs.readFileSync(path.join(__dirname, '..', 'css', 'styles.css'), 'utf8');
const adminEditor = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'review_editor.php'), 'utf8');
const adminPortal = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'adminPortal.js'), 'utf8');
const dbManager = fs.readFileSync(path.join(__dirname, '..', 'js', 'database', 'dbManager.js'), 'utf8');

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
  assert.match(styles, /grid-template-columns: minmax\(0, 480px\) minmax\(0, 1fr\)/);
  assert.match(styles, /@media \(max-width: 1200px\)/);
  assert.match(styles, /@media \(max-width: 700px\)/);
  assert.match(styles, /\.table-container\s*\{[\s\S]*overflow-x: auto/);
  assert.match(styles, /\.modal-card\s*\{[\s\S]*max-height: calc\(100vh - 2rem\)/);
});

test('manual dataset creation persists a distinct empty record and selects it by ID', () => {
  assert.match(adminEditor, /id="createManualDataset"/);
  assert.match(adminEditor, /id="manualDatasetForm"/);
  assert.match(adminPortal, /const fileName = manualDatasetName\?\.value\.trim\(\)/);
  assert.match(adminPortal, /fileType: 'manual',[\s\S]*headers: \[\], rows: \[\]/);
  assert.match(adminPortal, /ctx\.api\.renderAdminPortal\(record\.id\)/);
  assert.match(adminPortal, /preferredRecord \|\| matchedRecord/);
  assert.match(adminPortal, /setEditorRecordUrl\(record\.id\)/);
  assert.ok(dbManager.includes('id: record.id || `rec_${Date.now()}_'), 'New records receive their own generated ID');
  assert.ok(dbManager.includes('extractedData: record.extractedData ||'), 'Manual sheet data is sent to the records API');
});

test('summary-card editing uses a modal instead of expanding the admin page', () => {
  assert.match(adminEditor, /id="summaryCardEditorPanel" class="modal-overlay"/);
  assert.match(adminEditor, /id="summaryCardEditorList"/);
  assert.match(adminEditor, /id="summaryCardManagerView"/);
  assert.match(adminEditor, /id="summaryCardEditorForm" style="display:none;"/);
  assert.match(adminEditor, /const openEditor = \(\) =>/);
  assert.match(adminEditor, /const closeEditor = \(\) =>/);
  assert.match(adminEditor, /id="addSummaryCardFromManager"/);
  assert.match(adminEditor, /document\.getElementById\('closeSummaryCardEditor'\)\.addEventListener/);
  assert.match(adminEditor, /event\.key === 'Escape'/);
});

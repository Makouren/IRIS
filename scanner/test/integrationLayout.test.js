const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const styles = fs.readFileSync(path.join(__dirname, '..', 'css', 'styles.css'), 'utf8');
const adminEditor = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'review_editor.php'), 'utf8');
const adminHeader = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'header.php'), 'utf8');
const adminArchives = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'file_archives.php'), 'utf8');
const adminPortal = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'adminPortal.js'), 'utf8');
const savedGraphsTab = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'savedGraphsTab.js'), 'utf8');
const studioWorkbench = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioWorkbench.js'), 'utf8');
const dbManager = fs.readFileSync(path.join(__dirname, '..', 'js', 'database', 'dbManager.js'), 'utf8');
const studioAppend = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioAppend.js'), 'utf8');
const irisApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'iris.php'), 'utf8');
const publicDashboardApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'dashboard_graphs.php'), 'utf8');
const publicDashboard = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8');
const colorCustomizer = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioColorCustomizer.js'), 'utf8');
const importPreviewModal = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'import', 'importPreviewModal.js'), 'utf8');

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

test('File Archives has direct navigation that opens and loads its tab', () => {
  const navigation = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'navigation.js'), 'utf8');
  const tabs = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'navigationTabs.js'), 'utf8');
  assert.match(html, /id="navArchivesBtn"[^>]*>\s*[\s\S]*?File Archives/);
  assert.match(navigation, /const archivesButton = \$\('navArchivesBtn'\)/);
  assert.match(navigation, /archivesButton\?\.addEventListener\('click'/);
  assert.match(navigation, /querySelector\('\[data-admin-tab="adminFileArchivesPanel"\]'\)\?\.click\(\)/);
  assert.match(tabs, /button\.dataset\.adminTab === 'adminFileArchivesPanel'[\s\S]*?renderFileArchives/);
});

test('Super Admin menu opens File Archives in the active admin interface', () => {
  assert.match(adminHeader, /base_url\('admin\/file_archives\.php'\)[\s\S]*?File Archives/);
  assert.match(adminArchives, /\$activeNav = 'archives'/);
  assert.match(adminArchives, /id="fileArchivesTableBody"/);
  assert.match(adminArchives, /id="fileArchiveSearchInput"/);
  assert.match(adminArchives, /id="fileArchivesBulkActions"/);
  assert.match(adminArchives, /includes\/footer\.php/);
  assert.doesNotMatch(adminEditor, /Scanned Records Archive &amp;? Ingestion Logs|adminRecordsTableBody|adminSearchInput/);
});

test('record merge requires a chosen direction, resolves server conflicts, and retains source by default', () => {
  assert.match(studioAppend, /const superAdmin = mergeButton\?\.dataset\.role === 'super_admin'/);
  assert.match(studioAppend, /String\(candidate\.template_id \?\? ''\) !== String\(record\.template_id\)/);
  assert.match(studioAppend, /name="merge-direction" value="active-target"/);
  assert.match(studioAppend, /name="merge-direction" value="active-source"/);
  assert.match(studioAppend, /Merge the selected record \(A\) into the active record \(B\)/);
  assert.match(studioAppend, /A is kept by default/);
  assert.match(studioAppend, /A is deleted from the records list, while its archived version remains in File History/);
  assert.match(studioAppend, /Merge the active record \(A\) into the selected record \(B\)/);
  assert.match(studioAppend, /TARGET — THIS BECOMES THE UPDATED RECORD/);
  assert.match(studioAppend, /This record remains under its current ID and contains the merged result/);
  assert.match(studioAppend, /The source is not made into the new record/);
  assert.match(studioAppend, /if \(!direction\)/);
  assert.match(studioAppend, /previewRecordMerge/);
  assert.match(studioAppend, /consume_source: modal\.querySelector\('\[data-consume-source\]'\)\.checked/);
  assert.match(dbManager, /mergeRecords\(request\)/);
  assert.match(irisApi, /\$action, \['preview-record-merge', 'merge-records'\]/);
  assert.match(irisApi, /Only a Super Admin can merge records/);
  assert.match(irisApi, /source_id.*target_id|source_id/);
  assert.match(irisApi, /source_digest/);
  assert.match(irisApi, /Resolve every conflict before merging/);
  assert.match(irisApi, /'source-at-merge'/);
  assert.match(irisApi, /'pre-merge-target'/);
  assert.match(irisApi, /'post-merge-result'/);
  assert.match(irisApi, /\$consumeSource = \(\$data\['consume_source'\] \?\? false\) === true/);
  assert.match(dbManager, /'X-CSRF-Token'/);
});

test('File Archives exposes merge history view, comparison, download, and append-only restore', () => {
  assert.match(adminPortal, /data-id="\$\{escape\(record\.id\)\}"[^>]*>.*?File History/);
  assert.match(adminPortal, /getRecordFileHistory\(record\.id\)/);
  assert.match(adminPortal, /Compare selected/);
  assert.match(adminPortal, /getFileHistoryVersion/);
  assert.match(adminPortal, /restoreFileHistoryVersion/);
  assert.match(dbManager, /fileHistoryDownload/);
  assert.match(irisApi, /'restore-result'/);
  assert.match(irisApi, /'pre-restore'/);
});

test('legacy merge recovery actions retain their existing pending/template and 30-day restore checks', () => {
  const stageStart = irisApi.indexOf("$action === 'stage-restore'");
  const trashStart = irisApi.indexOf("$action === 'trash-stored-file'");
  const restoreStart = irisApi.indexOf("$action === 'restore-merge'");
  const bulkStart = irisApi.indexOf("$action === 'bulk-approve'", restoreStart);
  const stage = irisApi.slice(stageStart, trashStart);
  const restore = irisApi.slice(restoreStart, bulkStart);
  assert.match(stage, /ensure_admin_for_mutation\(\)/);
  assert.match(stage, /Pending Review/);
  assert.match(stage, /Both records must be assigned to the same template/);
  assert.match(stage, /'old_id' => \(string\)\$existingId/);
  assert.match(stage, /'office_saved_graphs' => \$officeGraphs/);
  assert.match(restore, /time\(\) - \$createdAt > 30 \* 24 \* 60 \* 60/);
  assert.match(restore, /insert_row_from_snapshot\(\$pdo, 'records', \$officeRow\)/);
  assert.match(restore, /insert_graph_from_snapshot/);
});

test('review and dashboard layouts have mobile overflow protections', () => {
  assert.match(styles, /grid-template-columns: minmax\(0, 480px\) minmax\(0, 1fr\)/);
  assert.match(styles, /\.studio-grid\s*\{[\s\S]*?width: 100%;[\s\S]*?min-width: 0;/);
  assert.match(styles, /#adminDatabaseView\s*\{[\s\S]*?width: 100%;[\s\S]*?min-width: 0;/);
  assert.match(styles, /\.review-editor-stats\s*\{[\s\S]*?display: grid;[\s\S]*?repeat\(4, minmax\(0, 1fr\)\)/);
  assert.match(adminEditor, /<div class="review-editor-stats">/);
  assert.match(styles, /@media \(max-width: 1200px\)/);
  assert.match(styles, /@media \(max-width: 700px\)/);
  assert.match(styles, /@media \(max-width: 640px\)[\s\S]*?\.review-editor-stats\s*\{[\s\S]*?repeat\(2, minmax\(0, 1fr\)\)/);
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

test('Review Editor saves and reopens complete graph configuration from Saved Graphs', () => {
  assert.match(adminEditor, /id="studioBtnSave"[^>]*>[\s\S]*?Save Graph/);
  assert.match(html, /id="studioBtnSave"[^>]*>[\s\S]*?Save Graph/);
  assert.match(studioWorkbench, /ctx\.dbManager\.updateGraph\(graphIdToUpdate, savedChart\)/);
  assert.match(studioWorkbench, /ctx\.dbManager\.saveGraph\(savedChart\)/);
  assert.match(studioWorkbench, /filterField: \$\('studioFilterField'\)/);
  assert.match(studioWorkbench, /filterOperator: \$\('studioFilterOperator'\)/);
  assert.match(studioWorkbench, /filterUpperValue:/);
  assert.match(studioWorkbench, /sortOrder: \$\('studioSortOrder'\)/);
  assert.match(studioWorkbench, /rowLimit:/);
  assert.match(studioWorkbench, /groupDuplicates:/);
  assert.match(adminPortal, /restoreValue\('studioFilterField', 'filterField'/);
  assert.match(adminPortal, /restoreValue\('studioFilterOperator', 'filterOperator'/);
  assert.match(adminPortal, /restoreValue\('studioSortOrder', 'sortOrder'/);
  assert.match(adminPortal, /restoreValue\('studioRowLimit', 'rowLimit'/);
  assert.match(adminPortal, /groupDuplicates\.checked = irisConfig\.groupDuplicates !== false/);
  assert.match(savedGraphsTab, /url\.searchParams\.set\('record_id', graph\.record_id\)/);
  assert.match(savedGraphsTab, /url\.searchParams\.set\('graph_id', graph\.id\)/);
  assert.match(studioWorkbench, /ctx\.api\.renderSavedGraphsTab/);
});

test('Chart colors collapse accessibly and save one shared color independently', () => {
  assert.match(adminEditor, /id="studioColorSectionToggle"[^>]*aria-expanded="false"[^>]*aria-controls="studioColorContent"/);
  assert.match(adminEditor, /id="studioColorSwatches"/);
  assert.match(adminEditor, /id="studioColorReset"/);
  assert.match(adminEditor, /id="studioColorApplyAll"/);
  assert.match(adminEditor, /id="studioManageFieldColors"/);
  assert.match(adminEditor, /id="studioColorPickerPanel"/);
  assert.match(colorCustomizer, /sectionToggle\.hidden = !multiple/);
  assert.match(colorCustomizer, /colorContent\.hidden = multiple && !colorSectionExpanded/);
  assert.match(colorCustomizer, /saveFieldColor\(index\)/);
  assert.match(colorCustomizer, /ctx\.dbManager\.saveFieldColor\(fieldKey, label, color\)/);
  assert.match(colorCustomizer, /unsaved: 'Unsaved', saving: 'Saving…', saved: 'Saved', failed: 'Failed — retry'/);
  const singleColorSave = colorCustomizer.slice(colorCustomizer.indexOf('async function saveFieldColor(index)'), colorCustomizer.indexOf('const renderPresets'));
  assert.doesNotMatch(singleColorSave, /saveGraph|updateGraph|saveRecord/);
  assert.match(colorCustomizer, /if \(!confirm\('Reset these fields to default colors and remove their saved shared colors\?/);
  assert.doesNotMatch(studioWorkbench, /persistStudioFieldColors/);
  assert.match(irisApi, /INSERT INTO field_colors \(field_name, color\) VALUES \(\?, \?\) ON DUPLICATE KEY UPDATE/);
  assert.match(publicDashboardApi, /sg\.updated_at/);
  assert.match(publicDashboardApi, /field_color_updated_at/);
  assert.match(publicDashboard, /window\.IRISFieldColorUpdatedAt = data\.field_color_updated_at/);
});

test('Field Mapping controls share responsive spacing classes in Review Editor and scanner UI', () => {
  assert.match(adminEditor, /id="studioFieldMappingRow" class="studio-field-mapping"/);
  assert.match(html, /id="studioFieldMappingRow" class="studio-field-mapping"/);
  assert.match(adminEditor, /id="studioValuePrecisionSelect" class="form-input studio-field-mapping-select"/);
  assert.match(html, /id="studioValuePrecisionSelect" class="form-input studio-field-mapping-select"/);
  assert.match(styles, /\.studio-field-mapping-select[\s\S]*padding: \.35rem 2\.25rem/);
  assert.match(styles, /#studioValuePrecisionSelect \{ min-width: 10\.5rem; \}/);
  assert.match(styles, /@media \(max-width: 640px\)/);
});

test('Super Admin can set the published summary-card category shown by default publicly', () => {
  assert.match(adminEditor, /if \(\(\$_SESSION\['role'\] \?\? ''\) === 'super_admin'\): \?>[\s\S]*?id="summaryCardPublicDefaultCategory"/);
  assert.match(adminEditor, /action=save-public-default/);
  assert.match(irisApi, /summary_cards_default_category/);
  assert.match(irisApi, /Only a Super Admin can change the public summary-card default/);
  assert.match(irisApi, /Choose a category with at least one published summary card/);
  assert.match(publicDashboardApi, /summary_cards_default_category/);
  assert.match(publicDashboard, /summaryCardDefaultCategorySlug = payload\.summary_cards_default_category/);
  assert.match(publicDashboard, /const initialSlug = requested \|\| summaryCardDefaultCategorySlug/);
  assert.match(adminEditor, /function renderPublicDefaultCategory\(\)/);
  assert.match(adminEditor, /renderPublicDefaultCategory\(\);/);
});

test('public summary-card default lists new categories and keeps unpublished ones unavailable', () => {
  assert.match(adminEditor, /categories\.map\(category => \{[\s\S]*?const hasPublishedCards = publishedCategoryIds\.has\(String\(category\.id\)\);[\s\S]*?option\.disabled = !hasPublishedCards/);
  assert.match(adminEditor, /category\.slug === publicDefaultCategorySlug[\s\S]*?publishedCategoryIds\.has\(String\(category\.id\)\)/);
  assert.match(irisApi, /Choose a category with at least one published summary card/);
});

test('Summary Card manager can add a category without first creating a card', () => {
  assert.match(adminEditor, /id="summaryCardManagerNewCategory"[^>]*maxlength="40"/);
  assert.match(adminEditor, /id="summaryCardManagerAddCategory"[^>]*>Confirm add/);
  assert.match(adminEditor, /id="summaryCardManagerCategoryStatus"[^>]*role="status"[^>]*aria-live="polite"/);
  assert.ok(adminEditor.indexOf('id="summaryCardManagerAddCategory"') < adminEditor.indexOf('id="summaryCardPublicDefaultHeading"'), 'Add category shortcut should be in the manager top actions.');
  assert.ok(adminEditor.indexOf('id="summaryCardManagerAddCategory"') < adminEditor.indexOf('id="summaryCardCategoryList"'), 'Add category shortcut should appear before the category list.');
  assert.match(adminEditor, /managerAddCategoryButton\.addEventListener\('click', async \(\) =>/);
  assert.match(adminEditor, /categoryApi,[\s\S]*?method: 'POST',[\s\S]*?JSON\.stringify\(\{ name, sort_order: nextSortOrder \}\)/);
  assert.match(adminEditor, /if \(!confirm\(`Add the category "\$\{name\}"\?`\)\) return/);
  assert.match(adminEditor, /await refreshSummaryCardEditor\(\);/);
  assert.match(adminEditor, /managerCategoryAction\.hidden = true;[\s\S]*?showCardList\(\);[\s\S]*?editorPanel\.classList\.add\('active'\);[\s\S]*?editorPanel\.setAttribute\('aria-hidden', 'false'\);[\s\S]*?await refreshSummaryCardEditor\(\);[\s\S]*?categoryFilter\.focus\(\);/);
  assert.doesNotMatch(adminEditor, /location\.reload\(\)/);
  assert.match(adminEditor, /<details class="summary-card-manager-actions-menu">[\s\S]*?id="addSummaryCardFromManager"[\s\S]*?id="summaryCardManagerShowCategory"/);
  assert.match(adminEditor, /id="summaryCardManagerCategoryAction" class="summary-card-manager-category-action" hidden/);
  assert.match(adminEditor, /if \(\(\$_SESSION\['role'\] \?\? ''\) === 'super_admin'\): \?>[\s\S]*?id="summaryCardManagerShowCategory"/);
  assert.match(adminEditor, /managerCategoryMenuButton\?\.addEventListener\('click', \(\) => \{[\s\S]*?managerCategoryAction\.hidden = false/);
  assert.match(importPreviewModal, /destination === 'summary_cards'[\s\S]*?getElementById\('summaryCardManagerActionMenu'\);[\s\S]*?menu\.append\(button\)/);
  assert.match(styles, /\.summary-card-manager-menu \{[\s\S]*position: absolute/);
  assert.match(styles, /\.summary-card-manager-category-action\[hidden\] \{ display: none !important; \}/);
});

test('Ranking History manager links directly to the Super Admin ranking-body dialog', () => {
  assert.match(adminEditor, /if \(\(\$_SESSION\['role'\] \?\? ''\) === 'super_admin'\): \?>[\s\S]*?data-ranking-body-manager-open[^>]*aria-controls="rankingBodyManagerModal"[\s\S]*?Manage ranking bodies/);
  assert.match(adminEditor, /id="rankingHistoryEditorHeading" class="modal-title">Manage Ranking History/);
  const rankingBodyManager = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'rankingBodyManager.js'), 'utf8');
  assert.match(rankingBodyManager, /querySelectorAll\('\[data-ranking-body-manager-open\]'\)\.forEach\(button => button\.addEventListener\('click', open\)\)/);
  assert.match(adminHeader + fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'footer.php'), 'utf8'), /ranking_body_manager_modal\.php/);
});

test('summary-card editing uses a modal instead of expanding the admin page', () => {
  assert.match(adminEditor, /id="summaryCardEditorPanel" class="modal-overlay"/);
  assert.match(adminEditor, /class="modal-card summary-card-editor-modal" role="dialog"/);
  assert.match(adminEditor, /id="summaryCardEditorList"/);
  assert.match(adminEditor, /id="summaryCardManagerView">[\s\S]*?id="summaryCardPublicDefaultHeading"[\s\S]*?id="summaryCardEditorList"[\s\S]*?id="summaryCardCategoryList"[\s\S]*?<\/section>\s*<\/div>\s*<form id="summaryCardEditorForm"/);
  assert.match(adminEditor, /id="summaryCardEditorForm" style="display:none;"/);
  assert.match(styles, /\.summary-card-editor-modal\s*\{[\s\S]*?width: min\(1120px, calc\(100vw - 2rem\)\);[\s\S]*?overflow: hidden/);
  assert.match(styles, /#summaryCardManagerView,[\s\S]*?#summaryCardEditorForm\s*\{[\s\S]*?overflow-y: auto/);
  assert.match(adminEditor, /const openEditor = \(\) =>/);
  assert.match(adminEditor, /const closeEditor = \(\) =>/);
  assert.match(adminEditor, /id="addSummaryCardFromManager"/);
  assert.match(adminEditor, /document\.getElementById\('closeSummaryCardEditor'\)\.addEventListener/);
  assert.match(adminEditor, /event\.key === 'Escape'/);
});

test('Summary Card Edit resolves API IDs consistently and opens the populated edit form', () => {
  assert.match(adminEditor, /editorList\.querySelectorAll\('\.summary-card-editor-edit'\)\.forEach\(button => \{/);
  assert.match(adminEditor, /const id = String\(button\.dataset\.id \|\| ''\);[\s\S]*?cards\.find\(item => String\(item\.id \?\? item\.card_id \?\? ''\) === id\)/);
  assert.match(adminEditor, /summaryCardEditorId'\)\.value = String\(card\.id \?\? card\.card_id \?\? ''\)/);
  assert.match(adminEditor, /summaryCardEditorTitle'\)\.value = card\.title \|\| ''/);
  assert.match(adminEditor, /showCardForm\(true\);[\s\S]*?openEditor\(\);/);
  assert.match(adminEditor, /This summary card could not be found\. Refresh the list and try again\./);
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const readIrisApiSource = () => [
  path.join(__dirname, '..', '..', 'api', 'iris.php'),
  path.join(__dirname, '..', '..', 'includes', 'api', 'common.php'),
  ...[
    'record_file_history',
    'field_colors',
    'summary_card_categories',
    'summary_card_history',
    'summary_cards',
    'records',
    'graphs'
  ].map(handler => path.join(__dirname, '..', '..', 'includes', 'api', 'handlers', `${handler}.php`))
].map(file => fs.readFileSync(file, 'utf8')).join('\n');

const html = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const dashboardStyles = fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'css', 'dashboard.css'), 'utf8');
const styles = [
  'base.css',
  'ingestion.css',
  'layout.css',
  'studio.css',
  'viewer.css',
  'charts.css',
  'tables.css',
  'form-controls.css',
  'modals.css',
  'docx-viewer.css',
  'dark-overrides.css'
].map(file => fs.readFileSync(path.join(__dirname, '..', 'css', file), 'utf8')).join('\n');
const adminEditor = [
  fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'review_editor.php'), 'utf8'),
  ...[
    'summaryCards.php',
    'starRatingCards.php',
    'rankingHistory.php',
    'studioFieldColors.php',
    'manualDataset.php',
    'recordEdit.php'
  ].map(file => fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'reviewEditor', file), 'utf8')),
  ...[
    'summaryCards.js',
    'starRatingCards.js',
    'rankingHistory.js'
  ].map(file => fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'reviewEditor', file), 'utf8'))
].join('\n');
const adminHeader = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'header.php'), 'utf8');
const rankingHistoryAdmin = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'rankingHistoryAdmin.js'), 'utf8');
const adminRankingsApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'admin_rankings.php'), 'utf8');
const rankingHistoryImportApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'imports', 'ranking_history_import.php'), 'utf8');
const publicRankingsApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'rankings.php'), 'utf8');
const adminArchives = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'file_archives.php'), 'utf8');
const adminSavedGraphs = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'saved_graphs.php'), 'utf8');
const adminFooter = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'footer.php'), 'utf8');
const adminPortal = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'adminPortal.js'), 'utf8');
const savedGraphsTab = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'savedGraphsTab.js'), 'utf8');
const studioWorkbench = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioWorkbench.js'), 'utf8');
const dbManager = fs.readFileSync(path.join(__dirname, '..', 'js', 'database', 'dbManager.js'), 'utf8');
const studioAppend = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioAppend.js'), 'utf8');
const irisApi = readIrisApiSource();
const publicDashboardApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'dashboard_graphs.php'), 'utf8');
const publicDashboard = [
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8'),
  dashboardStyles,
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'js', 'dashboard', 'main.js'), 'utf8'),
  fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'js', 'dashboard', 'chartBuilder.js'), 'utf8')
].join('\n');
const portalNav = fs.readFileSync(path.join(__dirname, '..', '..', 'includes', 'navigation', 'portal_nav.php'), 'utf8');
const changePasswordApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'change_password.php'), 'utf8');
const colorCustomizer = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'studioColorCustomizer.js'), 'utf8');
const importPreviewModal = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'import', 'importPreviewModal.js'), 'utf8');
const templateManager = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'templateManager.js'), 'utf8');
const templateManagerModal = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'template_manager_modal.php'), 'utf8');
const profileWorkbookMapper = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'profileWorkbookMapper.js'), 'utf8');
const profileWorkbookService = fs.readFileSync(path.join(__dirname, '..', '..', 'includes', 'helpers', 'ProfileWorkbookService.php'), 'utf8');
const templateApi = fs.readFileSync(path.join(__dirname, '..', '..', 'api', 'templates.php'), 'utf8');
const officeUpload = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'office_upload.php'), 'utf8');
const officeUploadProcess = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'upload_process.php'), 'utf8');
const officeTemplates = fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'js', 'officeTemplates.js'), 'utf8');
const changeRefresh = fs.readFileSync(path.join(__dirname, '..', 'js', 'ui', 'changeRefresh.js'), 'utf8');
const graphDrafts = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'graphsTab.js'), 'utf8');

test('template downloads do not leave the page loader covering the current page', () => {
  for (const source of [adminFooter, officeUpload]) {
    assert.match(source, /let templateDownloadPending = false/);
    assert.ok(source.includes("target.pathname.endsWith('/admin/template_download.php')"));
    assert.match(source, /if \(templateDownloadPending\)[\s\S]*?return;/);
  }
  assert.match(adminFooter, /if \(templateDownloadPending\)[\s\S]*?return;[\s\S]*?if \(window\.IRIS_STUDIO_DIRTY\)/);
});

test('Studio chart play area is larger and charts keep legends clear of titles', () => {
  assert.match(html, /class="studio-chart-play-area"/);
  assert.match(adminEditor, /class="studio-chart-play-area"/);
  assert.match(styles, /\.studio-chart-play-area\s*\{[\s\S]*?height:\s*clamp\(420px,\s*62vh,\s*560px\)/);
  assert.doesNotMatch(html, /height:\s*320px;\s*position:\s*relative;\s*width:\s*100%;\s*margin-bottom:\s*0\.75rem/);
  assert.match(fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'chartEngine.js'), 'utf8'), /top: showTitle \? 28 : 0/);
});

test('Studio and draft chart selectors expose exactly the six supported types in order', () => {
  const expected = [
    ['line', 'Line Chart'],
    ['stackedArea', 'Stacked Area Chart'],
    ['bar', 'Bar Chart'],
    ['pie', 'Pie Chart'],
    ['doughnut', 'Doughnut Chart'],
    ['nestedPie', 'Nested Pie']
  ];
  const readOptions = markup => [...markup.matchAll(/<option value="([^"]+)"[^>]*>([^<]+)<\/option>/g)]
    .map(match => [match[1], match[2].trim()]);
  for (const source of [html, adminEditor]) {
    const selector = source.match(/<select id="studioChartTypeSelect"[\s\S]*?<\/select>/)?.[0];
    assert.ok(selector);
    assert.deepEqual(readOptions(selector), expected);
  }
  const draftSelector = graphDrafts.match(/<select class="form-input chart-type-select"[\s\S]*?<\/select>/)?.[0];
  assert.ok(draftSelector);
  assert.deepEqual(readOptions(draftSelector), expected);
});

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

test('Review from File Archives opens the selected record in the Review Editor', () => {
  const navigation = fs.readFileSync(path.join(__dirname, '..', 'js', 'modules', 'navigation.js'), 'utf8');
  assert.match(adminPortal, /all\('\.btn-table-load-studio'\)[\s\S]*?ctx\.api\.openReviewStudio\(record\.id\)/);
  assert.match(navigation, /window\.location\.href = `review_editor\.php\$\{param\}`/);
  assert.match(navigation, /await ctx\.api\.renderAdminPortal\(recordId\)/);
});

test('File History table headers have enough room and do not overlap', () => {
  assert.match(styles, /\.file-history-table\s*\{[^}]*min-width:\s*860px/);
  assert.match(styles, /\.file-history-table th:first-child,\s*\.file-history-table td:first-child\s*\{[^}]*width:\s*7rem/);
  assert.match(styles, /\.file-history-table th\s*\{[^}]*white-space:\s*nowrap/);
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

test('Super Admin account and template controls load from Observatory view', () => {
  assert.match(publicDashboard, /if \(\(\$_SESSION\['role'\] \?\? ''\) === 'super_admin'\): \?>[\s\S]*?account_manager_modal\.php[\s\S]*?template_manager_modal\.php/);
  assert.match(publicDashboard, /base_url\('admin\/js\/accountManager\.js'\)/);
  assert.match(publicDashboard, /base_url\('admin\/js\/templateManager\.js'\)/);
  assert.match(publicDashboard, /base_url\('admin\/js\/profileWorkbookMapper\.js'\)/);
});

test('Change password opens a shared modal and submits securely from admin and office pages', () => {
  assert.match(portalNav, /data-password-change-open/);
  assert.match(portalNav, /id="passwordChangeModal"/);
  assert.match(portalNav, /base_url\('api\/change_password\.php'\)/);
  assert.match(fs.readFileSync(path.join(__dirname, '..', 'js', 'ui', 'portalNavigation.js'), 'utf8'), /passwordForm\.addEventListener\('submit'/);
  assert.match(changePasswordApi, /requireRole\(\['super_admin', 'admin', 'user'\], true\)/);
  assert.match(changePasswordApi, /HTTP_X_CSRF_TOKEN/);
  assert.equal(fs.existsSync(path.join(__dirname, '..', '..', 'auth', 'change_password.php')), false);
});

test('Data and Report Visualization uploads can use General without an active template', () => {
  assert.match(officeUpload, /Template profile \(optional\)/);
  assert.match(officeUpload, /<option value="">General \(uncategorized — template can be assigned later\)<\/option>/);
  assert.match(officeTemplates, /templateSelect\.required = false/);
  assert.match(officeTemplates, /templateSelect\.disabled = !purpose \|\| hasImportProfile/);
  assert.match(officeTemplates, /General lets you submit now; the Super Admin can configure a template later/);
  assert.doesNotMatch(officeUploadProcess, /if \(\$templateId === null && \$uploadPurpose === 'analytics'\)/);
  assert.match(officeUploadProcess, /\$recordMetadata\['template_status'\] = 'uncategorized'/);
  assert.match(adminPortal, /record\.metadata\?\.template_status === 'uncategorized'/);
  assert.match(adminPortal, /GENERAL · TEMPLATE NEEDED/);
});

test('Super Admin logo navigates to the public Observatory, not the retired admin landing page', () => {
  assert.match(adminHeader, /<a href="<\?= e\(base_url\('user\/dashboard\.php'\)\) \?>" class="flex items-center gap-3 min-w-0" aria-label="Go to the public Observatory">/);
  assert.doesNotMatch(adminHeader, /<a href="<\?= e\(base_url\('admin\/dashboard\.php'\)\) \?>" class="logo-refresh-trigger/);
});

test('Manage account and template dropdown buttons have link-matched hover feedback', () => {
  for (const menuStyles of [adminHeader, dashboardStyles]) {
    assert.match(menuStyles, /\.admin-dropdown li > button:hover[\s\S]*?background/);
    assert.match(menuStyles, /\.admin-dropdown li > button:not\(:disabled\):hover[\s\S]*?translateY\(-1px\)/);
  }
});

test('record merge requires a chosen direction, replaces differing values from source, and retains source by default', () => {
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
  assert.match(studioAppend, /Source values replace differing values in matching rows/);
  assert.match(studioAppend, /Source values will replace differing target values/);
  assert.match(studioAppend, /if \(!direction\)/);
  assert.match(studioAppend, /previewRecordMerge/);
  assert.match(studioAppend, /consume_source: modal\.querySelector\('\[data-consume-source\]'\)\.checked/);
  assert.match(dbManager, /mergeRecords\(request\)/);
  assert.match(irisApi, /\$action, \['preview-record-merge', 'merge-records'\]/);
  assert.match(irisApi, /Only a Super Admin can merge records/);
  assert.match(irisApi, /source_id.*target_id|source_id/);
  assert.match(irisApi, /source_digest/);
  assert.match(irisApi, /record_merge_sheet\(\$targetData\[\$targetSheetName\], \$sourceData\[\$sourceSheetName\], \$keyColumns\)/);
  assert.match(irisApi, /'source-at-merge'/);
  assert.match(irisApi, /'pre-merge-target'/);
  assert.match(irisApi, /'post-merge-result'/);
  assert.match(irisApi, /\$consumeSource = \(\$data\['consume_source'\] \?\? false\) === true/);
  assert.match(dbManager, /'X-CSRF-Token'/);
});

test('File Archives exposes merge history view, comparison, download, and append-only restore', () => {
  assert.match(adminPortal, /data-id="\$\{escape\(record\.id\)\}"[^>]*>.*?File History/);
  assert.match(adminPortal, /getRecordFileHistory\(record\.id\)/);
  assert.match(adminPortal, /file-history-table/);
  assert.match(adminPortal, /data-selection-count/);
  assert.match(adminPortal, /data-compare disabled>Compare/);
  assert.match(adminPortal, /getFileHistoryVersion/);
  assert.match(adminPortal, /restoreFileHistoryVersion/);
  assert.match(styles, /\.file-history-actions[\s\S]*?display: flex/);
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

test('saved graph charts stay inside their cards without a published-scope editor', () => {
  assert.doesNotMatch(savedGraphsTab, /Published scope|scopeEditor|Save scope/);
  assert.match(styles, /\.graph-canvas-container\s*\{[\s\S]*?overflow: hidden/);
  assert.match(styles, /\.graph-canvas-container > div\s*\{[\s\S]*?position: absolute;[\s\S]*?inset: 0/);
});

test('template uploads assign and retain one of the three office destinations', () => {
  for (const [value, label] of [
    ['analytics', 'Data &amp; Report Visualization'],
    ['summary_cards', 'Summary Cards'],
    ['ranking_history', 'Ranking History']
  ]) {
    assert.match(templateManagerModal, new RegExp(`<option value="${value}">${label}</option>`));
  }
  assert.match(templateApi, /templates\.destination, templates\.is_active/);
  assert.match(templateApi, /INSERT INTO templates \(name, file_path, original_filename, destination, ranking_body_id, uploaded_by\)/);
  assert.match(templateApi, /Apply migrations\/20261003_template_destination\.sql/);
  assert.match(officeTemplates, /name\.className = 'office-active-template-title break-words'/);
  assert.match(officeTemplates, /originalName\.className = 'office-active-template-filename break-all'/);
  assert.match(styles, /\.office-active-template-title\s*\{[^}]*color: #1F2A24 !important;[^}]*font-size: 1rem/);
  assert.match(styles, /\.office-active-template-filename\s*\{[^}]*color: #1F2A24 !important;[^}]*font-size: 1rem;[^}]*font-weight: 600/);
  assert.match(styles, /html\.dark \.office-active-template-filename \{ color: #F1F5F9 !important; \}/);
  assert.match(templateManager, /function showUploadConfirmation\(name\)/);
  assert.match(templateManager, /name\} uploaded successfully\./);
  assert.match(templateManager, /window\.setTimeout\(removeToast, 5000\)/);
  const uploadHandler = templateManager.slice(templateManager.indexOf("form.addEventListener('submit'"), templateManager.indexOf("list.addEventListener('click'"));
  assert.match(uploadHandler, /showUploadConfirmation\(uploadedName\)/);
  assert.doesNotMatch(uploadHandler, /location\.reload/);
  assert.match(templateApi, /CustomImportFields::columnExists\(\$pdo, 'template_import_profiles'\)[\s\S]*?NULL AS custom_fields/);
  assert.match(fs.readFileSync(path.join(__dirname, '..', '..', 'migrations', '20261003_template_destination.sql'), 'utf8'), /TABLE_SCHEMA = DATABASE\(\)/);
  assert.match(templateManager, /destinationLabels\[template\.import_destination\]/);
  assert.match(officeUpload, /COALESCE\(profiles\.destination, templates\.destination, "analytics"\) AS upload_purpose/);
  assert.match(officeUploadProcess, /COALESCE\(profiles\.destination, templates\.destination, \\'analytics\\'\) AS destination/);
  assert.match(officeUpload, /<option value="analytics">Data &amp; Report Visualization<\/option>/);
  assert.match(styles, /\.office-upload-layout \.text-gray-700,[\s\S]*?color: #475569 !important/);
});

test('Super Admin has no manual refresh control; public views update from change signals', () => {
  assert.doesNotMatch(adminHeader, /data-iris-manual-refresh|data-iris-refresh-status|Refresh public view/);
  assert.doesNotMatch(changeRefresh, /manualRefresh|refreshStatus|publicRefreshStorageKey|refresh_public|localStorage/);
  assert.match(fs.readFileSync(path.join(__dirname, '..', '..', 'user', 'dashboard.php'), 'utf8'), /\$irisChangeRefreshView = 'public'; require __DIR__ \. '\/\.\.\/includes\/scripts\/change_refresh_script\.php'/);
  assert.match(fs.readFileSync(path.join(__dirname, '..', '..', 'includes', 'scripts', 'change_refresh_script.php'), 'utf8'), /data-view="<\?= e\(\$irisChangeRefreshView \?\? ''\) \?>"/);
  assert.match(changeRefresh, /if \(isPublicView\) \{\s*if \(isDirty\(\)\) \{[\s\S]*?showPendingRefresh\(\);\s*\} else window\.location\.reload\(\);\s*return;\s*\}\s*if \(isSuperAdmin\) \{\s*return;\s*\}/);
  assert.match(changeRefresh, /if \(isDirty\(\)\) \{\s*window\.dispatchEvent\(new CustomEvent\('iris:data-changed'/);
  assert.match(publicDashboard, /addEventListener\('iris:data-changed', \(\) => \{ void loadSummaryCards\(\); \}\)/);
});

test('Ranking History rows can be published and unpublished from their current state', () => {
  assert.match(rankingHistoryAdmin, /const isPublished = Number\(row\.is_published \?\? 1\) !== 0/);
  assert.match(rankingHistoryAdmin, /data-toggle-ranking="\$\{Number\(row\.id\)\}" data-published="\$\{isPublished \? '1' : '0'\}">\$\{isPublished \? 'Unpublish' : 'Publish'\}/);
  assert.match(rankingHistoryAdmin, /method: 'PATCH'[\s\S]*?body: JSON\.stringify\(\{ action: 'toggle-published' \}\)[\s\S]*?await refresh\(\)/);
  assert.match(adminRankingsApi, /if \(\$method === 'PATCH' && \(\$data\['action'\] \?\? ''\) === 'toggle-published'\)/);
  assert.match(adminRankingsApi, /UPDATE rankings SET is_published = CASE WHEN COALESCE\(is_published, 1\) = 1 THEN 0 ELSE 1 END WHERE ranking_id = \?/);
  assert.match(adminRankingsApi, /custom_field_definitions[\s\S]*?admin_rankings_custom_field_definitions\(\$pdo\)/);
  assert.match(adminRankingsApi, /admin_rankings_custom_field_values\(\$pdo, \$data, \$currentId\)/);
  assert.match(rankingHistoryAdmin, /renderCustomFields\(row\.custom_fields \|\| \{\}\)/);
  assert.match(rankingHistoryAdmin, /await refresh\(\);[\s\S]*?renderCustomFields\(\)/);
  assert.match(rankingHistoryAdmin, /custom_fields: Object\.fromEntries/);
  assert.match(adminEditor, /id="rankingHistoryCustomFields"/);
  assert.match(publicRankingsApi, /WHERE rb\.short_name <> 'DEMO' AND \{\$publishedColumn\} = 1/);
  assert.match(publicRankingsApi, /TABLE_NAME = \? AND COLUMN_NAME = \?/);
  assert.match(fs.readFileSync(path.join(__dirname, '..', '..', 'migrations', '20261003_ranking_history_published_flag.sql'), 'utf8'), /ADD COLUMN is_published TINYINT\(1\) NOT NULL DEFAULT 1/);
});

test('Summary Card and Ranking History profiles preview mappings and activate newly mapped workbooks for Office', () => {
  const importProfiles = fs.readFileSync(path.join(__dirname, '..', '..', 'includes', 'helpers', 'SummaryCardImportProfiles.php'), 'utf8');
  assert.match(templateManagerModal, /data-profile-context="summary_cards"/);
  assert.match(templateManagerModal, /data-profile-context="ranking_history"/);
  assert.match(templateManager, /function renderProfileContext\(destination, profile\)/);
  assert.match(templateManagerModal, /Advanced profile settings \(JSON\)/);
  assert.match(templateManagerModal, /data-summary-profile-json/);
  assert.match(templateManagerModal, /data-ranking-profile-json/);
  assert.match(templateManager, /function renderWorkbookPreview\(preview\)/);
  assert.match(templateManager, /max-h-72 overflow-auto/);
  assert.match(templateManager, /preview\.filename.*preview\.sheet/);
  assert.match(templateManager, /details\.open = false/);
  assert.match(templateManagerModal, /data-mapper-close-preview/);
  assert.match(profileWorkbookMapper, /data-mapper-close-preview/);
  assert.match(profileWorkbookService, /public static function savedWorkbookPreview/);
  assert.match(profileWorkbookService, /'rows' => \$rows/);
  assert.match(profileWorkbookService, /array_slice\(\$row\['values'\], 0, 30\)/);
  assert.match(templateApi, /'workbook_preview' => ProfileWorkbookService::savedWorkbookPreview\(\$pdo, \(int\)\$profileId, \$destination\)/);
  assert.match(templateApi, /'workbook_preview' => ProfileWorkbookService::savedWorkbookPreview\(\$pdo, \(int\)\$profileId, 'summary_cards'\)/);
  assert.match(templateManager, /data-profile-context-content/);
  assert.match(templateManager, /if \(workbookToken\) \{\s*await postProfileSettingsAction\('activate-import-profile', \{ destination: 'summary_cards'/);
  assert.match(templateManager, /if \(workbookToken\) \{\s*await postProfileSettingsAction\('activate-import-profile', \{ destination: 'ranking_history'/);
  assert.match(importProfiles, /SELECT state_data FROM app_change_state WHERE id = 1 FOR UPDATE/);
  assert.match(importProfiles, /\$state\['summary_card_import_profiles'\]\[\$destination\] = \['active_profile_id' => \$profileId\]/);
  assert.match(importProfiles, /ON DUPLICATE KEY UPDATE state_data = VALUES\(state_data\)/);
  assert.match(templateApi, /empty\(\$profile\['is_active'\]\) \|\| empty\(\$profile\['workbook_original_filename'\]\)/);
  assert.match(officeTemplates, /fetch\(`\$\{api\}\?resource=office_import_profiles`/);
});

test('Ranking History accepts long organization names and explanatory context', () => {
  const rankingContextMigration = fs.readFileSync(path.join(__dirname, '..', '..', 'migrations', '20261006_expand_ranking_context_text.sql'), 'utf8');
  assert.doesNotMatch(adminEditor, /id="rankingHistoryAdminBody"[^>]*maxlength=/i);
  assert.doesNotMatch(adminEditor, /id="rankingHistoryAdminInfoText"[^>]*maxlength=/i);
  assert.doesNotMatch(fs.readFileSync(path.join(__dirname, '..', '..', 'admin', 'includes', 'ranking_body_manager_modal.php'), 'utf8'), /name="body_name"[^>]*maxlength=/i);
  assert.match(adminRankingsApi, /\$organizationLength > 512/);
  assert.match(rankingHistoryImportApi, /\$organizationLength > 512/);
  assert.match(adminRankingsApi, /\$infoText\) > 16777215/);
  assert.match(rankingHistoryImportApi, /'info_text', 16777215/);
  assert.match(rankingContextMigration, /USE iris_db_3nf/);
  assert.match(rankingContextMigration, /ranking_bodies\s+MODIFY COLUMN name VARCHAR\(512\)/);
  assert.match(rankingContextMigration, /rankings\s+MODIFY COLUMN info_text MEDIUMTEXT/);
  assert.doesNotMatch(rankingContextMigration, /ranking_history_display_settings/);
});

test('Ranking History blocked imports identify the invalid field and mapped source column', () => {
  assert.match(rankingHistoryImportApi, /Organization is blank/);
  assert.match(rankingHistoryImportApi, /Source column:.*Column/);
  assert.match(rankingHistoryImportApi, /'blocked_field' =>/);
  assert.match(rankingHistoryImportApi, /'blocked_column' => \$organizationColumn/);
  assert.match(importPreviewModal, /Blocked field: \$\{item\.blocked_field\}/);
  assert.match(importPreviewModal, /source column \$\{item\.blocked_column\.header\} \(Column \$\{item\.blocked_column\.letter\}\)/);
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

test('Saved Graphs includes report visualizations from every file alongside saved charts', () => {
  assert.match(savedGraphsTab, /function getReportVisualizationGraphs\(records\)/);
  assert.match(savedGraphsTab, /records\.flatMap\(record =>/);
  assert.match(savedGraphsTab, /Array\.isArray\(record\.graphDrafts\)/);
  assert.match(savedGraphsTab, /const reportGraphs = getReportVisualizationGraphs\(records\)/);
  assert.match(savedGraphsTab, /const graphs = \[\.\.\.savedGraphs, \.\.\.reportGraphs\]/);
  assert.match(savedGraphsTab, /renderReportVisualizationCard/);
  assert.match(savedGraphsTab, /card\.dataset\.reportVisualization = 'true'/);
  assert.match(savedGraphsTab, /visibleGraphs\.filter\(graph => !graph\.is_report_draft\)/);
  assert.match(adminSavedGraphs, /Saved charts and report visualizations from every file/);
  assert.match(html, /Saved charts and report visualizations from every file/);
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

test('Summary Card and Ranking History search include imported fields and normalized multiword terms', () => {
  assert.match(adminEditor, /normalizeSearchText = value => String\(value \?\? ''\)\.normalize\('NFD'\)/);
  assert.match(adminEditor, /card\.custom_fields/);
  assert.match(adminEditor, /searchTerms\.every\(term => searchable\.includes\(term\)\)/);
  assert.match(rankingHistoryAdmin, /normalizeSearchText = value => String\(value \?\? ''\)\.normalize\('NFD'\)/);
  assert.match(rankingHistoryAdmin, /row\.ph_rank, row\.info_text, row\.custom_fields/);
  assert.match(rankingHistoryAdmin, /searchTerms\.every\(term => searchable\.includes\(term\)\)/);
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

test('Summary Card categories are selectable independently with checkboxes', () => {
  assert.match(adminEditor, /id="summaryCardEditorCategory" role="group"/);
  assert.match(adminEditor, /type="checkbox" value="\$\{escapeHtml\(category\.id\)\}"/);
  assert.match(adminEditor, /function selectedSummaryCategoryIds\(\)/);
  assert.match(adminEditor, /function setSelectedSummaryCategoryIds\(ids\)/);
  assert.match(adminEditor, /payload\.category_ids = selectedSummaryCategoryIds\(\)\.map\(Number\)/);
  assert.match(adminEditor, /setSelectedSummaryCategoryIds\(selectedIds\)/);
});

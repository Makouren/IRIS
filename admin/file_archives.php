<?php
$activeNav = 'archives';
$pageTitle = 'File Archives - IRIS Admin';
require_once __DIR__ . '/includes/header.php';
?>

<section aria-labelledby="fileArchivesTitle">
    <div class="card-panel" style="margin-bottom:1.5rem;background:var(--bg-card);border:1px solid var(--border-light);border-left:5px solid var(--clsu-green);border-radius:var(--radius-lg);padding:1.5rem 2rem;box-shadow:var(--card-shadow);">
        <h2 id="fileArchivesTitle" style="font-size:1.4rem;font-weight:800;color:var(--clsu-green);">File Archives</h2>
        <p style="font-size:.88rem;color:var(--text-muted);">Browse uploaded records, manage publication status, and inspect a record's File History.</p>
    </div>

    <div style="display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;align-items:end;">
        <input type="search" id="fileArchiveSearchInput" class="form-input" placeholder="Search archived files..." aria-label="Search file archives" style="flex:1;min-width:250px;">
        <label class="grid gap-1 text-xs font-bold text-gray-600 dark:text-slate-300">Purpose
            <select id="fileArchivePurposeFilter" class="form-input" style="width:auto;min-width:220px;">
                <option value="all">All purposes</option>
                <option value="analytics">Data &amp; Report Visualization</option>
                <option value="summary_cards">Summary Cards</option>
                <option value="ranking_history">Ranking History</option>
            </select>
        </label>
        <label class="grid gap-1 text-xs font-bold text-gray-600 dark:text-slate-300">Office
            <select id="fileArchiveOfficeFilter" class="form-input" style="width:auto;min-width:150px;"><option value="all">All offices</option></select>
        </label>
        <label class="grid gap-1 text-xs font-bold text-gray-600 dark:text-slate-300">Status
            <select id="fileArchiveStatusFilter" class="form-input" style="width:auto;min-width:150px;">
                <option value="all">All statuses</option>
                <option value="Pending Review">Pending Review</option>
                <option value="Approved">Published</option>
                <option value="Needs Revision">Needs Revision</option>
            </select>
        </label>
    </div>

    <div class="table-container" style="box-shadow:var(--card-shadow);">
        <div id="fileArchivesBulkActions" class="admin-bulk-actions" hidden>
            <span id="fileArchivesBulkSelectionCount">0 records selected</span>
            <button id="fileArchivesBulkPublish" type="button" class="archive-load-button" disabled><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>
            <button id="fileArchivesBulkUnpublish" type="button" class="export-cancel-button" disabled><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>
            <button id="fileArchivesBulkDelete" type="button" class="archive-delete-button" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i> Bulk Delete</button>
            <button id="fileArchivesClearSelection" type="button" class="export-cancel-button">Clear selection</button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr>
                    <th><input id="fileArchivesSelectAll" type="checkbox" aria-label="Select all visible archived files"></th>
                    <th>Record ID</th><th>File Name</th><th>Uploaded By</th><th>Format</th><th>Review Status</th><th>Scanned Date</th><th>Actions</th>
                </tr></thead>
                <tbody id="fileArchivesTableBody"><tr><td colspan="8">Loading file archives...</td></tr></tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

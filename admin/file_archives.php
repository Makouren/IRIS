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
        <div id="fileArchivesBulkActions" class="admin-bulk-actions summary-card-manager-actions" hidden style="display: flex; gap: 0.75rem; align-items: center; background: #f8fafc; padding: 0.75rem; border-bottom: 1px solid var(--border-light);">
            <span id="fileArchivesBulkSelectionCount" style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-right: auto;">0 records selected</span>
            <button id="fileArchivesClearSelection" type="button" class="export-cancel-button">Clear selection</button>
            <button id="fileArchivesBulkDelete" type="button" class="archive-delete-button" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
            <details class="summary-card-manager-actions-menu" style="position: relative;">
                <summary class="summary-card-manager-menu-toggle" style="cursor: pointer; padding: 0.4rem 0.75rem; border: 1px solid var(--border-light); border-radius: 0.375rem; background: #fff; font-size: 0.8rem; font-weight: 700;">
                    <i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Actions
                </summary>
                <div class="summary-card-manager-menu" aria-label="File archive actions" style="position: absolute; right: 0; top: 100%; margin-top: 0.25rem; background: #fff; border: 1px solid var(--border-light); border-radius: 0.375rem; padding: 0.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); z-index: 50; display: flex; flex-direction: column; min-width: 180px;">
                    <button id="fileArchivesBulkPublish" type="button" style="text-align: left; padding: 0.5rem; background: none; border: none; width: 100%; font-size: 0.8rem; cursor: pointer; border-radius: 0.25rem;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'" disabled><i class="fa-solid fa-circle-check" aria-hidden="true" style="margin-right: 0.4rem;"></i> Publish Selected</button>
                    <button id="fileArchivesBulkUnpublish" type="button" style="text-align: left; padding: 0.5rem; background: none; border: none; width: 100%; font-size: 0.8rem; cursor: pointer; border-radius: 0.25rem;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'" disabled><i class="fa-solid fa-circle-minus" aria-hidden="true" style="margin-right: 0.4rem;"></i> Unpublish Selected</button>
                </div>
            </details>
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

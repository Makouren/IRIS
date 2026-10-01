<?php
$activeNav = 'saved_graphs';
$pageTitle = 'Saved Dashboard Graphs - IRIS Admin';
require_once __DIR__.'/includes/header.php';
?>

<section id="adminSavedGraphsPanel" data-role="<?= e($_SESSION['role'] ?? '') ?>" class="admin-tab-panel">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--clsu-green);">Saved Dashboard Graphs</h2>
            <p style="font-size: 0.88rem; color: var(--text-muted);">Published chart versions grouped per file record.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <button id="savedGraphsViewAllBtn" type="button" class="saved-graphs-bulk-button">View All</button>
            <label style="font-size: 0.78rem; font-weight: 800; color: #334155; text-transform: uppercase;">File:</label>
            <select id="savedGraphsRecordSelect" class="form-input" style="width: auto; min-width: 220px;">
                <option value="">Loading files...</option>
            </select>
        </div>
    </div>

    <div id="savedGraphsBulkToolbar" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--bg-highlight); border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
        <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 700; font-size: 0.82rem; color: var(--text-main);">
            <input id="savedGraphsSelectAll" type="checkbox"> Select All
        </label>
        <span id="savedGraphsSelectionCount" style="font-size: 0.8rem; color: var(--text-muted);">0 selected</span>
        <button id="savedGraphsPrintAll" type="button" class="saved-graphs-bulk-button" disabled>Print All</button>
        <button id="savedGraphsExportSelected" type="button" class="saved-graphs-bulk-button" disabled>Export</button>
        <button id="savedGraphsPublishSelected" type="button" class="saved-graphs-bulk-button" disabled><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>
        <button id="savedGraphsDeleteSelected" type="button" class="archive-delete-button" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
    </div>

    <div id="savedDashboardGraphsContainer"></div>
</section>

<?php require_once __DIR__.'/includes/footer.php'; ?>

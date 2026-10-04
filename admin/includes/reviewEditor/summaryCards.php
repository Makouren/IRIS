<?php
/**
 * Purpose: Review Editor markup partial for the summary cards section; included by the Review Editor page.
 */

?>
<div id="summaryCardEditorPanel" class="modal-overlay" aria-hidden="true">
            <div class="modal-card summary-card-editor-modal" role="dialog" aria-modal="true" aria-labelledby="summaryCardEditorTitleHeading">
                <div class="modal-header">
                    <h3 id="summaryCardEditorTitleHeading" class="modal-title">Manage summary cards</h3>
                    <button type="button" id="closeSummaryCardEditor" class="export-cancel-button" aria-label="Close">&times;</button>
                </div>
                <div id="summaryCardManagerView">
                    <div style="display:flex; justify-content:space-between; align-items:end; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                        <label class="form-label" for="summaryCardSearch" style="margin:0;">Search cards
                            <input id="summaryCardSearch" type="search" class="form-input" placeholder="Search titles, values, categories, custom fields…" aria-label="Search Summary Cards by title, value, category, or custom field" autocomplete="off" style="display:inline-block; width:min(300px, 65vw); margin-left:0.35rem;">
                        </label>
                        <label class="form-label" style="margin:0;">Filter category
                            <select id="summaryCardCategoryFilter" class="form-input" style="display:inline-block; width:auto; min-width:150px; margin-left:0.35rem;">
                                <option value="all">All categories</option>
                                <option value="uncategorized">Uncategorized</option>
                            </select>
                        </label>
                        <div class="summary-card-manager-actions">
                            <button id="refreshSummaryCardsBtn" type="button" class="export-cancel-button" style="padding: 0.4rem 0.6rem;" title="Refresh data" aria-label="Refresh data">
                                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                            </button>
                            <label id="selectAllSummaryCardsLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                                <input type="checkbox" id="selectAllSummaryCards" style="width:1rem;height:1rem; cursor:pointer;"> Select all
                            </label>
                            <button id="bulkDeleteSummaryCards" type="button" class="export-cancel-button" style="display:none; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2;">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteSummaryCardsCount">0</span>)
                            </button>

                            <details class="summary-card-manager-actions-menu">
                                <summary class="summary-card-manager-menu-toggle">
                                    <i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Actions
                                </summary>
                                <div id="summaryCardManagerActionMenu" class="summary-card-manager-menu" aria-label="Summary card actions">
                                <button id="bulkPublishSummaryCards" type="button" style="display:none;"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish Selected (<span id="bulkPublishSummaryCardsCount">0</span>)</button>
                                <button id="publishAllSummaryCards" type="button" style="display:none;"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish All (<span id="publishAllSummaryCardsCount">0</span>)</button>
                                <button id="bulkUnpublishSummaryCards" type="button" style="display:none;"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i> Unpublish Selected (<span id="bulkUnpublishSummaryCardsCount">0</span>)</button>
                                <hr style="margin: 0.25rem 0; border: none; border-top: 1px solid var(--border-light);">
                                <button id="addSummaryCardFromManager" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add summary card</button>
                                <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                                    <button id="summaryCardManagerShowCategory" type="button"><i class="fa-solid fa-folder-plus" aria-hidden="true"></i> Add category</button>
                                <?php endif; ?>
                                </div>
                            </details>
                        </div>
                    </div>
                    <section id="summaryCardManagerCategoryAction" class="summary-card-manager-category-action" hidden aria-labelledby="summaryCardManagerCategoryHeading">
                        <label id="summaryCardManagerCategoryHeading" for="summaryCardManagerNewCategory">New category name</label>
                        <input id="summaryCardManagerNewCategory" type="text" class="form-input" maxlength="40" placeholder="Category name" aria-describedby="summaryCardManagerCategoryStatus">
                        <div class="summary-card-manager-category-buttons">
                            <button id="summaryCardManagerAddCategory" type="button" class="btn-save-modal">Confirm add</button>
                            <button id="summaryCardManagerCancelCategory" type="button" class="export-cancel-button">Cancel</button>
                        </div>
                    </section>
                    <span id="summaryCardManagerCategoryStatus" role="status" aria-live="polite" style="display:block; margin:-0.35rem 0 0.75rem; color:var(--text-muted); font-size:0.78rem;"></span>
                    <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                        <section class="summary-card-public-default" aria-labelledby="summaryCardPublicDefaultHeading">
                            <div>
                                <h4 id="summaryCardPublicDefaultHeading">Public summary-card default</h4>
                                <p>Choose which category visitors see first. They can still switch categories.</p>
                            </div>
                            <label for="summaryCardPublicDefaultCategory">Default category
                                <select id="summaryCardPublicDefaultCategory" class="form-input">
                                    <option value="">All categories</option>
                                </select>
                            </label>
                            <button id="saveSummaryCardPublicDefault" type="button" class="btn-save-modal">Save default</button>
                            <span id="summaryCardPublicDefaultStatus" role="status" aria-live="polite"></span>
                        </section>
                    <?php endif; ?>
                    <div id="summaryCardEditorList" style="display:grid; gap:0.75rem;"></div>
                    <section style="margin-top:1.25rem; border-top:1px solid var(--border-light); padding-top:1rem;">
                        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:0.6rem;">
                            <h4 style="font-size:0.9rem; font-weight:800; color:var(--text-main);">Manage categories</h4>
                        </div>
                        <div id="summaryCardCategoryList" style="display:grid; gap:0.5rem;"></div>
                    </section>
                </div>
                <form id="summaryCardEditorForm" style="display:none;">
                    <input type="hidden" id="summaryCardEditorId">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div><label class="form-label" for="summaryCardEditorImportKey">Global Label</label><input type="text" id="summaryCardEditorImportKey" class="form-input" maxlength="100" required></div>
                        <div><label class="form-label" for="summaryCardEditorTitle">Title</label><input type="text" id="summaryCardEditorTitle" class="form-input" maxlength="255" required></div>
                        <div><label class="form-label" for="summaryCardEditorMainValue">Main Value</label><input type="text" id="summaryCardEditorMainValue" class="form-input" maxlength="255" required></div>
                        <div><label class="form-label" for="summaryCardEditorMainLabel">Main Label</label><input type="text" id="summaryCardEditorMainLabel" class="form-input" maxlength="255" required></div>
                        <div><label class="form-label" for="summaryCardEditorYearDate">Year / Date</label><input type="text" id="summaryCardEditorYearDate" class="form-input" maxlength="255"></div>
                        <div><label class="form-label" for="summaryCardEditorSecondaryLabel">Secondary Label</label><input type="text" id="summaryCardEditorSecondaryLabel" class="form-input" maxlength="255"></div>
                        <div><label class="form-label" for="summaryCardEditorSecondaryValue">Secondary Value</label><input type="text" id="summaryCardEditorSecondaryValue" class="form-input" maxlength="255"></div>
                        <div style="grid-column: 1 / -1;"><label class="form-label" for="summaryCardEditorDescription">Description</label><textarea id="summaryCardEditorDescription" class="form-input" rows="2" maxlength="65535"></textarea></div>
                        <div style="grid-column: 1 / -1;"><label class="form-label" for="summaryCardEditorSecondaryDescription">Italic Supporting Text</label><textarea id="summaryCardEditorSecondaryDescription" class="form-input" rows="2" maxlength="65535"></textarea></div>
                        <div style="grid-column: 1 / -1;"><label class="form-label" for="summaryCardEditorInfoText">ⓘ Information</label><textarea id="summaryCardEditorInfoText" class="form-input" rows="2" maxlength="65535"></textarea></div>
                        <div><label class="form-label" for="summaryCardEditorDisplayOrder">Display Order</label><input type="number" id="summaryCardEditorDisplayOrder" class="form-input" value="0" min="0" step="1"></div>
                        <div><span class="form-label">Categories</span><div id="summaryCardEditorCategory" role="group" aria-describedby="summaryCardEditorCategoryHelp" style="display:grid; gap:0.35rem; max-height:7.5rem; overflow-y:auto; padding:0.5rem; border:1px solid var(--border-light); border-radius:var(--radius-sm);"></div><span id="summaryCardEditorCategoryHelp" class="text-xs" style="color:var(--text-muted);">Choose any categories; leave all unchecked for Uncategorized.</span><div style="display:flex; gap:0.5rem; margin-top:0.5rem;"><button type="button" id="summaryCardEditorAddCategory" class="btn-studio-action">+ New category</button><input type="text" id="summaryCardEditorNewCategory" class="form-input" maxlength="40" placeholder="Category name" style="display:none;"></div></div>
                        <div><label class="form-label" for="summaryCardEditorPrecision">Display Precision</label><select id="summaryCardEditorPrecision" class="form-input"><option value="0">No decimals</option><option value="1">1 decimal</option><option value="2" selected>2 decimals</option></select></div>
                        <label style="display:flex; align-items:center; gap:0.5rem; margin-top:0.6rem; font-size:0.8rem; font-weight:700; color: var(--text-muted);"><input type="checkbox" id="summaryCardEditorPublished"> Publish card</label>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                        <button type="button" id="cancelSummaryCardEditor" class="export-cancel-button">Cancel</button>
                        <button type="submit" class="btn-save-modal"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save summary card</button>
                    </div>
                </form>
            </div>
        </div>

<?php
$activeNav = 'review';
$pageTitle = 'Review Editor & Records Studio - IRIS Admin';
require_once __DIR__.'/includes/header.php';
?>

<section id="adminDatabaseView">
    <div style="background: var(--bg-card); border: 1px solid var(--border-light); border-left: 5px solid var(--clsu-green); border-radius: var(--radius-lg); padding: 1.5rem 2rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--card-shadow);">
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--clsu-green);">Review record editor</h2>
            <p style="font-size: 0.88rem; color: var(--text-muted);">Review extracted fields, edit tabular cells, update draft status, and publish visualizations for the CLSU Observatory.</p>
        </div>
    </div>

    <div class="card-panel" style="margin-bottom: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-light); border-left: 5px solid var(--clsu-gold-dark); border-radius: var(--radius-md); padding: 1.25rem 1.5rem; box-shadow: var(--card-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div>
                <div style="font-size: 0.75rem; font-weight: 800; color: var(--clsu-green); text-transform: uppercase; letter-spacing: 0.06em;">Latest Performance Snapshot</div>
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-top: 0.2rem;">Admin-managed summary cards</h3>
            </div>
            <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
                <button id="toggleSummaryCardEditor" type="button" class="btn-save-modal"><i class="fa-solid fa-list" aria-hidden="true"></i> Manage summary cards</button>
                <button id="toggleStarRatingEditor" type="button" class="btn-save-modal"><i class="fa-solid fa-star" aria-hidden="true"></i> Manage star rating cards</button>
                <button id="toggleRankingHistoryEditor" type="button" class="btn-save-modal"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> Manage Ranking History</button>
            </div>
        </div>
        <div id="summaryCardEditorPanel" class="modal-overlay" aria-hidden="true">
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="summaryCardEditorTitleHeading" style="max-width: 720px; max-height: calc(100vh - 2rem); overflow-y: auto;">
                <div class="modal-header">
                    <h3 id="summaryCardEditorTitleHeading" class="modal-title">Manage summary cards</h3>
                    <button type="button" id="closeSummaryCardEditor" class="export-cancel-button" aria-label="Close">&times;</button>
                </div>
                <div id="summaryCardManagerView">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                        <label class="form-label" style="margin:0;">Filter category
                            <select id="summaryCardCategoryFilter" class="form-input" style="display:inline-block; width:auto; min-width:150px; margin-left:0.35rem;">
                                <option value="all">All categories</option>
                                <option value="uncategorized">Uncategorized</option>
                            </select>
                        </label>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <label id="selectAllSummaryCardsLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                                <input type="checkbox" id="selectAllSummaryCards" style="width:1rem;height:1rem; cursor:pointer;"> Select all
                            </label>
                            <button id="bulkDeleteSummaryCards" type="button" class="export-cancel-button" style="display:none; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2;">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteSummaryCardsCount">0</span>)
                            </button>
                            <button id="addSummaryCardFromManager" type="button" class="btn-save-modal"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add summary card</button>
                        </div>
                    </div>
                    <div id="summaryCardEditorList" style="display:grid; gap:0.75rem;"></div>
                    <section style="margin-top:1.25rem; border-top:1px solid var(--border-light); padding-top:1rem;">
                        <h4 style="font-size:0.9rem; font-weight:800; color:var(--text-main); margin-bottom:0.6rem;">Manage categories</h4>
                        <div id="summaryCardCategoryList" style="display:grid; gap:0.5rem;"></div>
                    </section>
                </div>
                <form id="summaryCardEditorForm" style="display:none;">
                    <input type="hidden" id="summaryCardEditorId">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div><label class="form-label" for="summaryCardEditorTitle">Title</label><input type="text" id="summaryCardEditorTitle" class="form-input" required></div>
                        <div><label class="form-label" for="summaryCardEditorMainValue">Main Value</label><input type="text" id="summaryCardEditorMainValue" class="form-input" required></div>
                        <div><label class="form-label" for="summaryCardEditorMainLabel">Main Label</label><input type="text" id="summaryCardEditorMainLabel" class="form-input" required></div>
                        <div><label class="form-label" for="summaryCardEditorYearDate">Year / Date</label><input type="text" id="summaryCardEditorYearDate" class="form-input"></div>
                        <div><label class="form-label" for="summaryCardEditorSecondaryLabel">Secondary Label</label><input type="text" id="summaryCardEditorSecondaryLabel" class="form-input"></div>
                        <div><label class="form-label" for="summaryCardEditorSecondaryValue">Secondary Value</label><input type="text" id="summaryCardEditorSecondaryValue" class="form-input"></div>
                        <div style="grid-column: 1 / -1;"><label class="form-label" for="summaryCardEditorDescription">Description</label><textarea id="summaryCardEditorDescription" class="form-input" rows="2"></textarea></div>
                        <div><label class="form-label" for="summaryCardEditorDisplayOrder">Display Order</label><input type="number" id="summaryCardEditorDisplayOrder" class="form-input" value="0" min="0"></div>
                        <div><label class="form-label" for="summaryCardEditorCategory">Categories</label><select id="summaryCardEditorCategory" class="form-input" multiple size="3" aria-describedby="summaryCardEditorCategoryHelp"></select><span id="summaryCardEditorCategoryHelp" class="text-xs" style="color:var(--text-muted);">Select one or more; leave empty for Uncategorized.</span><div style="display:flex; gap:0.5rem; margin-top:0.5rem;"><button type="button" id="summaryCardEditorAddCategory" class="btn-studio-action">+ New category</button><input type="text" id="summaryCardEditorNewCategory" class="form-input" maxlength="40" placeholder="Category name" style="display:none;"></div></div>
                        <div><label class="form-label" for="summaryCardEditorPrecision">Display Precision</label><select id="summaryCardEditorPrecision" class="form-input"><option value="0">No decimals</option><option value="1">1 decimal</option><option value="2" selected>2 decimals</option></select></div>
                        <label style="display:flex; align-items:center; gap:0.5rem; margin-top:0.6rem; font-size:0.8rem; font-weight:700; color: var(--text-muted);"><input type="checkbox" id="summaryCardEditorPublished" checked> Publish card</label>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                        <button type="button" id="cancelSummaryCardEditor" class="export-cancel-button">Cancel</button>
                        <button type="submit" class="btn-save-modal"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save summary card</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="starRatingEditorPanel" class="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="starRatingEditorHeading" style="max-width: 860px; max-height: calc(100vh - 2rem); overflow-y: auto;">
            <div class="modal-header">
                <h3 id="starRatingEditorHeading" class="modal-title">Manage star rating cards</h3>
                <button type="button" id="closeStarRatingEditor" class="export-cancel-button" aria-label="Close">&times;</button>
            </div>
            <div id="starRatingManagerView">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.75rem;">
                    <label class="form-label" style="margin:0;">Filter category
                        <select id="starRatingCategoryFilter" class="form-input" style="display:inline-block; width:auto; min-width:150px; margin-left:0.35rem;">
                            <option value="all">All categories</option>
                            <option value="uncategorized">Uncategorized</option>
                        </select>
                    </label>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
        <label id="selectAllStarCardsLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                <input type="checkbox" id="selectAllStarCards" style="width:1rem;height:1rem; cursor:pointer;"> Select all
            </label>
            <button id="bulkDeleteStarCards" type="button" class="export-cancel-button" style="display:none; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2;">
            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteStarCardsCount">0</span>)
        </button>
        <button id="addStarRatingCard" type="button" class="btn-save-modal"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add star card</button>
    </div>
                </div>
                <div id="starRatingCardList" style="display:grid; gap:0.75rem;"></div>
                <section style="margin-top:1.25rem; border-top:1px solid var(--border-light); padding-top:1rem;">
                    <h4 style="font-size:0.9rem; font-weight:800; color:var(--text-main); margin-bottom:0.6rem;">Manage star rating categories</h4>
                    <div id="starRatingCategoryList" style="display:grid; gap:0.5rem;"></div>
                </section>
            </div>
            <form id="starRatingCardForm" style="display:none;" enctype="multipart/form-data">
                <input type="hidden" id="starRatingCardId" name="id">
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem;">
                    <label class="form-label">Title<input type="text" id="starRatingTitle" name="title" class="form-input" maxlength="255" required></label>
                    <div><label class="form-label" for="starRatingCategorySelect">Categories</label><select id="starRatingCategorySelect" class="form-input" multiple size="3" aria-describedby="starRatingCategoryHelp"></select><span id="starRatingCategoryHelp" class="text-xs" style="color:var(--text-muted);">Select one or more; leave empty for Uncategorized.</span><div style="display:flex; gap:0.5rem; margin-top:0.5rem;"><button type="button" id="starRatingAddCategory" class="btn-studio-action">+ New category</button><input type="text" id="starRatingNewCategory" class="form-input" maxlength="40" placeholder="Category name" style="display:none;"></div></div>
                    <label class="form-label">Year / Date<input type="text" id="starRatingYear" name="year" class="form-input" maxlength="10" placeholder="2026"></label>
                    <label class="form-label">Display Order<input type="number" id="starRatingDisplayOrder" name="display_order" class="form-input" value="0" step="1"></label>
                    <label class="form-label">Logo (PNG, JPG, WebP, SVG; max 1 MB)<input type="file" id="starRatingLogo" name="logo" class="form-input" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"></label>
                    <div id="starRatingCurrentLogo" style="display:none; grid-column:1/-1; align-items:center; gap:0.75rem;">
                        <img id="starRatingCurrentLogoImage" alt="Current star card logo" style="max-width:150px; max-height:72px; object-fit:contain;">
                        <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; color:var(--text-muted);"><input type="checkbox" id="starRatingRemoveLogo" name="remove_logo" value="1"> Remove current logo</label>
                    </div>
                    <label style="display:flex; align-items:center; gap:0.5rem; margin-top:0.6rem; font-size:0.8rem; font-weight:700; color:var(--text-muted);"><input type="checkbox" id="starRatingPublished" name="is_published" checked> Publish card</label>
                </div>
                <div style="margin-top:1rem;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; margin-bottom:0.5rem;">
                        <h4 style="font-size:0.95rem; font-weight:800; color:var(--text-main);">Rating rows</h4>
                        <button type="button" id="addStarRatingRow" class="btn-studio-action"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add row</button>
                    </div>
                    <div id="starRatingRows" style="display:grid; gap:0.5rem;"></div>
                </div>
                <div style="margin-top:1rem; border:1px solid var(--border-light); border-radius:var(--radius-md); padding:1rem; background:var(--bg-card);">
                    <div style="font-size:0.72rem; font-weight:800; color:var(--clsu-green); text-transform:uppercase; margin-bottom:0.75rem;">Live preview</div>
                    <div id="starRatingPreview"></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                    <button type="button" id="cancelStarRatingEditor" class="export-cancel-button">Cancel</button>
                    <button type="submit" class="btn-save-modal"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save star card</button>
                </div>
            </form>
        </div>
    </div>

    <div id="rankingHistoryEditorPanel" class="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="rankingHistoryEditorHeading" style="max-width: 960px; max-height: calc(100vh - 2rem); overflow-y: auto;">
            <div class="modal-header">
                <h3 id="rankingHistoryEditorHeading" class="modal-title">Manage Ranking History</h3>
                <button type="button" id="closeRankingHistoryEditor" class="export-cancel-button" aria-label="Close">&times;</button>
            </div>
            <div id="rankingHistoryManagerView">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.75rem;">
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                        <label class="form-label" style="margin:0; display:inline-flex; align-items:center; gap:0.25rem;">Year
                            <select id="rankingHistoryAdminYearFilter" class="form-input" style="display:inline-block; width:auto; min-width:110px; margin-left:0.25rem;"><option value="all">All years</option></select>
                        </label>
                        <label class="form-label" style="margin:0; display:inline-flex; align-items:center; gap:0.25rem;">Scope
                            <select id="rankingHistoryAdminScopeFilter" class="form-input" style="display:inline-block; width:auto; min-width:130px; margin-left:0.25rem;"><option value="all">All scopes</option><option value="unassigned">Unassigned</option></select>
                            <button type="button" id="openManageScopesModal" class="btn-studio-action" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Manage Scopes"><i class="fa-solid fa-gear" aria-hidden="true"></i> Scopes</button>
                        </label>
                        <label class="form-label" style="margin:0; display:inline-flex; align-items:center; gap:0.25rem;">Level
                            <select id="rankingHistoryAdminLevelFilter" class="form-input" style="display:inline-block; width:auto; min-width:120px; margin-left:0.25rem;"><option value="all">All levels</option><option value="unassigned">Unassigned</option></select>
                            <button type="button" id="openManageLevelsModal" class="btn-studio-action" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Manage Levels"><i class="fa-solid fa-gear" aria-hidden="true"></i> Levels</button>
                        </label>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <label id="selectAllRankingLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                            <input type="checkbox" id="selectAllRanking" style="width:1rem;height:1rem; cursor:pointer;"> Select all
                        </label>
                        <button id="bulkDeleteRankingHistory" type="button" class="export-cancel-button" style="display:none; align-items:center; gap:0.35rem; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2; font-weight:700; padding:0.3rem 0.65rem; border-radius:0.5rem;">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteCount">0</span>)
                        </button>
                        <button id="addRankingHistoryRow" type="button" class="btn-save-modal"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add ranking</button>
                    </div>
                </div>
                <div id="rankingHistoryAdminList" style="display:grid; gap:0.5rem;"></div>
            </div>
            <form id="rankingHistoryAdminForm" style="display:none;">
                <input type="hidden" id="rankingHistoryAdminId">
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:1rem;">
                    <label class="form-label">Organization<input type="text" id="rankingHistoryAdminBody" list="rankingBodySuggestions" class="form-input bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-lg" placeholder="Select or type organization..." required><datalist id="rankingBodySuggestions"></datalist></label>
                    <label class="form-label">Scope<input type="text" id="rankingHistoryAdminScope" list="rankingScopeSuggestions" class="form-input bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-lg" placeholder="Select or type scope (Unassigned)"><datalist id="rankingScopeSuggestions"></datalist></label>
                    <label class="form-label">Level<input type="text" id="rankingHistoryAdminLevel" list="rankingLevelSuggestions" class="form-input bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-lg" placeholder="Select or type level (Unassigned)"><datalist id="rankingLevelSuggestions"></datalist></label>
                    <label class="form-label">Ranking type<input type="text" id="rankingHistoryAdminType" list="rankingTypeSuggestions" class="form-input bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-lg" maxlength="100" placeholder="QS Asia"><datalist id="rankingTypeSuggestions"></datalist></label>
                    <label class="form-label">Year<input type="number" id="rankingHistoryAdminYear" class="form-input" min="1900" max="2200" step="1" required></label>
                    <label class="form-label">Edition<input type="text" id="rankingHistoryAdminEdition" class="form-input" maxlength="80" placeholder="Annual, July, March"></label>
                    <label class="form-label">Category<input type="text" id="rankingHistoryAdminCategory" class="form-input" maxlength="100" placeholder="Overall"></label>
                    <label class="form-label">Rank display<input type="text" id="rankingHistoryAdminGlobalRank" class="form-input" maxlength="50" placeholder="161, 601-650, or 601+"><span id="rankTextNote" class="text-xs text-amber-600 dark:text-amber-400 mt-1 block font-medium" style="display:none;">Saved as text; not plotted on charts.</span></label>
                    <label class="form-label">Rank lower bound<input type="number" id="rankingHistoryAdminRankLow" class="form-input" min="0" step="1"></label>
                    <label class="form-label">Rank upper bound<input type="number" id="rankingHistoryAdminRankHigh" class="form-input" min="0" step="1" placeholder="Leave blank for 601+"></label>
                    <label class="form-label">Philippine Rank<input type="text" id="rankingHistoryAdminPhRank" class="form-input" maxlength="50"></label>
                    <label class="form-label" style="grid-column:1/-1;">Note<input type="text" id="rankingHistoryAdminNote" class="form-input" maxlength="255"></label>
                    <label class="form-label" style="grid-column:1/-1;">Source<input type="text" id="rankingHistoryAdminSource" class="form-input" maxlength="500" placeholder="Source name or URL"></label>
                    <label class="form-label">Verification status<select id="rankingHistoryAdminStatus" class="form-input"><option value="unverified">Unverified</option><option value="verified">Verified</option><option value="inferred">Inferred</option><option value="conflicting">Conflicting</option><option value="assumed">Assumed</option></select></label>
                </div>
                <p class="text-xs" style="color:var(--text-muted); margin-top:0.5rem;">Numeric chart values are derived from the rank text. The Change column updates automatically from the previous year.</p>
                <div id="formDuplicateError" class="p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg text-sm text-amber-800 dark:text-amber-200 font-medium mt-3" style="display:none;">This ranking already exists. <button type="button" id="editDuplicateLink" class="underline font-bold hover:text-amber-900 dark:hover:text-amber-100">Edit it instead?</button></div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                    <button type="button" id="cancelRankingHistoryAdminForm" class="export-cancel-button">Cancel</button>
                    <button type="submit" class="btn-save-modal"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save ranking</button>
                </div>
            </form>
        </div>
    </div>

    <div id="manageScopesModalPanel" class="modal-overlay" aria-hidden="true" style="z-index: 1050;">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="manageScopesModalHeading" style="max-width: 600px; width: 100%;">
            <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding-bottom:0.75rem; border-bottom:1px solid var(--border-light);">
                <h3 id="manageScopesModalHeading" class="modal-title" style="font-size:1.1rem; font-weight:800; color:var(--text-main); margin:0;">Manage Ranking Scopes</h3>
                <button type="button" id="closeManageScopesModal" class="export-cancel-button" aria-label="Close">&times;</button>
            </div>
            <div style="padding-top: 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <label id="selectAllScopesLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                            <input type="checkbox" id="selectAllScopes" style="width:1rem;height:1rem; cursor:pointer;"> Select all
                        </label>
                        <button id="bulkDeleteScopes" type="button" class="export-cancel-button" style="display:none; align-items:center; gap:0.35rem; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2; font-weight:700; padding:0.3rem 0.65rem; border-radius:0.5rem;">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteScopesCount">0</span>)
                        </button>
                    </div>
                </div>
                <div id="rankingScopeAdminList" style="display:grid; gap:0.5rem; max-height:350px; overflow-y:auto; padding-right:0.25rem;"></div>
                <div style="display:flex; gap:0.5rem; margin-top:1rem; padding-top:0.75rem; border-top:1px solid var(--border-light);">
                    <input type="text" id="rankingScopeNewName" class="form-input" maxlength="80" placeholder="New scope name (e.g. SDG 3, Regional)">
                    <button type="button" id="addRankingScope" class="btn-studio-action" style="white-space:nowrap;"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add scope</button>
                </div>
            </div>
        </div>
    </div>

    <div id="manageLevelsModalPanel" class="modal-overlay" aria-hidden="true" style="z-index: 1050;">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="manageLevelsModalHeading" style="max-width: 600px; width: 100%;">
            <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding-bottom:0.75rem; border-bottom:1px solid var(--border-light);">
                <h3 id="manageLevelsModalHeading" class="modal-title" style="font-size:1.1rem; font-weight:800; color:var(--text-main); margin:0;">Manage Ranking Levels</h3>
                <button type="button" id="closeManageLevelsModal" class="export-cancel-button" aria-label="Close">&times;</button>
            </div>
            <div style="padding-top: 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <label id="selectAllLevelsLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                            <input type="checkbox" id="selectAllLevels" style="width:1rem;height:1rem; cursor:pointer;"> Select all
                        </label>
                        <button id="bulkDeleteLevels" type="button" class="export-cancel-button" style="display:none; align-items:center; gap:0.35rem; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2; font-weight:700; padding:0.3rem 0.65rem; border-radius:0.5rem;">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteLevelsCount">0</span>)
                        </button>
                    </div>
                </div>
                <div id="rankingLevelAdminList" style="display:grid; gap:0.5rem; max-height:350px; overflow-y:auto; padding-right:0.25rem;"></div>
                <div style="display:flex; gap:0.5rem; margin-top:1rem; padding-top:0.75rem; border-top:1px solid var(--border-light);">
                    <input type="text" id="rankingLevelNewName" class="form-input" maxlength="80" placeholder="New level name (e.g. Local, ASEAN, Asia, World)">
                    <button type="button" id="addRankingLevel" class="btn-studio-action" style="white-space:nowrap;"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add level</button>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">TOTAL SCANNED FILES</div>
            <div id="statTotalDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-green);">0</div>
        </div>
        <div style="background: var(--bg-card); border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">PENDING DRAFTS</div>
            <div id="statPendingDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-gold-dark);">0</div>
        </div>
        <div style="background: var(--bg-card); border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">PUBLISHED</div>
            <div id="statVerifiedDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-green-light);">0</div>
        </div>
        <div style="background: var(--bg-card); border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">EXTRACTED TABLES</div>
            <div id="statTablesDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-green);">0</div>
        </div>
    </div>

    <div class="studio-container" id="studioContainer" style="margin-bottom: 2rem;">
        <div class="studio-header-card">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.4rem;"><i class="fa-solid fa-palette" aria-hidden="true"></i></span>
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: var(--clsu-green); text-transform: uppercase; letter-spacing: 0.05em;">ACTIVE DASHBOARD STUDIO WORKBENCH</div>
                        <div style="font-size: 1.2rem; font-weight: 800; color: #0F172A;" id="studioActiveFileName">Loading Scanned Dataset...</div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <label style="font-size: 0.82rem; font-weight: 800; color: #334155; text-transform: uppercase;">Switch Dataset:</label>
                    <select id="studioRecordSelect" class="form-input" style="width: auto; min-width: 250px; font-weight: 700; color: #0F172A;"></select>
                    <button id="createManualDataset" type="button" class="btn-studio-action"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create Data Manually</button>
                </div>
            </div>
        </div>

        <div class="studio-grid">
            <div class="studio-left-card">
                <div class="studio-card-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <span><i class="fa-solid fa-window-maximize" aria-hidden="true"></i> Scanned Source Document Window</span>
                    <span class="badge badge-low" style="font-size: 0.68rem; background: #ECFDF5; color: #047857;">Live Ingestion View</span>
                </div>
                <p style="font-size: 0.78rem; color: #64748B; margin-bottom: 0.75rem;">Read the full file directly side-by-side. Click any cell or word to copy value directly into your dashboard fields.</p>

                <div class="doc-viewer-container">
                    <div class="acrobat-toolbar">
                        <div class="acrobat-title-group">
                            <span class="acrobat-badge-icon" id="acrobatDocBadge">PDF</span>
                            <span class="acrobat-filename" id="docWindowTitle">document.docx</span>
                        </div>

                        <div class="acrobat-controls-center" id="acrobatPageNavControls">
                            <button type="button" id="btnAcrobatPrevPage" class="acrobat-tool-btn" title="Previous Page">▲</button>
                            <input type="number" id="acrobatCurrentPageInput" class="acrobat-page-input" value="1" min="1" max="1" title="Go to Page">
                            <span style="font-size: 0.72rem; color: var(--text-dim);">/</span>
                            <span id="acrobatTotalPagesSpan" style="font-size: 0.72rem; color: var(--text-main); font-weight: 600;">1</span>
                            <button type="button" id="btnAcrobatNextPage" class="acrobat-tool-btn" title="Next Page">▼</button>
                        </div>

                        <div class="acrobat-controls-right">
                            <div id="studioDocSheetSelectorContainer" style="display: none; align-items: center; gap: 0.35rem;">
                                <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600;">Sheet:</span>
                                <select id="studioDocSheetSelect" class="form-input doc-sheet-select" style="background: var(--bg-input) !important; color: var(--text-main) !important; border-color: var(--border-light) !important;"></select>
                            </div>

                            <div id="acrobatZoomControlsGroup" style="display: flex; align-items: center; gap: 0.25rem; background: var(--bg-highlight); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--border-light);">
                                <button type="button" id="btnAcrobatZoomOut" class="acrobat-tool-btn" title="Zoom Out">−</button>
                                <span id="acrobatZoomValue" class="acrobat-zoom-label">100%</span>
                                <button type="button" id="btnAcrobatZoomIn" class="acrobat-tool-btn" title="Zoom In">+</button>
                                <button type="button" id="btnAcrobatFitWidth" class="acrobat-tool-btn" title="Fit Width" style="font-size: 0.68rem; margin-left: 2px;">↔</button>
                            </div>

                            <span id="docWindowPageCount" style="display: none;"></span>
                            <span id="docWindowWordCount" style="display: none;"></span>
                        </div>
                    </div>

                    <div id="studioDocContentArea" class="acrobat-viewer-body">
                        <div class="acrobat-page-card">
                            <p style="color: #64748B; text-align: center;">Loading document content...</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 0.65rem; font-size: 0.74rem; color: #64748B; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> <strong>Tip:</strong> Highlight or click any text to copy directly.</span>
                    <span id="docWindowCopyStatus" style="color: var(--clsu-green); font-weight: 700;"></span>
                </div>
            </div>

            <div class="studio-right-card">
                <div class="studio-chart-box">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; gap: 1rem; flex-wrap: nowrap;">
                        <div style="flex: 1 1 auto; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; flex-wrap: wrap; min-width: 0;">
                                <span style="font-size: 0.82rem; font-weight: 800; color: var(--clsu-green); text-transform: uppercase; white-space: nowrap;">Chart Title:</span>
                                <input type="text" id="studioChartTitleInput" class="form-input" value="Observatory Draft" placeholder="Type chart title..." style="padding: 0.3rem 0.65rem; font-size: 0.95rem; font-weight: 800; color: var(--clsu-green); border: 1.5px solid var(--border-light); background: var(--bg-input); flex: 1 1 auto; min-width: 180px;" title="Click to edit the chart title">
                            </div>
                            <p id="studioChartSubtitleDisplay" style="font-size: 0.78rem; color: var(--text-muted);">Live interactive rendering from data fields below</p>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-left: auto; flex-shrink: 0;">
                            <label style="font-size: 0.78rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; white-space: nowrap;">Chart Type:</label>
                            <select id="studioChartTypeSelect" class="form-input" style="width: auto; min-width: 150px; padding: 0.35rem 0.75rem; font-size: 0.82rem; font-weight: 700; color: var(--text-main);">
                                <option value="bar">Bar Chart</option>
                                <option value="line">Line Chart</option>
                                <option value="pie">Pie Chart</option>
                                <option value="doughnut">Doughnut Chart</option>
                                <option value="rankedBar">Ranked Bar Chart</option>
                                <option value="nestedPie">Nested Pie</option>
                            </select>
                        </div>
                    </div>

                    <div id="studioFieldMappingRow" style="background: rgba(59,130,246,0.08); border: 1px solid rgba(147,197,253,0.45); border-radius: var(--radius-sm); padding: 0.65rem 1rem; margin-bottom: 0.75rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem;">
                        <span style="font-size: 0.78rem; font-weight: 800; color: var(--text-brand); text-transform: uppercase;"><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Field Mapping:</span>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;" id="studioCategoryLabel">Category (X-axis):</label>
                            <select id="studioCategoryCol" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Category column"></select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;" id="studioValueLabel">Value (Y-axis):</label>
                            <select id="studioValueCol" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Value column"></select>
                        </div>
                        <div id="studioGroupFieldWrapper" style="display: none; align-items: center; gap: 0.35rem;">
                            <label for="studioGroupField" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Group (inner ring):</label>
                            <select id="studioGroupField" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;"></select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;" id="studioValuePrecisionLabel">Display Precision:</label>
                            <select id="studioValuePrecisionSelect" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Display precision">
                                <option value="0">No decimals</option>
                                <option value="1">1 decimal</option>
                                <option value="2" selected>2 decimals</option>
                            </select>
                        </div>
                        <div id="studioRankedYearWrapper" style="display: none; align-items: center; gap: 0.35rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;" id="studioRankedYearLabel">Year:</label>
                            <select id="studioRankedYearSelect" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Year for Ranked Bar Chart"></select>
                        </div>
                        <div id="studioRankedReverseOrderWrapper" style="display: none; align-items: center; gap: 0.35rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;" id="studioRankedReverseOrderLabel">
                                <input id="studioRankedReverseOrder" type="checkbox" style="accent-color: var(--clsu-green); margin-right: 0.25rem;" aria-label="Reverse ranked bar display order">Reverse order
                            </label>
                        </div>
                        <div id="studioFieldWarning" style="display:none; font-size: 0.75rem; color: #DC2626; font-weight: 700; background: rgba(254,242,242,0.9); border: 1px solid #FECACA; border-radius: 4px; padding: 0.2rem 0.6rem;"></div>
                    </div>

                        <div id="studioColorCustomizer" class="studio-color-customizer" hidden>
                            <div class="studio-color-heading">
                                <strong>Chart colors</strong>
                                <button id="studioColorReset" type="button" class="export-cancel-button">Reset to default</button>
                            </div>
                            <div class="studio-color-heading" style="margin-top:.65rem;">
                                <label style="display:flex;align-items:center;gap:.45rem;font-size:.78rem;font-weight:700;color:var(--text-muted);"><input id="studioColorApplyAll" type="checkbox" checked> Apply to all charts</label>
                                <button id="studioManageFieldColors" type="button" class="export-cancel-button">Field colors</button>
                            </div>
                            <div id="studioColorSwatches" class="studio-color-swatches" aria-label="Chart series and category colors"></div>
                            <div id="studioColorPickerPanel" class="studio-color-picker-panel" hidden>
                                <hex-color-picker id="studioColorPicker" color="#1E6031" aria-label="Choose chart color"></hex-color-picker>
                                <div class="studio-color-values">
                                    <label>HEX<input id="studioColorHex" class="form-input" type="text" value="#1E6031" maxlength="7" spellcheck="false" aria-label="Hex color"></label>
                                    <label>R<input id="studioColorR" class="form-input" type="number" min="0" max="255" value="30" aria-label="Red channel"></label>
                                    <label>G<input id="studioColorG" class="form-input" type="number" min="0" max="255" value="96" aria-label="Green channel"></label>
                                    <label>B<input id="studioColorB" class="form-input" type="number" min="0" max="255" value="49" aria-label="Blue channel"></label>
                                </div>
                                <div class="studio-color-preset-row">
                                    <span>CLSU palette</span>
                                    <div id="studioColorPresets" class="studio-color-presets"></div>
                                </div>
                                <button id="studioColorSaveField" type="button" class="btn-save-modal" hidden>Save field color</button>
                            </div>
                        </div>
                        <div id="studioFieldColorsModal" class="modal-overlay" aria-hidden="true">
                            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="studioFieldColorsTitle" style="max-width:560px;max-height:calc(100vh - 2rem);overflow-y:auto;">
                                <div class="modal-header">
                                    <h3 id="studioFieldColorsTitle" class="modal-title">Field colors</h3>
                                    <button id="studioCloseFieldColors" type="button" class="export-cancel-button" aria-label="Close">&times;</button>
                                </div>
                                <div id="studioFieldColorsList" style="display:grid;gap:.5rem;"></div>
                            </div>
                        </div>

                    <div style="background: var(--bg-highlight); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 0.65rem 1rem; margin-bottom: 0.85rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem;">
                        <span style="font-size: 0.78rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Filter extracted rows:</span>
                        <select id="studioFilterField" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Filter data scope">
                            <option value="all">All selected data</option>
                            <option value="context">Context / label only</option>
                            <option value="value">Metric / value only</option>
                        </select>
                        <select id="studioFilterOperator" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Filter operator">
                            <option value="all">All rows</option>
                            <option value="contains">Contains</option>
                            <option value="starts-with">Starts with</option>
                            <option value="ends-with">Ends with</option>
                            <option value="equals">Equals</option>
                            <option value="not-equals">Does not equal</option>
                            <option value="greater-than">Value greater than</option>
                            <option value="less-than">Value less than</option>
                            <option value="between">Value between</option>
                        </select>
                        <input id="studioFilterValue" class="form-input" type="search" placeholder="Broad search across selected data..." style="min-width: 190px; flex: 1; padding: 0.3rem 0.65rem; font-size: 0.78rem;" aria-label="Filter value">
                        <input id="studioFilterUpperValue" class="form-input" type="number" placeholder="Maximum" style="display: none; width: 6.5rem; padding: 0.3rem 0.65rem; font-size: 0.78rem;" aria-label="Filter maximum value">
                        <select id="studioSortOrder" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Sort chart rows">
                            <option value="source">Source order</option>
                            <option value="value-asc">Metric: low to high</option>
                            <option value="value-desc">Metric: high to low</option>
                            <option value="label-asc">Label: A to Z</option>
                            <option value="label-desc">Label: Z to A</option>
                        </select>
                        <label style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; white-space: nowrap;">Show <input id="studioRowLimit" class="form-input" type="number" min="1" max="100" value="30" style="width: 4.5rem; display: inline-block; padding: 0.3rem 0.45rem; font-size: 0.78rem;"> rows</label>
                        <label style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; white-space: nowrap;"><input id="studioGroupDuplicates" type="checkbox" checked style="accent-color: var(--clsu-green); margin-right: 0.25rem;"> Group duplicate labels</label>
                    </div>

                    <div style="height: 320px; position: relative; width: 100%; margin-bottom: 0.75rem;">
                        <div id="studioChartCanvas" style="height: 100%; width: 100%;"></div>
                        <div id="studioChartEmptyState" style="display:none; position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background: rgba(15,23,42,0.08); border-radius:var(--radius-sm); border:2px dashed var(--border-light);">
                            <span style="font-size:2rem;"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span>
                            <p id="studioChartEmptyMsg" style="font-size:0.88rem; color: var(--text-muted); font-weight:600; margin-top:0.5rem; text-align:center; max-width:280px;">Select a Category field and a numeric Value field above to render the chart.</p>
                        </div>
                    </div>
                </div>

                <div class="studio-data-manager">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Editable Data Grid & Custom Fields</h4>
                            <p style="font-size: 0.78rem; color: var(--text-muted);">Edit cell values directly, add new columns/metrics, or paste copied values.</p>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <button id="studioBtnAddField" type="button" class="btn-studio-action" style="background: var(--bg-highlight); border: 1.5px solid rgba(59,130,246,0.5); color: var(--text-main);">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Field / Column
                            </button>
                            <button id="studioBtnAddRow" type="button" class="btn-studio-action" style="background: rgba(16,185,129,0.12); border: 1.5px solid rgba(16,185,129,0.7); color: var(--text-main);">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Row
                            </button>
                        </div>
                    </div>

                    <div id="studioTableContainer" class="table-container" style="max-height: 280px; margin-bottom: 1.25rem;"></div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; background: var(--bg-highlight); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);">
                        <div>
                            <label class="form-label" style="font-weight: 800; font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Classification Category</label>
                            <input type="text" id="studioDocTypeInput" class="form-input" style="font-weight: 600; color: var(--text-main);">
                        </div>
                        <div>
                            <label class="form-label" style="font-weight: 800; font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Publication Status</label>
                            <select id="studioStatusSelect" class="form-input" style="font-weight: 600; color: var(--text-main);">
                                <option value="Pending Review">Pending Review</option>
                                <option value="Approved">Published</option>
                                <option value="Needs Revision">Needs Revision</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" style="font-weight: 800; font-size: 0.78rem; color: #334155; text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Admin Verification Notes</label>
                            <textarea id="studioNotesInput" class="form-input" rows="2" placeholder="Add verification logs and approval notes..." style="font-weight: 500; color: #0F172A; line-height: 1.5;"></textarea>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.85rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                        <button id="studioBtnSave" type="button" class="btn-save-modal">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Dashboard Changes
                        </button>
                        <button id="studioBtnApprove" type="button" class="btn-approve-modal">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="manualDatasetModal" class="modal-overlay" aria-hidden="true">
        <form id="manualDatasetForm" class="modal-card" role="dialog" aria-modal="true" aria-labelledby="manualDatasetTitle" style="max-width: 520px;">
            <div class="modal-header">
                <h3 id="manualDatasetTitle" class="modal-title">Create Data Manually</h3>
                <button id="cancelManualDataset" type="button" class="export-cancel-button" aria-label="Close">&times;</button>
            </div>
            <label for="manualDatasetFileName" class="form-label">File Name</label>
            <input id="manualDatasetFileName" name="fileName" type="text" class="form-input" maxlength="255" required autocomplete="off">
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem;">
                <button id="cancelManualDatasetFooter" type="button" class="export-cancel-button">Cancel</button>
                <button id="submitManualDataset" type="submit" class="btn-save-modal">Create Dataset</button>
            </div>
        </form>
    </div>

    <div class="clsu-section-title" style="margin-top: 2rem;">
        <span><i class="fa-solid fa-folder" aria-hidden="true"></i></span> Scanned Records Archive & Ingestion Logs
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <input type="text" id="adminSearchInput" class="form-input" placeholder="Search records by filename, category, or values..." style="flex: 1; min-width: 250px;">
        <select id="adminOfficeFilter" class="form-input" style="width: auto; min-width: 180px;"><option value="all">All offices</option></select>
        <select id="adminStatusFilter" class="form-input" style="width: auto;">
            <option value="all">All Statuses</option>
            <option value="Pending Review">Pending Review</option>
            <option value="Approved">Published</option>
            <option value="Needs Revision">Needs Revision</option>
        </select>
    </div>

    <div class="table-container" style="box-shadow: var(--card-shadow);">
        <div id="adminBulkActions" class="admin-bulk-actions" hidden>
            <span id="adminBulkSelectionCount">0 records selected</span>
            <button id="adminBulkPublish" type="button" class="archive-load-button" disabled><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>
            <button id="adminBulkUnpublish" type="button" class="export-cancel-button" disabled><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>
            <button id="adminBulkDelete" type="button" class="archive-delete-button" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i> Bulk Delete</button>
            <button id="adminClearSelection" type="button" class="export-cancel-button">Clear selection</button>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th><input id="adminSelectAll" type="checkbox" aria-label="Select all visible records"></th>
                    <th>Record ID</th>
                    <th>File Name</th>
                    <th>Uploaded By</th>
                    <th>Format</th>
                    <th>Review Status</th>
                    <th>Scanned Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="adminRecordsTableBody"></tbody>
        </table>
    </div>

    <div id="recordEditModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="recordEditTitle" class="modal-title">Edit Record Data</h3>
                <button id="btnCloseRecordModal" style="background: none; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer;">&times;</button>
            </div>
            <div id="recordEditBody" style="max-height: 75vh; overflow-y: auto; padding-right: 0.5rem;"></div>
        </div>
    </div>
</section>

    <script>
        (function () {
            const summaryCardApi = '<?= e(base_url('api/iris.php')) ?>?resource=summary_cards';
            const categoryApi = '<?= e(base_url('api/iris.php')) ?>?resource=summary_card_categories';
            const csrfToken = <?= json_encode(csrf_token()) ?>;
            const editorPanel = document.getElementById('summaryCardEditorPanel');
            const editorList = document.getElementById('summaryCardEditorList');
            const categoryList = document.getElementById('summaryCardCategoryList');
            const form = document.getElementById('summaryCardEditorForm');
            const managerView = document.getElementById('summaryCardManagerView');
            const editorHeading = document.getElementById('summaryCardEditorTitleHeading');
            const categorySelect = document.getElementById('summaryCardEditorCategory');
            const newCategoryInput = document.getElementById('summaryCardEditorNewCategory');
            const categoryFilter = document.getElementById('summaryCardCategoryFilter');
            let cards = [];
            let categories = [];
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            const mutationHeaders = () => ({ 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken });
            const closeEditor = () => {
                editorPanel.classList.remove('active');
                editorPanel.setAttribute('aria-hidden', 'true');
            };
            const openEditor = () => {
                editorPanel.classList.add('active');
                editorPanel.setAttribute('aria-hidden', 'false');
                const focusTarget = managerView.style.display === 'none'
                    ? document.getElementById('summaryCardEditorTitle')
                    : document.getElementById('addSummaryCardFromManager');
                focusTarget.focus();
            };
            const showCardForm = isEdit => {
                managerView.style.display = 'none';
                form.style.display = 'block';
                editorHeading.textContent = isEdit ? 'Edit summary card' : 'Add summary card';
                document.getElementById('summaryCardEditorTitle').focus();
            };
            const showCardList = () => {
                form.style.display = 'none';
                managerView.style.display = 'block';
                editorHeading.textContent = 'Manage summary cards';
            };

            function renderCategoryOptions() {
                const selected = [...categorySelect.selectedOptions].map(option => option.value);
                categorySelect.innerHTML = categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                [...categorySelect.options].forEach(option => { option.selected = selected.includes(option.value); });
                const filterValue = categoryFilter.value || 'all';
                categoryFilter.innerHTML = '<option value="all">All categories</option><option value="uncategorized">Uncategorized</option>' + categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                if ([...categoryFilter.options].some(option => option.value === filterValue)) categoryFilter.value = filterValue;
            }

            function renderCategoryList() {
                if (!categories.length) {
                    categoryList.innerHTML = '<div class="text-xs" style="color:var(--text-muted);">No categories yet. Create one from the card form.</div>';
                    return;
                }
                categoryList.innerHTML = categories.map(category => `
                    <div class="summary-card-category-row" data-id="${escapeHtml(category.id)}" style="display:grid; grid-template-columns:minmax(120px,1fr) 90px auto auto; align-items:center; gap:0.5rem;">
                        <input class="form-input" data-category-name value="${escapeHtml(category.name)}" maxlength="40" aria-label="Category name">
                        <input class="form-input" data-category-order type="number" value="${escapeHtml(category.sort_order)}" aria-label="Category sort order">
                        <button type="button" class="btn-studio-action summary-category-save">Save</button>
                        <button type="button" class="archive-delete-button summary-category-delete">Delete</button>
                    </div>`).join('');
                categoryList.querySelectorAll('.summary-category-save').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.summary-card-category-row');
                    const response = await fetch(categoryApi + '&id=' + encodeURIComponent(row.dataset.id), {
                        method: 'PUT', headers: mutationHeaders(),
                        body: JSON.stringify({ name: row.querySelector('[data-category-name]').value.trim(), sort_order: Number(row.querySelector('[data-category-order]').value || 0) })
                    });
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        alert(error.error || 'Unable to update category.');
                        return;
                    }
                    await refreshSummaryCardEditor();
                }));
                categoryList.querySelectorAll('.summary-category-delete').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.summary-card-category-row');
                    const category = categories.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${category?.name || 'this category'}”? Its cards will move to Uncategorized.`)) return;
                    const response = await fetch(categoryApi + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken } });
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        alert(error.error || 'Unable to delete category.');
                        return;
                    }
                    await refreshSummaryCardEditor();
                }));
            }

            function renderCards() {
                const selectedCategory = categoryFilter.value || 'all';
                const visibleCards = cards.filter(card => selectedCategory === 'all'
                    || (selectedCategory === 'uncategorized'
                        ? !(card.category_ids || (card.category_id ? [card.category_id] : [])).length
                        : (card.category_ids || (card.category_id ? [card.category_id] : [])).some(categoryId => String(categoryId) === selectedCategory)));
                if (!visibleCards.length) {
                    editorList.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 text-center">No summary cards in this category.</div>';
                    return;
                }
                editorList.innerHTML = visibleCards
                    .slice()
                    .sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0))
                    .map(card => `
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" class="bulk-delete-summary-checkbox mt-1 cursor-pointer" data-id="${escapeHtml(card.id)}" aria-label="Select for bulk delete" style="width:1rem;height:1rem;">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="font-bold text-sm text-slate-900">${escapeHtml(card.title || 'Summary Card')}</div>
                                            <div class="text-xs text-slate-500">${escapeHtml(card.main_value || '')} · ${escapeHtml(card.main_label || '')}</div>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 px-2 py-0.5 mt-1 text-[10px] font-semibold">${escapeHtml(card.category_name || 'Uncategorized')}</span>
                                        </div>
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${card.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}">${card.is_published ? 'Published' : 'Draft'}</span>
                                    </div>
                                    <div class="flex gap-2 mt-3">
                                        <button type="button" class="summary-card-editor-edit btn-studio-action" data-id="${escapeHtml(card.id)}">Edit</button>
                                        <button type="button" class="summary-card-editor-toggle btn-studio-action" data-id="${escapeHtml(card.id)}" data-published="${card.is_published ? '1' : '0'}">${card.is_published ? 'Unpublish' : 'Publish'}</button>
                                        <button type="button" class="summary-card-editor-delete archive-delete-button" data-id="${escapeHtml(card.id)}">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');

                                                // Bulk delete wiring
                const selectAllSummary = document.getElementById('selectAllSummaryCards');
                const selectAllSummaryLabel = document.getElementById('selectAllSummaryCardsLabel');

                if (selectAllSummaryLabel) selectAllSummaryLabel.style.display = visibleCards.length ? 'inline-flex' : 'none';
                if (selectAllSummary) selectAllSummary.checked = false;

                const updateBulkBtn = () => {
                    const currentBulkBtn = document.getElementById('bulkDeleteSummaryCards');
                    const currentBulkCount = document.getElementById('bulkDeleteSummaryCardsCount');
                    const allCbs = Array.from(editorList.querySelectorAll('.bulk-delete-summary-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (currentBulkBtn) currentBulkBtn.style.display = selected.length > 0 ? 'inline-block' : 'none';
                    if (currentBulkCount) currentBulkCount.textContent = selected.length;
                    if (selectAllSummary) selectAllSummary.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                editorList.querySelectorAll('.bulk-delete-summary-checkbox').forEach(cb => cb.addEventListener('change', updateBulkBtn));

                if (selectAllSummary) {
                    selectAllSummary.onchange = () => {
                        editorList.querySelectorAll('.bulk-delete-summary-checkbox').forEach(cb => { cb.checked = selectAllSummary.checked; });
                        updateBulkBtn();
                    };
                }

                const bulkBtn = document.getElementById('bulkDeleteSummaryCards');
                if (bulkBtn) {
                    const newBulkBtn = bulkBtn.cloneNode(true);
                    bulkBtn.parentNode.replaceChild(newBulkBtn, bulkBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(editorList.querySelectorAll('.bulk-delete-summary-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected summary card(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const res = await fetch(summaryCardApi + '&id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken } });
                            if (!res.ok) hasError = true;
                        }
                        if (hasError) alert('Some summary cards could not be deleted.');
                        await refreshSummaryCardEditor();
                    });
                }
                updateBulkBtn();

                editorList.querySelectorAll('.summary-card-editor-edit').forEach(button => {
                    button.addEventListener('click', () => {
                        const id = button.dataset.id;
                        const card = cards.find(item => item.id === id);
                        if (!card) return;
                        document.getElementById('summaryCardEditorId').value = card.id || '';
                        document.getElementById('summaryCardEditorTitle').value = card.title || '';
                        document.getElementById('summaryCardEditorMainValue').value = card.main_value || '';
                        document.getElementById('summaryCardEditorMainLabel').value = card.main_label || '';
                        document.getElementById('summaryCardEditorYearDate').value = card.year_date || '';
                        document.getElementById('summaryCardEditorSecondaryLabel').value = card.secondary_label || '';
                        document.getElementById('summaryCardEditorSecondaryValue').value = card.secondary_value || '';
                        document.getElementById('summaryCardEditorDescription').value = card.description || '';
                        document.getElementById('summaryCardEditorDisplayOrder').value = card.display_order ?? 0;
                        const selectedIds = (card.category_ids || (card.category_id ? [card.category_id] : [])).map(String);
                        [...categorySelect.options].forEach(option => { option.selected = selectedIds.includes(option.value); });
                        newCategoryInput.value = '';
                        newCategoryInput.style.display = 'none';
                        document.getElementById('summaryCardEditorPrecision').value = String(card.display_precision ?? 2);
                        document.getElementById('summaryCardEditorPublished').checked = !!card.is_published;
                        showCardForm(true);
                        openEditor();
                    });
                });

                editorList.querySelectorAll('.summary-card-editor-toggle').forEach(button => {
                    button.addEventListener('click', async () => {
                        const id = button.dataset.id;
                        const published = button.dataset.published === '1';
                        await fetch(summaryCardApi + '&id=' + encodeURIComponent(id), {
                            method: 'PUT',
                            headers: mutationHeaders(),
                            body: JSON.stringify({ is_published: !published })
                        });
                        refreshSummaryCardEditor();
                    });
                });

                editorList.querySelectorAll('.summary-card-editor-delete').forEach(button => {
                    button.addEventListener('click', async () => {
                        if (!confirm('Delete this summary card?')) return;
                        await fetch(summaryCardApi + '&id=' + encodeURIComponent(button.dataset.id), {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken }
                        });
                        refreshSummaryCardEditor();
                    });
                });
            }

            async function refreshSummaryCardEditor() {
                const [cardResponse, categoryResponse] = await Promise.all([
                    fetch(summaryCardApi, { headers: { Accept: 'application/json' } }),
                    fetch(categoryApi, { headers: { Accept: 'application/json' } })
                ]);
                if (!cardResponse.ok || !categoryResponse.ok) return;
                cards = await cardResponse.json();
                categories = await categoryResponse.json();
                renderCategoryOptions();
                renderCategoryList();
                renderCards();
            }

            categoryFilter.addEventListener('change', renderCards);
            document.getElementById('summaryCardEditorAddCategory').addEventListener('click', () => {
                newCategoryInput.style.display = newCategoryInput.style.display === 'none' ? 'block' : 'none';
                if (newCategoryInput.style.display === 'block') newCategoryInput.focus();
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const payload = {
                    title: document.getElementById('summaryCardEditorTitle').value.trim(),
                    main_value: document.getElementById('summaryCardEditorMainValue').value.trim(),
                    main_label: document.getElementById('summaryCardEditorMainLabel').value.trim(),
                    year_date: document.getElementById('summaryCardEditorYearDate').value.trim(),
                    secondary_label: document.getElementById('summaryCardEditorSecondaryLabel').value.trim(),
                    secondary_value: document.getElementById('summaryCardEditorSecondaryValue').value.trim(),
                    description: document.getElementById('summaryCardEditorDescription').value.trim(),
                    display_order: Number(document.getElementById('summaryCardEditorDisplayOrder').value || 0),
                    display_precision: (() => {
                        const precisionValue = Number(document.getElementById('summaryCardEditorPrecision').value);
                        return Number.isFinite(precisionValue) ? precisionValue : 2;
                    })(),
                    is_published: document.getElementById('summaryCardEditorPublished').checked
                };
                payload.category_ids = [...categorySelect.selectedOptions].map(option => Number(option.value));
                const newCategoryName = newCategoryInput.value.trim();
                if (newCategoryName) payload.category_names = [newCategoryName];
                const id = document.getElementById('summaryCardEditorId').value;
                const response = await fetch(summaryCardApi + (id ? '&id=' + encodeURIComponent(id) : ''), {
                    method: id ? 'PUT' : 'POST',
                    headers: mutationHeaders(),
                    body: JSON.stringify(payload)
                });
                if (response.ok) {
                    form.reset();
                    document.getElementById('summaryCardEditorId').value = '';
                    newCategoryInput.style.display = 'none';
                    newCategoryInput.value = '';
                    showCardList();
                    closeEditor();
                    refreshSummaryCardEditor();
                }
            });

            document.getElementById('toggleSummaryCardEditor').addEventListener('click', () => {
                showCardList();
                openEditor();
            });
            document.getElementById('addSummaryCardFromManager').addEventListener('click', () => {
                form.reset();
                document.getElementById('summaryCardEditorId').value = '';
                [...categorySelect.options].forEach(option => { option.selected = false; });
                newCategoryInput.style.display = 'none';
                newCategoryInput.value = '';
                document.getElementById('summaryCardEditorPrecision').value = '2';
                document.getElementById('summaryCardEditorPublished').checked = true;
                showCardForm(false);
            });

            document.getElementById('cancelSummaryCardEditor').addEventListener('click', () => {
                form.reset();
                document.getElementById('summaryCardEditorId').value = '';
                [...categorySelect.options].forEach(option => { option.selected = false; });
                newCategoryInput.style.display = 'none';
                newCategoryInput.value = '';
                showCardList();
            });
            document.getElementById('closeSummaryCardEditor').addEventListener('click', closeEditor);
            editorPanel.addEventListener('click', event => {
                if (event.target === editorPanel) closeEditor();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && editorPanel.classList.contains('active')) closeEditor();
            });

            refreshSummaryCardEditor();
        })();
    </script>
    <script>
        (function () {
            const apiUrl = '<?= e(base_url('api/star_rating_cards.php')) ?>';
            const categoryApiUrl = apiUrl + '?resource=categories';
            const baseUrl = <?= json_encode(base_url('')) ?>;
            const csrfToken = <?= json_encode(csrf_token()) ?>;
            const panel = document.getElementById('starRatingEditorPanel');
            const managerView = document.getElementById('starRatingManagerView');
            const form = document.getElementById('starRatingCardForm');
            const cardList = document.getElementById('starRatingCardList');
            const rowsHost = document.getElementById('starRatingRows');
            const preview = document.getElementById('starRatingPreview');
            const categoryFilter = document.getElementById('starRatingCategoryFilter');
            const categorySelect = document.getElementById('starRatingCategorySelect');
            const categoryList = document.getElementById('starRatingCategoryList');
            const newCategoryInput = document.getElementById('starRatingNewCategory');
            const logoInput = document.getElementById('starRatingLogo');
            const currentLogo = document.getElementById('starRatingCurrentLogo');
            const currentLogoImage = document.getElementById('starRatingCurrentLogoImage');
            const removeLogo = document.getElementById('starRatingRemoveLogo');
            const starPath = 'M12 2.5 14.9 8.4l6.6 1-4.75 4.62 1.12 6.53L12 17.47l-5.87 3.08 1.12-6.53L2.5 9.4l6.6-1L12 2.5Z';
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            let localLogoPreviewUrl = '';
            let cards = [];
            let categories = [];

            function clearLocalLogoPreview() {
                if (localLogoPreviewUrl) URL.revokeObjectURL(localLogoPreviewUrl);
                localLogoPreviewUrl = '';
            }

            function starIcons(maxStars, score, prefix) {
                return Array.from({ length: maxStars }, (_, index) => {
                    const remaining = score - index;
                    const fill = remaining >= 1 ? '#E0A70D' : remaining >= 0.5 ? `url(#${prefix}-${index})` : 'none';
                    const half = remaining >= 0.5 && remaining < 1
                        ? `<defs><linearGradient id="${prefix}-${index}"><stop offset="50%" stop-color="#E0A70D"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs>`
                        : '';
                    return `<svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" aria-hidden="true">${half}<path d="${starPath}" fill="${fill}" stroke="#E0A70D" stroke-width="1.5" stroke-linejoin="round"/></svg>`;
                }).join('');
            }

            function rowValues() {
                return [...rowsHost.querySelectorAll('[data-star-row]')].map(row => ({
                    label: row.querySelector('[data-label]').value.trim(),
                    max_stars: Number(row.querySelector('[data-max-stars]').value),
                    score: Number(row.querySelector('[data-score]').value)
                }));
            }

            function renderPreview() {
                const title = document.getElementById('starRatingTitle').value.trim() || 'Star rating title';
                const year = document.getElementById('starRatingYear').value.trim();
                const logoSrc = localLogoPreviewUrl || (currentLogo.style.display !== 'none' && !removeLogo.checked ? currentLogoImage.src : '');
                const rows = rowValues();
                const rowsHtml = rows.length ? rows.map((row, index) => {
                    const maxStars = Math.max(1, Math.min(10, Number.isFinite(row.max_stars) ? row.max_stars : 10));
                    const score = Math.max(0, Math.min(maxStars, Number.isFinite(row.score) ? row.score : 0));
                    const label = row.label || 'Category';
                    return `<div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 py-2 dark:border-gray-700" aria-label="${escapeHtml(label)}: ${score} out of ${maxStars} stars" title="${escapeHtml(label)}: ${score} out of ${maxStars} stars"><div class="flex max-w-full flex-wrap items-center gap-0.5">${starIcons(maxStars, score, `preview-star-${index}`)}<span class="ml-1 text-xs text-gray-500 dark:text-gray-400">${score} / ${maxStars}</span></div><span class="text-sm font-semibold text-gray-800 dark:text-gray-100">${escapeHtml(label)}</span></div>`;
                }).join('') : '<div class="text-sm text-gray-500 dark:text-gray-400">Add a row to preview ratings.</div>';
                preview.innerHTML = `<div class="mx-auto max-w-lg rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex min-h-16 flex-col items-center justify-center gap-2 text-center">${logoSrc ? `<img src="${escapeHtml(logoSrc)}" alt="" class="max-h-12 max-w-36 object-contain">` : ''}<div class="font-bold text-gray-900 dark:text-white">${escapeHtml(title)}</div></div>${year ? `<div class="mt-2 border-y border-gray-200 py-1 text-center text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">${escapeHtml(year)}</div>` : ''}<div class="mt-2">${rowsHtml}</div></div>`;
            }

            function updateRowButtons() {
                const rows = [...rowsHost.querySelectorAll('[data-star-row]')];
                rows.forEach((row, index) => {
                    row.querySelector('[data-move-up]').disabled = index === 0;
                    row.querySelector('[data-move-down]').disabled = index === rows.length - 1;
                });
                document.getElementById('addStarRatingRow').disabled = rows.length >= 10;
            }

            function addRow(value = {}) {
                if (rowsHost.children.length >= 10) return;
                const row = document.createElement('div');
                row.dataset.starRow = '1';
                row.style.cssText = 'display:grid; grid-template-columns:minmax(130px,1.4fr) minmax(95px,0.8fr) minmax(95px,0.8fr) auto; align-items:end; gap:0.5rem; padding:0.65rem; border:1px solid var(--border-light); border-radius:var(--radius-md);';
                const options = Array.from({ length: 10 }, (_, index) => `<option value="${index + 1}" ${Number(value.max_stars || 10) === index + 1 ? 'selected' : ''}>${index + 1}</option>`).join('');
                row.innerHTML = `<label class="form-label" style="margin:0;">Label<input data-label type="text" class="form-input" maxlength="80" value="${escapeHtml(value.label || '')}" placeholder="Teaching"></label><label class="form-label" style="margin:0;">Number of stars<select data-max-stars class="form-input">${options}</select></label><label class="form-label" style="margin:0;">Score<input data-score type="number" class="form-input" min="0" max="${Number(value.max_stars || 10)}" step="0.5" value="${Number.isFinite(Number(value.score)) ? Number(value.score) : 0}"></label><div style="display:flex; gap:0.25rem;"><button type="button" data-move-up class="btn-studio-action" aria-label="Move row up" title="Move up">↑</button><button type="button" data-move-down class="btn-studio-action" aria-label="Move row down" title="Move down">↓</button><button type="button" data-remove-row class="archive-delete-button" aria-label="Remove row" title="Remove row">×</button></div>`;
                rowsHost.appendChild(row);
                row.querySelector('[data-max-stars]').addEventListener('change', event => {
                    const scoreInput = row.querySelector('[data-score]');
                    const maxStars = Number(event.target.value);
                    scoreInput.max = String(maxStars);
                    if (Number(scoreInput.value) > maxStars) scoreInput.value = String(maxStars);
                    renderPreview();
                });
                row.querySelectorAll('input').forEach(input => input.addEventListener('input', renderPreview));
                row.querySelector('[data-remove-row]').addEventListener('click', () => { row.remove(); updateRowButtons(); renderPreview(); });
                row.querySelector('[data-move-up]').addEventListener('click', () => { row.previousElementSibling && rowsHost.insertBefore(row, row.previousElementSibling); updateRowButtons(); });
                row.querySelector('[data-move-down]').addEventListener('click', () => { row.nextElementSibling && rowsHost.insertBefore(row.nextElementSibling, row); updateRowButtons(); });
                updateRowButtons();
                renderPreview();
            }

            function showList() {
                form.style.display = 'none';
                managerView.style.display = 'block';
                document.getElementById('starRatingEditorHeading').textContent = 'Manage star rating cards';
            }

            function showForm(isEdit) {
                managerView.style.display = 'none';
                form.style.display = 'block';
                document.getElementById('starRatingEditorHeading').textContent = isEdit ? 'Edit star rating card' : 'Add star rating card';
                document.getElementById('starRatingTitle').focus();
            }

            function openPanel() {
                panel.classList.add('active');
                panel.setAttribute('aria-hidden', 'false');
                (managerView.style.display === 'none' ? document.getElementById('starRatingTitle') : document.getElementById('addStarRatingCard')).focus();
            }

            function closePanel() {
                panel.classList.remove('active');
                panel.setAttribute('aria-hidden', 'true');
            }

            function renderCategoryOptions() {
                const selected = [...categorySelect.selectedOptions].map(option => option.value);
                categorySelect.innerHTML = categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                [...categorySelect.options].forEach(option => { option.selected = selected.includes(option.value); });
                const filterValue = categoryFilter.value || 'all';
                categoryFilter.innerHTML = '<option value="all">All categories</option><option value="uncategorized">Uncategorized</option>' + categories.map(category => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('');
                if ([...categoryFilter.options].some(option => option.value === filterValue)) categoryFilter.value = filterValue;
            }

            function renderCategoryList() {
                if (!categories.length) {
                    categoryList.innerHTML = '<div class="text-xs" style="color:var(--text-muted);">No star rating categories yet. Create one from the card form.</div>';
                    return;
                }
                categoryList.innerHTML = categories.map(category => `<div class="star-rating-category-row" data-id="${escapeHtml(category.id)}" style="display:grid; grid-template-columns:minmax(120px,1fr) 90px auto auto; align-items:center; gap:0.5rem;"><input class="form-input" data-category-name value="${escapeHtml(category.name)}" maxlength="40" aria-label="Star rating category name"><input class="form-input" data-category-order type="number" value="${escapeHtml(category.sort_order)}" aria-label="Star rating category sort order"><button type="button" class="btn-studio-action" data-save-category>Save</button><button type="button" class="archive-delete-button" data-delete-category>Delete</button></div>`).join('');
                categoryList.querySelectorAll('[data-save-category]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.star-rating-category-row');
                    const response = await fetch(categoryApiUrl + '&id=' + encodeURIComponent(row.dataset.id), {
                        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                        body: JSON.stringify({ name: row.querySelector('[data-category-name]').value.trim(), sort_order: Number(row.querySelector('[data-category-order]').value || 0) })
                    });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to update category.'); return; }
                    await refreshCards();
                }));
                categoryList.querySelectorAll('[data-delete-category]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.star-rating-category-row');
                    const category = categories.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${category?.name || 'this category'}”? It will be removed from assigned star cards.`)) return;
                    const response = await fetch(categoryApiUrl + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete category.'); return; }
                    await refreshCards();
                }));
            }

            function rowSummary(rows) {
                return rows.map(row => `${escapeHtml(row.label)} ${Number(row.score)} / ${Number(row.max_stars)}`).join(' · ');
            }

            async function refreshCards() {
                const [response, categoryResponse] = await Promise.all([
                    fetch(apiUrl, { headers: { Accept: 'application/json' } }),
                    fetch(categoryApiUrl, { headers: { Accept: 'application/json' } })
                ]);
                if (!response.ok || !categoryResponse.ok) return;
                cards = await response.json();
                categories = await categoryResponse.json();
                renderCategoryOptions();
                renderCategoryList();
                const selectedCategory = categoryFilter.value || 'all';
                const visibleCards = cards.filter(card => selectedCategory === 'all'
                    || (selectedCategory === 'uncategorized'
                        ? !(card.category_ids || []).length
                        : (card.category_ids || []).some(categoryId => String(categoryId) === selectedCategory)));
                if (!visibleCards.length) {
                    const emptyText = cards.length ? 'No star rating cards in this category.' : 'No star rating cards yet.';
                    cardList.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 text-center">${emptyText}</div>`;
                    return;
                }
                cardList.innerHTML = visibleCards.map(card => `<article class="rounded-xl border border-gray-200 bg-gray-50 p-3"><div class="flex items-start gap-3"><input type="checkbox" class="bulk-delete-star-checkbox mt-1 cursor-pointer" data-id="${Number(card.id)}" aria-label="Select for bulk delete" style="width:1rem;height:1rem;"> <div class="flex-1 flex flex-wrap items-start justify-between gap-3"><div><h4 class="font-bold text-sm text-slate-900">${escapeHtml(card.title)}</h4><p class="text-xs text-slate-500">${escapeHtml(card.year || 'No year')} · ${rowSummary(card.rows || [])}</p><span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">${escapeHtml((card.category_names || []).join(', ') || 'Uncategorized')}</span></div><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase ${card.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}">${card.is_published ? 'Published' : 'Draft'}</span></div></div><div class="mt-3 flex flex-wrap gap-2 pl-7"><button type="button" class="btn-studio-action" data-edit="${card.id}">Edit</button><button type="button" class="btn-studio-action" data-toggle="${card.id}">${card.is_published ? 'Unpublish' : 'Publish'}</button><button type="button" class="archive-delete-button" data-delete="${card.id}">Delete</button></div></article>`).join('');
                                                                const selectAllStar = document.getElementById('selectAllStarCards');
                const selectAllStarLabel = document.getElementById('selectAllStarCardsLabel');

                if (selectAllStarLabel) selectAllStarLabel.style.display = visibleCards.length ? 'inline-flex' : 'none';
                if (selectAllStar) selectAllStar.checked = false;

                const updateBulkBtn = () => {
                    const currentBulkBtn = document.getElementById('bulkDeleteStarCards');
                    const currentBulkCount = document.getElementById('bulkDeleteStarCardsCount');
                    const allCbs = Array.from(cardList.querySelectorAll('.bulk-delete-star-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (currentBulkBtn) currentBulkBtn.style.display = selected.length > 0 ? 'inline-block' : 'none';
                    if (currentBulkCount) currentBulkCount.textContent = selected.length;
                    if (selectAllStar) selectAllStar.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                cardList.querySelectorAll('.bulk-delete-star-checkbox').forEach(cb => cb.addEventListener('change', updateBulkBtn));

                if (selectAllStar) {
                    selectAllStar.onchange = () => {
                        cardList.querySelectorAll('.bulk-delete-star-checkbox').forEach(cb => { cb.checked = selectAllStar.checked; });
                        updateBulkBtn();
                    };
                }

                const bulkBtn = document.getElementById('bulkDeleteStarCards');
                if (bulkBtn) {
                    const newBulkBtn = bulkBtn.cloneNode(true);
                    bulkBtn.parentNode.replaceChild(newBulkBtn, bulkBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(cardList.querySelectorAll('.bulk-delete-star-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected star rating card(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(apiUrl + '?id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some star rating cards could not be deleted.');
                        await refreshCards();
                    });
                }
                updateBulkBtn();
                cardList.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => {
                    const card = cards.find(item => String(item.id) === button.dataset.edit);
                    if (!card) return;
                    clearLocalLogoPreview();
                    form.reset();
                    document.getElementById('starRatingCardId').value = card.id;
                    document.getElementById('starRatingTitle').value = card.title || '';
                    document.getElementById('starRatingYear').value = card.year || '';
                    document.getElementById('starRatingDisplayOrder').value = card.display_order ?? 0;
                    document.getElementById('starRatingPublished').checked = !!card.is_published;
                    const selectedCategoryIds = (card.category_ids || []).map(String);
                    [...categorySelect.options].forEach(option => { option.selected = selectedCategoryIds.includes(option.value); });
                    newCategoryInput.value = '';
                    newCategoryInput.style.display = 'none';
                    rowsHost.innerHTML = '';
                    (card.rows || []).forEach(addRow);
                    currentLogo.style.display = card.logo_path ? 'flex' : 'none';
                    currentLogoImage.src = card.logo_path ? baseUrl + card.logo_path : '';
                    removeLogo.checked = false;
                    showForm(true);
                    openPanel();
                }));
                cardList.querySelectorAll('[data-toggle]').forEach(button => button.addEventListener('click', async () => {
                    const card = cards.find(item => String(item.id) === button.dataset.toggle);
                    if (!card) return;
                    const data = new FormData();
                    data.set('title', card.title);
                    data.set('year', card.year || '');
                    data.set('display_order', card.display_order);
                    data.set('is_published', card.is_published ? '0' : '1');
                    data.set('rows', JSON.stringify(card.rows || []));
                    data.set('category_ids', JSON.stringify(card.category_ids || []));
                    const response = await fetch(apiUrl + '?id=' + encodeURIComponent(card.id), { method: 'POST', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' }, body: data });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to update publication.'); return; }
                    refreshCards();
                }));
                cardList.querySelectorAll('[data-delete]').forEach(button => button.addEventListener('click', async () => {
                    if (!confirm('Delete this star rating card?')) return;
                    const response = await fetch(apiUrl + '?id=' + encodeURIComponent(button.dataset.delete), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete star rating card.'); return; }
                    refreshCards();
                }));
            }

            document.getElementById('toggleStarRatingEditor').addEventListener('click', () => { showList(); openPanel(); });
            categoryFilter.addEventListener('change', refreshCards);
            document.getElementById('starRatingAddCategory').addEventListener('click', () => {
                newCategoryInput.style.display = newCategoryInput.style.display === 'none' ? 'block' : 'none';
                if (newCategoryInput.style.display === 'block') newCategoryInput.focus();
            });
            document.getElementById('addStarRatingCard').addEventListener('click', () => {
                clearLocalLogoPreview();
                form.reset();
                [...categorySelect.options].forEach(option => { option.selected = false; });
                newCategoryInput.value = '';
                newCategoryInput.style.display = 'none';
                document.getElementById('starRatingCardId').value = '';
                document.getElementById('starRatingDisplayOrder').value = '0';
                document.getElementById('starRatingPublished').checked = true;
                rowsHost.innerHTML = '';
                currentLogo.style.display = 'none';
                currentLogoImage.removeAttribute('src');
                logoInput.value = '';
                removeLogo.checked = false;
                addRow({ max_stars: 10, score: 0 });
                showForm(false);
            });
            document.getElementById('addStarRatingRow').addEventListener('click', () => addRow({ max_stars: 10, score: 0 }));
            form.addEventListener('input', renderPreview);
            form.addEventListener('change', renderPreview);
            logoInput.addEventListener('change', () => {
                clearLocalLogoPreview();
                if (logoInput.files?.[0]) localLogoPreviewUrl = URL.createObjectURL(logoInput.files[0]);
                renderPreview();
            });
            removeLogo.addEventListener('change', renderPreview);
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const data = new FormData(form);
                data.set('rows', JSON.stringify(rowValues()));
                data.set('category_ids', JSON.stringify([...categorySelect.selectedOptions].map(option => Number(option.value))));
                data.set('new_category_name', newCategoryInput.value.trim());
                data.set('is_published', document.getElementById('starRatingPublished').checked ? '1' : '0');
                data.set('remove_logo', removeLogo.checked ? '1' : '0');
                const id = document.getElementById('starRatingCardId').value;
                const response = await fetch(apiUrl + (id ? '?id=' + encodeURIComponent(id) : ''), { method: 'POST', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' }, body: data });
                if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to save star rating card.'); return; }
                form.reset();
                clearLocalLogoPreview();
                rowsHost.innerHTML = '';
                showList();
                closePanel();
                await refreshCards();
            });
            document.getElementById('cancelStarRatingEditor').addEventListener('click', () => { form.reset(); [...categorySelect.options].forEach(option => { option.selected = false; }); newCategoryInput.value = ''; newCategoryInput.style.display = 'none'; clearLocalLogoPreview(); rowsHost.innerHTML = ''; showList(); });
            document.getElementById('closeStarRatingEditor').addEventListener('click', closePanel);
            panel.addEventListener('click', event => { if (event.target === panel) closePanel(); });
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel.classList.contains('active')) closePanel(); });
            document.getElementById('starRatingTitle').addEventListener('input', renderPreview);
            document.getElementById('starRatingYear').addEventListener('input', renderPreview);
            refreshCards();
        })();
    </script>
    <script>
        (function () {
            const apiUrl = '<?= e(base_url('api/admin_rankings.php')) ?>';
            const csrfToken = <?= json_encode(csrf_token()) ?>;
            const panel = document.getElementById('rankingHistoryEditorPanel');
            const manager = document.getElementById('rankingHistoryManagerView');
            const form = document.getElementById('rankingHistoryAdminForm');
            const list = document.getElementById('rankingHistoryAdminList');
            const bodySelect = document.getElementById('rankingHistoryAdminBody');
            const scopeSelect = document.getElementById('rankingHistoryAdminScope');
            const yearFilter = document.getElementById('rankingHistoryAdminYearFilter');
            const scopeFilter = document.getElementById('rankingHistoryAdminScopeFilter');
            const levelFilter = document.getElementById('rankingHistoryAdminLevelFilter');
            const scopeList = document.getElementById('rankingScopeAdminList');
            const levelList = document.getElementById('rankingLevelAdminList');
            const scopeApiUrl = apiUrl + '?resource=scopes';
            const levelApiUrl = apiUrl + '?resource=levels';
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
            let rankings = [];
            let bodies = [];
            let scopes = [];
            let levels = [];

            const scopesModal = document.getElementById('manageScopesModalPanel');
            const levelsModal = document.getElementById('manageLevelsModalPanel');

            document.getElementById('openManageScopesModal')?.addEventListener('click', () => {
                if (scopesModal) { scopesModal.classList.add('active'); scopesModal.setAttribute('aria-hidden', 'false'); }
            });
            document.getElementById('closeManageScopesModal')?.addEventListener('click', () => {
                if (scopesModal) { scopesModal.classList.remove('active'); scopesModal.setAttribute('aria-hidden', 'true'); }
            });
            document.getElementById('openManageLevelsModal')?.addEventListener('click', () => {
                if (levelsModal) { levelsModal.classList.add('active'); levelsModal.setAttribute('aria-hidden', 'false'); }
            });
            document.getElementById('closeManageLevelsModal')?.addEventListener('click', () => {
                if (levelsModal) { levelsModal.classList.remove('active'); levelsModal.setAttribute('aria-hidden', 'true'); }
            });

            function showList() {
                form.style.display = 'none';
                manager.style.display = 'block';
                document.getElementById('rankingHistoryEditorHeading').textContent = 'Manage Ranking History';
            }

            function showForm(isEdit) {
                manager.style.display = 'none';
                form.style.display = 'block';
                document.getElementById('rankingHistoryEditorHeading').textContent = isEdit ? 'Edit ranking' : 'Add ranking';
                document.getElementById('rankingHistoryAdminBody').focus();
            }

            function renderScopes() {
                const selectAllScopes = document.getElementById('selectAllScopes');
                const selectAllScopesLabel = document.getElementById('selectAllScopesLabel');
                const bulkScopesBtn = document.getElementById('bulkDeleteScopes');
                const bulkScopesCount = document.getElementById('bulkDeleteScopesCount');

                if (selectAllScopesLabel) selectAllScopesLabel.style.display = scopes.length ? 'inline-flex' : 'none';
                if (selectAllScopes) selectAllScopes.checked = false;
                if (bulkScopesBtn) bulkScopesBtn.style.display = 'none';

                if (!scopes.length) {
                    scopeList.innerHTML = '<div class="text-xs text-gray-600 dark:text-gray-300">No ranking scopes yet. Add one below.</div>';
                    return;
                }

                scopeList.innerHTML = scopes.map(scope => `<div class="ranking-scope-row grid grid-cols-[auto_minmax(120px,1fr)_90px_auto_auto] items-center gap-2" data-id="${Number(scope.id)}"><input type="checkbox" class="bulk-delete-scope-checkbox cursor-pointer" data-id="${Number(scope.id)}" aria-label="Select scope for bulk delete" style="width:1rem;height:1rem;"><input class="form-input" data-scope-name maxlength="80" value="${escapeHtml(scope.name)}" aria-label="Scope name"><input class="form-input" data-scope-order type="number" step="1" value="${Number(scope.sort_order)}" aria-label="Scope order"><button type="button" data-save-scope class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100">Save</button><button type="button" data-delete-scope class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">Delete</button></div>`).join('');

                const updateBulkScopesBtn = () => {
                    const allCbs = Array.from(scopeList.querySelectorAll('.bulk-delete-scope-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (bulkScopesBtn) bulkScopesBtn.style.display = selected.length > 0 ? 'inline-flex' : 'none';
                    if (bulkScopesCount) bulkScopesCount.textContent = selected.length;
                    if (selectAllScopes) selectAllScopes.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                scopeList.querySelectorAll('.bulk-delete-scope-checkbox').forEach(cb => cb.addEventListener('change', updateBulkScopesBtn));

                if (selectAllScopes) {
                    selectAllScopes.onchange = () => {
                        scopeList.querySelectorAll('.bulk-delete-scope-checkbox').forEach(cb => { cb.checked = selectAllScopes.checked; });
                        updateBulkScopesBtn();
                    };
                }

                if (bulkScopesBtn) {
                    const newBulkBtn = bulkScopesBtn.cloneNode(true);
                    bulkScopesBtn.parentNode.replaceChild(newBulkBtn, bulkScopesBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(scopeList.querySelectorAll('.bulk-delete-scope-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected scope(s)? Their rankings will become unassigned.`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(scopeApiUrl + '&id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some scopes could not be deleted.');
                        await refresh();
                    });
                }

                scopeList.querySelectorAll('[data-save-scope]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-scope-row');
                    const response = await fetch(scopeApiUrl + '&id=' + encodeURIComponent(row.dataset.id), {
                        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                        body: JSON.stringify({ name: row.querySelector('[data-scope-name]').value.trim(), sort_order: Number(row.querySelector('[data-scope-order]').value || 0) })
                    });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to update scope.'); return; }
                    await refresh();
                }));
                scopeList.querySelectorAll('[data-delete-scope]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-scope-row');
                    const scope = scopes.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${scope?.name || 'this scope'}”? Its rankings will become unassigned.`)) return;
                    const response = await fetch(scopeApiUrl + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete scope.'); return; }
                    await refresh();
                }));
            }

            function renderLevels() {
                const selectAllLevels = document.getElementById('selectAllLevels');
                const selectAllLevelsLabel = document.getElementById('selectAllLevelsLabel');
                const bulkLevelsBtn = document.getElementById('bulkDeleteLevels');
                const bulkLevelsCount = document.getElementById('bulkDeleteLevelsCount');

                if (selectAllLevelsLabel) selectAllLevelsLabel.style.display = levels.length ? 'inline-flex' : 'none';
                if (selectAllLevels) selectAllLevels.checked = false;
                if (bulkLevelsBtn) bulkLevelsBtn.style.display = 'none';

                if (!levels.length) {
                    levelList.innerHTML = '<div class="text-xs text-gray-600 dark:text-gray-300">No ranking levels yet. Add one below.</div>';
                    return;
                }

                levelList.innerHTML = levels.map(level => `<div class="ranking-level-row grid grid-cols-[auto_minmax(120px,1fr)_90px_auto_auto] items-center gap-2" data-id="${Number(level.id)}"><input type="checkbox" class="bulk-delete-level-checkbox cursor-pointer" data-id="${Number(level.id)}" aria-label="Select level for bulk delete" style="width:1rem;height:1rem;"><input class="form-input" data-level-name maxlength="80" value="${escapeHtml(level.name)}" aria-label="Level name"><input class="form-input" data-level-order type="number" step="1" value="${Number(level.sort_order)}" aria-label="Level order"><button type="button" data-save-level class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100">Save</button><button type="button" data-delete-level class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">Delete</button></div>`).join('');

                const updateBulkLevelsBtn = () => {
                    const allCbs = Array.from(levelList.querySelectorAll('.bulk-delete-level-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (bulkLevelsBtn) bulkLevelsBtn.style.display = selected.length > 0 ? 'inline-flex' : 'none';
                    if (bulkLevelsCount) bulkLevelsCount.textContent = selected.length;
                    if (selectAllLevels) selectAllLevels.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                levelList.querySelectorAll('.bulk-delete-level-checkbox').forEach(cb => cb.addEventListener('change', updateBulkLevelsBtn));

                if (selectAllLevels) {
                    selectAllLevels.onchange = () => {
                        levelList.querySelectorAll('.bulk-delete-level-checkbox').forEach(cb => { cb.checked = selectAllLevels.checked; });
                        updateBulkLevelsBtn();
                    };
                }

                if (bulkLevelsBtn) {
                    const newBulkBtn = bulkLevelsBtn.cloneNode(true);
                    bulkLevelsBtn.parentNode.replaceChild(newBulkBtn, bulkLevelsBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(levelList.querySelectorAll('.bulk-delete-level-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected level(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(levelApiUrl + '&id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some levels could not be deleted.');
                        await refresh();
                    });
                }

                levelList.querySelectorAll('[data-save-level]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-level-row');
                    const response = await fetch(levelApiUrl + '&id=' + encodeURIComponent(row.dataset.id), {
                        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                        body: JSON.stringify({ name: row.querySelector('[data-level-name]').value.trim(), sort_order: Number(row.querySelector('[data-level-order]').value || 0) })
                    });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to update level.'); return; }
                    await refresh();
                }));
                levelList.querySelectorAll('[data-delete-level]').forEach(button => button.addEventListener('click', async () => {
                    const row = button.closest('.ranking-level-row');
                    const levelItem = levels.find(item => String(item.id) === row.dataset.id);
                    if (!confirm(`Delete “${levelItem?.name || 'this level'}”?`)) return;
                    const response = await fetch(levelApiUrl + '&id=' + encodeURIComponent(row.dataset.id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete level.'); return; }
                    await refresh();
                }));
            }

            function render() {
                const filterValue = yearFilter.value || 'all';
                const years = [...new Set(rankings.map(row => String(row.year)))].sort((a, b) => Number(b) - Number(a));
                yearFilter.innerHTML = '<option value="all">All years</option>' + years.map(year => `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`).join('');
                if ([...yearFilter.options].some(option => option.value === filterValue)) yearFilter.value = filterValue;
                const selectedScope = scopeFilter.value || 'all';
                const selectedLevel = levelFilter.value || 'all';
                const visible = rankings.filter(row => (yearFilter.value === 'all' || String(row.year) === yearFilter.value)
                    && (selectedScope === 'all' || (selectedScope === 'unassigned' ? !row.scope_id : String(row.scope_id) === selectedScope))
                    && (selectedLevel === 'all' || (selectedLevel === 'unassigned' ? !row.level : row.level === selectedLevel)));
                if (!visible.length) {
                    list.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">No ranking rows found.</div>';
                    return;
                }
                list.innerHTML = visible.map(row => `<article class="ranking-history-admin-card rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800"><div class="flex items-start gap-3"><input type="checkbox" class="bulk-delete-checkbox mt-1 cursor-pointer" data-id="${Number(row.id)}" aria-label="Select for bulk delete" style="width:1rem;height:1rem;"> <div class="flex-1 flex flex-wrap items-start justify-between gap-3"><div><div class="font-bold text-sm text-slate-900">${escapeHtml(row.ranking_type || row.body_name || row.body_short_name)}</div><div class="text-xs text-slate-600">${escapeHtml(row.year)} · ${escapeHtml(row.edition || 'Annual')} · ${escapeHtml(row.level || 'Unclassified')} · ${escapeHtml(row.scope_name || 'Unassigned')} · ${escapeHtml(row.category || 'Overall')} · Rank: ${escapeHtml(row.global_rank || '—')} · Bounds: ${escapeHtml(row.rank_low ?? '—')}–${escapeHtml(row.rank_high ?? '+')}</div><span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${row.verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : row.verification_status === 'conflicting' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'}">${escapeHtml(row.verification_status || 'unverified')}</span>${row.note ? `<div class="mt-1 text-xs text-slate-500">${escapeHtml(row.note)}</div>` : ''}${row.source ? `<div class="mt-1 break-all text-xs text-blue-700 dark:text-blue-300">${escapeHtml(row.source)}</div>` : ''}</div><div class="flex gap-2"><button type="button" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600" data-edit-ranking="${Number(row.id)}">Edit</button><button type="button" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100 dark:hover:bg-red-900/70" data-delete-ranking="${Number(row.id)}">Delete</button></div></div></div></article>`).join('');
                                                                const selectAllRanking = document.getElementById('selectAllRanking');
                const selectAllRankingLabel = document.getElementById('selectAllRankingLabel');

                if (selectAllRankingLabel) selectAllRankingLabel.style.display = visible.length ? 'inline-flex' : 'none';
                if (selectAllRanking) selectAllRanking.checked = false;

                const updateBulkBtn = () => {
                    const currentBulkBtn = document.getElementById('bulkDeleteRankingHistory');
                    const currentBulkCount = document.getElementById('bulkDeleteCount');
                    const allCbs = Array.from(list.querySelectorAll('.bulk-delete-checkbox'));
                    const selected = allCbs.filter(cb => cb.checked);
                    if (currentBulkBtn) currentBulkBtn.style.display = selected.length > 0 ? 'inline-block' : 'none';
                    if (currentBulkCount) currentBulkCount.textContent = selected.length;
                    if (selectAllRanking) selectAllRanking.checked = allCbs.length > 0 && selected.length === allCbs.length;
                };

                list.querySelectorAll('.bulk-delete-checkbox').forEach(cb => cb.addEventListener('change', updateBulkBtn));

                if (selectAllRanking) {
                    selectAllRanking.onchange = () => {
                        list.querySelectorAll('.bulk-delete-checkbox').forEach(cb => { cb.checked = selectAllRanking.checked; });
                        updateBulkBtn();
                    };
                }

                const bulkBtn = document.getElementById('bulkDeleteRankingHistory');
                if (bulkBtn) {
                    const newBulkBtn = bulkBtn.cloneNode(true);
                    bulkBtn.parentNode.replaceChild(newBulkBtn, bulkBtn);
                    newBulkBtn.addEventListener('click', async () => {
                        const selectedIds = Array.from(list.querySelectorAll('.bulk-delete-checkbox:checked')).map(cb => cb.dataset.id);
                        if (!selectedIds.length) return;
                        if (!confirm(`Delete ${selectedIds.length} selected ranking history row(s)?`)) return;
                        newBulkBtn.disabled = true;
                        newBulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        let hasError = false;
                        for (const id of selectedIds) {
                            const response = await fetch(apiUrl + '?id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                            if (!response.ok) hasError = true;
                        }
                        if (hasError) alert('Some ranking history rows could not be deleted.');
                        await refresh();
                    });
                }
                updateBulkBtn();
                list.querySelectorAll('[data-edit-ranking]').forEach(button => button.addEventListener('click', () => {
                    const row = rankings.find(item => String(item.id) === button.dataset.editRanking);
                    if (row) loadRankingIntoForm(row);
                }));
                list.querySelectorAll('[data-delete-ranking]').forEach(button => button.addEventListener('click', async () => {
                    const row = rankings.find(item => String(item.id) === button.dataset.deleteRanking);
                    if (!confirm(`Delete ${row?.body_name || 'this'} ${row?.year || ''} ranking row?`)) return;
                    const response = await fetch(apiUrl + '?id=' + encodeURIComponent(button.dataset.deleteRanking), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' } });
                    if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to delete ranking.'); return; }
                    await refresh();
                }));
            }

            function loadRankingIntoForm(row) {
                document.getElementById('rankingHistoryAdminId').value = row.id;
                bodySelect.value = row.body_name || row.body_short_name || '';
                scopeSelect.value = row.scope_name || '';
                document.getElementById('rankingHistoryAdminLevel').value = row.level || '';
                document.getElementById('rankingHistoryAdminType').value = row.ranking_type || '';
                document.getElementById('rankingHistoryAdminYear').value = row.year;
                document.getElementById('rankingHistoryAdminEdition').value = row.edition || 'Annual';
                document.getElementById('rankingHistoryAdminCategory').value = row.category || 'Overall';
                rankDisplayInput.value = row.global_rank || '';
                rankLowInput.value = row.rank_low ?? '';
                rankHighInput.value = row.rank_high ?? '';
                document.getElementById('rankingHistoryAdminPhRank').value = row.ph_rank || '';
                document.getElementById('rankingHistoryAdminNote').value = row.note || '';
                document.getElementById('rankingHistoryAdminSource').value = row.source || '';
                document.getElementById('rankingHistoryAdminStatus').value = row.verification_status || 'unverified';
                
                const val = (row.global_rank || '').trim();
                if (val) {
                    const res = parseRankDisplayJS(val);
                    if (!res.matched && rankTextNote) rankTextNote.classList.remove('hidden');
                    else if (rankTextNote) rankTextNote.classList.add('hidden');
                } else {
                    if (rankTextNote) rankTextNote.classList.add('hidden');
                }
                const dupContainer = document.getElementById('formDuplicateError');
                if (dupContainer) dupContainer.classList.add('hidden');
                showForm(true);
            }

            async function refresh() {
                const response = await fetch(apiUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const payload = await response.json();
                rankings = payload.rankings || [];
                bodies = payload.bodies || [];
                scopes = payload.scopes || [];
                levels = payload.levels || [];

                const bodyDatalist = document.getElementById('rankingBodySuggestions');
                if (bodyDatalist) {
                    const bodyOptions = [...new Set(bodies.map(b => b.name))].filter(Boolean);
                    bodyDatalist.innerHTML = bodyOptions.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const scopeDatalist = document.getElementById('rankingScopeSuggestions');
                if (scopeDatalist) {
                    const scopeOptions = [...new Set(scopes.map(s => s.name))].filter(Boolean);
                    scopeDatalist.innerHTML = scopeOptions.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const levelDatalist = document.getElementById('rankingLevelSuggestions');
                if (levelDatalist) {
                    const dbLevels = levels.map(l => l.name);
                    const presetLevels = ['World', 'Asia', 'ASEAN', 'Local'];
                    const allLevels = [...new Set([...dbLevels, ...presetLevels, ...rankings.map(r => r.level)])].filter(Boolean);
                    levelDatalist.innerHTML = allLevels.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const typeDatalist = document.getElementById('rankingTypeSuggestions');
                if (typeDatalist) {
                    const allTypes = [...new Set(rankings.map(r => r.ranking_type))].filter(Boolean);
                    typeDatalist.innerHTML = allTypes.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
                }

                const selectedScopeFilter = scopeFilter.value || 'all';
                scopeFilter.innerHTML = '<option value="all">All scopes</option><option value="unassigned">Unassigned</option>' + scopes.map(scope => `<option value="${escapeHtml(scope.name)}">${escapeHtml(scope.name)}</option>`).join('');
                if ([...scopeFilter.options].some(option => option.value === selectedScopeFilter)) scopeFilter.value = selectedScopeFilter;
                else scopeFilter.value = 'all';

                const levelOptions = [...new Set([...levels.map(l => l.name), 'World', 'Asia', 'ASEAN', 'Local', ...rankings.map(r => r.level)])].filter(Boolean);
                const selectedLevelFilter = levelFilter.value || 'all';
                levelFilter.innerHTML = '<option value="all">All levels</option><option value="unassigned">Unassigned</option>' + levelOptions.map(l => `<option value="${escapeHtml(l)}">${escapeHtml(l)}</option>`).join('');
                if ([...levelFilter.options].some(o => o.value === selectedLevelFilter)) levelFilter.value = selectedLevelFilter;
                else levelFilter.value = 'all';

                renderScopes();
                renderLevels();
                render();
            }

            const rankDisplayInput = document.getElementById('rankingHistoryAdminGlobalRank');
            const rankLowInput = document.getElementById('rankingHistoryAdminRankLow');
            const rankHighInput = document.getElementById('rankingHistoryAdminRankHigh');
            const rankTextNote = document.getElementById('rankTextNote');

            function parseRankDisplayJS(value) {
                const raw = String(value || '').trim();
                if (!raw) return { matched: true, low: null, high: null };
                let m = raw.match(/^Top\s+(\d+)$/i);
                if (m) return { matched: true, low: 1, high: parseInt(m[1], 10) };
                m = raw.match(/^=?\s*(\d+)\s*\+$/);
                if (m) return { matched: true, low: parseInt(m[1], 10), high: null };
                m = raw.match(/^=?\s*(\d+)\s*[-–—]\s*(\d+)$/);
                if (m) return { matched: true, low: parseInt(m[1], 10), high: parseInt(m[2], 10) };
                m = raw.match(/^=?\s*(\d+)$/);
                if (m) return { matched: true, low: parseInt(m[1], 10), high: parseInt(m[1], 10) };
                return { matched: false, low: null, high: null };
            }

            function updateRankBoundsNote() {
                const val = rankDisplayInput.value.trim();
                if (!val) {
                    if (rankTextNote) rankTextNote.classList.add('hidden');
                    return;
                }
                const res = parseRankDisplayJS(val);
                if (res.matched) {
                    if (res.low !== null) rankLowInput.value = res.low;
                    else rankLowInput.value = '';
                    if (res.high !== null) rankHighInput.value = res.high;
                    else rankHighInput.value = '';
                    if (rankTextNote) rankTextNote.classList.add('hidden');
                } else {
                    rankLowInput.value = '';
                    rankHighInput.value = '';
                    if (rankTextNote) rankTextNote.classList.remove('hidden');
                }
            }

            rankDisplayInput?.addEventListener('input', updateRankBoundsNote);

            document.getElementById('toggleRankingHistoryEditor').addEventListener('click', () => {
                showList(); panel.classList.add('active'); panel.setAttribute('aria-hidden', 'false');
                document.getElementById('addRankingHistoryRow').focus();
            });
            document.getElementById('addRankingHistoryRow').addEventListener('click', () => {
                form.reset();
                document.getElementById('rankingHistoryAdminId').value = '';
                document.getElementById('rankingHistoryAdminYear').value = new Date().getFullYear();
                document.getElementById('rankingHistoryAdminCategory').value = 'Overall';
                document.getElementById('rankingHistoryAdminEdition').value = 'Annual';
                document.getElementById('rankingHistoryAdminStatus').value = 'verified';
                if (rankTextNote) rankTextNote.classList.add('hidden');
                const dupContainer = document.getElementById('formDuplicateError');
                if (dupContainer) dupContainer.classList.add('hidden');
                showForm(false);
            });
            yearFilter.addEventListener('change', render);
            scopeFilter.addEventListener('change', render);
            levelFilter.addEventListener('change', render);
            document.getElementById('addRankingScope').addEventListener('click', async () => {
                const input = document.getElementById('rankingScopeNewName');
                const name = input.value.trim();
                if (!name) { input.focus(); return; }
                const response = await fetch(scopeApiUrl, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                    body: JSON.stringify({ name })
                });
                if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to add scope.'); return; }
                input.value = '';
                await refresh();
            });
            document.getElementById('addRankingLevel')?.addEventListener('click', async () => {
                const input = document.getElementById('rankingLevelNewName');
                const name = input.value.trim();
                if (!name) { input.focus(); return; }
                const response = await fetch(levelApiUrl, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                    body: JSON.stringify({ name })
                });
                if (!response.ok) { const error = await response.json().catch(() => ({})); alert(error.error || 'Unable to add level.'); return; }
                input.value = '';
                await refresh();
            });
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const id = document.getElementById('rankingHistoryAdminId').value;
                const lowVal = rankLowInput.value === '' ? null : Number(rankLowInput.value);
                const highVal = rankHighInput.value === '' ? null : Number(rankHighInput.value);

                if (lowVal !== null && highVal !== null && lowVal > highVal) {
                    alert('Lower bound must be less than or equal to upper bound.');
                    return;
                }

                const payload = {
                    organization: bodySelect.value.trim(),
                    scope: scopeSelect.value.trim(),
                    level: document.getElementById('rankingHistoryAdminLevel').value.trim(),
                    ranking_type: document.getElementById('rankingHistoryAdminType').value.trim(),
                    year: Number(document.getElementById('rankingHistoryAdminYear').value),
                    edition: document.getElementById('rankingHistoryAdminEdition').value.trim(),
                    category: document.getElementById('rankingHistoryAdminCategory').value.trim(),
                    global_rank: rankDisplayInput.value.trim(),
                    rank_low: lowVal,
                    rank_high: highVal,
                    ph_rank: document.getElementById('rankingHistoryAdminPhRank').value.trim(),
                    note: document.getElementById('rankingHistoryAdminNote').value.trim(),
                    source: document.getElementById('rankingHistoryAdminSource').value.trim(),
                    verification_status: document.getElementById('rankingHistoryAdminStatus').value
                };

                const dupContainer = document.getElementById('formDuplicateError');
                if (dupContainer) dupContainer.classList.add('hidden');

                const response = await fetch(apiUrl + (id ? '?id=' + encodeURIComponent(id) : ''), {
                    method: id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    if (response.status === 409 && errorData.duplicate_id) {
                        const dupLink = document.getElementById('editDuplicateLink');
                        if (dupContainer && dupLink) {
                            dupLink.onclick = (e) => {
                                e.preventDefault();
                                const dupRow = rankings.find(r => r.id == errorData.duplicate_id);
                                if (dupRow) loadRankingIntoForm(dupRow);
                            };
                            dupContainer.classList.remove('hidden');
                        } else {
                            alert(errorData.error || 'This ranking already exists.');
                        }
                        return;
                    }
                    alert(errorData.error || 'Unable to save ranking.');
                    return;
                }
                form.reset();
                showList();
                await refresh();
            });
            document.getElementById('cancelRankingHistoryAdminForm').addEventListener('click', () => { form.reset(); showList(); });
            const close = () => { panel.classList.remove('active'); panel.setAttribute('aria-hidden', 'true'); };
            document.getElementById('closeRankingHistoryEditor').addEventListener('click', close);
            panel.addEventListener('click', event => { if (event.target === panel) close(); });
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel.classList.contains('active')) close(); });
            refresh();
        })();
    </script>
<?php require_once __DIR__.'/includes/footer.php'; ?>

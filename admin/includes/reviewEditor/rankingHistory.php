<?php
/**
 * Purpose: Review Editor markup partial for the ranking history section; included by the Review Editor page.
 */

?>
<div id="rankingHistoryEditorPanel" class="modal-overlay" aria-hidden="true">
        <div class="modal-card summary-card-editor-modal" role="dialog" aria-modal="true" aria-labelledby="rankingHistoryEditorHeading">
            <div class="modal-header">
                <h3 id="rankingHistoryEditorHeading" class="modal-title">Manage Ranking History</h3>
                <button type="button" id="closeRankingHistoryEditor" class="export-cancel-button" aria-label="Close">&times;</button>
            </div>
            <div id="rankingHistoryManagerView">
                <div style="display:flex; justify-content:space-between; align-items:end; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                    <label class="form-label" for="rankingHistoryAdminSearch" style="margin:0;">Search rankings
                        <input id="rankingHistoryAdminSearch" type="search" class="form-input" placeholder="Search organization, pack, year, rank, or custom fields…" aria-label="Search Ranking History by organization, pack, year, rank, information, or custom field" autocomplete="off" style="display:inline-block; width:min(300px, 65vw); margin-left:0.35rem;">
                    </label>
                    <label class="form-label" for="rankingHistoryAdminYearFilter" style="margin:0;">Filter year
                        <select id="rankingHistoryAdminYearFilter" class="form-input" style="display:inline-block; width:auto; min-width:130px; margin-left:0.35rem;"><option value="all">All years</option></select>
                    </label>
                    <label class="form-label" for="rankingHistoryAdminPackFilter" style="margin:0;">Filter pack
                        <select id="rankingHistoryAdminPackFilter" class="form-input" style="display:inline-block; width:auto; min-width:180px; margin-left:0.35rem;"><option value="all">All packs</option></select>
                    </label>

                    <div class="summary-card-manager-actions">
                        <button id="refreshRankingHistoryBtn" type="button" class="export-cancel-button" style="padding: 0.4rem 0.6rem;" title="Refresh data" aria-label="Refresh data">
                            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                        </button>
                        <label id="selectAllRankingLabel" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8rem; font-weight:700; cursor:pointer; color:var(--text-muted);">
                            <input type="checkbox" id="selectAllRanking" style="width:1rem;height:1rem; cursor:pointer;"> Select all
                        </label>
                        <button id="bulkDeleteRankingHistory" type="button" class="export-cancel-button" style="display:none; color:#B91C1C; border-color:#FECACA; background-color:#FEF2F2;">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete Selected (<span id="bulkDeleteCount">0</span>)
                        </button>
                        <details class="summary-card-manager-actions-menu">
                            <summary class="summary-card-manager-menu-toggle">
                                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Actions
                            </summary>
                            <div class="summary-card-manager-menu" aria-label="Ranking history actions">
                                <button id="bulkPublishRankingHistory" type="button" style="display:none;"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish Selected (<span id="bulkPublishRankingHistoryCount">0</span>)</button>
                                <button id="bulkUnpublishRankingHistory" type="button" style="display:none;"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i> Unpublish Selected (<span id="bulkUnpublishRankingHistoryCount">0</span>)</button>
                                <hr style="margin: 0.25rem 0; border: none; border-top: 1px solid var(--border-light);">
                                <button id="addRankingHistoryRow" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add ranking</button>
                                <button id="expandRankingHistoryGroups" type="button"><i class="fa-solid fa-expand" aria-hidden="true"></i> Expand all packs</button>
                                <button id="collapseRankingHistoryGroups" type="button"><i class="fa-solid fa-compress" aria-hidden="true"></i> Collapse all packs</button>
                                <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                                    <button type="button" data-ranking-body-manager-open aria-haspopup="dialog" aria-controls="rankingBodyManagerModal">
                                        <i class="fa-solid fa-building-columns" aria-hidden="true"></i> Manage ranking bodies
                                    </button>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                </div>
                <section class="summary-card-public-default" aria-labelledby="rankingHistoryChartDefaultHeading">
                    <div>
                        <h4 id="rankingHistoryChartDefaultHeading">Public chart defaults</h4>
                        <p>Choose which organization and list visitors see first on the public ranking chart. Visitors can still switch options.</p>
                    </div>
                    <label for="rankingHistoryChartDefaultOrganization">Public organization
                        <select id="rankingHistoryChartDefaultOrganization" class="form-input"><option value="">All organizations</option></select>
                    </label>
                    <label for="rankingHistoryChartDefaultList">Default list
                        <select id="rankingHistoryChartDefaultList" class="form-input"><option value="">All lists for selected organization</option></select>
                    </label>
                    <button id="saveRankingHistoryChartDefaults" type="button" class="btn-save-modal">Save chart defaults</button>
                    <span id="rankingHistoryChartDefaultsStatus" role="status" aria-live="polite"></span>
                </section>
                <div id="rankingHistoryAdminList" style="display:grid; gap:0.75rem;"></div>
            </div>
            <form id="rankingHistoryAdminForm" style="display:none;">
                <input type="hidden" id="rankingHistoryAdminId">
                <p id="rankingHistoryAdminNotice" role="status" aria-live="polite" hidden></p>
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:1rem;">
                    <label class="form-label">Organization<input type="text" id="rankingHistoryAdminBody" list="rankingBodySuggestions" class="form-input bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-lg" placeholder="Select or type organization..." required><datalist id="rankingBodySuggestions"></datalist></label>
                    <label class="form-label">Ranking type<input type="text" id="rankingHistoryAdminType" list="rankingTypeSuggestions" class="form-input bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-lg" maxlength="320" placeholder="QS Asia" required><datalist id="rankingTypeSuggestions"></datalist></label>
                    <label class="form-label">Year<input type="number" id="rankingHistoryAdminYear" class="form-input" min="1900" max="2200" step="1" required></label>
                    <label class="form-label">Rank display<input type="text" id="rankingHistoryAdminGlobalRank" class="form-input" maxlength="50" placeholder="161, 601-650, or 601+" required></label>
                    <label class="form-label" style="grid-column:1/-1;">Information<textarea id="rankingHistoryAdminInfoText" class="form-input" rows="3" placeholder="Additional context shown from the information icon"></textarea></label>
                    <section id="rankingHistoryCustomFields" style="grid-column:1/-1;" aria-labelledby="rankingHistoryCustomFieldsHeading">
                        <h4 id="rankingHistoryCustomFieldsHeading" class="form-label">Custom fields</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Fields configured in Manage Templates appear here automatically.</p>
                        <div id="rankingHistoryCustomFieldsList"></div>
                    </section>
                </div>
                <div id="formDuplicateError" class="p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg text-sm text-amber-800 dark:text-amber-200 font-medium mt-3" style="display:none;">This ranking already exists. <button type="button" id="editDuplicateLink" class="underline font-bold hover:text-amber-900 dark:hover:text-amber-100">Edit it instead?</button></div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                    <button type="button" id="cancelRankingHistoryAdminForm" class="export-cancel-button">Cancel</button>
                    <button type="submit" class="btn-save-modal"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save ranking</button>
                </div>
            </form>
        </div>
    </div>

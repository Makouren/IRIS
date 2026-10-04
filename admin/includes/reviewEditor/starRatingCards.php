<?php
/**
 * Purpose: Review Editor markup partial for the star rating cards section; included by the Review Editor page.
 */

?>
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
                    <label class="form-label">Display Order<input type="number" id="starRatingDisplayOrder" name="display_order" class="form-input" value="0" min="0" step="1"></label>
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

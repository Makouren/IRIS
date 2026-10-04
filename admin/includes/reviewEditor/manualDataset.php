<?php
/**
 * Purpose: Review Editor markup partial for the manual dataset section; included by the Review Editor page.
 */

?>
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

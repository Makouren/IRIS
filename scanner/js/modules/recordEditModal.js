import { $, parseEditableValue, escapeHtml } from '../utils/helpers.js';

export function initRecordEditModal(ctx) {
  ctx.api.openRecordEditModal = async recordId => {
    const record = (await ctx.dbManager.getAllRecords()).find(item => item.id === recordId); if (!record) return;
    $('recordEditTitle').textContent = `Edit Record: ${record.fileName}`; const body = $('recordEditBody'); body.innerHTML = `<div class="modal-editor-section"><label>File Name<input id="editFileName" value="${escapeHtml(record.fileName)}"></label><label>Category / Classification<input id="editDocType" value="${escapeHtml(record.docType || 'General Institutional Data')}"></label><label>Publication Status<select id="editStatus"><option value="Pending Review">Pending Review</option><option value="Approved">Published</option><option value="Needs Revision">Needs Revision</option></select></label><label>Admin Verification Notes<textarea id="editAdminNotes">${escapeHtml(record.adminNotes || '')}</textarea></label></div>`;
    $('editFileName').required = true;
    $('editFileName').maxLength = 255;
    $('editDocType').required = true;
    $('editDocType').maxLength = 100;
    $('editAdminNotes').maxLength = 65535;
    $('editStatus').value = record.status || 'Pending Review';
    const sheets = Object.keys(record.extractedData || {}).filter(key => Array.isArray(record.extractedData[key]?.headers) && Array.isArray(record.extractedData[key]?.rows));
    if (sheets.length) { const key = sheets[0]; const sheet = record.extractedData[key]; const section = document.createElement('div'); section.className = 'modal-editor-section'; section.innerHTML = `<h4><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Extracted Table Cells (${escapeHtml(key)})</h4><button id="btnAddRowBtn" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Row</button><div class="table-container"><table class="data-table"><thead><tr>${sheet.headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}<th>Action</th></tr></thead><tbody>${sheet.rows.slice(0, 50).map((row, rowIndex) => `<tr>${sheet.headers.map((_, column) => `<td><input class="cell-input" data-sheet="${escapeHtml(key)}" data-row="${rowIndex}" data-col="${column}" value="${escapeHtml(row[column] ?? '')}"></td>`).join('')}<td><button type="button" class="btn-delete-row" data-row="${rowIndex}" aria-label="Remove row ${rowIndex + 1}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></td></tr>`).join('')}</tbody></table></div>`; body.appendChild(section); section.querySelector('#btnAddRowBtn').onclick = () => { sheet.rows.unshift(sheet.headers.map(() => '')); ctx.api.openRecordEditModal(recordId); }; section.querySelectorAll('.btn-delete-row').forEach(button => button.onclick = () => { if (!window.confirm(`Remove row ${Number(button.dataset.row) + 1} from this draft?`)) return; sheet.rows.splice(Number(button.dataset.row), 1); ctx.api.openRecordEditModal(recordId); }); }
    const text = document.createElement('div'); text.className = 'modal-editor-section'; text.innerHTML = `<label><i class="fa-solid fa-file-pen" aria-hidden="true"></i> Extracted Text Content<textarea id="editRawText" rows="6">${escapeHtml(record.rawText || '')}</textarea></label>`; body.appendChild(text);
    const actions = document.createElement('div'); actions.innerHTML = '<p id="recordEditValidationError" role="alert" aria-live="assertive" hidden></p><button id="btnCancelEdit" type="button">Cancel</button><button id="btnSaveRecordChanges" type="button"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Changes</button><button id="btnApproveDraft" type="button"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>'; body.appendChild(actions);
    $('btnCancelEdit').onclick = () => $('recordEditModal').classList.remove('active'); $('btnSaveRecordChanges').onclick = () => ctx.api.saveModalData(record, recordId, false); $('btnApproveDraft').onclick = () => ctx.api.saveModalData(record, recordId, true); $('recordEditModal').classList.add('active');
  };
  ctx.api.saveModalData = async (record, id, approve) => {
    const fileNameInput = $('editFileName');
    const docTypeInput = $('editDocType');
    const errorNotice = $('recordEditValidationError');
    for (const [input, label] of [[fileNameInput, 'File name'], [docTypeInput, 'Category']]) {
      input.setCustomValidity(input.value.trim() ? '' : `${label} is required.`);
    }
    const statusInput = $('editStatus');
    statusInput.setCustomValidity(['Pending Review', 'Approved', 'Needs Revision'].includes(statusInput.value) ? '' : 'Choose a valid publication status.');
    if (!fileNameInput.reportValidity() || !docTypeInput.reportValidity() || !statusInput.reportValidity()) return;
    const saveButton = $('btnSaveRecordChanges');
    const publishButton = $('btnApproveDraft');
    if (saveButton.disabled || publishButton.disabled) return;
    saveButton.disabled = true;
    publishButton.disabled = true;
    errorNotice.hidden = true;
    document.querySelectorAll('.cell-input').forEach(input => { const sheet = record.extractedData[input.dataset.sheet]; const row = Number(input.dataset.row); const column = Number(input.dataset.col); if (sheet?.rows?.[row]) sheet.rows[row][column] = parseEditableValue(input.value); });
    try {
      const updated = await ctx.dbManager.updateRecord(id, { fileName: fileNameInput.value.trim(), docType: docTypeInput.value.trim(), status: approve ? 'Approved' : statusInput.value, adminNotes: $('editAdminNotes').value.trim(), rawText: $('editRawText').value, extractedData: record.extractedData });
      if (ctx.state.activeScan?.id === id) { ctx.state.activeScan = { ...ctx.state.activeScan, ...updated }; await ctx.api.renderOverviewTab(ctx.state.activeScan); ctx.api.renderViewerTab(ctx.state.activeScan); }
      $('recordEditModal').classList.remove('active'); await ctx.api.renderAdminPortal();
    } catch (error) {
      errorNotice.textContent = `Unable to save record: ${error.message || error}`;
      errorNotice.hidden = false;
    } finally {
      saveButton.disabled = false;
      publishButton.disabled = false;
    }
  };
  $('btnCloseRecordModal')?.addEventListener('click', () => $('recordEditModal').classList.remove('active'));
}

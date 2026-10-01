import { $, escapeHtml, parseEditableValue } from '../utils/helpers.js';
export function initTableGrid(ctx) {
  ctx.api.ensureTableDataStructure = record => {
    if (!record || !record.extractedData || typeof record.extractedData !== 'object' || Array.isArray(record.extractedData)) {
      if (record) record.extractedData = {};
      return;
    }
    Object.values(record.extractedData).forEach(sheet => {
      if (!sheet || typeof sheet !== 'object') return;
      if (!Array.isArray(sheet.headers)) sheet.headers = [];
      if (!Array.isArray(sheet.rows)) sheet.rows = [];
    });
  };
  ctx.api.getStudioActiveSheet = record => {
    const data = record?.extractedData;
    if (!data || typeof data !== 'object' || Array.isArray(data)) return null;
    const keys = Object.keys(data).filter(key => Array.isArray(data[key]?.headers) && Array.isArray(data[key]?.rows));
    const name = ctx.state.docWindowActiveSheetKey && keys.includes(ctx.state.docWindowActiveSheetKey)
      ? ctx.state.docWindowActiveSheetKey
      : keys[0];
    if (!name) return null;
    ctx.state.docWindowActiveSheetKey = name;
    return { name, data: data[name] };
  };
    ctx.api.renderStudioTableGrid = record => {
      const container = $('studioTableContainer'); const info = ctx.api.getStudioActiveSheet(record); if (!container) return;
      if (!info?.data) { container.textContent = 'No parsed spreadsheet data is available for this record. Re-upload the file to extract its contents.'; return; }
      const sheet = info.data;
      let html = '<table class="data-table"><thead><tr>';
      sheet.headers.forEach((header, column) => { html += `<th><div class="header-cell-box"><button type="button" class="btn-delete-col" data-col="${column}" ${sheet.headers.length <= 1 ? 'disabled' : ''}><i class="fa-solid fa-xmark" aria-hidden="true"></i></button><input type="text" class="header-rename-input" data-col="${column}" value="${escapeHtml(header || '')}" placeholder="Field Name..."></div></th>`; });
      html += '<th>Action</th></tr></thead><tbody>';
      (sheet.rows || []).forEach((row, rowIndex) => { html += `<tr>${sheet.headers.map((_, column) => `<td><input type="text" class="studio-cell-input" data-row="${rowIndex}" data-col="${column}" value="${escapeHtml(row[column] ?? '')}"></td>`).join('')}<td><button type="button" class="studio-delete-row" data-row="${rowIndex}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></td></tr>`; });
      container.innerHTML = `${html}</tbody></table>`;
      container.querySelectorAll('.studio-cell-input').forEach(input => input.addEventListener('input', () => { sheet.rows[Number(input.dataset.row)][Number(input.dataset.col)] = parseEditableValue(input.value); ctx.api.updateStudioChart(); }));
      container.querySelectorAll('.header-rename-input').forEach(input => input.addEventListener('input', () => { const column = Number(input.dataset.col); sheet.headers[column] = input.value.trim() || `Field_${column + 1}`; ctx.api.updateFieldSelectOptions(sheet); ctx.api.updateStudioChart(); }));
      container.querySelectorAll('.btn-delete-col').forEach(button => button.addEventListener('click', () => { const column = Number(button.dataset.col); if (sheet.headers.length <= 1 || !confirm(`Are you sure you want to delete the field "${sheet.headers[column]}"?`)) return; sheet.headers.splice(column, 1); sheet.rows.forEach(row => row.splice(column, 1)); ctx.api.updateFieldSelectOptions(sheet); ctx.api.renderStudioTableGrid(record); ctx.api.updateStudioChart(); }));
      container.querySelectorAll('.studio-delete-row').forEach(button => button.addEventListener('click', () => { sheet.rows.splice(Number(button.dataset.row), 1); ctx.api.renderStudioTableGrid(record); ctx.api.updateStudioChart(); }));
    };
    $('studioBtnAddField')?.addEventListener('click', () => { const info = ctx.api.getStudioActiveSheet(ctx.state.studioActiveRecord); if (!info) return; info.data.headers.push(`Field_${info.data.headers.length + 1}`); info.data.rows.forEach(row => row.push('')); ctx.api.updateFieldSelectOptions(info.data); ctx.api.renderStudioTableGrid(ctx.state.studioActiveRecord); ctx.api.updateStudioChart(); });
    $('studioBtnAddRow')?.addEventListener('click', () => { const info = ctx.api.getStudioActiveSheet(ctx.state.studioActiveRecord); if (!info) return; info.data.rows.unshift(info.data.headers.map(() => '')); ctx.api.renderStudioTableGrid(ctx.state.studioActiveRecord); ctx.api.updateStudioChart(); });
}

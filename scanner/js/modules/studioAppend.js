/**
 * Purpose: Scanner interface module for studio append; loaded by the Scanner application.
 */
import { $, escapeHtml } from '../utils/helpers.js';

const MAX_PREVIEW_ROWS = 200;

function statusLabel(status) {
  return status === 'inserted' ? 'INSERTED' : status === 'updated' ? 'UPDATED' : status === 'unchanged' ? 'UNCHANGED' : 'SKIPPED';
}

function rowStatusColor(status) {
  if (status === 'inserted') return '#dcfce7';
  if (status === 'updated') return '#fef3c7';
  if (status === 'unchanged') return '#f1f5f9';
  return '#fee2e2';
}

function sheetsFor(record) {
  const data = record?.extractedData;
  if (!data || typeof data !== 'object' || Array.isArray(data)) return [];
  return Object.entries(data).filter(([, sheet]) => Array.isArray(sheet?.headers) && Array.isArray(sheet?.rows));
}

function bestSheetFor(record, activeSheetName, activeHeaders, allowPartialHeaderOverlap = false) {
  const sheets = sheetsFor(record);
  if (!sheets.length) return null;
  const exact = sheets.find(([name]) => name === activeSheetName);
  if (exact) return { name: exact[0], sheet: exact[1] };
  if (sheets.length === 1) return { name: sheets[0][0], sheet: sheets[0][1] };
  const active = new Set(activeHeaders.map(normalizedHeader).filter(Boolean));
  const ranked = sheets.map(([name, sheet]) => {
    const candidate = new Set(sheet.headers.map(normalizedHeader).filter(Boolean));
    const overlap = [...active].filter(header => candidate.has(header)).length;
    return { name, sheet, ratio: active.size ? overlap / active.size : 0 };
  }).sort((left, right) => right.ratio - left.ratio);
  return (allowPartialHeaderOverlap ? ranked[0]?.ratio > 0 : ranked[0]?.ratio >= 0.5)
    ? { name: ranked[0].name, sheet: ranked[0].sheet }
    : null;
}

function normalizedTemplateId(record) {
  const templateId = record?.template_id;
  return templateId === null || templateId === undefined || templateId === '' || String(templateId) === '0'
    ? null
    : String(templateId);
}

function canMergeTemplates(left, right) {
  const leftTemplateId = normalizedTemplateId(left);
  const rightTemplateId = normalizedTemplateId(right);
  return leftTemplateId === null && rightTemplateId === null
    || leftTemplateId !== null && leftTemplateId === rightTemplateId;
}

function normalizedHeader(value) {
  return String(value ?? '').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
}

function previewTable(sheet, rowStatuses = null) {
  const headers = sheet.headers || [];
  const rows = sheet.rows || [];
  const visible = rows.slice(0, MAX_PREVIEW_ROWS);
  const head = headers.map(header => `<th style="position:sticky;top:0;background:#f1f5f9;padding:.4rem .55rem;text-align:left;white-space:nowrap;">${escapeHtml(header)}</th>`).join('');
  const body = visible.map((row, rowIndex) => {
    const status = rowStatuses?.[rowIndex];
    const marker = status ? `<td style="padding:.35rem .5rem;white-space:nowrap;background:${rowStatusColor(status)}"><strong>${statusLabel(status)}</strong></td>` : '';
    const cells = headers.map((_, column) => `<td style="padding:.35rem .5rem;border-top:1px solid #e2e8f0;white-space:nowrap;">${escapeHtml(row?.[column] instanceof Date ? row[column].toISOString().slice(0, 10) : row?.[column] ?? '')}</td>`).join('');
    return `<tr>${marker}${cells}</tr>`;
  }).join('');
  return `<div style="max-height:360px;overflow:auto;border:1px solid #cbd5e1;border-radius:6px"><table style="border-collapse:collapse;min-width:100%;font-size:.78rem"><thead><tr>${rowStatuses ? '<th style="position:sticky;top:0;background:#f1f5f9;padding:.4rem .55rem">Merge</th>' : ''}${head}</tr></thead><tbody>${body}</tbody></table></div><p style="margin:.4rem 0 0;color:#64748b;font-size:.75rem">${visible.length} of ${rows.length} rows shown${rows.length > MAX_PREVIEW_ROWS ? ` (preview capped at ${MAX_PREVIEW_ROWS})` : ''}</p>`;
}

export function initStudioAppend(ctx) {
  const mergeButton = $('studioBtnMergeUpload');
  const superAdmin = mergeButton?.dataset.role === 'super_admin';
  if (!superAdmin || !ctx?.state || !ctx?.dbManager || !ctx?.api || !window.SheetMerge) return;

  const activeRecord = () => ctx.state.studioActiveRecord;
  const activeSheet = () => {
    const record = activeRecord();
    const info = record && ctx.api.getStudioActiveSheet?.(record);
    return info?.data && Array.isArray(info.data.headers) && Array.isArray(info.data.rows) ? info : null;
  };

  const syncStudioHeader = () => {
    const record = activeRecord();
    const fileLabel = $('studioActiveFileName');
    if (!record || !fileLabel) return;
    $('studioMergeNewBadge')?.remove();
    $('studioRestorePreviousFile')?.remove();
    const merge = record.metadata?.merge;
    if (window.SheetMerge.isRecentMerge(record.metadata, new Date())) {
      const badge = document.createElement('span');
      badge.id = 'studioMergeNewBadge';
      badge.className = 'badge badge-low';
      badge.textContent = 'NEW';
      badge.title = `Merged ${merge.merged_at || ''} from ${merge.source_office || 'office'}, replaced ${merge.replaced_file || 'previous file'}`;
      badge.style.marginLeft = '.5rem';
      fileLabel.after(badge);
    }
    if (!merge?.trash_id) return;
    const restore = document.createElement('button');
    restore.id = 'studioRestorePreviousFile';
    restore.type = 'button';
    restore.className = 'btn-studio-action';
    restore.textContent = 'Restore previous file';
    restore.title = 'Restore the previous file and office upload';
    restore.style.marginLeft = '.5rem';
    restore.addEventListener('click', restorePreviousFile);
    ($('studioMergeNewBadge') || fileLabel).after(restore);
  };

  const showModal = content => {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = content;
    document.body.appendChild(overlay);
    return overlay;
  };

  const openSourcePicker = candidates => {
    const modal = showModal(`<div class="modal-card" style="max-width:720px;max-height:calc(100vh - 2rem);overflow:auto">
      <div class="modal-header"><h3 class="modal-title">Choose merge direction and record</h3><button type="button" class="export-cancel-button" data-cancel>Cancel</button></div>
      <p style="margin:.5rem 0 1rem">Choose a direction first, then select the other record. Source values replace differing values in matching rows; blank source cells leave target values unchanged. Target-only rows stay.</p>
      <fieldset style="display:grid;gap:.75rem;margin:0 0 1rem;padding:.75rem;border:1px solid #cbd5e1;border-radius:6px">
        <legend>Merge direction</legend>
        <label style="display:grid;grid-template-columns:auto 1fr;gap:.5rem;align-items:start">
          <input type="radio" name="merge-direction" value="active-target" style="margin-top:.25rem">
          <span><strong>Merge the selected record (A) into the active record (B)</strong><br><small>IRIS applies A’s changes and new rows to B; B remains as the updated record, including its existing-only rows. A is kept by default. If you select “Delete the source record after merging” on the next screen, A is deleted from the records list, while its archived version remains in File History.</small></span>
        </label>
        <label style="display:grid;grid-template-columns:auto 1fr;gap:.5rem;align-items:start">
          <input type="radio" name="merge-direction" value="active-source" style="margin-top:.25rem">
          <span><strong>Merge the active record (A) into the selected record (B)</strong><br><small>IRIS applies A’s changes and new rows to B; B remains as the updated record, including its existing-only rows. A is kept by default. If you select “Delete the source record after merging” on the next screen, A is deleted from the records list, while its archived version remains in File History.</small></span>
        </label>
      </fieldset>
      <p style="margin:.5rem 0;font-weight:700">Select the record to use as the other side of the merge:</p>
      <p data-picker-error role="alert" aria-live="assertive" style="color:#b91c1c"></p>
      <div style="display:grid;gap:.5rem">${candidates.map(item => `<button type="button" class="archive-load-button" data-source="${escapeHtml(item.record.id)}" style="display:flex;justify-content:space-between;gap:1rem;text-align:left"><span><strong>${escapeHtml(item.record.fileName || 'Untitled')}</strong><br><small>${escapeHtml(item.record.office_name || 'Record')} · ${escapeHtml(item.record.status || '')} · ${escapeHtml(item.sheet.name)}</small></span><span>${item.sheet.sheet.rows.length} rows</span></button>`).join('')}</div>
      </div>`);
    modal.querySelectorAll('[data-cancel]').forEach(button => button.onclick = () => modal.remove());
    modal.addEventListener('click', event => {
      const button = event.target.closest('[data-source]');
      if (!button) return;
      const direction = modal.querySelector('[name="merge-direction"]:checked')?.value;
      if (!direction) {
        const error = modal.querySelector('[data-picker-error]');
        error.textContent = 'Select a merge direction before choosing a record.';
        modal.querySelector('[name="merge-direction"]')?.focus();
        return;
      }
      const selected = candidates.find(item => String(item.record.id) === button.dataset.source);
      modal.remove();
      if (selected) openMergeReview(selected, direction);
    });
  };

  const openMergeReview = (selected, direction) => {
    const active = activeRecord();
    const activeInfo = activeSheet();
    if (!active || !activeInfo) return;
    const activeIsTarget = direction === 'active-target';
    const source = activeIsTarget ? selected.record : active;
    const target = activeIsTarget ? active : selected.record;
    const sourceInfo = activeIsTarget ? selected.sheet : { name: activeInfo.name, sheet: activeInfo.data };
    const targetInfo = activeIsTarget ? { name: activeInfo.name, sheet: activeInfo.data } : selected.sheet;
    const isGeneralPair = normalizedTemplateId(source) === null && normalizedTemplateId(target) === null;
    const sourceHeaders = new Set(sourceInfo.sheet.headers.map(normalizedHeader).filter(Boolean));
    const sharedKeyColumns = targetInfo.sheet.headers
      .map((header, index) => ({ header, index }))
      .filter(({ header }) => sourceHeaders.has(normalizedHeader(header)));
    const sharedKeyColumnIndices = new Set(sharedKeyColumns.map(({ index }) => index));
    let keyColumns = window.SheetMerge.defaultKeyColumns(targetInfo.sheet.headers, targetInfo.sheet.rows)
      .filter(index => sharedKeyColumnIndices.has(index))
      .slice(0, 1);
    if (!keyColumns.length && sharedKeyColumns.length) keyColumns = [sharedKeyColumns[0].index];
    let latestPreview = null;
    let previewSequence = 0;
    let previewError = '';
    let confirmed = false;
    const modal = showModal(`<div class="modal-card" style="max-width:1200px;max-height:calc(100vh - 2rem);overflow:auto">
      <div class="modal-header"><h3 class="modal-title">Review record merge</h3><button type="button" class="export-cancel-button" data-cancel>Cancel</button></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1rem">
        <section><h4 style="font-weight:800;margin:.35rem 0">SOURCE — changes come from this record</h4><p>${escapeHtml(source.fileName || 'Untitled')} · Record ${escapeHtml(source.id)} · ${escapeHtml(sourceInfo.name)} · ${sourceInfo.sheet.rows.length} rows</p><p>Its matching-row changes and new rows are applied to the target below.</p><div data-source-preview></div></section>
        <section style="padding:.65rem;border:2px solid #16a34a;border-radius:6px;background:#f0fdf4"><h4 style="font-weight:800;margin:.35rem 0;color:#166534">TARGET — THIS BECOMES THE UPDATED RECORD</h4><p><strong>${escapeHtml(target.fileName || 'Untitled')}</strong> · Record ${escapeHtml(target.id)} · ${escapeHtml(targetInfo.name)} · ${targetInfo.sheet.rows.length} rows</p><p>This record remains under its current ID and contains the merged result. The source is not made into the new record.</p><div data-target-preview></div></section>
      </div>
      <section style="margin-top:1rem"><h4 style="font-weight:800">Key columns</h4>${isGeneralPair ? '<p>General worksheets can have different layouts. IRIS uses a shared column to match rows and merges only columns present in both worksheets; source-only columns are ignored.</p>' : ''}<div data-key-list style="display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0"></div></section>
      <section data-conflicts style="margin:.75rem 0"></section>
      <section data-validation role="status" aria-live="polite" style="margin:.75rem 0;padding:.75rem;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px"></section>
      <section data-merged-preview style="margin:.75rem 0"></section>
      <label style="display:flex;gap:.5rem;align-items:flex-start;margin:1rem 0"><input type="checkbox" data-consume-source><span>Delete the source record after merging (off by default; the source version remains in File History).</span></label>
      <label style="display:flex;gap:.5rem;align-items:flex-start;margin:1rem 0"><input type="checkbox" data-reviewed><span>I reviewed the source, target, and merge result.</span></label>
      <div style="display:flex;justify-content:flex-end;gap:.5rem"><button type="button" class="export-cancel-button" data-cancel>Cancel</button><button type="button" class="btn-save-modal" data-merge disabled>Merge</button></div>
    </div>`);

    modal.querySelectorAll('[data-cancel]').forEach(button => button.onclick = () => modal.remove());
    modal.querySelector('[data-source-preview]').innerHTML = previewTable(sourceInfo.sheet);
    modal.querySelector('[data-target-preview]').innerHTML = previewTable(targetInfo.sheet);
    const keyList = modal.querySelector('[data-key-list]');
    sharedKeyColumns.forEach(({ header, index }) => {
      const label = document.createElement('label');
      label.style.cssText = 'display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .45rem;border:1px solid #cbd5e1;border-radius:4px';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.dataset.keyColumn = String(index);
      checkbox.checked = keyColumns.includes(index);
      const text = document.createElement('span');
      text.textContent = header;
      label.append(checkbox, text);
      keyList.appendChild(label);
    });
    if (!sharedKeyColumns.length) {
      keyList.textContent = 'These worksheets do not have a shared header to use as a merge key.';
    }

    const refreshReview = async () => {
      keyColumns = [...modal.querySelectorAll('[data-key-column]:checked')].map(input => Number(input.dataset.keyColumn));
      const sequence = ++previewSequence;
      const validation = modal.querySelector('[data-validation]');
      const conflictSection = modal.querySelector('[data-conflicts]');
      const mergeButton = modal.querySelector('[data-merge]');
      const mergedPreview = modal.querySelector('[data-merged-preview]');
      latestPreview = null;
      mergeButton.disabled = true;
      mergedPreview.innerHTML = '';
      if (!sharedKeyColumns.length) {
        validation.textContent = 'These worksheets do not share a header. They cannot be matched safely for merging.';
        conflictSection.innerHTML = '';
        return;
      }
      if (!keyColumns.length) {
        validation.textContent = 'Select at least one key column.';
        conflictSection.innerHTML = '';
        return;
      }
      validation.textContent = 'Preparing merge preview...';
      try {
        const preview = await ctx.dbManager.previewRecordMerge({
          source_id: source.id,
          target_id: target.id,
          source_sheet_name: sourceInfo.name,
          target_sheet_name: targetInfo.name,
          key_columns: keyColumns,
          method: 'merge'
        });
        if (!modal.isConnected || sequence !== previewSequence) return;
        latestPreview = preview;
        previewError = '';
        conflictSection.innerHTML = '';
        validation.innerHTML = `<strong style="color:#166534">Source values will replace differing target values.</strong><p>Inserted ${preview.stats.inserted}; updated ${preview.stats.updated}; unchanged ${preview.stats.unchanged}; skipped ${preview.stats.skipped}; duplicate incoming ${preview.stats.duplicateIncoming}; duplicate existing ${preview.stats.duplicateExisting}.</p><p>Ignored source-only columns: ${(preview.stats.ignoredColumns || []).map(escapeHtml).join(', ') || 'none'}</p>`;
        if (preview.sheet) {
          mergedPreview.innerHTML = `<h4 style="font-weight:800">Proposed target result</h4>${previewTable(preview.sheet, preview.stats?.rowStatus)}`;
        }
        mergeButton.disabled = !(preview.sheet && preview.unresolved === 0 && confirmed);
      } catch (error) {
        if (!modal.isConnected || sequence !== previewSequence) return;
        previewError = error.message || String(error);
        validation.textContent = previewError;
        conflictSection.innerHTML = '';
      }
    };

    keyList.addEventListener('change', () => {
      refreshReview();
    });
    modal.querySelector('[data-reviewed]').addEventListener('change', event => {
      confirmed = event.currentTarget.checked;
      refreshReview();
    });
    modal.querySelector('[data-merge]').onclick = async event => {
      if (!latestPreview?.sheet || latestPreview.unresolved || !confirmed || previewError) return;
      const button = event.currentTarget;
      button.disabled = true;
      button.textContent = 'Merging...';
      let result;
      try {
        result = await ctx.dbManager.mergeRecords({
          source_id: source.id,
          target_id: target.id,
          source_sheet_name: sourceInfo.name,
          target_sheet_name: targetInfo.name,
          key_columns: keyColumns,
          source_digest: latestPreview.source_digest,
          target_digest: latestPreview.target_digest,
          method: 'merge',
          consume_source: modal.querySelector('[data-consume-source]').checked
        });
      } catch (error) {
        button.disabled = false;
        button.textContent = 'Merge';
        alert(`Merge was not applied: ${error.message} Refresh the preview and try again.`);
        await refreshReview();
        return;
      }
      modal.remove();
      try {
        const records = await ctx.dbManager.getAllRecords();
        const mergedRecord = records.find(record => String(record.id) === String(target.id));
        if (!mergedRecord) throw new Error('The target record could not be reloaded.');
        ctx.state.studioActiveRecord = mergedRecord;
        ctx.state.docWindowActiveSheetKey = targetInfo.name;
        window.IRIS_STUDIO_DIRTY = false;
        ctx.api.renderStudioTableGrid(mergedRecord);
        ctx.api.updateStudioChart();
        syncStudioHeader();
        await ctx.api.renderAdminPortal();
        alert(`Merge complete. File History contains the pre-merge target, source-at-merge, and post-merge result.${result.source_deleted ? ' The source record was deleted; its archived version remains restorable.' : ''}`);
      } catch (error) {
        alert(`The merge succeeded and is archived in File History, but the page could not refresh: ${error.message}`);
      }
    };
    refreshReview();
  };

  async function restorePreviousFile() {
    const record = activeRecord();
    if (!record || !superAdmin) return;
    if (window.IRIS_STUDIO_DIRTY) {
      alert('Save or discard current edits first');
      return;
    }
    const merge = record.metadata?.merge;
    if (!merge?.trash_id) return;
    if (!confirm(`Restore ${merge.replaced_file || 'the previous file'}? The record returns to its pre-merge data, the office upload goes back to the Pending Review queue, and any edits made since the merge are lost. The saved graph is not reverted; click Save Graph afterwards.`)) return;
    const button = $('studioRestorePreviousFile');
    if (button) button.disabled = true;
    try {
      const result = await ctx.dbManager.restoreMerge(record.id, merge.trash_id);
      const restored = result.record;
      if (!restored) throw new Error('Restore returned no record.');
      ctx.state.studioActiveRecord = restored;
      ctx.state.docWindowActiveSheetKey = ctx.api.getStudioActiveSheet(restored)?.name || '';
      window.IRIS_STUDIO_DIRTY = false;
      ctx.api.renderStudioTableGrid(restored);
      ctx.api.updateStudioChart();
      syncStudioHeader();
      await ctx.api.renderAdminPortal();
      alert(result.file_restored === false
        ? 'Record data was restored, but the previous source file could not be moved back. The saved graph was not reverted.'
        : 'Previous file and record data restored. The saved graph was not reverted; click Save Graph afterwards.');
    } catch (error) {
      if (button) button.disabled = false;
      alert(`Unable to restore the previous file: ${error.message || error}`);
    }
  }

  mergeButton.addEventListener('click', async () => {
    if (!superAdmin) return;
    const record = activeRecord();
    const info = activeSheet();
    if (!record || !info) {
      alert('Open a record with a readable worksheet before merging.');
      return;
    }
    if (window.IRIS_STUDIO_DIRTY === true) {
      alert('Save or discard current edits first');
      return;
    }
    let records;
    try {
      records = await ctx.dbManager.getAllRecords();
    } catch (error) {
      alert(`Unable to load records: ${error.message || error}`);
      return;
    }
    const otherSpreadsheetRecords = records.filter(candidate =>
      String(candidate.id) !== String(record.id)
      && ['xlsx', 'csv', 'tsv'].includes(String(candidate.fileType || '').toLowerCase())
    );
    const activeTemplateId = normalizedTemplateId(record);
    const hasGeneralMismatch = otherSpreadsheetRecords.some(candidate =>
      (activeTemplateId === null) !== (normalizedTemplateId(candidate) === null)
    );
    const hasDifferentTemplate = otherSpreadsheetRecords.some(candidate => {
      const candidateTemplateId = normalizedTemplateId(candidate);
      return activeTemplateId !== null && candidateTemplateId !== null && activeTemplateId !== candidateTemplateId;
    });
    const candidates = otherSpreadsheetRecords.flatMap(candidate => {
      if (!canMergeTemplates(record, candidate)) return [];
      const isGeneralPair = activeTemplateId === null && normalizedTemplateId(candidate) === null;
      const sheet = bestSheetFor(candidate, info.name, info.data.headers, isGeneralPair);
      return sheet ? [{ record: candidate, sheet }] : [];
    });
    if (!candidates.length) {
      const message = hasGeneralMismatch
        ? 'A General (uncategorized) record can only be merged with another General record, not one assigned to a template.'
        : hasDifferentTemplate
          ? 'Records assigned to different templates cannot be merged. Choose another record with the same template.'
          : activeTemplateId === null
            ? 'No other General (uncategorized) record with a compatible worksheet was found.'
            : 'No other record with the same template and a compatible worksheet was found.';
      alert(message);
      return;
    }
    openSourcePicker(candidates);
  });

  const fileNameElement = $('studioActiveFileName');
  if (fileNameElement && typeof MutationObserver !== 'undefined') {
    new MutationObserver(syncStudioHeader).observe(fileNameElement, { childList: true, characterData: true, subtree: true });
  }
  syncStudioHeader();
}
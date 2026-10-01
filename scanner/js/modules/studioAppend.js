import { $, escapeHtml } from '../utils/helpers.js';

const MAX_PREVIEW_ROWS = 200;

function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

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

function bestSheetFor(record, activeSheetName, activeHeaders) {
  const sheets = sheetsFor(record);
  if (!sheets.length) return null;
  const exact = sheets.find(([name]) => name === activeSheetName);
  if (exact) return { name: exact[0], sheet: exact[1] };
  if (sheets.length === 1) return { name: sheets[0][0], sheet: sheets[0][1] };
  const normalize = value => window.TableFilter?.normalize?.(value) || String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
  const active = new Set(activeHeaders.map(normalize).filter(Boolean));
  const ranked = sheets.map(([name, sheet]) => {
    const candidate = new Set(sheet.headers.map(normalize).filter(Boolean));
    const overlap = [...active].filter(header => candidate.has(header)).length;
    return { name, sheet, ratio: active.size ? overlap / active.size : 0 };
  }).sort((left, right) => right.ratio - left.ratio);
  return ranked[0]?.ratio >= 0.5 ? { name: ranked[0].name, sheet: ranked[0].sheet } : null;
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

function downloadRecordBackup(record) {
  const backup = { id: record.id, fileName: record.fileName, extractedData: record.extractedData, metadata: record.metadata };
  const payload = [
    'IRIS RECORD BACKUP - BEFORE OFFICE MERGE',
    `Created: ${new Date().toISOString()}`,
    `Record ID: ${record.id}`,
    `File name: ${record.fileName || ''}`,
    '',
    'Record data (JSON-formatted text):',
    JSON.stringify(backup, null, 2)
  ].join('\n');
  const url = URL.createObjectURL(new Blob([payload], { type: 'text/plain;charset=utf-8' }));
  const link = document.createElement('a');
  const safeName = String(record.fileName || record.id).replace(/[^a-z0-9._-]+/gi, '_');
  link.href = url;
  link.download = `${safeName}.before-office-merge.txt`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
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
      <div class="modal-header"><h3 class="modal-title">Choose office upload</h3><button type="button" class="export-cancel-button" data-cancel>Cancel</button></div>
      <p style="margin:.5rem 0 1rem">Choose a Pending Review office upload on the same template and worksheet.</p>
      <div style="display:grid;gap:.5rem">${candidates.map(item => `<button type="button" class="archive-load-button" data-source="${escapeHtml(item.record.id)}" style="display:flex;justify-content:space-between;gap:1rem;text-align:left"><span><strong>${escapeHtml(item.record.fileName || 'Untitled')}</strong><br><small>${escapeHtml(item.record.office_name || 'Office')} · ${escapeHtml(item.record.uploaded_at || '')} · ${escapeHtml(item.sheet.name)}</small></span><span>${item.sheet.sheet.rows.length} rows</span></button>`).join('')}</div>
      </div>`);
    modal.querySelector('[data-cancel]').onclick = () => modal.remove();
    modal.addEventListener('click', event => {
      const button = event.target.closest('[data-source]');
      if (!button) return;
      const selected = candidates.find(item => String(item.record.id) === button.dataset.source);
      modal.remove();
      if (selected) openMergeReview(selected);
    });
  };

  const openMergeReview = selected => {
    const oldRecord = activeRecord();
    const oldInfo = activeSheet();
    if (!oldRecord || !oldInfo) return;
    let keyColumns = window.SheetMerge.defaultKeyColumns(oldInfo.data.headers, oldInfo.data.rows);
    let latestResult = null;
    let latestValidation = null;
    let confirmed = false;
    const modal = showModal(`<div class="modal-card" style="max-width:1200px;max-height:calc(100vh - 2rem);overflow:auto">
      <div class="modal-header"><h3 class="modal-title">Review office upload merge</h3><button type="button" class="export-cancel-button" data-cancel>Cancel</button></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1rem">
        <section><h4 style="font-weight:800;margin:.35rem 0">Current file (old)</h4><p>${escapeHtml(oldRecord.fileName || 'Untitled')} · ${escapeHtml(oldInfo.name)} · ${oldInfo.data.rows.length} rows</p><div data-old-preview></div></section>
        <section><h4 style="font-weight:800;margin:.35rem 0">Office upload (new)</h4><p>${escapeHtml(selected.record.fileName || 'Untitled')} · ${escapeHtml(selected.record.office_name || 'Office')} · ${escapeHtml(selected.sheet.name)} · ${selected.sheet.sheet.rows.length} rows</p><div data-new-preview></div></section>
      </div>
      <section style="margin-top:1rem"><h4 style="font-weight:800">Key columns</h4><div data-key-list style="display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0"></div></section>
      <section data-validation style="margin:.75rem 0;padding:.75rem;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px"></section>
      <label style="display:flex;gap:.5rem;align-items:flex-start;margin:1rem 0"><input type="checkbox" data-reviewed><span>I reviewed the new file and it is correct.</span></label>
      <div style="display:flex;justify-content:flex-end;gap:.5rem"><button type="button" class="export-cancel-button" data-cancel>Cancel</button><button type="button" class="btn-save-modal" data-merge disabled>Merge</button></div>
    </div>`);

    modal.querySelector('[data-cancel]').onclick = () => modal.remove();
    modal.querySelector('[data-old-preview]').innerHTML = previewTable(oldInfo.data);
    modal.querySelector('[data-new-preview]').innerHTML = previewTable(selected.sheet.sheet);
    const keyList = modal.querySelector('[data-key-list]');
    oldInfo.data.headers.forEach((header, index) => {
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

    const refreshReview = () => {
      keyColumns = [...modal.querySelectorAll('[data-key-column]:checked')].map(input => Number(input.dataset.keyColumn));
      latestValidation = window.SheetMerge.validateSheet(selected.sheet.sheet, oldInfo.data, keyColumns);
      latestResult = keyColumns.length ? window.SheetMerge.mergeSheet(oldInfo.data, selected.sheet.sheet, { keyColumns }) : { error: 'Select at least one key column.' };
      const validation = modal.querySelector('[data-validation]');
      if (latestValidation.ok && !latestResult.error) {
        const stats = latestResult.stats;
        const updateSummary = Object.entries(stats.updatedByColumn).map(([name, count]) => `${escapeHtml(name)}: ${count}`).join(', ') || 'none';
        validation.innerHTML = `<strong style="color:#166534">Validation passed</strong><p>Inserted ${stats.inserted}; updated ${stats.updated}; unchanged ${stats.unchanged}; skipped ${stats.skipped}; duplicate incoming ${stats.duplicateIncoming}; duplicate existing ${stats.duplicateExisting}.</p><p>Updated by column: ${updateSummary}</p><p>Ignored new columns: ${stats.ignoredColumns.map(escapeHtml).join(', ') || 'none'}</p><p>Rows are marked in the 200-row preview. New rows append in existing column order; existing row order and columns are preserved.</p>`;
        modal.querySelector('[data-new-preview]').innerHTML = previewTable(selected.sheet.sheet, stats.rowStatus);
      } else {
        const problems = latestValidation.problems.length ? latestValidation.problems : [latestResult.error];
        validation.innerHTML = `<strong style="color:#b91c1c">Merge is blocked</strong><ul style="margin:.4rem 0;color:#b91c1c">${problems.map(problem => `<li>${escapeHtml(problem)}</li>`).join('')}</ul>`;
      }
      modal.querySelector('[data-merge]').disabled = !(latestValidation.ok && latestResult.sheet && confirmed);
    };

    keyList.addEventListener('change', refreshReview);
    modal.querySelector('[data-reviewed]').addEventListener('change', event => {
      confirmed = event.currentTarget.checked;
      refreshReview();
    });
    modal.querySelector('[data-merge]').onclick = async event => {
      if (!latestResult?.sheet || !latestValidation?.ok || !confirmed) return;
      event.currentTarget.disabled = true;
      modal.remove();
      await commitMerge(oldRecord, oldInfo, selected, latestResult);
    };
    refreshReview();
  };

  const commitMerge = async (oldRecord, oldInfo, selected, mergeResult) => {
    const officeRecord = selected.record;
    const mergeDate = new Date().toISOString();
    const oldFileName = oldRecord.fileName || 'previous file';
    const rollback = {
      fileName: oldRecord.fileName,
      fileType: oldRecord.fileType,
      fileSize: oldRecord.fileSize,
      extractedData: clone(oldRecord.extractedData || {}),
      metadata: clone(oldRecord.metadata || {}),
      adminNotes: oldRecord.adminNotes || ''
    };
    downloadRecordBackup(oldRecord);

    let staged;
    try {
      staged = await ctx.dbManager.stageRestore(oldRecord.id, officeRecord.id);
    } catch (error) {
      alert(`Merge was not started. Recovery staging failed: ${error.message}`);
      return;
    }

    const extractedData = clone(oldRecord.extractedData || {});
    extractedData[oldInfo.name] = mergeResult.sheet;
    const adminNotes = [oldRecord.adminNotes, `Merged from ${officeRecord.fileName || 'office upload'} on ${mergeDate}`].filter(Boolean).join('\n');
    const metadata = {
      ...(officeRecord.metadata || {}),
      merge: {
        merged_at: mergeDate,
        replaced_file: oldFileName,
        source_record_id: officeRecord.id,
        source_office: officeRecord.office_name || '',
        trash_id: staged.trash_id
      }
    };
    let updatedRecord;
    try {
      updatedRecord = await ctx.dbManager.updateRecord(oldRecord.id, {
        fileName: officeRecord.fileName,
        fileType: officeRecord.fileType,
        fileSize: officeRecord.fileSize,
        extractedData,
        metadata,
        adminNotes
      });
    } catch (error) {
      try {
        await ctx.dbManager.updateRecord(oldRecord.id, rollback);
      } catch (rollbackError) {
        alert(`Merge update failed and rollback also failed. The recovery snapshot remains staged: ${rollbackError.message}`);
        return;
      }
      alert(`Merge was not applied. The existing record was restored: ${error.message}`);
      return;
    }

    const records = await ctx.dbManager.getAllRecords();
    const verified = records.find(record => String(record.id) === String(oldRecord.id));
    const actualSheet = verified?.extractedData?.[oldInfo.name];
    const matches = actualSheet
      && JSON.stringify(actualSheet.headers) === JSON.stringify(oldInfo.data.headers)
      && actualSheet.rowCount === mergeResult.sheet.rowCount
      && JSON.stringify(actualSheet.numericStats) === JSON.stringify(mergeResult.sheet.numericStats)
      && JSON.stringify(actualSheet.rows) === JSON.stringify(mergeResult.sheet.rows);
    if (!matches) {
      try {
        await ctx.dbManager.updateRecord(oldRecord.id, rollback);
        alert('Merge verification failed. The previous record data was restored; the office upload remains in the review queue.');
      } catch (error) {
        alert(`Merge verification failed and automatic rollback also failed. The recovery snapshot remains staged: ${error.message}`);
      }
      return;
    }

    try {
      await ctx.dbManager.deleteRecord(officeRecord.id);
    } catch (error) {
      let restored = false;
      try {
        const afterFailure = await ctx.dbManager.getAllRecords();
        const officeRemains = afterFailure.some(item => String(item.id) === String(officeRecord.id));
        if (officeRemains) {
          await ctx.dbManager.updateRecord(oldRecord.id, rollback);
          restored = true;
        } else {
          await ctx.dbManager.restoreMerge(oldRecord.id, staged.trash_id);
          restored = true;
        }
        const refreshed = await ctx.dbManager.getAllRecords();
        const currentOld = refreshed.find(item => String(item.id) === String(oldRecord.id));
        if (currentOld) {
          ctx.state.studioActiveRecord = currentOld;
          ctx.api.renderStudioTableGrid(currentOld);
          ctx.api.updateStudioChart();
          syncStudioHeader();
        }
        await ctx.api.renderAdminPortal();
      } catch (rollbackError) {
        alert(`The office record delete failed. Recovery data is retained under ${staged.trash_id}. ${rollbackError.message}`);
        return;
      }
      alert(`${restored ? 'The merge was rolled back and both records were retained.' : 'Both records remain available.'} Office record deletion failed: ${error.message}`);
      return;
    }

    let trashWarning = '';
    try {
      await ctx.dbManager.trashStoredFile(oldRecord.id, staged.trash_id);
    } catch (error) {
      trashWarning = ` The merge succeeded, but the old file could not be moved to trash: ${error.message}`;
    }

    ctx.state.studioActiveRecord = verified;
    ctx.state.docWindowActiveSheetKey = oldInfo.name;
    window.IRIS_STUDIO_DIRTY = false;
    ctx.api.renderStudioTableGrid(verified);
    ctx.api.updateStudioChart();
    syncStudioHeader();
    await ctx.api.renderAdminPortal();
    alert(`Merge complete. Click Save Dashboard Changes to update the saved graph.${trashWarning}`);
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
    if (!confirm(`Restore ${merge.replaced_file || 'the previous file'}? The record returns to its pre-merge data, the office upload goes back to the Pending Review queue, and any edits made since the merge are lost. The saved graph is not reverted; click Save Dashboard Changes afterwards.`)) return;
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
        : 'Previous file and record data restored. The saved graph was not reverted; click Save Dashboard Changes afterwards.');
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
    if (record.template_id === null || record.template_id === undefined || record.template_id === '') {
      alert('This record has no linked template. Assign a template in Diff & approve before merging an office upload.');
      return;
    }
    let records;
    try {
      records = await ctx.dbManager.getAllRecords();
    } catch (error) {
      alert(`Unable to load office uploads: ${error.message || error}`);
      return;
    }
    const candidates = records.flatMap(candidate => {
      if (String(candidate.id) === String(record.id)
        || String(candidate.template_id ?? '') !== String(record.template_id)
        || String(candidate.status || '').toLowerCase() !== 'pending review'
        || !candidate.uploaded_by
        || !candidate.office_name
        || !['xlsx', 'csv', 'tsv'].includes(String(candidate.fileType || '').toLowerCase())) return [];
      const sheet = bestSheetFor(candidate, info.name, info.data.headers);
      return sheet ? [{ record: candidate, sheet }] : [];
    });
    if (!candidates.length) {
      alert('No Pending Review office upload with the same linked template and a compatible worksheet was found.');
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
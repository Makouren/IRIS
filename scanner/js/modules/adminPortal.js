import { $, all } from '../utils/helpers.js';

export function initAdminPortal(ctx) {
  const selectedRecordIds = new Set();

  const escape = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  const showToast = message => {
    const toast = document.createElement('div');
    toast.className = 'pdf-copy-toast visible';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  };

  const updateBulkActions = () => {
    const bar = $('adminBulkActions');
    const count = $('adminBulkSelectionCount');
    const deleteBtn = $('adminBulkDelete');
    const publishBtn = $('adminBulkPublish');
    const unpublishBtn = $('adminBulkUnpublish');
    const n = selectedRecordIds.size;
    if (bar) bar.hidden = n === 0;
    if (count) count.textContent = `${n} record${n === 1 ? '' : 's'} selected`;
    if (deleteBtn) deleteBtn.disabled = n === 0;
    if (publishBtn) publishBtn.disabled = n === 0;
    if (unpublishBtn) unpublishBtn.disabled = n === 0;
  };

  const syncSelectAll = () => {
    const boxes = [...document.querySelectorAll('#adminRecordsTableBody .admin-record-checkbox')];
    const selectAll = $('adminSelectAll');
    if (!selectAll) return;
    const visibleIds = boxes.map(box => box.dataset.id).filter(Boolean);
    selectAll.checked = visibleIds.length > 0 && visibleIds.every(id => selectedRecordIds.has(id));
    selectAll.indeterminate = visibleIds.some(id => selectedRecordIds.has(id)) && !selectAll.checked;
  };

  const bindSelection = () => {
    document.querySelectorAll('#adminRecordsTableBody .admin-record-checkbox').forEach(box => {
      box.checked = selectedRecordIds.has(box.dataset.id);
      box.onchange = () => {
        if (box.checked) selectedRecordIds.add(box.dataset.id);
        else selectedRecordIds.delete(box.dataset.id);
        updateBulkActions();
        syncSelectAll();
      };
    });
    const selectAll = $('adminSelectAll');
    if (selectAll) selectAll.onchange = () => {
      document.querySelectorAll('#adminRecordsTableBody .admin-record-checkbox').forEach(box => {
        if (selectAll.checked) selectedRecordIds.add(box.dataset.id);
        else selectedRecordIds.delete(box.dataset.id);
        box.checked = selectAll.checked;
      });
      updateBulkActions();
      syncSelectAll();
    };
    updateBulkActions();
    syncSelectAll();
  };

  const renderRows = filtered => {
    const body = $('adminRecordsTableBody');
    if (!body) return;
    const html = filtered.map(record => {
      const scannedDate = record.scannedAt || (() => {
        const match = String(record.id || '').match(/^scan_(\d+)_/);
        return match ? new Date(Number(match[1])).toISOString() : '';
      })();
      const status = record.status || 'Pending Review';
      const displayStatus = status === 'Approved' ? 'Published' : status;
      const approved = status === 'Approved';
      return `<tr>
        <td><input class="admin-record-checkbox" type="checkbox" data-id="${escape(record.id)}" aria-label="Select record ${escape(record.id)}"></td>
        <td>${escape(record.id)}</td>
        <td>${escape(record.fileName || 'Untitled')}</td>
        <td>${escape((record.fileType || 'UNKNOWN').toUpperCase())}</td>
        <td><span class="badge">${escape(displayStatus)}</span></td>
        <td>${scannedDate ? escape(new Date(scannedDate).toLocaleString()) : 'N/A'}</td>
        <td><div class="admin-record-actions">
          <button class="archive-load-button btn-table-load-studio" data-id="${escape(record.id)}"><i class="fa-solid fa-palette" aria-hidden="true"></i> Review</button>
          ${approved ? `<button class="archive-load-button btn-table-unpublish" data-id="${escape(record.id)}" title="Unpublish this record and its saved charts"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>` : `<button class="archive-load-button btn-table-approve" data-id="${escape(record.id)}"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>`}
          <button class="archive-delete-button btn-table-delete" data-id="${escape(record.id)}" title="Delete record"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
        </div></td>
      </tr>`;
    }).join('');
    body.innerHTML = html || '<tr><td colspan="7">No matching scanned records in database.</td></tr>';

    all('.btn-table-load-studio').forEach(button => button.onclick = () => {
      const record = filtered.find(item => String(item.id) === String(button.dataset.id));
      if (record) {
        ctx.state.studioActiveRecord = record;
        ctx.api.renderStudioWorkbench(record);
        const workbench = $('studioContainer');
        if (workbench) requestAnimationFrame(() => workbench.scrollIntoView({ behavior: 'smooth', block: 'start' }));
      }
    });

    all('.btn-table-approve').forEach(button => button.onclick = () => {
      const record = filtered.find(item => String(item.id) === String(button.dataset.id));
      if (!record) return;
      ctx.state.studioActiveRecord = record;
      ctx.api.renderStudioWorkbench(record);
      $('studioChartCanvas')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      showToast('Review detected column roles and acknowledge warnings before approval.');
    });

    all('.btn-table-unpublish').forEach(button => button.onclick = async () => {
      const id = button.dataset.id;
      const record = filtered.find(item => String(item.id) === String(id));
      if (!record || !confirm(`Unpublish "${record.fileName || id}" and all of its published charts? The record will return to Pending Review.`)) return;
      button.disabled = true;
      try {
        const result = await ctx.dbManager.unpublishRecord(id);
        selectedRecordIds.delete(id);
        await ctx.api.renderAdminPortal();
        const chartCount = Number(result.unpublished_graph_count || 0);
        showToast(`${record.fileName || id} unpublished with ${chartCount} related chart${chartCount === 1 ? '' : 's'}.`);
      } catch (error) {
        button.disabled = false;
        alert(`Unpublish failed: ${error.message}`);
      }
    });

    all('.btn-table-delete').forEach(button => button.onclick = async () => {
      const id = button.dataset.id;
      const record = filtered.find(item => String(item.id) === String(id));
      if (!record || !confirm(`Remove "${record.fileName || id}" from the Observatory? This also deletes its published charts permanently.`)) return;
      button.disabled = true;
      try {
        await ctx.dbManager.deleteRecord(id);
        selectedRecordIds.delete(id);
        await ctx.api.renderAdminPortal();
        showToast('Upload and its published charts were removed from the Observatory.');
      } catch (error) {
        button.disabled = false;
        alert(`Delete failed: ${error.message}`);
      }
    });
    bindSelection();
  };

  const confirmBulk = async mode => {
    const ids = [...selectedRecordIds];
    if (!ids.length) return;
    const records = await ctx.dbManager.getAllRecords();
    const selected = records.filter(record => ids.includes(String(record.id)));
    const verb = mode;
    const description = mode === 'delete'
      ? `Permanently delete <strong>${selected.length}</strong> selected upload${selected.length === 1 ? '' : 's'} and their saved charts?`
      : `${mode === 'publish' ? 'Publish' : 'Unpublish'} <strong>${selected.length}</strong> selected upload${selected.length === 1 ? '' : 's'} and all saved charts attached to them?`;
    const actionClass = mode === 'delete' ? 'archive-delete-button' : 'archive-load-button';
    const actionLabel = mode === 'delete' ? 'Delete selected' : `${mode === 'publish' ? 'Publish' : 'Unpublish'} selected`;
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.innerHTML = `<div class="modal-card" style="max-width:620px;">
      <div class="modal-header"><h3 class="modal-title">Confirm bulk ${verb}</h3><button type="button" class="export-cancel-button" data-close>Cancel</button></div>
      <p>${description}</p>
      <ul style="max-height:260px;overflow:auto;">${selected.map(r => `<li>${escape(r.fileName || 'Untitled')} <small>(${escape(r.id)})</small></li>`).join('')}</ul>
      <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1rem;"><button type="button" class="${actionClass}" data-confirm>${actionLabel}</button></div>
    </div>`;
    document.body.appendChild(modal);
    const close = () => modal.remove();
    modal.querySelector('[data-close]').onclick = close;
    modal.querySelector('[data-confirm]').onclick = async () => {
      const action = modal.querySelector('[data-confirm]');
      action.disabled = true;
      try {
        const result = mode === 'delete'
          ? await ctx.dbManager.deleteRecords(ids)
          : await ctx.dbManager.setRecordsPublication(ids, mode === 'publish');
        close();
        selectedRecordIds.clear();
        await ctx.api.renderAdminPortal();
        const relatedCharts = Number(result.published_graph_count || 0);
        const message = mode === 'delete'
          ? `${result.successCount} of ${ids.length} records deleted successfully.`
          : `${result.successCount} of ${ids.length} records ${mode}ed with ${relatedCharts} related chart${relatedCharts === 1 ? '' : 's'}.`;
        showToast(message);
      } catch (error) {
        action.disabled = false;
        alert(`${mode[0].toUpperCase()}${mode.slice(1)} failed: ${error.message}`);
      }
    };
  };

  ctx.api.renderAdminPortal = async () => {
    const select = $('studioRecordSelect');
    try {
      const records = await ctx.dbManager.getAllRecords();
      if ($('statTotalDb')) $('statTotalDb').textContent = records.length;
      if ($('statPendingDb')) $('statPendingDb').textContent = records.filter(r => r.status === 'Pending Review' || !r.status).length;
      if ($('statVerifiedDb')) $('statVerifiedDb').textContent = records.filter(r => ['Approved', 'Verified & Approved'].includes(r.status)).length;
      if ($('statTablesDb')) $('statTablesDb').textContent = records.reduce((sum, r) => sum + Object.keys(r.extractedData || {}).length, 0);

      if (records.length) {
        if (select) {
          select.innerHTML = records.map(r => `<option value="${escape(r.id)}">${escape(r.fileName)} (${escape((r.fileType || '').toUpperCase())})</option>`).join('');
          select.onchange = event => {
            ctx.state.studioActiveRecord = records.find(r => String(r.id) === String(event.target.value)) || null;
            ctx.api.renderStudioWorkbench(ctx.state.studioActiveRecord);
          };
        }
        const urlParams = new URLSearchParams(window.location.search);
        const urlRecordId = urlParams.get('record_id');
        const matchedRecord = urlRecordId ? records.find(r => String(r.id) === String(urlRecordId)) : null;
        ctx.state.studioActiveRecord = matchedRecord || records.find(r => String(r.id) === String(ctx.state.studioActiveRecord?.id)) || records[0];
        if (select) select.value = String(ctx.state.studioActiveRecord.id);
        ctx.api.renderStudioWorkbench(ctx.state.studioActiveRecord);
      } else {
        ctx.state.studioActiveRecord = null;
        if (select) {
          select.innerHTML = '<option value="">Please upload files</option>';
        }
      }

      const term = (($('adminSearchInput')?.value || '')).toLowerCase();
      const status = $('adminStatusFilter')?.value || 'all';
      const filtered = records.filter(record => {
        const haystack = [record.id, record.fileName, record.docType, record.rawText].join(' ').toLowerCase();
        return haystack.includes(term) && (status === 'all' || record.status === status || (status === 'Pending Review' && !record.status));
      });
      const visible = new Set(filtered.map(r => String(r.id)));
      [...selectedRecordIds].forEach(id => { if (!visible.has(String(id))) selectedRecordIds.delete(id); });
      renderRows(filtered);
    } catch (error) {
      console.error(error);
      if ($('adminRecordsTableBody')) {
        $('adminRecordsTableBody').innerHTML = '<tr><td colspan="7">Please upload files to inspect scanner records.</td></tr>';
      }
    }
  };

  $('adminBulkDelete')?.addEventListener('click', () => confirmBulk('delete'));
  $('adminBulkPublish')?.addEventListener('click', () => confirmBulk('publish'));
  $('adminBulkUnpublish')?.addEventListener('click', () => confirmBulk('unpublish'));
  $('adminClearSelection')?.addEventListener('click', () => { selectedRecordIds.clear(); bindSelection(); });
  $('adminSearchInput')?.addEventListener('input', () => ctx.api.renderAdminPortal());
  $('adminStatusFilter')?.addEventListener('change', () => ctx.api.renderAdminPortal());

  ctx.api.renderAdminPortal();
}

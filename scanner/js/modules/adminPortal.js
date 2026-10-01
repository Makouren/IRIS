import { $, all } from '../utils/helpers.js';

export function initAdminPortal(ctx) {
  const selectedRecordIds = new Set();

  const escape = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  const projectBase = ctx.dbManager.config.endpoints.records.split('/api/iris.php')[0];
  const showToast = message => {
    const toast = document.createElement('div');
    toast.className = 'pdf-copy-toast visible';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  };
  const setGraphEditMessage = (message, published = false) => {
    if (message.startsWith('Editing saved graph:')) return;
    showToast(message);
  };
  const clearGraphEditState = (removeGraphParam = false) => {
    ctx.state.studioActiveGraphId = null;
    ctx.state.studioActiveGraphPublished = false;
    if (removeGraphParam) {
      const url = new URL(window.location.href);
      url.searchParams.delete('graph_id');
      window.history.replaceState(null, '', url);
    }
  };
  const restoreSavedGraph = async (graphId, record, records, requestedRecordId) => {
    if (!graphId) return;
    let graph;
    try {
      graph = await ctx.dbManager.getGraphById(graphId);
    } catch (error) {
      const requestedSourceExists = records.some(item => String(item.id) === String(requestedRecordId));
      clearGraphEditState(true);
      setGraphEditMessage(requestedSourceExists
        ? 'This saved graph is no longer available. The source file was loaded normally.'
        : 'The source file for this graph is no longer available.');
      return;
    }

    const sourceRecord = records.find(item => String(item.id) === String(graph.record_id));
    const sourceStatus = String(sourceRecord?.status || '').trim().toLowerCase();
    if (!sourceRecord || sourceStatus.includes('supersed')) {
      clearGraphEditState(true);
      setGraphEditMessage('The source file for this graph is no longer available.');
      return;
    }
    if (!record || String(graph.record_id) !== String(record.id)) {
      clearGraphEditState(true);
      setGraphEditMessage('This graph does not belong to the selected source file. The graph was not loaded.');
      return;
    }

    ctx.state.studioActiveGraphId = String(graph.id);
    ctx.state.studioActiveGraphPublished = Boolean(graph.is_published);
    const titleInput = $('studioChartTitleInput');
    if (titleInput) {
      titleInput.value = graph.title || 'Saved Chart';
      titleInput.setAttribute('data-customized', 'true');
    }
    const typeSelect = $('studioChartTypeSelect');
    const savedType = String(graph.chart_type || 'bar');
    const chartType = /^(?:polararea|polar-area|rose|nightingale)$/i.test(savedType) ? 'bar' : savedType;
    const irisConfig = graph.chart_data?.irisConfig || {};
    if (typeSelect && [...typeSelect.options].some(option => option.value === chartType)) typeSelect.value = chartType;
    ctx.state.studioChartConfig = { ...(ctx.state.studioChartConfig || {}), ...irisConfig, orientation: graph.orientation || irisConfig.orientation || 'vertical' };
    ctx.state.studioChartOverrides = Array.isArray(graph.colors) ? [...graph.colors] : null;

    const sheet = ctx.api.getStudioActiveSheet(record)?.data;
    const mapping = graph.chart_data?.rankedBar || {};
    if (sheet) {
      ctx.api.updateFieldSelectOptions(sheet);
      const category = $('studioCategoryCol');
      const value = $('studioValueCol');
      const hasSavedIndex = index => index !== null && index !== undefined && index !== '' && Number.isInteger(Number(index)) && Number(index) >= 0 && Number(index) < sheet.headers.length;
      const categoryField = irisConfig.categoryField ?? mapping.categoryField;
      const valueField = irisConfig.valueField ?? mapping.valueField;
      const groupField = irisConfig.groupField;
      if (hasSavedIndex(categoryField) && category) category.value = String(categoryField);
      if (hasSavedIndex(valueField) && value) value.value = String(valueField);
      const group = $('studioGroupField');
      if (hasSavedIndex(groupField) && group) group.value = String(groupField);
      const precision = $('studioValuePrecisionSelect');
      if (precision && irisConfig.precision !== undefined) precision.value = String(irisConfig.precision);
      const reverse = $('studioRankedReverseOrder');
      if (reverse) reverse.checked = Boolean(irisConfig.reverseOrder ?? mapping.reverseOrder);
      ctx.api.renderStudioTableGrid(record);
      ctx.api.renderStudioChart(record);
    }
    window.IRIS_STUDIO_DIRTY = false;
    setGraphEditMessage(`Editing saved graph: ${graph.title || 'Saved Chart'}`, ctx.state.studioActiveGraphPublished);
  };
  const setEditorRecordUrl = recordId => {
    clearGraphEditState();
    const url = new URL(window.location.href);
    if (recordId) url.searchParams.set('record_id', recordId);
    else url.searchParams.delete('record_id');
    url.searchParams.delete('graph_id');
    window.history.replaceState(null, '', url);
  };
  const manualDatasetModal = $('manualDatasetModal');
  const manualDatasetForm = $('manualDatasetForm');
  const manualDatasetName = $('manualDatasetFileName');
  const closeManualDatasetModal = () => {
    manualDatasetModal?.classList.remove('active');
    manualDatasetModal?.setAttribute('aria-hidden', 'true');
  };

  $('createManualDataset')?.addEventListener('click', () => {
    manualDatasetForm?.reset();
    manualDatasetName?.setCustomValidity('');
    manualDatasetModal?.classList.add('active');
    manualDatasetModal?.setAttribute('aria-hidden', 'false');
    manualDatasetName?.focus();
  });
  $('cancelManualDataset')?.addEventListener('click', closeManualDatasetModal);
  $('cancelManualDatasetFooter')?.addEventListener('click', closeManualDatasetModal);
  manualDatasetModal?.addEventListener('click', event => {
    if (event.target === manualDatasetModal) closeManualDatasetModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && manualDatasetModal?.classList.contains('active')) closeManualDatasetModal();
  });
  manualDatasetForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const fileName = manualDatasetName?.value.trim() || '';
    if (!fileName) {
      manualDatasetName?.setCustomValidity('Enter a file name.');
      manualDatasetName?.reportValidity();
      return;
    }
    manualDatasetName?.setCustomValidity('');
    const submit = $('submitManualDataset');
    if (submit) submit.disabled = true;
    try {
      const record = await ctx.dbManager.saveRecord({
        fileName,
        fileType: 'manual',
        extractedData: { Manual_Data: { name: 'Manual Data', headers: [], rows: [] } },
        metadata: { creationMethod: 'manual' }
      });
      selectedRecordIds.clear();
      clearGraphEditState(true);
      ctx.state.docWindowActiveSheetKey = 'Manual_Data';
      ctx.state.studioChartConfig = {};
      ctx.state.studioChartInstance?.dispose?.();
      ctx.state.studioChartInstance = null;
      window.IRIS_STUDIO_DIRTY = false;
      setEditorRecordUrl(record.id);
      closeManualDatasetModal();
      await ctx.api.renderAdminPortal(record.id);
      showToast(`Created empty dataset: ${record.fileName}`);
    } catch (error) {
      alert(`Unable to create dataset: ${error.message}`);
    } finally {
      if (submit) submit.disabled = false;
    }
  });

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
      const officeName = record.office_name || '';
      const uploader = officeName || (record.uploaded_by ? 'Office' : 'Legacy / Super Admin');
      const templateLabel = record.template_name ? ` · via ${record.template_name}` : '';
      const isNew = Boolean(record.uploaded_at && !record.opened_at);
        const merge = record.metadata?.merge;
        const recentMerge = window.SheetMerge?.isRecentMerge(record.metadata, new Date()) === true;
        const mergeBadgeTitle = recentMerge
          ? `Merged ${new Date(merge.merged_at).toLocaleString()} from ${merge.source_office || 'office'}, replaced ${merge.replaced_file || 'previous file'}`
          : '';
      return `<tr>
        <td><input class="admin-record-checkbox" type="checkbox" data-id="${escape(record.id)}" aria-label="Select record ${escape(record.id)}"></td>
        <td>${escape(record.id)}</td>
          <td>${escape(record.fileName || 'Untitled')}${recentMerge ? ` <span class="badge badge-low" title="${escape(mergeBadgeTitle)}">NEW</span>` : ''}</td>
        <td>${escape(uploader)}${escape(templateLabel)}${isNew ? ' <span class="badge badge-low" aria-label="New, not yet opened">New</span>' : ''}</td>
        <td>${escape((record.fileType || 'UNKNOWN').toUpperCase())}</td>
        <td><span class="badge">${escape(displayStatus)}</span></td>
        <td>${scannedDate ? escape(new Date(scannedDate).toLocaleString()) : 'N/A'}</td>
        <td><div class="admin-record-actions">
          <button class="archive-load-button btn-table-load-studio" data-id="${escape(record.id)}"><i class="fa-solid fa-palette" aria-hidden="true"></i> Review</button>
          ${record.metadata?.stored_file ? `<a class="archive-load-button" href="${escape(projectBase)}/admin/upload_source.php?id=${encodeURIComponent(record.id)}" target="_blank" rel="noopener"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Source</a>` : ''}
          ${record.metadata?.stored_file && status !== 'Approved' ? `<button class="archive-load-button" type="button" data-template-review-record="${escape(record.id)}"><i class="fa-solid fa-code-compare" aria-hidden="true"></i> Diff &amp; approve</button>` : ''}
          ${approved ? `<button class="archive-load-button btn-table-unpublish" data-id="${escape(record.id)}" title="Unpublish this record and its saved charts"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>` : `<button class="archive-load-button btn-table-approve" data-id="${escape(record.id)}"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>`}
          <button class="archive-delete-button btn-table-delete" data-id="${escape(record.id)}" title="Delete record"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
        </div></td>
      </tr>`;
    }).join('');
    body.innerHTML = html || '<tr><td colspan="8">No matching scanned records in database.</td></tr>';

    all('.btn-table-load-studio').forEach(button => button.onclick = async () => {
      const record = filtered.find(item => String(item.id) === String(button.dataset.id));
      if (record) {
        try {
          const response = await fetch(ctx.dbManager.config.endpoints.recordById(record.id), { headers: { Accept: 'application/json' } });
          if (response.ok) Object.assign(record, await response.json());
        } catch (error) {}
        ctx.state.studioActiveRecord = record;
        setEditorRecordUrl(record.id);
        ctx.api.renderStudioWorkbench(record);
        const workbench = $('studioContainer');
        if (workbench) requestAnimationFrame(() => workbench.scrollIntoView({ behavior: 'smooth', block: 'start' }));
      }
    });

    all('.btn-table-approve').forEach(button => button.onclick = () => {
      const record = filtered.find(item => String(item.id) === String(button.dataset.id));
      if (!record) return;
      clearGraphEditState(true);
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

  ctx.api.renderAdminPortal = async (preferredRecordId = null) => {
    const select = $('studioRecordSelect');
    const urlParams = new URLSearchParams(window.location.search);
    const graphId = urlParams.get('graph_id');
    const urlRecordId = urlParams.get('record_id');
    if (!graphId) clearGraphEditState();
    try {
      try {
        globalThis.IRISFieldColors = await ctx.dbManager.getFieldColors();
      } catch (error) {
        globalThis.IRISFieldColors = globalThis.IRISFieldColors || {};
        console.warn('Field colors are temporarily unavailable:', error);
      }
      const records = await ctx.dbManager.getAllRecords();
      if ($('statTotalDb')) $('statTotalDb').textContent = records.length;
      if ($('statPendingDb')) $('statPendingDb').textContent = records.filter(r => r.status === 'Pending Review' || !r.status).length;
      if ($('statVerifiedDb')) $('statVerifiedDb').textContent = records.filter(r => ['Approved', 'Verified & Approved'].includes(r.status)).length;
      if ($('statTablesDb')) $('statTablesDb').textContent = records.reduce((sum, r) => sum + Object.keys(r.extractedData || {}).length, 0);
      const officeFilter = $('adminOfficeFilter');
      if (officeFilter) {
        const selectedOffice = officeFilter.value || 'all';
        const offices = [...new Set(records.map(record => record.office_name).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        officeFilter.innerHTML = '<option value="all">All offices</option>' + offices.map(name => `<option value="${escape(name)}">${escape(name)}</option>`).join('');
        officeFilter.value = offices.includes(selectedOffice) ? selectedOffice : 'all';
      }

      if (records.length) {
        if (select) {
          select.innerHTML = records.map(r => `<option value="${escape(r.id)}">${escape(r.fileName)} (${escape((r.fileType || '').toUpperCase())})${r.office_name ? ` · Uploaded by ${escape(r.office_name)}` : ''}${r.template_name ? ` · via ${escape(r.template_name)}` : ''}</option>`).join('');
          select.onchange = async event => {
            const record = records.find(r => String(r.id) === String(event.target.value)) || null;
            if (record) {
              try {
                const response = await fetch(ctx.dbManager.config.endpoints.recordById(record.id), { headers: { Accept: 'application/json' } });
                if (response.ok) Object.assign(record, await response.json());
              } catch (error) {}
            }
            ctx.state.studioActiveRecord = record;
            setEditorRecordUrl(ctx.state.studioActiveRecord?.id);
            ctx.api.renderStudioWorkbench(ctx.state.studioActiveRecord);
          };
        }
        const preferredRecord = preferredRecordId ? records.find(r => String(r.id) === String(preferredRecordId)) : null;
        const matchedRecord = urlRecordId ? records.find(r => String(r.id) === String(urlRecordId)) : null;
        ctx.state.studioActiveRecord = preferredRecord || matchedRecord || records.find(r => String(r.id) === String(ctx.state.studioActiveRecord?.id)) || records[0];
        if (ctx.state.studioActiveRecord?.uploaded_at && !ctx.state.studioActiveRecord.opened_at) {
          try {
            const response = await fetch(ctx.dbManager.config.endpoints.recordById(ctx.state.studioActiveRecord.id), { headers: { Accept: 'application/json' } });
            if (response.ok) Object.assign(ctx.state.studioActiveRecord, await response.json());
          } catch (error) {}
        }
        if (select) select.value = String(ctx.state.studioActiveRecord.id);
        ctx.api.renderStudioWorkbench(ctx.state.studioActiveRecord);
      } else {
        ctx.state.studioActiveRecord = null;
        if (select) {
          select.innerHTML = '<option value="">Please upload files</option>';
        }
      }

      if (graphId) await restoreSavedGraph(graphId, ctx.state.studioActiveRecord, records, urlRecordId);

      const term = (($('adminSearchInput')?.value || '')).toLowerCase();
      const status = $('adminStatusFilter')?.value || 'all';
      const office = $('adminOfficeFilter')?.value || 'all';
      const filtered = records.filter(record => {
        const haystack = [record.id, record.fileName, record.docType, record.rawText].join(' ').toLowerCase();
        return haystack.includes(term) && (status === 'all' || record.status === status || (status === 'Pending Review' && !record.status))
          && (office === 'all' || record.office_name === office);
      });
      const visible = new Set(filtered.map(r => String(r.id)));
      [...selectedRecordIds].forEach(id => { if (!visible.has(String(id))) selectedRecordIds.delete(id); });
      renderRows(filtered);
    } catch (error) {
      console.error(error);
      if ($('adminRecordsTableBody')) {
        $('adminRecordsTableBody').innerHTML = '<tr><td colspan="8">Please upload files to inspect scanner records.</td></tr>';
      }
    }
  };

  $('adminBulkDelete')?.addEventListener('click', () => confirmBulk('delete'));
  $('adminBulkPublish')?.addEventListener('click', () => confirmBulk('publish'));
  $('adminBulkUnpublish')?.addEventListener('click', () => confirmBulk('unpublish'));
  $('adminClearSelection')?.addEventListener('click', () => { selectedRecordIds.clear(); bindSelection(); });
  $('adminSearchInput')?.addEventListener('input', () => ctx.api.renderAdminPortal());
  $('adminStatusFilter')?.addEventListener('change', () => ctx.api.renderAdminPortal());
  $('adminOfficeFilter')?.addEventListener('change', () => ctx.api.renderAdminPortal());
  document.addEventListener('iris:template-review-complete', () => ctx.api.renderAdminPortal());
  document.addEventListener('iris:template-review-complete', () => ctx.api.renderAdminPortal());

  let refreshTimer = null;
  const refreshArchive = async () => {
    if (document.visibilityState !== 'visible') return;
    try {
      const records = await ctx.dbManager.getAllRecords();
      const term = (($('adminSearchInput')?.value || '')).toLowerCase();
      const status = $('adminStatusFilter')?.value || 'all';
      const office = $('adminOfficeFilter')?.value || 'all';
      const officeFilter = $('adminOfficeFilter');
      if (officeFilter) {
        const offices = [...new Set(records.map(record => record.office_name).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        const selectedOffice = offices.includes(office) ? office : 'all';
        officeFilter.innerHTML = '<option value="all">All offices</option>' + offices.map(name => `<option value="${escape(name)}">${escape(name)}</option>`).join('');
        officeFilter.value = selectedOffice;
      }
      const filtered = records.filter(record => {
        const haystack = [record.id, record.fileName, record.docType, record.rawText].join(' ').toLowerCase();
        return haystack.includes(term) && (status === 'all' || record.status === status || (status === 'Pending Review' && !record.status))
          && (office === 'all' || record.office_name === office);
      });
      renderRows(filtered);
      if ($('statTotalDb')) $('statTotalDb').textContent = records.length;
      if ($('statPendingDb')) $('statPendingDb').textContent = records.filter(record => record.status === 'Pending Review' || !record.status).length;
      if ($('statVerifiedDb')) $('statVerifiedDb').textContent = records.filter(record => ['Approved', 'Verified & Approved'].includes(record.status)).length;
      if ($('statTablesDb')) $('statTablesDb').textContent = records.reduce((sum, record) => sum + Object.keys(record.extractedData || {}).length, 0);
    } catch (error) { console.warn('Archive refresh failed:', error); }
  };
  window.addEventListener('focus', refreshArchive);
  document.addEventListener('visibilitychange', refreshArchive);
  refreshTimer = window.setInterval(refreshArchive, 30000);
  window.addEventListener('pagehide', () => window.clearInterval(refreshTimer), { once: true });

  ctx.api.renderAdminPortal();
}

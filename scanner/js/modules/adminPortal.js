import { $, all } from '../utils/helpers.js';

export function initAdminPortal(ctx) {
  const selectedRecordIds = new Set();
  let bulkPromptOpen = false;
  let archiveRecords = [];
  const importDestinations = new Set(['summary_cards', 'ranking_history']);
  const recordPurpose = record => {
    const purpose = String(record.metadata?.upload_purpose || record.import_destination || '');
    return ['analytics', 'summary_cards', 'ranking_history'].includes(purpose) ? purpose : 'analytics';
  };

  const escape = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  const projectBase = ctx.dbManager.config.endpoints.records.split('/api/iris.php')[0];
  let openedRecordMenu = null;
  const closeRecordMenu = (restoreFocus = false) => {
    const menu = openedRecordMenu;
    if (!menu) return;
    const trigger = menu._trigger;
    const placeholder = menu._placeholder;
    menu.classList.remove('is-open', 'is-above');
    menu.hidden = true;
    menu.removeAttribute('style');
    placeholder?.append(menu);
    trigger?.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger?.focus();
    menu._trigger = null;
    menu._placeholder = null;
    openedRecordMenu = null;
  };
  const openRecordMenu = trigger => {
    closeRecordMenu();
    const menu = trigger.parentElement?.querySelector('.record-actions-menu');
    const placeholder = menu?.parentElement;
    if (!menu || !placeholder) return;
    menu._trigger = trigger;
    menu._placeholder = placeholder;
    openedRecordMenu = menu;
    document.body.append(menu);
    menu.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    const triggerRect = trigger.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const table = trigger.closest('.table-container');
    const tableBottom = table?.getBoundingClientRect().bottom ?? window.innerHeight;
    const lowerBoundary = Math.min(window.innerHeight, tableBottom);
    const spaceBelow = lowerBoundary - triggerRect.bottom;
    const spaceAbove = triggerRect.top;
    const openAbove = menuRect.height + 8 > spaceBelow && spaceAbove > spaceBelow;
    let top = openAbove ? triggerRect.top - menuRect.height - 4 : triggerRect.bottom + 4;
    if (top + menuRect.height > lowerBoundary) top = Math.max(8, lowerBoundary - menuRect.height - 8);
    top = Math.max(8, top);
    const left = Math.min(Math.max(8, triggerRect.right - menuRect.width), window.innerWidth - menuRect.width - 8);
    menu.style.top = `${top}px`;
    menu.style.left = `${left}px`;
    menu.classList.toggle('is-above', openAbove);
    requestAnimationFrame(() => menu.classList.add('is-open'));
    menu.querySelector('[role="menuitem"]')?.focus();
  };
  document.addEventListener('pointerdown', event => {
    if (openedRecordMenu && !openedRecordMenu.contains(event.target) && event.target !== openedRecordMenu._trigger) closeRecordMenu();
  });
  document.addEventListener('keydown', event => {
    if (!openedRecordMenu) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeRecordMenu(true);
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const items = [...openedRecordMenu.querySelectorAll('[role="menuitem"]:not([disabled])')];
      if (!items.length) return;
      const current = items.indexOf(document.activeElement);
      const offset = event.key === 'ArrowDown' ? 1 : -1;
      items[(current + offset + items.length) % items.length].focus();
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault();
      const items = [...openedRecordMenu.querySelectorAll('[role="menuitem"]:not([disabled])')];
      items[event.key === 'Home' ? 0 : items.length - 1]?.focus();
    } else if (event.key === 'Tab') {
      setTimeout(() => {
        if (openedRecordMenu && !openedRecordMenu.contains(document.activeElement)) closeRecordMenu();
      });
    }
  });
  document.addEventListener('focusin', event => {
    if (openedRecordMenu && !openedRecordMenu.contains(event.target) && event.target !== openedRecordMenu._trigger) closeRecordMenu();
  });
  window.addEventListener('resize', () => closeRecordMenu());
  window.addEventListener('scroll', () => closeRecordMenu(), true);
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
  const openRecordHistory = async record => {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = '<div class="modal-card file-history-card" role="dialog" aria-modal="true" aria-labelledby="recordHistoryTitle"><div class="modal-header"><h3 class="modal-title" id="recordHistoryTitle">File History</h3><button type="button" class="export-cancel-button" data-close>Close</button></div><p role="status" aria-live="polite">Loading versions...</p></div>';
    document.body.appendChild(overlay);
    const card = overlay.querySelector('.modal-card');
    const close = () => overlay.remove();
    overlay.querySelector('[data-close]').addEventListener('click', close);
    let versions;
    try {
      versions = await ctx.dbManager.getRecordFileHistory(record.id);
    } catch (error) {
      card.querySelector('[role="status"]').textContent = `File History could not be loaded: ${error.message}`;
      return;
    }
    if (!versions.length) {
      card.querySelector('[role="status"]').textContent = 'No File History versions exist for this record.';
      return;
    }

    const renderList = () => {
      card.innerHTML = `<div class="modal-header"><h3 class="modal-title" id="recordHistoryTitle">File History: ${escape(record.fileName || `Record ${record.id}`)}</h3><button type="button" class="export-cancel-button" data-close>Close</button></div>
        <div class="file-history-toolbar"><p>Select two versions to compare.</p><div><span data-selection-count aria-live="polite">0 selected</span><button type="button" class="archive-load-button" data-compare disabled>Compare</button></div></div>
        <div class="file-history-table-wrap"><table class="admin-records-table file-history-table"><thead><tr><th scope="col">Compare</th><th scope="col">Version</th><th scope="col">Created</th><th scope="col">File</th><th scope="col">Actions</th></tr></thead><tbody>
          ${versions.map(version => `<tr><td><input type="checkbox" data-compare-version="${escape(version.version_id)}" aria-label="Select version ${escape(version.version_id)} for comparison"></td><td><div class="file-history-version"><strong>v${escape(version.version_id)}</strong><span>${escape(version.entry_type)} · ${escape(version.merge_method)}</span></div></td><td class="file-history-created">${escape(new Date(version.created_at).toLocaleString())}</td><td><span class="file-history-filename" title="${escape(version.original_file_name || 'No file snapshot')}">${version.original_file_name ? escape(version.original_file_name) : 'No file snapshot'}</span></td><td><div class="file-history-actions"><button type="button" class="archive-load-button file-history-action-button" data-view-version="${escape(version.version_id)}">View</button>${version.file_snapshot_key ? `<a class="archive-load-button file-history-action-button" href="${escape(ctx.dbManager.config.endpoints.fileHistoryDownload(version.version_id))}">Download</a>` : ''}<button type="button" class="export-cancel-button file-history-action-button" data-restore-version="${escape(version.version_id)}">Restore</button></div></td></tr>`).join('')}
        </tbody></table></div>`;
      card.querySelector('[data-close]').addEventListener('click', close);
      const compareButton = card.querySelector('[data-compare]');
      const selectionCount = card.querySelector('[data-selection-count]');
      card.querySelectorAll('[data-compare-version]').forEach(input => input.addEventListener('change', () => {
        const selected = [...card.querySelectorAll('[data-compare-version]:checked')];
        if (selected.length > 2) input.checked = false;
        const count = card.querySelectorAll('[data-compare-version]:checked').length;
        compareButton.disabled = count !== 2;
        selectionCount.textContent = `${count} selected`;
      }));
      compareButton.addEventListener('click', async () => {
        const ids = [...card.querySelectorAll('[data-compare-version]:checked')].map(input => input.dataset.compareVersion);
        if (ids.length !== 2) return;
        compareButton.disabled = true;
        try {
          const [left, right] = await Promise.all(ids.map(id => ctx.dbManager.getFileHistoryVersion(id)));
          const changes = [];
          const compare = (path, a, b) => {
            if (changes.length >= 250 || JSON.stringify(a) === JSON.stringify(b)) return;
            if (a && b && typeof a === 'object' && typeof b === 'object' && Array.isArray(a) === Array.isArray(b)) {
              const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
              keys.forEach(key => compare(`${path}[${key}]`, a[key], b[key]));
            } else {
              changes.push({ path: path || '$', left: a, right: b });
            }
          };
          compare('', left.snapshot, right.snapshot);
          card.innerHTML = `<div class="modal-header"><h3 class="modal-title">File History comparison</h3><button type="button" class="export-cancel-button" data-back>Back</button></div><p>${escape(left.entry_type)} v${escape(left.version_id)} compared with ${escape(right.entry_type)} v${escape(right.version_id)}.</p><div style="max-height:65vh;overflow:auto"><table class="admin-records-table"><thead><tr><th>Path</th><th>Version ${escape(left.version_id)}</th><th>Version ${escape(right.version_id)}</th></tr></thead><tbody>${changes.map(change => `<tr><td>${escape(change.path)}</td><td><pre style="white-space:pre-wrap">${escape(JSON.stringify(change.left) ?? 'undefined')}</pre></td><td><pre style="white-space:pre-wrap">${escape(JSON.stringify(change.right) ?? 'undefined')}</pre></td></tr>`).join('') || '<tr><td colspan="3">The selected versions are identical.</td></tr>'}</tbody></table></div>${changes.length >= 250 ? '<p>Comparison capped at 250 differences.</p>' : ''}`;
          card.querySelector('[data-back]').addEventListener('click', renderList);
        } catch (error) {
          alert(`Unable to compare versions: ${error.message}`);
          compareButton.disabled = false;
        }
      });
      card.querySelectorAll('[data-view-version]').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          const version = await ctx.dbManager.getFileHistoryVersion(button.dataset.viewVersion);
          card.innerHTML = `<div class="modal-header"><h3 class="modal-title">Version ${escape(version.version_id)} · ${escape(version.entry_type)}</h3><button type="button" class="export-cancel-button" data-back>Back</button></div><p>Created ${escape(new Date(version.created_at).toLocaleString())} by Super Admin ${escape(version.acting_super_admin_id)}. Related source ${escape(version.source_record_id)}; target ${escape(version.target_record_id)}.</p><pre style="max-height:65vh;overflow:auto;white-space:pre-wrap">${escape(JSON.stringify(version.snapshot, null, 2))}</pre>`;
          card.querySelector('[data-back]').addEventListener('click', renderList);
        } catch (error) {
          button.disabled = false;
          alert(`Unable to view archived version: ${error.message}`);
        }
      }));
      card.querySelectorAll('[data-restore-version]').forEach(button => button.addEventListener('click', async () => {
        const versionId = button.dataset.restoreVersion;
        if (!confirm(`Restore version ${versionId}? The current state will first be archived as a new File History entry.`)) return;
        button.disabled = true;
        try {
          await ctx.dbManager.restoreFileHistoryVersion(versionId);
          close();
          await ctx.api.renderAdminPortal();
          showToast('Version restored. A new restore-result version was added to File History.');
        } catch (error) {
          button.disabled = false;
          alert(`Unable to restore archived version: ${error.message}`);
        }
      }));
    };
    renderList();
  };
  const clearGraphEditState = (removeGraphParam = false) => {
    ctx.state.studioActiveGraphId = null;
    ctx.state.studioActiveGraphPublished = false;
    ctx.state.studioActiveGraphUpdatedAt = null;
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
    ctx.state.studioActiveGraphUpdatedAt = graph.updated_at || graph.updatedAt || null;
    const titleInput = $('studioChartTitleInput');
    if (titleInput) {
      titleInput.value = graph.title || 'Saved Chart';
      titleInput.setAttribute('data-customized', 'true');
    }
    const typeSelect = $('studioChartTypeSelect');
    const savedType = String(graph.chart_type || 'bar');
    const chartType = typeSelect && [...typeSelect.options].some(option => option.value === savedType) ? savedType : 'bar';
    const irisConfig = graph.chart_data?.irisConfig || {};
    if (typeSelect) typeSelect.value = chartType;
    ctx.state.studioChartConfig = { ...(ctx.state.studioChartConfig || {}), ...irisConfig, orientation: graph.orientation || irisConfig.orientation || 'vertical' };
    ctx.state.studioChartOverrides = Array.isArray(graph.colors) ? [...graph.colors] : null;

    const sheet = ctx.api.getStudioActiveSheet(record)?.data;
    if (sheet) {
      ctx.api.updateFieldSelectOptions(sheet);
      const category = $('studioCategoryCol');
      const value = $('studioValueCol');
      const hasSavedIndex = index => index !== null && index !== undefined && index !== '' && Number.isInteger(Number(index)) && Number(index) >= 0 && Number(index) < sheet.headers.length;
      const categoryField = irisConfig.categoryField;
      const valueField = irisConfig.valueField;
      const groupField = irisConfig.groupField;
      if (hasSavedIndex(categoryField) && category) category.value = String(categoryField);
      if (hasSavedIndex(valueField) && value) value.value = String(valueField);
      ctx.api.updateFieldSelectOptions(sheet);
      const group = $('studioGroupField');
      if (hasSavedIndex(groupField) && group) group.value = String(groupField);
      const seriesField = $('studioSeriesField');
      if (hasSavedIndex(irisConfig.seriesField) && seriesField && [...seriesField.options].some(option => option.value === String(irisConfig.seriesField))) seriesField.value = String(irisConfig.seriesField);
      const precision = $('studioValuePrecisionSelect');
      if (precision && irisConfig.precision !== undefined) precision.value = String(irisConfig.precision);
      const reverse = $('studioReverseOrder');
      if (reverse) reverse.checked = Boolean(irisConfig.reverseOrder);
      const restoreValue = (id, key, fallback = '') => {
        const input = $(id);
        if (input && irisConfig[key] !== undefined && irisConfig[key] !== null) input.value = String(irisConfig[key]);
        else if (input) input.value = String(fallback);
      };
      restoreValue('studioFilterField', 'filterField', 'all');
      restoreValue('studioFilterOperator', 'filterOperator', 'all');
      restoreValue('studioFilterValue', 'filterValue');
      restoreValue('studioFilterUpperValue', 'filterUpperValue');
      restoreValue('studioSortOrder', 'sortOrder', 'source');
      restoreValue('studioRowLimit', 'rowLimit', 30);
      const groupDuplicates = $('studioGroupDuplicates');
      if (groupDuplicates) groupDuplicates.checked = irisConfig.groupDuplicates !== false;
      const applyColorsToAll = $('studioColorApplyAll');
      if (applyColorsToAll) applyColorsToAll.checked = irisConfig.applyColorsToAllCharts !== false && !Array.isArray(graph.colors);
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
    const bar = $('fileArchivesBulkActions');
    const count = $('fileArchivesBulkSelectionCount');
    const deleteBtn = $('fileArchivesBulkDelete');
    const publishBtn = $('fileArchivesBulkPublish');
    const unpublishBtn = $('fileArchivesBulkUnpublish');
    const n = selectedRecordIds.size;
    if (bar) bar.hidden = n === 0;
    if (count) count.textContent = `${n} record${n === 1 ? '' : 's'} selected`;
    if (deleteBtn) deleteBtn.disabled = n === 0;
    if (publishBtn) publishBtn.disabled = n === 0;
    if (unpublishBtn) unpublishBtn.disabled = n === 0;
  };

  const syncSelectAll = () => {
    const boxes = [...document.querySelectorAll('#fileArchivesTableBody .admin-record-checkbox')];
    const selectAll = $('fileArchivesSelectAll');
    if (!selectAll) return;
    const visibleIds = boxes.map(box => box.dataset.id).filter(Boolean);
    selectAll.checked = visibleIds.length > 0 && visibleIds.every(id => selectedRecordIds.has(id));
    selectAll.indeterminate = visibleIds.some(id => selectedRecordIds.has(id)) && !selectAll.checked;
  };

  const bindSelection = () => {
    document.querySelectorAll('#fileArchivesTableBody .admin-record-checkbox').forEach(box => {
      box.checked = selectedRecordIds.has(box.dataset.id);
      box.onchange = () => {
        if (box.checked) selectedRecordIds.add(box.dataset.id);
        else selectedRecordIds.delete(box.dataset.id);
        updateBulkActions();
        syncSelectAll();
      };
    });
    const selectAll = $('fileArchivesSelectAll');
    if (selectAll) selectAll.onchange = () => {
      document.querySelectorAll('#fileArchivesTableBody .admin-record-checkbox').forEach(box => {
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
    const body = $('fileArchivesTableBody');
    if (!body) return;
    closeRecordMenu();
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
      const isUncategorized = record.metadata?.template_status === 'uncategorized';
        const recordId = escape(record.id);
        const merge = record.metadata?.merge;
        const recentMerge = window.SheetMerge?.isRecentMerge(record.metadata, new Date()) === true;
        const mergeBadgeTitle = recentMerge
          ? `Merged ${new Date(merge.merged_at).toLocaleString()} from ${merge.source_office || 'office'}, replaced ${merge.replaced_file || 'previous file'}`
          : '';
      return `<tr>
        <td><input class="admin-record-checkbox" type="checkbox" data-id="${escape(record.id)}" aria-label="Select record ${escape(record.id)}"></td>
        <td>${escape(record.id)}</td>
          <td>${escape(record.fileName || 'Untitled')}${isUncategorized ? ' <span class="badge badge-low" title="General upload without a template; assign or configure a template during review">GENERAL · TEMPLATE NEEDED</span>' : ''}${recentMerge ? ` <span class="badge badge-low" title="${escape(mergeBadgeTitle)}">NEW</span>` : ''}</td>
        <td>${escape(uploader)}${escape(templateLabel)}${isNew ? ' <span class="badge badge-low" aria-label="New, not yet opened">New</span>' : ''}</td>
        <td>${escape((record.fileType || 'UNKNOWN').toUpperCase())}</td>
        <td><span class="badge">${escape(displayStatus)}</span></td>
        <td>${scannedDate ? escape(new Date(scannedDate).toLocaleString()) : 'N/A'}</td>
        <td><div class="admin-record-actions">
          <button class="record-actions-trigger" type="button" aria-label="File actions" aria-haspopup="menu" aria-expanded="false" data-record-menu-trigger>
            <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a5 5 0 0 0-6.6 6.6L3 18v3h3l5.1-5.1a5 5 0 0 0 6.6-6.6l-3 3-3-3 3-3Z"/><path d="m18 6 2-2"/></svg>
          </button>
          <div class="record-actions-menu-placeholder">
            <div class="record-actions-menu" role="menu" aria-label="File actions" hidden>
              <div class="record-actions-menu-group">Workflow</div>
              <button class="record-actions-menu-item btn-table-load-studio" type="button" role="menuitem" data-id="${recordId}"><i class="fa-solid fa-palette" aria-hidden="true"></i><span>Review</span></button>
              ${approved ? `<button class="record-actions-menu-item btn-table-unpublish" type="button" role="menuitem" data-id="${recordId}" title="Unpublish this record and its saved charts"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i><span>Unpublish</span></button>` : `<button class="record-actions-menu-item btn-table-approve" type="button" role="menuitem" data-id="${recordId}"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Publish</span></button>`}
              <div class="record-actions-menu-divider" role="separator"></div>
              <div class="record-actions-menu-group">Information</div>
              <button class="record-actions-menu-item btn-table-history" type="button" role="menuitem" data-id="${recordId}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>File History</span></button>
              ${record.metadata?.stored_file ? `<a class="record-actions-menu-item" role="menuitem" href="${escape(projectBase)}/admin/upload_source.php?id=${encodeURIComponent(record.id)}" target="_blank" rel="noopener"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i><span>Source</span></a>` : ''}
              <div class="record-actions-menu-divider" role="separator"></div>
              <div class="record-actions-menu-group">Danger zone</div>
              <button class="record-actions-menu-item record-actions-menu-danger btn-table-delete" type="button" role="menuitem" data-id="${recordId}" title="Delete record"><i class="fa-solid fa-trash" aria-hidden="true"></i><span>Delete</span></button>
            </div>
          </div>
        </div></td>
      </tr>`;
    }).join('');
    body.innerHTML = html || '<tr><td colspan="8">No files match these archive filters.</td></tr>';

    body.querySelectorAll('[data-record-menu-trigger]').forEach(trigger => {
      trigger.onclick = () => {
        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        if (expanded) closeRecordMenu();
        else openRecordMenu(trigger);
      };
    });
    body.querySelectorAll('.record-actions-menu').forEach(menu => {
      menu.onclick = event => {
        if (event.target.closest('[role="menuitem"]')) closeRecordMenu();
      };
    });

    all('.btn-table-load-studio').forEach(button => button.onclick = async () => {
      const record = filtered.find(item => String(item.id) === String(button.dataset.id));
      if (!record) return;
      try {
        await ctx.api.openReviewStudio(record.id);
      } catch (error) {
        alert(`Unable to open "${record.fileName || record.id}" for review: ${error.message}`);
      }
    });

    all('.btn-table-history').forEach(button => button.onclick = async () => {
      const record = filtered.find(item => String(item.id) === String(button.dataset.id));
      if (!record) return;
      button.disabled = true;
      try {
        await openRecordHistory(record);
      } finally {
        button.disabled = false;
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
    if (bulkPromptOpen) return;
    const ids = [...selectedRecordIds];
    if (!ids.length) return;
    bulkPromptOpen = true;
    let selected;
    try {
      const records = await ctx.dbManager.getAllRecords();
      selected = records.filter(record => ids.includes(String(record.id)));
    } catch (error) {
      bulkPromptOpen = false;
      alert(`Unable to load the selected uploads: ${error.message || error}`);
      return;
    }
    if (!selected.length) {
      bulkPromptOpen = false;
      selectedRecordIds.clear();
      bindSelection();
      alert('The selected uploads are no longer available. Refresh the archive and try again.');
      return;
    }
    const selectedIds = selected.map(record => String(record.id));
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
    const close = () => {
      bulkPromptOpen = false;
      modal.remove();
    };
    modal.querySelector('[data-close]').onclick = close;
    modal.querySelector('[data-confirm]').onclick = async () => {
      const action = modal.querySelector('[data-confirm]');
      action.disabled = true;
      try {
        const result = mode === 'delete'
          ? await ctx.dbManager.deleteRecords(selectedIds)
          : await ctx.dbManager.setRecordsPublication(selectedIds, mode === 'publish');
        close();
        selectedRecordIds.clear();
        await ctx.api.renderAdminPortal();
        const relatedCharts = Number(result.published_graph_count || 0);
        const message = mode === 'delete'
          ? `${result.successCount} of ${selectedIds.length} records deleted successfully.`
          : `${result.successCount} of ${selectedIds.length} records ${mode}ed with ${relatedCharts} related chart${relatedCharts === 1 ? '' : 's'}.`;
        showToast(message);
      } catch (error) {
        action.disabled = false;
        if (mode === 'delete' && error.message && error.message.includes('was stopped to protect')) {
          let overrideWarning = modal.querySelector('[data-override-warning]');
          if (!overrideWarning) {
            overrideWarning = document.createElement('div');
            overrideWarning.dataset.overrideWarning = '';
            overrideWarning.style.cssText = 'margin-top:.75rem;padding:.65rem .75rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:.5rem;font-size:.8rem;color:#991b1b;';
            overrideWarning.innerHTML = `<strong>⚠ Data protection triggered:</strong> ${error.message}<br><br>` +
              `<button type="button" data-override-confirm style="margin-top:.4rem;padding:.4rem .85rem;background:#dc2626;color:#fff;border:none;border-radius:.375rem;font-size:.8rem;font-weight:700;cursor:pointer;">Override &amp; force delete</button>` +
              `<span style="margin-left:.5rem;font-size:.75rem;color:#7f1d1d;">This will delete even if the data has changed since import.</span>`;
            action.closest('div').before(overrideWarning);
            overrideWarning.querySelector('[data-override-confirm]').onclick = async () => {
              overrideWarning.querySelector('[data-override-confirm]').disabled = true;
              try {
                const result = await ctx.dbManager.deleteRecords(selectedIds, { override: true });
                close();
                selectedRecordIds.clear();
                await ctx.api.renderAdminPortal();
                showToast(`${result.successCount} of ${selectedIds.length} records force-deleted.`);
              } catch (overrideError) {
                overrideWarning.querySelector('[data-override-confirm]').disabled = false;
                alert(`Force delete failed: ${overrideError.message}`);
              }
            };
          }
        } else {
          alert(`${mode[0].toUpperCase()}${mode.slice(1)} failed: ${error.message}`);
        }
      }
    };
  };

  const renderFileArchives = records => {
    archiveRecords = Array.isArray(records) ? records : archiveRecords;
    const officeFilter = $('fileArchiveOfficeFilter');
    const selectedOffice = officeFilter?.value || 'all';
    const offices = [...new Set(archiveRecords.map(record => record.office_name).filter(Boolean))].sort((a, b) => a.localeCompare(b));
    if (officeFilter) {
      officeFilter.innerHTML = '<option value="all">All offices</option>' + offices.map(name => `<option value="${escape(name)}">${escape(name)}</option>`).join('');
      officeFilter.value = offices.includes(selectedOffice) ? selectedOffice : 'all';
    }
    const term = (($('fileArchiveSearchInput')?.value || '')).toLowerCase();
    const purpose = $('fileArchivePurposeFilter')?.value || 'all';
    const status = $('fileArchiveStatusFilter')?.value || 'all';
    const office = officeFilter?.value || 'all';
    const filtered = archiveRecords.filter(record => {
      const haystack = [record.id, record.fileName, record.docType, record.rawText].join(' ').toLowerCase();
      return (purpose === 'all' || recordPurpose(record) === purpose)
        && haystack.includes(term)
        && (status === 'all' || record.status === status || (status === 'Pending Review' && !record.status))
        && (office === 'all' || record.office_name === office);
    });
    const visible = new Set(filtered.map(record => String(record.id)));
    [...selectedRecordIds].forEach(id => { if (!visible.has(String(id))) selectedRecordIds.delete(id); });
    renderRows(filtered);
  };

  ctx.api.renderFileArchives = async () => renderFileArchives(await ctx.dbManager.getAllRecords());

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
      const allRecords = await ctx.dbManager.getAllRecords();
      const records = allRecords.filter(record => !importDestinations.has(recordPurpose(record)));
      if ($('statTotalDb')) $('statTotalDb').textContent = records.length;
      if ($('statPendingDb')) $('statPendingDb').textContent = records.filter(r => r.status === 'Pending Review' || !r.status).length;
      if ($('statVerifiedDb')) $('statVerifiedDb').textContent = records.filter(r => ['Approved', 'Verified & Approved'].includes(r.status)).length;
      if ($('statTablesDb')) $('statTablesDb').textContent = records.reduce((sum, r) => sum + Object.keys(r.extractedData || {}).length, 0);
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

      renderFileArchives(allRecords);
    } catch (error) {
      console.error(error);
      if ($('fileArchivesTableBody')) {
        $('fileArchivesTableBody').innerHTML = '<tr><td colspan="8">File archives are temporarily unavailable.</td></tr>';
      }
    }
  };

  $('fileArchivesBulkDelete')?.addEventListener('click', () => confirmBulk('delete'));
  $('fileArchivesBulkPublish')?.addEventListener('click', () => confirmBulk('publish'));
  $('fileArchivesBulkUnpublish')?.addEventListener('click', () => confirmBulk('unpublish'));
  $('fileArchivesClearSelection')?.addEventListener('click', () => { selectedRecordIds.clear(); bindSelection(); });
  $('fileArchiveSearchInput')?.addEventListener('input', () => renderFileArchives());
  $('fileArchivePurposeFilter')?.addEventListener('change', () => renderFileArchives());
  $('fileArchiveStatusFilter')?.addEventListener('change', () => renderFileArchives());
  $('fileArchiveOfficeFilter')?.addEventListener('change', () => renderFileArchives());
  document.addEventListener('iris:template-review-complete', () => ctx.api.renderAdminPortal());
  document.addEventListener('iris:template-review-complete', () => ctx.api.renderAdminPortal());

  let refreshTimer = null;
  const refreshArchive = async () => {
    if (document.visibilityState !== 'visible') return;
    try {
      const records = await ctx.dbManager.getAllRecords();
      renderFileArchives(records);
      const studioRecords = records.filter(record => !importDestinations.has(recordPurpose(record)));
      if ($('statTotalDb')) $('statTotalDb').textContent = studioRecords.length;
      if ($('statPendingDb')) $('statPendingDb').textContent = studioRecords.filter(record => record.status === 'Pending Review' || !record.status).length;
      if ($('statVerifiedDb')) $('statVerifiedDb').textContent = studioRecords.filter(record => ['Approved', 'Verified & Approved'].includes(record.status)).length;
      if ($('statTablesDb')) $('statTablesDb').textContent = studioRecords.reduce((sum, record) => sum + Object.keys(record.extractedData || {}).length, 0);
    } catch (error) { console.warn('Archive refresh failed:', error); }
  };
  window.addEventListener('focus', refreshArchive);
  document.addEventListener('visibilitychange', refreshArchive);
  refreshTimer = window.setInterval(refreshArchive, 30000);

  let changeChannel = null;
  if ('BroadcastChannel' in window) {
    changeChannel = new BroadcastChannel('iris-data-change');
    changeChannel.addEventListener('message', () => {
      // If we are currently visible, fetch immediately. If not, visibilitychange will catch it.
      if (document.visibilityState === 'visible') {
        refreshArchive();
      }
    });
  }

  window.addEventListener('pagehide', () => {
    window.clearInterval(refreshTimer);
    if (changeChannel) changeChannel.close();
  }, { once: true });

  ctx.api.renderAdminPortal();
}

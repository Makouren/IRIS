/**
 * Purpose: Scanner import module for import preview modal; supports the browser-side import workflow.
 */
import { getJson, postJson } from './importApiClient.js';
import { displayValues, identityLabel } from './importDataMapper.js';

const modal = document.getElementById('templateImportModal');
if (modal) {
  const notice = modal.querySelector('[data-import-notice]');
  const rowHost = modal.querySelector('[data-import-rows]');
  const fileLabel = modal.querySelector('[data-import-file]');
  const sheetLabel = modal.querySelector('[data-import-sheet]');
  const pageLabel = modal.querySelector('[data-import-page]');
  const previousButton = modal.querySelector('[data-import-previous]');
  const nextButton = modal.querySelector('[data-import-next]');
  const applyButton = modal.querySelector('[data-import-apply]');
  const reviewedCheckbox = modal.querySelector('[data-import-reviewed]');
  const revertButton = modal.querySelector('[data-import-revert]');
  const sourcePicker = modal.querySelector('[data-import-source-picker]');
  const sourceSelect = modal.querySelector('[data-import-source]');
  const loadSourceButton = modal.querySelector('[data-import-load-source]');
  const deleteUploadButton = modal.querySelector('[data-import-delete-upload]');
  const sheetPicker = modal.querySelector('[data-import-sheet-picker]');
  const sheetSelect = modal.querySelector('[data-import-sheet-select]');
  const sheetChooseButton = modal.querySelector('[data-import-sheet-choose]');
  const reviewSurface = modal.querySelector('[data-import-review-surface]');
  const pagination = modal.querySelector('[data-import-pagination]');
  const identityHeading = modal.querySelector('[data-import-identity-heading]');
  const existingHeading = modal.querySelector('[data-import-existing-heading]');
  const incomingHeading = modal.querySelector('[data-import-incoming-heading]');
  const applyHeading = modal.querySelector('[data-import-apply-heading]');
  const summarySelection = modal.querySelector('[data-import-summary-selection]');
  const selectAllSummary = modal.querySelector('[data-import-select-all]');
  const selectionCount = modal.querySelector('[data-import-selection-count]');
  const pageSize = 200;
  let state = null;
  let previewPending = false;

  function addDestinationImportButton(anchorId, destination, label) {
    const anchor = document.getElementById(anchorId);
    if (!anchor || document.getElementById(`import-${destination}-button`)) return;
    const button = document.createElement('button');
    button.id = `import-${destination}-button`;
    button.type = 'button';
    button.className = 'btn-save-modal';
    button.dataset.importDestination = destination;
    if (destination === 'summary_cards') {
      button.innerHTML = '<i class="fa-solid fa-file-import" aria-hidden="true"></i> ';
      button.append(document.createTextNode(label));
      button.className = 'summary-card-manager-menu-item';
      const menu = document.getElementById('summaryCardManagerActionMenu');
      if (menu) {
        menu.append(button);
        return;
      }
    }
    if (destination === 'ranking_history') {
      button.innerHTML = '<i class="fa-solid fa-file-import" aria-hidden="true"></i> ';
      button.append(document.createTextNode(label));
      button.className = 'summary-card-manager-menu-item';
      anchor.before(button);
      return;
    }
    if (!button.textContent) {
      button.innerHTML = '<i class="fa-solid fa-file-import" aria-hidden="true"></i> ';
      button.append(document.createTextNode(label));
    }
    anchor.before(button);
  }

  addDestinationImportButton('addSummaryCardFromManager', 'summary_cards', 'Import snapshot');
  addDestinationImportButton('addRankingHistoryRow', 'ranking_history', 'Import rankings');

  function setNotice(message, error = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${error ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  function showSheetChoices(error) {
    summarySelection.classList.add('hidden');
    summarySelection.classList.remove('flex');
    sheetSelect.replaceChildren(new Option('Choose a worksheet', ''));
    for (const name of error.candidate_sheets || []) sheetSelect.add(new Option(name, name));
    if (error.selected_sheet && (error.candidate_sheets || []).includes(error.selected_sheet)) sheetSelect.value = error.selected_sheet;
    sourcePicker.classList.add('hidden');
    sourcePicker.classList.remove('flex');
    reviewSurface.classList.add('hidden');
    pagination.classList.add('hidden');
    sheetPicker.classList.remove('hidden');
    sheetPicker.classList.add('flex');
    setNotice(error.message || 'Choose the worksheet to preview.', true);
  }

  function show() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
  }

  function close() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
  }

  function addCell(row, value, className = '') {
    const cell = document.createElement('td');
    cell.className = `whitespace-pre-wrap p-2 align-top text-xs ${className}`;
    cell.textContent = value == null ? '' : String(value);
    row.append(cell);
  }

  function syncApplyButton() {
    const isSummary = state?.destination === 'summary_cards';
    const selectedSummaryChange = isSummary && state.rows.some(row => state.selected.has(row.key) && ['new_period', 'updated_period'].includes(row.kind));
    const actionable = state?.rows.filter(row => isSummary
      ? ['new_period', 'updated_period'].includes(row.kind)
      : !['blocked', 'unchanged'].includes(row.kind)) || [];
    const selectedCount = actionable.filter(row => state.selected.has(row.key)).length;
    selectAllSummary.checked = actionable.length > 0 && selectedCount === actionable.length;
    selectAllSummary.indeterminate = selectedCount > 0 && selectedCount < actionable.length;
    selectAllSummary.disabled = actionable.length === 0;
    selectionCount.textContent = `${selectedCount} of ${actionable.length} available row(s) selected`;
    const selectedRankingRows = !isSummary && selectedCount > 0;
    applyButton.disabled = !reviewedCheckbox.checked || (isSummary ? !selectedSummaryChange : !selectedRankingRows);
  }

  function valuesFor(row, side) {
    if (state.destination === 'summary_cards') {
      const includeCardSettings = (values, settings) => {
        const cardSettings = { ...(settings || {}) };
        if (Array.isArray(cardSettings.category_names)) cardSettings.category_names = cardSettings.category_names.join(', ');
        return { ...values, ...cardSettings };
      };
      if (side === 'existing') {
        const existing = row.snapshot_before;
        const existingValues = existing
          ? Object.fromEntries(['title', 'main_value', 'secondary_value', 'year_date', 'main_label', 'secondary_label', 'description', 'secondary_description', 'info_text', 'source_info', 'updated_at']
            .filter(field => existing[field] !== null && existing[field] !== undefined && existing[field] !== '')
            .map(field => [field, existing[field]]))
          : {};
        return includeCardSettings(existingValues, row.card_settings_before);
      }
      const { period_key, period_label, period_sort, period_precision, ...sourceValues } = row.snapshot_after || {};
      return includeCardSettings(sourceValues, row.card_settings_after);
    }
    const displayFields = ['organization', 'ranking_type', 'year', 'global_rank', 'rank_value', 'ph_rank', 'source'];
    const values = side === 'existing' ? row.existing : row.identity;
    return Object.fromEntries(displayFields.filter(field => values?.[field] !== null && values?.[field] !== undefined && values?.[field] !== '')
      .map(field => [field, values[field]]));
  }

  function renderRows() {
    const rows = state?.rows || [];
    const isSummary = state?.destination === 'summary_cards';
    summarySelection.classList.remove('hidden');
    summarySelection.classList.add('flex');
    identityHeading.textContent = isSummary ? 'Card description / year' : 'Action / identity';
    existingHeading.hidden = false;
    existingHeading.textContent = isSummary ? 'Existing snapshot / card' : 'Existing values';
    incomingHeading.textContent = isSummary ? 'Values from file' : 'Incoming values';
    applyHeading.hidden = isSummary;
    applyHeading.textContent = 'Apply row';
    const start = state.page * pageSize;
    const visible = rows.slice(start, start + pageSize);
    rowHost.replaceChildren();
    if (!rows.length) {
      const emptyRow = document.createElement('tr');
      const emptyCell = document.createElement('td');
      emptyCell.colSpan = 5;
      emptyCell.className = 'p-5 text-center text-sm text-gray-500';
      emptyCell.textContent = 'No import rows were returned.';
      emptyRow.append(emptyCell);
      rowHost.append(emptyRow);
    }
    for (const item of visible) {
      const row = document.createElement('tr');
      const selectCell = document.createElement('td');
      selectCell.className = 'p-2 text-center align-top';
      if (!isSummary) {
        const select = document.createElement('input');
        select.type = 'checkbox';
        select.dataset.importKey = item.key;
        select.checked = state.selected.has(item.key);
        select.disabled = ['blocked', 'unchanged'].includes(item.kind);
        select.setAttribute('aria-label', `Apply ${item.kind} from row ${item.row_number}`);
        select.addEventListener('change', () => {
          if (select.checked) state.selected.add(item.key);
          else state.selected.delete(item.key);
          syncApplyButton();
        });
        selectCell.append(select);
      }
      if (isSummary) {
        const sourceCell = document.createElement('td');
        sourceCell.className = 'p-2 align-top text-xs';
        const choice = document.createElement('label');
        choice.className = 'inline-flex items-start gap-2';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = state.selected.has(item.key);
        checkbox.disabled = !['new_period', 'updated_period'].includes(item.kind);
        checkbox.setAttribute('aria-label', `Select ${item.period_label} from ${item.sheet_name}, row ${item.row_number}`);
        checkbox.addEventListener('change', () => {
          if (checkbox.checked) state.selected.add(item.key);
          else state.selected.delete(item.key);
          reviewedCheckbox.checked = false;
          syncApplyButton();
        });
        choice.append(checkbox, document.createTextNode(`${item.sheet_name} · row ${item.row_number}`));
        sourceCell.append(choice);
        row.append(sourceCell);
      } else {
        addCell(row, `${item.sheet_name} · row ${item.row_number}`);
      }
      const snapshotTime = item.snapshot_before?.updated_at ? ` · Snapshot updated ${item.snapshot_before.updated_at}` : '';
      const rankingAction = item.new_type ? `NEW TYPE · ${item.kind.toUpperCase()}` : item.kind === 'legacy' ? 'ADOPT LEGACY' : item.kind.toUpperCase();
      const rankingError = !isSummary && item.error
        ? `${item.error}${item.blocked_field ? `\nBlocked field: ${item.blocked_field}${item.blocked_column ? ` · source column ${item.blocked_column.header} (Column ${item.blocked_column.letter})` : ''}` : ''}`
        : '';
      addCell(row, isSummary
        ? `${item.preview_status || item.kind} · ${item.snapshot_after?.main_label || item.import_key} · ${item.period_label}${snapshotTime}`
        : `${rankingAction} · ${identityLabel(item, state.destination)}${rankingError ? `\n${rankingError}` : ''}`,
      item.kind === 'blocked' ? 'text-red-700' : '');
      const existingCell = document.createElement('td');
      existingCell.className = 'whitespace-pre-wrap p-2 align-top text-xs';
      existingCell.textContent = displayValues(valuesFor(item, 'existing'));
      existingCell.hidden = false;
      row.append(existingCell);
      addCell(row, displayValues(valuesFor(item, 'incoming')));
      if (!isSummary) row.append(selectCell);
      rowHost.append(row);
    }
    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    pageLabel.textContent = `Page ${state.page + 1} of ${totalPages} · ${rows.length} rows`;
    previousButton.disabled = state.page === 0;
    nextButton.disabled = state.page + 1 >= totalPages;
    syncApplyButton();
  }

  async function previewRecord(recordId, destination, sheetName = null) {
    if (previewPending) return;
    previewPending = true;
    loadSourceButton.disabled = true;
    sheetChooseButton.disabled = true;
    state = { recordId, page: 0, rows: [], selected: new Set(), destination, rowVersions: {}, sheetName };
    summarySelection.classList.add('hidden');
    summarySelection.classList.remove('flex');
    reviewedCheckbox.checked = false;
    rowHost.replaceChildren();
    sourcePicker.classList.add('hidden');
    sourcePicker.classList.remove('flex');
    sheetPicker.classList.add('hidden');
    sheetPicker.classList.remove('flex');
    reviewSurface.classList.remove('hidden');
    pagination.classList.remove('hidden');
    pagination.classList.add('flex');
    fileLabel.textContent = `Upload ${recordId}`;
    sheetLabel.textContent = '';
    revertButton.classList.add('hidden');
    revertButton.disabled = false;
    applyButton.disabled = true;
    show();
    setNotice('Loading the template profile and uploaded worksheet...');
    try {
      const profile = await getJson(`${modal.dataset.profileApi}?resource=import_profile_for_record&record_id=${encodeURIComponent(recordId)}`);
      if (profile.destination !== destination) throw new Error('This upload is configured for a different import destination.');
      const endpoint = profile.destination === 'ranking_history' ? modal.dataset.rankingApi : modal.dataset.summaryApi;
      state.endpoint = endpoint;
      fileLabel.textContent = `${profile.template_name} · ${profile.destination === 'ranking_history' ? 'Ranking History' : 'Summary Cards'}`;
      const payload = { action: 'preview', record_id: recordId };
      if (sheetName) payload.sheet_name = sheetName;
      const result = await postJson(endpoint, modal.dataset.csrf, payload);
      state.sheetName = result.sheet_name || sheetName || null;
      state.rows = result.rows || [];
      state.rowVersions = Object.fromEntries(state.rows.map(row => [row.key, row.row_version]));
      reviewedCheckbox.checked = false;
      sheetLabel.textContent = `Worksheet: ${result.sheet_name || 'selected sheet'}`;
      renderRows();
      const actionable = state.rows.filter(row => ['new_period', 'updated_period', 'update', 'legacy'].includes(row.kind)).length;
      setNotice(actionable
        ? destination === 'summary_cards'
            ? `${actionable} period(s) are available. Select any rows to apply. Blank cells preserve prior values; use __CLEAR__ to clear a field.`
          : `${actionable} row(s) can be applied. Select the rows you approve.`
        : 'No changes detected.');
    } catch (error) {
      if (error.requires_sheet_selection) {
        showSheetChoices(error);
        return;
      }
      rowHost.replaceChildren();
      summarySelection.classList.add('hidden');
      summarySelection.classList.remove('flex');
      reviewSurface.classList.add('hidden');
      pagination.classList.add('hidden');
      sourcePicker.classList.remove('hidden');
      sourcePicker.classList.add('flex');
      sheetPicker.classList.add('hidden');
      sheetPicker.classList.remove('flex');
      setNotice(error.message || 'Unable to preview this import.', true);
    } finally {
      previewPending = false;
      loadSourceButton.disabled = !sourceSelect.value;
      sheetChooseButton.disabled = !sheetSelect.value;
    }
  }

  function renderImportRecords(records, selectedId = '') {
    sourceSelect.replaceChildren(new Option('Choose an upload', ''));
    for (const record of records) {
      const details = [record.template_name, record.office_name || 'Office', record.uploaded_at || record.status].filter(Boolean).join(' · ');
      sourceSelect.add(new Option(`${record.file_name} · ${details}`, String(record.id)));
    }
    if (selectedId) sourceSelect.value = selectedId;
    const hasSelection = Boolean(sourceSelect.value);
    loadSourceButton.disabled = !hasSelection;
    deleteUploadButton.disabled = !hasSelection;
  }

  async function openImportPicker(destination) {
    state = { page: 0, rows: [], selected: new Set(), destination, rowVersions: {} };
    summarySelection.classList.add('hidden');
    summarySelection.classList.remove('flex');
    fileLabel.textContent = destination === 'summary_cards' ? 'Latest Performance Snapshot' : 'Ranking History';
    sheetLabel.textContent = '';
    rowHost.replaceChildren();
    reviewSurface.classList.add('hidden');
    pagination.classList.add('hidden');
    sourcePicker.classList.remove('hidden');
    sourcePicker.classList.add('flex');
    sheetPicker.classList.add('hidden');
    sheetPicker.classList.remove('flex');
    sourceSelect.replaceChildren(new Option('Choose an upload', ''));
    const summaryImport = destination === 'summary_cards';
    loadSourceButton.classList.toggle('hidden', !summaryImport);
    deleteUploadButton.classList.toggle('hidden', !summaryImport);
    loadSourceButton.disabled = true;
    deleteUploadButton.disabled = true;
    applyButton.disabled = true;
    reviewedCheckbox.checked = false;
    revertButton.classList.add('hidden');
    show();
    setNotice('Loading uploads assigned to a matching import profile...');
    try {
      const records = await getJson(`${modal.dataset.profileApi}?resource=import_records&destination=${encodeURIComponent(destination)}`);
      renderImportRecords(records);
      setNotice(records.length ? 'Choose an upload to load its preview.' : 'No supported uploads have a matching template profile.', records.length === 0);
    } catch (error) {
      setNotice(error.message || 'Unable to load matching uploads.', true);
    }
  }

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-import-destination]');
    if (trigger) openImportPicker(trigger.dataset.importDestination);
  });
  modal.querySelectorAll('[data-import-close]').forEach(button => button.addEventListener('click', close));
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('flex')) close(); });
  previousButton.addEventListener('click', () => { if (state?.page > 0) { state.page--; renderRows(); } });
  nextButton.addEventListener('click', () => { if (state && (state.page + 1) * pageSize < state.rows.length) { state.page++; renderRows(); } });
  reviewedCheckbox.addEventListener('change', syncApplyButton);
  selectAllSummary.addEventListener('change', () => {
    if (!state) return;
    for (const row of state.rows) {
      const actionable = state.destination === 'summary_cards'
        ? ['new_period', 'updated_period'].includes(row.kind)
        : !['blocked', 'unchanged'].includes(row.kind);
      if (!actionable) continue;
      if (selectAllSummary.checked) state.selected.add(row.key);
      else state.selected.delete(row.key);
    }
    reviewedCheckbox.checked = false;
    renderRows();
  });
  sourceSelect.addEventListener('change', () => {
    const summaryImport = state?.destination === 'summary_cards';
    loadSourceButton.disabled = !sourceSelect.value;
    deleteUploadButton.disabled = !summaryImport || !sourceSelect.value;
    if (!summaryImport && state?.destination && sourceSelect.value) previewRecord(sourceSelect.value, state.destination);
  });
  loadSourceButton.addEventListener('click', () => {
    if (state?.destination === 'summary_cards' && sourceSelect.value) previewRecord(sourceSelect.value, state.destination);
  });
  deleteUploadButton.addEventListener('click', async () => {
    if (state?.destination !== 'summary_cards' || !sourceSelect.value) return;
    const selectedRecordId = sourceSelect.value;
    const selectedLabel = sourceSelect.selectedOptions[0]?.textContent || 'this upload';
    if (!window.confirm(`Remove ${selectedLabel} from the import list and delete its uploaded file? Applied Summary Card data and history will be preserved.`)) return;
    deleteUploadButton.disabled = true;
    try {
      const data = new FormData();
      data.set('action', 'delete-summary-card-upload');
      data.set('record_id', selectedRecordId);
      data.set('_csrf', modal.dataset.csrf || '');
      const response = await fetch(modal.dataset.profileApi, {
        method: 'POST',
        headers: { 'X-CSRF-Token': modal.dataset.csrf || '', Accept: 'application/json' },
        body: data
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'Unable to delete this upload.');
      const records = await getJson(`${modal.dataset.profileApi}?resource=import_records&destination=summary_cards`);
      renderImportRecords(records);
      setNotice(result.preserved_record
        ? 'Upload file removed from the list. Its applied Summary Card history and audit record were preserved.'
        : 'Upload file and unused record deleted. Existing Summary Card data and history were not changed.');
    } catch (error) {
      setNotice(error.message || 'Unable to delete this upload.', true);
      deleteUploadButton.disabled = !sourceSelect.value;
    }
  });
  sheetChooseButton.disabled = true;
  sheetSelect.addEventListener('change', () => { sheetChooseButton.disabled = !sheetSelect.value || previewPending; });
  sheetChooseButton.addEventListener('click', () => {
    if (previewPending || !state?.recordId || !state?.destination) return;
    if (!sheetSelect.value) {
      setNotice('Choose a worksheet before continuing.', true);
      return;
    }
    previewRecord(state.recordId, state.destination, sheetSelect.value);
  });

  applyButton.addEventListener('click', async () => {
    if (applyButton.disabled || !state || !reviewedCheckbox.checked) return;
    const selectedSummaryRows = state.destination === 'summary_cards'
      ? state.rows.filter(row => state.selected.has(row.key) && ['new_period', 'updated_period'].includes(row.kind))
      : [];
    if (state.destination === 'summary_cards' && !selectedSummaryRows.length) return;
    const plannedRows = state.destination === 'summary_cards'
      ? selectedSummaryRows
      : state.rows.filter(row => state.selected.has(row.key));
    const confirmation = state.destination === 'summary_cards'
      ? `Apply ${selectedSummaryRows.length} selected Summary Card row(s)?`
      : `Apply ${plannedRows.length} selected import row(s)?`;
    if (!window.confirm(confirmation)) return;
    applyButton.disabled = true;
    try {
      const result = await postJson(state.endpoint, modal.dataset.csrf, {
        action: 'apply', record_id: state.recordId, sheet_name: state.sheetName, row_versions: state.rowVersions, reviewed_diff: true,
        ...(state.destination === 'summary_cards'
          ? { selected_rows: selectedSummaryRows.map(row => ({ sheet_name: row.sheet_name, row_number: row.row_number })) }
          : { accepted_keys: [...state.selected] })
      });
      setNotice(result.message || 'Import completed.');
      if (result.batch_id) {
        state.batchId = result.batch_id;
        revertButton.classList.remove('hidden');
        revertButton.dataset.batchId = String(result.batch_id);
      }
      let refreshed;
      try {
        refreshed = await postJson(state.endpoint, modal.dataset.csrf, { action: 'preview', record_id: state.recordId, sheet_name: state.sheetName });
      } catch (refreshError) {
        state.selected.clear();
        reviewedCheckbox.checked = false;
        renderRows();
        setNotice(`Import completed, but the preview could not refresh: ${refreshError.message}`, true);
        return;
      }
      state.rows = refreshed.rows || [];
      state.rowVersions = Object.fromEntries(state.rows.map(row => [row.key, row.row_version]));
      state.selected.clear();
      reviewedCheckbox.checked = false;
      renderRows();
      document.dispatchEvent(new CustomEvent('iris:template-import-complete', { detail: { ...result, destination: state.destination } }));
    } catch (error) {
      if (error.requires_sheet_selection) {
        showSheetChoices(error);
        setNotice('The template sheet selection changed. Choose a worksheet and review the new preview.', true);
        return;
      }
      setNotice(error.message || 'Import failed.', true);
      applyButton.disabled = state.selected.size === 0;
    }
  });

  revertButton.addEventListener('click', async () => {
    if (!state?.batchId || !window.confirm('Revert this import? It will be stopped if affected data changed after the import.')) return;
    revertButton.disabled = true;
    try {
      const result = await postJson(modal.dataset.recoveryApi, modal.dataset.csrf, { batch_id: state.batchId });
      setNotice(`Import reverted. ${result.restored_rows} audit row(s) restored.`);
      revertButton.classList.add('hidden');
    } catch (error) {
      setNotice(error.message || 'Unable to revert this import.', true);
      revertButton.disabled = false;
    }
  });
}

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
  const reviewSurface = modal.querySelector('[data-import-review-surface]');
  const pagination = modal.querySelector('[data-import-pagination]');
  const identityHeading = modal.querySelector('[data-import-identity-heading]');
  const existingHeading = modal.querySelector('[data-import-existing-heading]');
  const incomingHeading = modal.querySelector('[data-import-incoming-heading]');
  const applyHeading = modal.querySelector('[data-import-apply-heading]');
  const pageSize = 200;
  let state = null;

  function addDestinationImportButton(anchorId, destination, label) {
    const anchor = document.getElementById(anchorId);
    if (!anchor || document.getElementById(`import-${destination}-button`)) return;
    const button = document.createElement('button');
    button.id = `import-${destination}-button`;
    button.type = 'button';
    button.className = 'btn-save-modal';
    button.dataset.importDestination = destination;
    button.innerHTML = '<i class="fa-solid fa-file-import" aria-hidden="true"></i> ';
    button.append(document.createTextNode(label));
    anchor.before(button);
  }

  addDestinationImportButton('addSummaryCardFromManager', 'summary_cards', 'Import snapshot');
  addDestinationImportButton('addRankingHistoryRow', 'ranking_history', 'Import rankings');

  function setNotice(message, error = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${error ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
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
    const labels = isSummary ? new Set(state.rows.map(row => row.import_key)) : new Set();
    const summaryReady = !isSummary || [...labels].every(label => state.currentKeys?.[label]);
    const summaryChanges = isSummary && (state.rows.some(row => ['insert', 'replace'].includes(row.kind))
      || Object.values(state.currentKeys || {}).some(key => {
        const row = state.rows.find(candidate => candidate.key === key);
        return row && (row.current_changed || row.new_card);
      }));
    const selectedRankingRows = state?.destination !== 'summary_cards' && state?.selected.size;
    applyButton.disabled = !reviewedCheckbox.checked || !summaryReady || (!selectedRankingRows && !summaryChanges);
  }

  function valuesFor(row, side) {
    if (state.destination === 'summary_cards') {
      if (side === 'existing') {
        const existing = row.snapshot_before || row.current;
        if (!existing) return {};
        return Object.fromEntries(['main_value', 'secondary_value', 'year_date', 'main_label', 'secondary_label', 'description', 'secondary_description', 'info_text', 'updated_at']
          .filter(field => existing[field] !== null && existing[field] !== undefined && existing[field] !== '')
          .map(field => [field, existing[field]]));
      }
      const { import_key, period_key, ...sourceValues } = row.incoming || {};
      return sourceValues;
    }
    return side === 'existing'
      ? { ...row.existing, id: undefined, seed_managed: undefined }
      : row.identity;
  }

  function renderRows() {
    const rows = state?.rows || [];
    const isSummary = state?.destination === 'summary_cards';
    identityHeading.textContent = isSummary ? 'Card description / year' : 'Action / identity';
    existingHeading.hidden = false;
    existingHeading.textContent = isSummary ? 'Existing snapshot / card' : 'Existing values';
    incomingHeading.textContent = isSummary ? 'Values from file' : 'Incoming values';
    applyHeading.hidden = false;
    applyHeading.textContent = isSummary ? 'Apply' : 'Apply row';
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
      if (isSummary) {
        const select = document.createElement('input');
        select.type = 'radio';
        select.name = `summary-apply-${item.import_key}`;
        select.value = item.key;
        select.checked = state.currentKeys[item.import_key] === item.key;
        select.setAttribute('aria-label', `Apply ${item.period_key} for ${item.import_key}`);
        select.addEventListener('change', () => {
          state.currentKeys[item.import_key] = item.key;
          syncApplyButton();
        });
        selectCell.append(select);
      } else {
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
      addCell(row, `${item.sheet_name} · row ${item.row_number}`);
      const updateTime = item.current?.updated_at ? ` · Card updated ${item.current.updated_at}` : '';
      const snapshotTime = item.snapshot_before?.updated_at ? ` · Snapshot updated ${item.snapshot_before.updated_at}` : '';
      addCell(row, isSummary
        ? `${item.kind === 'replace' ? 'REPLACE EXISTING' : item.kind === 'unchanged' ? 'UNCHANGED' : item.new_card ? 'NEW CARD' : 'NEW PERIOD'} · ${item.incoming?.main_label || item.import_key} · ${item.period_key}${updateTime}${snapshotTime}`
        : `${item.kind === 'legacy' ? 'ADOPT LEGACY' : item.kind.toUpperCase()} · ${identityLabel(item, state.destination)}${item.error ? `\n${item.error}` : ''}`,
      item.kind === 'blocked' ? 'text-red-700' : '');
      const existingCell = document.createElement('td');
      existingCell.className = 'whitespace-pre-wrap p-2 align-top text-xs';
      existingCell.textContent = displayValues(valuesFor(item, 'existing'));
      existingCell.hidden = false;
      row.append(existingCell);
      addCell(row, displayValues(valuesFor(item, 'incoming')));
      row.append(selectCell);
      rowHost.append(row);
    }
    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    pageLabel.textContent = `Page ${state.page + 1} of ${totalPages} · ${rows.length} rows`;
    previousButton.disabled = state.page === 0;
    nextButton.disabled = state.page + 1 >= totalPages;
    syncApplyButton();
  }

  async function previewRecord(recordId, destination) {
    state = { recordId, page: 0, rows: [], selected: new Set(), currentKeys: Object.create(null), destination, rowVersions: {} };
    reviewedCheckbox.checked = false;
    rowHost.replaceChildren();
    sourcePicker.classList.add('hidden');
    sourcePicker.classList.remove('flex');
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
      const result = await postJson(endpoint, modal.dataset.csrf, { action: 'preview', record_id: recordId });
      state.rows = result.rows || [];
      state.rowVersions = Object.fromEntries(state.rows.map(row => [row.key, row.row_version]));
      if (destination === 'summary_cards') state.rows.filter(row => row.suggested).forEach(row => { state.currentKeys[row.import_key] = row.key; });
      reviewedCheckbox.checked = false;
      sheetLabel.textContent = `Worksheet: ${result.sheet_name || 'selected sheet'}`;
      renderRows();
      const actionable = state.rows.filter(row => ['insert', 'replace', 'update', 'legacy'].includes(row.kind)).length;
      const liveUpdates = state.rows.filter(row => row.suggested && (row.current_changed || row.new_card)).length;
      setNotice(actionable || liveUpdates
        ? destination === 'summary_cards'
          ? `${actionable} period(s) will be archived automatically. One row per Global Label is selected for the live card.`
          : `${actionable} row(s) can be applied. Select the rows you approve.`
        : 'No changes detected.');
    } catch (error) {
      rowHost.replaceChildren();
      reviewSurface.classList.add('hidden');
      pagination.classList.add('hidden');
      sourcePicker.classList.remove('hidden');
      sourcePicker.classList.add('flex');
      setNotice(error.message || 'Unable to preview this import.', true);
    }
  }

  async function openImportPicker(destination) {
    state = { page: 0, rows: [], selected: new Set(), currentKeys: Object.create(null), destination, rowVersions: {} };
    fileLabel.textContent = destination === 'summary_cards' ? 'Latest Performance Snapshot' : 'Ranking History';
    sheetLabel.textContent = '';
    rowHost.replaceChildren();
    reviewSurface.classList.add('hidden');
    pagination.classList.add('hidden');
    sourcePicker.classList.remove('hidden');
    sourcePicker.classList.add('flex');
    sourceSelect.replaceChildren(new Option('Choose an upload', ''));
    applyButton.disabled = true;
    reviewedCheckbox.checked = false;
    revertButton.classList.add('hidden');
    show();
    setNotice('Loading uploads assigned to a matching import profile...');
    try {
      const records = await getJson(`${modal.dataset.profileApi}?resource=import_records&destination=${encodeURIComponent(destination)}`);
      sourceSelect.replaceChildren(new Option('Choose an upload', ''));
      for (const record of records) {
        const details = [record.template_name, record.office_name || 'Office', record.uploaded_at || record.status].filter(Boolean).join(' · ');
        sourceSelect.add(new Option(`${record.file_name} · ${details}`, String(record.id)));
      }
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
  sourceSelect.addEventListener('change', () => {
    if (state?.destination && sourceSelect.value) previewRecord(sourceSelect.value, state.destination);
  });

  applyButton.addEventListener('click', async () => {
    if (!state || !reviewedCheckbox.checked) return;
    const plannedRows = state.destination === 'summary_cards'
      ? state.rows.filter(row => ['insert', 'replace'].includes(row.kind)
        || Object.values(state.currentKeys).includes(row.key) && (row.new_card || row.current_changed))
      : state.rows.filter(row => state.selected.has(row.key));
    const replacementCount = plannedRows.filter(row => row.kind === 'replace').length;
    const currentChanges = Object.values(state.currentKeys).filter(key => state.rows.find(row => row.key === key)?.current_changed || state.rows.find(row => row.key === key)?.new_card).length;
    const confirmation = `Apply ${plannedRows.length} row(s), replace ${replacementCount} existing period(s), and update ${currentChanges} live card(s)?`;
    if (!window.confirm(confirmation)) return;
    applyButton.disabled = true;
    try {
      const result = await postJson(state.endpoint, modal.dataset.csrf, {
        action: 'apply', record_id: state.recordId, current_keys: state.currentKeys, row_versions: state.rowVersions, reviewed_diff: true,
        ...(state.destination === 'ranking_history' ? { accepted_keys: [...state.selected] } : {})
      });
      setNotice(result.message || 'Import completed.');
      if (result.batch_id) {
        state.batchId = result.batch_id;
        revertButton.classList.remove('hidden');
        revertButton.dataset.batchId = String(result.batch_id);
      }
      let refreshed;
      try {
        refreshed = await postJson(state.endpoint, modal.dataset.csrf, { action: 'preview', record_id: state.recordId });
      } catch (refreshError) {
        state.selected.clear();
        reviewedCheckbox.checked = false;
        renderRows();
        setNotice(`Import completed, but the preview could not refresh: ${refreshError.message}`, true);
        return;
      }
      state.rows = refreshed.rows || [];
      state.rowVersions = Object.fromEntries(state.rows.map(row => [row.key, row.row_version]));
      state.currentKeys = Object.create(null);
      if (state.destination === 'summary_cards') state.rows.filter(row => row.suggested).forEach(row => { state.currentKeys[row.import_key] = row.key; });
      state.selected.clear();
      reviewedCheckbox.checked = false;
      renderRows();
      document.dispatchEvent(new CustomEvent('iris:template-import-complete', { detail: result }));
    } catch (error) {
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

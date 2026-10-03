(() => {
  const modal = document.getElementById('templateManagerModal');
  if (!modal) return;
  const api = modal.dataset.api;
  const token = modal.dataset.csrf || '';
  const panels = [...modal.querySelectorAll('[data-profile-mapper]')];
  const states = new Map();
  const labels = {
    import_key: 'Import key', card_title: 'Card title', period_key: 'Period', main_value: 'Main value', main_label: 'Main label',
    year_date: 'Year / date', secondary_label: 'Secondary label', secondary_value: 'Secondary value', description: 'Description',
    secondary_description: 'Secondary description', info_text: 'Information text', source_info: 'Source information',
    category_names: 'Categories', display_precision: 'Display precision', organization: 'Organization', ranking_type: 'Ranking type',
    year: 'Year', global_rank: 'Rank', ph_rank: 'Philippine Rank', info_text: 'Information'
  };

  function showNotice(message) {
    const notice = document.getElementById('templateManagerNotice');
    if (!notice) return;
    notice.textContent = message;
    notice.className = 'mb-4 rounded-lg p-3 text-sm bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200';
  }

  function getProfileId(destination) {
    const select = modal.querySelector(destination === 'summary_cards' ? '[data-summary-profile-select]' : '[data-ranking-profile-select]');
    return select?.value || '';
  }

  function getEditor(destination) {
    return modal.querySelector(destination === 'summary_cards' ? '[data-summary-profile-json]' : '[data-ranking-profile-json]');
  }

  function getSaveButton(destination) {
    return modal.querySelector(destination === 'summary_cards' ? '[data-summary-profile-save]' : '[data-ranking-profile-save]');
  }

  function profileJson(destination) {
    try {
      const value = JSON.parse(getEditor(destination)?.value || '{}');
      return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    } catch (error) { return {}; }
  }

  async function requestJson(responsePromise) {
    let response;
    try { response = await responsePromise; }
    catch {
      const message = 'Server error. Check the PHP log.';
      showNotice(message);
      throw new Error(message);
    }
    let result;
    try { result = JSON.parse(await response.text()); }
    catch {
      const message = 'Server error. Check the PHP log.';
      showNotice(message);
      throw new Error(message);
    }
    return { response, result };
  }

  async function post(data) {
    data.set('_csrf', token);
    const { response, result } = await requestJson(fetch(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data }));
    if (!response.ok) {
      const message = result.error || 'Workbook mapping request failed.';
      showNotice(message);
      throw new Error(message);
    }
    return result;
  }

  function statusMessage(panel, message, isError = false) {
    const target = panel.querySelector('[data-mapper-status]');
    target.textContent = message;
    target.className = `mb-2 text-xs ${isError ? 'font-semibold text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-slate-300'}`;
  }

  function columnLabel(index) {
    let value = index + 1;
    let label = '';
    while (value > 0) {
      value--;
      label = String.fromCharCode(65 + (value % 26)) + label;
      value = Math.floor(value / 26);
    }
    return label;
  }

  async function refreshPanel(panel) {
    const destination = panel.dataset.profileMapper;
    const profileId = getProfileId(destination);
    const input = panel.querySelector('[data-mapper-file]');
    const selectButton = panel.querySelector('[data-mapper-upload-label]');
    if (!profileId) {
      input.disabled = true;
      if (selectButton) selectButton.classList.add('pointer-events-none', 'opacity-50');
      statusMessage(panel, 'Choose an available profile before mapping a workbook.');
      return;
    }
    try {
      const { response, result } = await requestJson(fetch(`${api}?resource=profile_mapper_status&destination=${encodeURIComponent(destination)}&profile_id=${encodeURIComponent(profileId)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' }));
      if (!response.ok) throw new Error(result.error || 'Unable to check the profile.');
      panel.dataset.customFieldsReady = String(Boolean(result.custom_fields_ready));
      input.disabled = !result.ready;
      if (selectButton) selectButton.classList.toggle('pointer-events-none', !result.ready);
      if (selectButton) selectButton.classList.toggle('opacity-50', !result.ready);
      statusMessage(panel, result.ready
        ? (result.custom_fields_ready ? 'Upload a workbook to map its headers to this profile.' : 'Workbook mapping is ready. Apply the custom import fields migration before adding custom fields.')
        : (result.message || 'Workbook mapping is unavailable for this profile.'));
    } catch (error) {
      input.disabled = true;
      if (selectButton) selectButton.classList.add('pointer-events-none', 'opacity-50');
      showNotice(error.message);
      statusMessage(panel, error.message, true);
    }
  }

  function excelFile(file) {
    if (!/\.xls$/i.test(file.name)) return Promise.resolve({ file, originalName: file.name });
    if (file.size < 1 || file.size > 10 * 1024 * 1024) return Promise.reject(new Error('The workbook must be between 1 byte and 10 MB.'));
    if (typeof XLSX === 'undefined') return Promise.reject(new Error('Excel legacy workbook support is unavailable on this page. Save the file as XLSX.'));
    return file.arrayBuffer().then(buffer => {
      const signature = new Uint8Array(buffer.slice(0, 8));
      const expected = [0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1];
      if (expected.some((byte, index) => signature[index] !== byte)) throw new Error('The file contents do not match the selected XLS file type.');
      if (file.type && !['application/vnd.ms-excel', 'application/x-ole-storage', 'application/x-cfb', 'application/octet-stream'].includes(file.type)) {
        throw new Error('The file MIME type does not match an XLS workbook.');
      }
      const workbook = XLSX.read(buffer, { type: 'array', cellDates: true });
      const output = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
      return { file: new File([output], file.name.replace(/\.xls$/i, '.xlsx'), { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), originalName: file.name };
    });
  }

  function clearWorkbook(panel) {
    const state = states.get(panel);
    if (state) states.delete(panel);
    panel.dataset.workbookToken = '';
    delete panel.dataset.workbookHeaderRow;
    panel.querySelector('[data-mapper-file]').value = '';
    const saveButton = getSaveButton(panel.dataset.profileMapper);
    if (saveButton) saveButton.disabled = false;
    panel.querySelector('[data-mapper-controls]').classList.add('hidden');
    panel.querySelector('[data-mapper-preview-panel]').classList.add('hidden');
  }

  function renderSelectors(panel, upload) {
    const destination = panel.dataset.profileMapper;
    const profile = profileJson(destination);
    const preferredSheet = String(profile.sheet_selector || '');
    const profileId = getProfileId(destination);
    const state = { token: upload.token, profileId, destination, originalName: upload.original_filename, sheets: upload.sheets || [], preview: null, confirmed: false, addedFields: [], customFieldsReady: panel.dataset.customFieldsReady === 'true' };
    states.set(panel, state);
    panel.dataset.workbookToken = upload.token;
    const sheetSelect = panel.querySelector('[data-mapper-sheet]');
    const headerSelect = panel.querySelector('[data-mapper-header-row]');
    sheetSelect.replaceChildren(...state.sheets.map(sheet => new Option(sheet.name, sheet.name)));
    if (state.sheets.some(sheet => sheet.name === preferredSheet)) sheetSelect.value = preferredSheet;
    const updateHeaderRows = () => {
      const sheet = state.sheets.find(item => item.name === sheetSelect.value);
      const savedRow = Number(profile.workbook_header_row || 1);
      const maxRow = Number(sheet?.max_row || 1);
      headerSelect.max = String(Math.min(10000, Math.max(1, maxRow)));
      headerSelect.value = String(savedRow <= Number(headerSelect.max) ? savedRow : 1);
    };
    sheetSelect.addEventListener('change', updateHeaderRows);
    updateHeaderRows();
    const invalidatePreview = () => {
      state.preview = null;
      state.confirmed = false;
      const saveButton = getSaveButton(destination);
      if (saveButton) saveButton.disabled = true;
      panel.querySelector('[data-mapper-preview-panel]').classList.add('hidden');
      panel.querySelector('[data-mapper-confirm]').disabled = true;
      statusMessage(panel, 'Worksheet or header row changed. Preview mappings again before confirming.');
    };
    sheetSelect.addEventListener('change', invalidatePreview);
    headerSelect.addEventListener('change', invalidatePreview);
    panel.querySelector('[data-mapper-controls]').classList.remove('hidden');
    panel.querySelector('[data-mapper-controls]').classList.add('flex');
    panel.querySelector('[data-mapper-preview-panel]').classList.add('hidden');
    statusMessage(panel, `Workbook loaded: ${upload.original_filename}. Select its worksheet and header row.`);
  }

  function renderTable(panel, data) {
    const state = states.get(panel);
    state.preview = data;
    const body = panel.querySelector('[data-mapper-rows]');
    const headers = data.headers || [];
    const suggestions = data.suggestions || {};
    const samples = data.samples || [];
    const required = new Set(data.required || []);
    body.replaceChildren();
    const targetFields = [...(data.targets || []), ...state.addedFields.map(item => `custom_fields.${item.key}`)];
    for (const field of targetFields) {
      const suggestion = suggestions[field] || { header: null, match_type: 'none' };
      const row = document.createElement('tr');
      row.dataset.field = field;
      const customField = field.startsWith('custom_fields.');
      row.dataset.required = required.has(field) || state.addedFields.some(item => `custom_fields.${item.key}` === field) ? '1' : '0';
      if (required.has(field) && !suggestion.header) row.className = 'bg-red-50 dark:bg-red-950/30';
      const targetCell = document.createElement('td');
      targetCell.className = 'p-2 font-semibold';
      if (customField) {
        const key = field.slice('custom_fields.'.length);
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = 80;
        input.required = true;
        input.placeholder = 'Custom field label';
        input.value = state.addedFields.find(item => item.key === key)?.label || profileJson(state.destination).custom_fields?.[key] || '';
        input.className = 'w-full rounded border border-gray-300 bg-white p-1.5 text-gray-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
        input.dataset.customFieldLabel = key;
        input.addEventListener('input', () => { state.confirmed = false; updateValidation(panel); });
        targetCell.append(input);
      } else {
        targetCell.textContent = labels[field] || field;
      }
      const selectCell = document.createElement('td');
      selectCell.className = 'p-2';
      const select = document.createElement('select');
      select.className = 'w-full rounded border border-gray-300 bg-white p-1.5 dark:border-slate-700 dark:bg-slate-800';
      select.dataset.fieldSelect = field;
      select.add(new Option('Not mapped', ''));
      headers.forEach((header, index) => select.add(new Option(`${header} (${columnLabel(index)})`, String(index))));
      if (suggestion.header && suggestion.index !== null) select.value = String(suggestion.index);
      select.addEventListener('change', () => {
        state.confirmed = false;
        const typeCell = row.querySelector('[data-match-type]');
        const initial = suggestions[field];
        const closeIndex = initial?.suggested_header ? headers.findIndex(header => header === initial.suggested_header) : -1;
        typeCell.textContent = select.value === '' ? (initial?.match_type === 'close' ? 'close (needs confirmation)' : 'none')
          : (initial?.match_type === 'close' && closeIndex === Number(select.value) ? 'close (confirmed)' : initial?.index === Number(select.value) ? initial.match_type : 'manual');
        sampleCell.textContent = select.value === '' ? '' : samples.map(sample => String(sample[Number(select.value)] ?? '').trim()).filter(Boolean).slice(0, 3).join(' · ');
        updateValidation(panel);
      });
      selectCell.append(select);
      const matchCell = document.createElement('td');
      matchCell.className = 'p-2';
      matchCell.dataset.matchType = '';
      matchCell.textContent = suggestion.match_type === 'close'
        ? `close (needs confirmation): ${suggestion.suggested_header || ''}`
        : suggestion.match_type;
      const sampleCell = document.createElement('td');
      sampleCell.className = 'whitespace-pre-wrap p-2 text-gray-700 dark:text-slate-200';
      const columnIndex = suggestion.index === null && suggestion.suggested_header
        ? headers.findIndex(header => header === suggestion.suggested_header)
        : suggestion.index;
      sampleCell.textContent = columnIndex === null ? '' : samples.map(sample => String(sample[columnIndex] ?? '').trim()).filter(Boolean).slice(0, 3).join(' · ');
      row.append(targetCell, selectCell, matchCell, sampleCell);
      body.append(row);
    }
    panel.querySelector('[data-mapper-preview-panel]').classList.remove('hidden');
    updateValidation(panel);
  }

  function updateValidation(panel) {
    const state = states.get(panel);
    if (!state?.preview) return;
    const rows = [...panel.querySelectorAll('[data-mapper-rows] tr')];
    const selects = rows.map(row => row.querySelector('[data-field-select]'));
    const assigned = selects.map(select => select.value).filter(value => value !== '');
    const duplicate = new Set(assigned).size !== assigned.length;
    const missing = rows.filter(row => row.dataset.required === '1' && !row.querySelector('select').value).map(row => row.querySelector('td').textContent);
    const summary = panel.querySelector('[data-mapper-columns-status]');
    const messages = [];
    if (missing.length) messages.push(`Unmapped required fields: ${missing.join(', ')}.`);
    if (duplicate) messages.push('A worksheet column is assigned to more than one field.');
    const invalidCustom = rows.some(row => row.dataset.field.startsWith('custom_fields.') && !String(row.querySelector('[data-custom-field-label]')?.value || '').trim());
    if (invalidCustom) messages.push('Enter a label for every custom field.');
    summary.textContent = messages.length ? messages.join(' ') : 'All required fields are mapped. Unmapped columns will be ignored.';
    const duplicateValues = new Set(assigned.filter(value => assigned.indexOf(value) !== assigned.lastIndexOf(value)));
    selects.forEach(select => select.classList.toggle('border-red-500', duplicateValues.has(select.value)));
    panel.querySelector('[data-mapper-add-field]').disabled = !state.customFieldsReady || rows.filter(row => row.dataset.field.startsWith('custom_fields.')).length >= 50;
    summary.className = `mb-2 rounded px-2 py-1 text-xs ${missing.length || duplicate ? 'bg-red-50 font-semibold text-red-700 dark:bg-red-950/30 dark:text-red-300' : 'text-gray-600 dark:text-slate-300'}`;
    panel.querySelector('[data-mapper-confirm]').disabled = missing.length > 0 || duplicate || invalidCustom;
    const saveButton = getSaveButton(state.destination);
    if (saveButton) saveButton.disabled = missing.length > 0 || duplicate || invalidCustom || !state.confirmed;
  }

  function addCustomField(panel) {
    const state = states.get(panel);
    if (!state?.preview || !state.customFieldsReady) return;
    const existing = new Set([
      ...Object.keys(profileJson(state.destination).custom_fields || {}),
      ...state.addedFields.map(item => item.key)
    ]);
    if (existing.size >= 50) return;
    let index = 1;
    while (existing.has(`custom_field_${index}`)) index++;
    const key = `custom_field_${index}`;
    state.addedFields.push({ key, label: '' });
    renderTable(panel, state.preview);
    panel.querySelector(`[data-field-select="custom_fields.${key}"]`)?.focus();
  }

  async function upload(panel, file) {
    const destination = panel.dataset.profileMapper;
    const profileId = getProfileId(destination);
    if (!profileId) return;
    if (panel.dataset.uploading === 'true') return;
    panel.dataset.uploading = 'true';
    const fileInput = panel.querySelector('[data-mapper-file]');
    if (fileInput) fileInput.disabled = true;
    clearWorkbook(panel);
    const saveButton = getSaveButton(destination);
    if (saveButton) saveButton.disabled = true;
    statusMessage(panel, 'Reading workbook…');
    try {
      const converted = await excelFile(file);
      const data = new FormData();
      data.set('action', 'upload-profile-workbook');
      data.set('destination', destination);
      data.set('profile_id', profileId);
      data.set('original_filename', converted.originalName);
      data.set('profile_workbook', converted.file);
      const result = await post(data);
      renderSelectors(panel, result);
    } catch (error) {
      if (saveButton) saveButton.disabled = false;
      showNotice(error.message);
      statusMessage(panel, error.message || 'Unable to read workbook.', true);
    } finally {
      panel.dataset.uploading = 'false';
      if (fileInput) fileInput.disabled = false;
    }
  }

  async function preview(panel) {
    const state = states.get(panel);
    const previewButton = panel.querySelector('[data-mapper-preview]');
    if (!state || previewButton.disabled) return;
    previewButton.disabled = true;
    const data = new FormData();
    data.set('action', 'preview-profile-workbook');
    data.set('destination', state.destination);
    data.set('profile_id', state.profileId);
    data.set('workbook_token', state.token);
    data.set('sheet_name', panel.querySelector('[data-mapper-sheet]').value);
    data.set('header_row', panel.querySelector('[data-mapper-header-row]').value);
    try {
      const result = await post(data);
      state.confirmed = false;
      renderTable(panel, result);
      statusMessage(panel, `Previewing ${result.sheet}, header row ${result.header_row}.`);
    } catch (error) {
      panel.querySelector('[data-mapper-preview-panel]').classList.add('hidden');
      showNotice(error.message);
      statusMessage(panel, error.message || 'Unable to preview workbook headers.', true);
    } finally {
      previewButton.disabled = false;
    }
  }

  function confirm(panel) {
    const state = states.get(panel);
    const preview = state?.preview;
    if (!state || !preview) return;
    const selectors = [...panel.querySelectorAll('[data-field-select]')];
    const selected = selectors.map(select => select.value).filter(value => value !== '');
    if (new Set(selected).size !== selected.length) { updateValidation(panel); return; }
    const profile = profileJson(state.destination);
    const mappings = { ...(profile.mapping_rules || {}) };
    const aliases = { ...(profile.header_aliases || {}) };
    const customFields = { ...(profile.custom_fields || {}) };
    for (const key of Object.keys(customFields)) {
      delete mappings[`custom_fields.${key}`];
      delete aliases[`custom_fields.${key}`];
    }
    for (const select of selectors) {
      const field = select.dataset.fieldSelect;
      if (field.startsWith('custom_fields.')) {
        const key = field.slice('custom_fields.'.length);
        const label = panel.querySelector(`[data-custom-field-label="${key}"]`)?.value.trim() || '';
        if (!label) continue;
        customFields[key] = label;
        if (select.value === '') continue;
        const header = preview.headers[Number(select.value)];
        mappings[field] = header;
        continue;
      }
      if (select.value === '') { delete mappings[field]; delete aliases[field]; continue; }
      const header = preview.headers[Number(select.value)];
      mappings[field] = header;
      const existing = Array.isArray(aliases[field]) ? aliases[field] : [];
      aliases[field] = [...new Set([header, ...existing])];
    }
    profile.mapping_rules = mappings;
    profile.header_aliases = aliases;
    profile.custom_fields = customFields;
    profile.sheet_selector = panel.querySelector('[data-mapper-sheet]').value;
    const editor = getEditor(state.destination);
    editor.value = JSON.stringify(profile, null, 2);
    editor.dispatchEvent(new Event('input', { bubbles: true }));
    panel.dataset.workbookToken = state.token;
    panel.dataset.workbookHeaderRow = String(preview.header_row);
    state.confirmed = true;
    const saveButton = getSaveButton(state.destination);
    if (saveButton) saveButton.disabled = false;
    statusMessage(panel, 'Mappings confirmed in Field mappings. Use Save profile settings to save the profile and workbook.');
  }

  panels.forEach(panel => {
    const destination = panel.dataset.profileMapper;
    const fileInput = panel.querySelector('[data-mapper-file]');
    const select = modal.querySelector(destination === 'summary_cards' ? '[data-summary-profile-select]' : '[data-ranking-profile-select]');
    fileInput.addEventListener('change', () => { if (fileInput.files?.[0]) upload(panel, fileInput.files[0]); });
    select?.addEventListener('change', () => { clearWorkbook(panel); refreshPanel(panel); });
    panel.querySelector('[data-mapper-preview]').addEventListener('click', () => preview(panel));
    panel.querySelector('[data-mapper-close-preview]').addEventListener('click', () => {
      panel.querySelector('[data-mapper-preview-panel]').classList.add('hidden');
    });
    panel.querySelector('[data-mapper-confirm]').addEventListener('click', () => confirm(panel));
    panel.querySelector('[data-mapper-add-field]').addEventListener('click', () => addCustomField(panel));
  });

  window.IRISProfileWorkbookMapper = {
    refresh: () => panels.forEach(refreshPanel),
    workbookToken: (destination, profileId) => {
      const panel = panels.find(item => item.dataset.profileMapper === destination);
      const state = panel ? states.get(panel) : null;
      return state && String(state.profileId) === String(profileId) && state.preview && state.confirmed ? state.token : '';
    },
    workbookHeaderRow: destination => panels.find(item => item.dataset.profileMapper === destination)?.dataset.workbookHeaderRow || '' ,
    clear: destination => {
      const panel = panels.find(item => item.dataset.profileMapper === destination);
      if (panel) clearWorkbook(panel);
    }
  };
})();
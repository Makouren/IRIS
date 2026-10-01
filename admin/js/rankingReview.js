(() => {
  const modal = document.getElementById('rankingReviewModal');
  if (!modal) return;

  const api = modal.dataset.api;
  const templateApi = modal.dataset.templateApi;
  const sourceBase = modal.dataset.sourceBase;
  const token = modal.dataset.csrf || '';
  const templateSelect = document.getElementById('rankingReviewTemplate');
  const recordName = document.getElementById('rankingReviewRecordName');
  const notice = document.getElementById('rankingReviewNotice');
  const counts = document.getElementById('rankingReviewCounts');
  const rowsContainer = document.getElementById('rankingReviewRows');
  const previewButton = document.getElementById('rankingReviewPreview');
  const approveButton = document.getElementById('rankingReviewApprove');
  let record = null;
  let parsedRows = [];
  let preview = null;

  function showNotice(message, isError = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${isError ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  async function readResponse(response) {
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Request failed (${response.status}).`);
    return payload;
  }

  async function post(data) {
    const response = await fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
      body: JSON.stringify(data)
    });
    return readResponse(response);
  }

  function reset() {
    record = null;
    parsedRows = [];
    preview = null;
    recordName.textContent = '';
    counts.replaceChildren();
    rowsContainer.replaceChildren();
    const empty = document.createElement('p');
    empty.className = 'px-3 py-5 text-sm text-gray-500 dark:text-slate-400';
    empty.textContent = 'Choose a linked template and preview the upload.';
    rowsContainer.append(empty);
    approveButton.disabled = true;
    templateSelect.replaceChildren(new Option('Choose a linked template', ''));
    notice.className = 'mb-4 hidden rounded-lg p-3 text-sm';
  }

  function showModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
  }

  function normalizedHeader(value) {
    return String(value ?? '').trim().toLowerCase();
  }

  async function parseRankingRows(sourceRecord) {
    const extension = String(sourceRecord.fileType || '').toLowerCase();
    if (!['xlsx', 'xls', 'csv'].includes(extension)) {
      throw new Error('Ranking diff supports XLSX, XLS, and CSV uploads.');
    }
    if (typeof window.ExcelParser !== 'function') throw new Error('The existing spreadsheet parser is unavailable.');
    const response = await fetch(`${sourceBase}?id=${encodeURIComponent(sourceRecord.id)}`, { cache: 'no-store' });
    if (!response.ok) throw new Error('Unable to load the uploaded source file.');
    const blob = await response.blob();
    const file = new File([blob], sourceRecord.fileName || `upload.${extension}`, { type: blob.type });
    const parser = new window.ExcelParser();
    const parsed = await parser.parse(file);
    const sheets = Object.values(parsed.sheetsData || {});
    const result = [];
    let matchedSheets = 0;
    for (const sheet of sheets) {
      const headers = (sheet.headers || []).map(normalizedHeader);
      const yearIndex = headers.indexOf('year');
      const categoryIndex = headers.indexOf('category');
      const rankIndex = ['overall rank', 'global rank', 'rank']
        .map(header => headers.indexOf(header))
        .find(index => index >= 0) ?? -1;
      if (yearIndex < 0 || rankIndex < 0) continue;
      matchedSheets++;
      (sheet.rows || []).forEach((values, index) => {
        result.push({
          year: values[yearIndex] ?? '',
          category: categoryIndex >= 0 ? (values[categoryIndex] ?? '') : '',
          global_rank: values[rankIndex] ?? '',
          sheet_name: sheet.name || '',
          row_number: index + 2
        });
      });
    }
    if (!matchedSheets) throw new Error('No sheet has the exact required headers: Year and Rank, Overall Rank, or Global Rank. Category is optional.');
    if (!result.length) throw new Error('No ranking data rows were found under the required headers.');
    return result;
  }

  function renderCounts(result) {
    counts.replaceChildren();
    for (const [key, label] of [['new', 'New'], ['changed', 'Changed'], ['unchanged', 'Unchanged']]) {
      const card = document.createElement('div');
      card.className = 'rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800';
      const value = document.createElement('p');
      value.className = 'text-xl font-extrabold';
      value.textContent = String(result.counts?.[key] ?? 0);
      const caption = document.createElement('p');
      caption.className = 'text-xs font-semibold text-gray-500 dark:text-slate-400';
      caption.textContent = label;
      card.append(value, caption);
      counts.append(card);
    }
    const skipped = document.createElement('div');
    skipped.className = 'rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800';
    const skippedValue = document.createElement('p');
    skippedValue.className = 'text-xl font-extrabold';
    skippedValue.textContent = String(result.skipped_rows ?? 0);
    const skippedLabel = document.createElement('p');
    skippedLabel.className = 'text-xs font-semibold text-gray-500 dark:text-slate-400';
    skippedLabel.textContent = 'Blank rows';
    skipped.append(skippedValue, skippedLabel);
    counts.append(skipped);
  }

  function renderDiff(result) {
    rowsContainer.replaceChildren();
    if (!result.rows?.length) {
      const empty = document.createElement('p');
      empty.className = 'px-3 py-5 text-sm text-gray-500 dark:text-slate-400';
      empty.textContent = 'No new or changed rows. The record can still be approved.';
      rowsContainer.append(empty);
      approveButton.disabled = false;
      return;
    }
    for (const row of result.rows) {
      const container = document.createElement('div');
      container.className = 'grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1.3fr)_100px] items-center gap-2 px-3 py-2 text-sm';
      const yearCategory = document.createElement('div');
      yearCategory.className = 'min-w-0';
      const primary = document.createElement('p');
      primary.className = 'font-bold';
      primary.textContent = `${row.year}${row.category ? ` · ${row.category}` : ''}`;
      const type = document.createElement('p');
      type.className = row.kind === 'changed' ? 'text-xs font-bold text-amber-700 dark:text-amber-300' : 'text-xs font-bold text-emerald-700 dark:text-emerald-300';
      type.textContent = row.kind === 'changed' ? 'Changed' : 'New';
      yearCategory.append(primary, type);
      const current = document.createElement('span');
      current.className = 'break-words text-gray-600 dark:text-slate-300';
      current.textContent = row.kind === 'changed' ? String(row.existing_global_rank ?? row.existing_rank_value ?? '—') : '—';
      const uploaded = document.createElement('span');
      uploaded.className = 'break-words font-semibold';
      uploaded.textContent = String(row.global_rank);
      const decision = document.createElement('label');
      decision.className = 'flex items-center gap-2 text-xs font-semibold';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.dataset.acceptKey = row.key;
      checkbox.checked = row.kind === 'new';
      checkbox.addEventListener('change', () => { approveButton.disabled = false; });
      const decisionText = document.createElement('span');
      decisionText.textContent = 'Accept';
      decision.append(checkbox, decisionText);
      container.append(yearCategory, current, uploaded, decision);
      rowsContainer.append(container);
    }
    approveButton.disabled = false;
  }

  async function loadRecord(recordId) {
    const response = await fetch(`${api}?record_id=${encodeURIComponent(recordId)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const payload = await readResponse(response);
    record = payload.record;
    recordName.textContent = `${record.fileName} · ${record.office_name || 'Legacy / Super Admin'} · ${record.status}`;
    for (const template of payload.templates || []) {
      const option = new Option(`${template.name} · ${template.ranking_body_name} (${template.ranking_body_short_name})`, String(template.id));
      option.dataset.bodyId = String(template.ranking_body_id);
      templateSelect.add(option);
    }
    if (record.template_id) templateSelect.value = String(record.template_id);
    if (!payload.templates?.length) showNotice('No templates are linked to a ranking body yet.', true);
  }

  async function openForRecord(recordId) {
    reset();
    showModal();
    try { await loadRecord(recordId); }
    catch (error) { showNotice(error.message, true); }
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-template-review-record]');
    if (button) openForRecord(button.dataset.templateReviewRecord);
  });
  modal.querySelectorAll('[data-ranking-review-close]').forEach(button => button.addEventListener('click', closeModal));
  modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });

  previewButton.addEventListener('click', async () => {
    if (!record) return;
    const templateId = templateSelect.value;
    if (!templateId) { showNotice('Choose a template linked to a ranking body.', true); return; }
    previewButton.disabled = true;
    approveButton.disabled = true;
    try {
      if (String(record.template_id || '') !== templateId) {
        await post({ action: 'assign-template', record_id: record.id, template_id: templateId });
        record.template_id = Number(templateId);
      }
      parsedRows = await parseRankingRows(record);
      preview = await post({ action: 'preview', record_id: record.id, rows: parsedRows });
      renderCounts(preview);
      renderDiff(preview);
      showNotice(`Compared against ${templateSelect.selectedOptions[0]?.textContent || 'the selected ranking body'}.`);
    } catch (error) { showNotice(error.message, true); }
    finally { previewButton.disabled = false; }
  });

  approveButton.addEventListener('click', async () => {
    if (!record || !preview) return;
    const acceptedKeys = [...rowsContainer.querySelectorAll('[data-accept-key]:checked')].map(input => input.dataset.acceptKey);
    if (!window.confirm(`Approve this record and apply ${acceptedKeys.length} selected new/changed ranking row(s)?`)) return;
    approveButton.disabled = true;
    try {
      const result = await post({ action: 'approve', record_id: record.id, rows: parsedRows, accepted_keys: acceptedKeys });
      showNotice(`Record approved. ${result.inserted} row(s) inserted; ${result.updated} row(s) updated.`);
      document.dispatchEvent(new CustomEvent('iris:template-review-complete'));
    } catch (error) {
      showNotice(error.message, true);
      approveButton.disabled = false;
    }
  });
})();

(() => {
  const modal = document.getElementById('summaryCardHistoryModal');
  if (!modal) return;
  const api = modal.dataset.api;
  const rowsHost = modal.querySelector('[data-summary-history-rows]');
  const notice = modal.querySelector('[data-summary-history-notice]');
  const currentLabel = modal.querySelector('[data-summary-history-current]');
  const token = modal.dataset.csrf || '';
  let cardId = '';
  let periods = [];

  const fields = [
    ['title', 'Card title'], ['main_value', 'Main value'], ['main_label', 'Main label'], ['year_date', 'Display period'],
    ['secondary_label', 'Secondary label'], ['secondary_value', 'Secondary value'], ['description', 'Description'],
    ['secondary_description', 'Secondary description'], ['info_text', 'Information text'], ['source_info', 'Source information']
  ];

  function setNotice(text, error = false) {
    notice.textContent = text;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${error ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  function close() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
  }

  async function load() {
    const response = await fetch(`${api}&id=${encodeURIComponent(cardId)}&view=admin`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to load Summary Card history.');
    periods = result.periods || [];
    currentLabel.textContent = `Current public period: ${result.current_public_period || 'None'} · Latest imported period: ${result.latest_imported_period || 'None'}`;
    render();
  }

  function field(label, name, value) {
    const wrapper = document.createElement('label');
    wrapper.className = 'block text-xs font-semibold';
    wrapper.append(document.createTextNode(label));
    const input = ['description', 'secondary_description', 'info_text', 'source_info'].includes(name)
      ? document.createElement('textarea') : document.createElement('input');
    input.className = 'mt-1 block w-full rounded-md border border-gray-300 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-800';
    input.dataset.field = name;
    input.value = String(value ?? '');
    if (name === 'main_value') input.required = true;
    wrapper.append(input);
    return wrapper;
  }

  function render() {
    rowsHost.replaceChildren();
    for (const period of periods) {
      const article = document.createElement('article');
      article.className = 'rounded-lg border border-gray-200 p-3 dark:border-slate-700';
      article.dataset.periodKey = period.period_key;
      const heading = document.createElement('div');
      heading.className = 'flex flex-wrap items-center justify-between gap-2';
      const title = document.createElement('strong');
      title.textContent = `${period.period_label || period.period_key} · ${period.title || 'Summary Card'}`;
      const state = document.createElement('span');
      state.className = 'text-xs font-bold';
      state.textContent = period.is_current_public ? 'Current public period' : period.is_published ? 'Published history' : 'Unpublished';
      heading.append(title, state);
      const content = document.createElement('p');
      content.className = 'mt-2 whitespace-pre-wrap text-xs text-gray-600 dark:text-slate-300';
      content.textContent = `${period.main_value} · ${period.main_label}${period.secondary_label ? ` · ${period.secondary_label}: ${period.secondary_value}` : ''}`;
      const provenance = document.createElement('p');
      provenance.className = 'mt-2 text-xs text-gray-500 dark:text-slate-400';
      provenance.textContent = `Original import: ${period.source_info || period.source_record_id || 'Manual'} (batch ${period.batch_id || 'n/a'}) · Latest import: ${period.last_source_record_id || 'none'} (batch ${period.last_batch_id || 'n/a'}) · Created ${period.created_at || 'unknown'} · Updated ${period.updated_at || 'unknown'}`;
      const inspect = document.createElement('details');
      inspect.className = 'mt-2 text-xs';
      const summary = document.createElement('summary');
      summary.className = 'cursor-pointer font-semibold';
      summary.textContent = 'Inspect complete period state';
      const stateDetails = document.createElement('pre');
      stateDetails.className = 'mt-2 whitespace-pre-wrap font-sans';
      stateDetails.textContent = [
        `Main value: ${period.main_value || ''}`, `Main label: ${period.main_label || ''}`,
        `Secondary label: ${period.secondary_label || ''}`, `Secondary value: ${period.secondary_value || ''}`,
        `Year/date: ${period.year_date || ''}`, `Description: ${period.description || ''}`,
        `Secondary description: ${period.secondary_description || ''}`, `Information: ${period.info_text || ''}`,
        `Source: ${period.source_info || ''}`
      ].join('\n');
      inspect.append(summary, stateDetails);
      const changeLog = document.createElement('p');
      changeLog.className = 'mt-2 text-xs text-gray-500 dark:text-slate-400';
      changeLog.textContent = period.changes?.length
        ? `Changes: ${period.changes.map(change => `${change.action} by user ${change.changed_by || 'unknown'} at ${change.created_at}`).join(' · ')}`
        : 'No manual corrections or publication changes recorded.';
      const actions = document.createElement('div');
      actions.className = 'mt-3 flex flex-wrap gap-2';
      const correct = document.createElement('button');
      correct.type = 'button';
      correct.dataset.action = 'correct';
      correct.className = 'rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-bold dark:border-slate-700';
      correct.textContent = 'Correct period';
      const publish = document.createElement('button');
      publish.type = 'button';
      publish.dataset.action = period.is_published ? 'unpublish' : 'publish';
      publish.className = 'rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-bold dark:border-slate-700';
      publish.textContent = period.is_published ? 'Unpublish period' : 'Publish period';
      actions.append(correct, publish);
      article.append(heading, content, provenance, inspect, changeLog, actions);
      rowsHost.append(article);
    }
    if (!periods.length) {
      const empty = document.createElement('p');
      empty.className = 'py-8 text-center text-sm text-gray-500';
      empty.textContent = 'No imported periods yet.';
      rowsHost.append(empty);
    }
  }

  async function mutate(payload) {
    const response = await fetch(`${api}&id=${encodeURIComponent(cardId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to update this period.');
    await load();
    setNotice('Summary Card history saved.');
  }

  function showCorrection(article, period) {
    article.querySelector('[data-correction-form]')?.remove();
    const form = document.createElement('form');
    form.dataset.correctionForm = 'true';
    form.className = 'mt-3 grid gap-3 md:grid-cols-2';
    for (const [name, label] of fields) form.append(field(label, name, period[name]));
    const save = document.createElement('button');
    save.type = 'submit';
    save.className = 'rounded-md bg-emerald-700 px-3 py-2 text-sm font-bold text-white';
    save.textContent = 'Save correction';
    form.append(save);
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const payload = { action: 'correct', period_key: period.period_key, row_version: period.row_version };
      form.querySelectorAll('[data-field]').forEach(input => { payload[input.dataset.field] = input.value; });
      save.disabled = true;
      try { await mutate(payload); }
      catch (error) { setNotice(error.message, true); save.disabled = false; }
    });
    article.append(form);
  }

  document.addEventListener('click', async event => {
    const open = event.target.closest('[data-summary-history-open]');
    if (open) {
      cardId = open.dataset.id;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      modal.setAttribute('aria-hidden', 'false');
      setNotice('Loading period history...');
      try { await load(); setNotice('Inspect, correct, or change publication for each period.'); }
      catch (error) { setNotice(error.message, true); }
      return;
    }
    const button = event.target.closest('#summaryCardHistoryModal button[data-action]');
    if (!button) return;
    const article = button.closest('[data-period-key]');
    const period = periods.find(item => item.period_key === article?.dataset.periodKey);
    if (!period) return;
    if (button.dataset.action === 'correct') { showCorrection(article, period); return; }
    button.disabled = true;
    try { await mutate({ action: button.dataset.action, period_key: period.period_key, row_version: period.row_version }); }
    catch (error) { setNotice(error.message, true); button.disabled = false; }
  });
  modal.querySelectorAll('[data-summary-history-close]').forEach(button => button.addEventListener('click', close));
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('flex')) close(); });
})();

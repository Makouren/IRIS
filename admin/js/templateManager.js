(() => {
  const modal = document.getElementById('templateManagerModal');
  const list = document.getElementById('templateManagerList');
  const form = document.getElementById('templateManagerForm');
  if (!modal || !list || !form) return;

  const api = modal.dataset.api;
  const token = modal.dataset.csrf || '';
  const downloadBase = modal.dataset.downloadBase;
  const notice = document.getElementById('templateManagerNotice');
  const rankingBodySelect = document.getElementById('templateRankingBodySelect');
  let templates = [];
  let rankingBodies = [];

  function showNotice(message, isError = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${isError ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  function button(label, action, id, destructive = false) {
    const element = document.createElement('button');
    element.type = 'button';
    element.dataset.action = action;
    element.dataset.id = String(id);
    element.className = `rounded-md border px-2.5 py-1 text-xs font-bold ${destructive ? 'border-red-300 text-red-800 hover:bg-red-50 dark:border-red-800 dark:text-red-200 dark:hover:bg-red-950' : 'border-gray-300 hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800'}`;
    element.textContent = label;
    return element;
  }

  function renderTemplates() {
    list.replaceChildren();
    if (!templates.length) {
      const empty = document.createElement('p');
      empty.className = 'rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-slate-800 dark:text-slate-400';
      empty.textContent = 'No templates uploaded.';
      list.append(empty);
      return;
    }
    for (const template of templates) {
      const row = document.createElement('article');
      row.className = 'rounded-lg border border-gray-200 p-3 dark:border-slate-700';
      const title = document.createElement('p');
      title.className = 'font-bold';
      title.textContent = template.name;
      const detail = document.createElement('p');
      detail.className = 'text-xs text-gray-500 dark:text-slate-400';
      detail.textContent = `${template.original_filename} · ${template.created_at} · ${Number(template.is_active) ? 'Active' : 'Inactive'} · ${template.ranking_body_name || 'Unlinked'}`;
      const actions = document.createElement('div');
      actions.className = 'mt-3 flex flex-wrap gap-2';
      const download = document.createElement('a');
      download.className = 'rounded-md border border-gray-300 px-2.5 py-1 text-xs font-bold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800';
      download.href = `${downloadBase}?id=${encodeURIComponent(template.id)}`;
      download.textContent = 'Download';
      actions.append(download);
      const bodySelect = document.createElement('select');
      bodySelect.className = 'rounded-md border border-gray-300 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800';
      bodySelect.dataset.bodySelect = String(template.id);
      const unlinked = document.createElement('option');
      unlinked.value = '';
      unlinked.textContent = 'Not linked';
      bodySelect.append(unlinked);
      for (const body of rankingBodies) {
        const option = document.createElement('option');
        option.value = String(body.id);
        option.textContent = `${body.name} (${body.short_name})`;
        bodySelect.append(option);
      }
      bodySelect.value = template.ranking_body_id ? String(template.ranking_body_id) : '';
      actions.append(bodySelect);
      actions.append(button('Save link', 'set-ranking-body', template.id));
      actions.append(button(Number(template.is_active) ? 'Deactivate' : 'Reactivate', Number(template.is_active) ? 'deactivate' : 'activate', template.id));
      actions.append(button('Delete', 'delete', template.id, true));
      row.append(title, detail, actions);
      list.append(row);
    }
  }

  async function loadTemplates() {
    const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to load templates.');
    templates = result;
    renderTemplates();
  }

  async function loadRankingBodies() {
    const response = await fetch(`${api}?resource=ranking_bodies`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to load ranking bodies.');
    rankingBodies = result;
    rankingBodySelect.replaceChildren(new Option('Not linked', ''));
    for (const body of rankingBodies) rankingBodySelect.add(new Option(`${body.name} (${body.short_name})`, String(body.id)));
  }

  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    Promise.all([loadTemplates(), loadRankingBodies()]).then(renderTemplates).catch(error => showNotice(error.message, true));
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
  }

  document.querySelectorAll('[data-template-manager-open]').forEach(element => element.addEventListener('click', openModal));
  document.addEventListener('iris:ranking-bodies-changed', async () => {
    try {
      await loadRankingBodies();
      renderTemplates();
    } catch (error) { showNotice(error.message, true); }
  });
  modal.querySelectorAll('[data-template-manager-close]').forEach(element => element.addEventListener('click', closeModal));
  modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = new FormData(form);
    data.set('action', 'upload');
    data.set('_csrf', token);
    try {
      const response = await fetch(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to upload template.');
      form.reset();
      showNotice('Template uploaded and activated.');
      await loadTemplates();
      renderTemplates();
    } catch (error) { showNotice(error.message, true); }
  });

  list.addEventListener('click', async event => {
    const target = event.target.closest('button[data-action]');
    if (!target) return;
    if (target.dataset.action === 'delete' && !window.confirm('Delete this template permanently?')) return;
    const data = new FormData();
    data.set('action', target.dataset.action);
    data.set('id', target.dataset.id);
    data.set('_csrf', token);
    if (target.dataset.action === 'set-ranking-body') {
      data.set('ranking_body_id', list.querySelector(`[data-body-select="${CSS.escape(target.dataset.id)}"]`)?.value || '');
    }
    target.disabled = true;
    try {
      const response = await fetch(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to update template.');
      showNotice(target.dataset.action === 'delete' ? 'Template deleted.' : target.dataset.action === 'set-ranking-body' ? 'Ranking body link saved.' : `Template ${target.dataset.action === 'activate' ? 'reactivated' : 'deactivated'}.`);
      await loadTemplates();
    } catch (error) {
      showNotice(error.message, true);
      target.disabled = false;
    }
  });
})();

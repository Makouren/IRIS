(() => {
  const modal = document.getElementById('rankingBodyManagerModal');
  const list = document.getElementById('rankingBodyManagerList');
  const form = document.getElementById('rankingBodyManagerForm');
  if (!modal || !list || !form) return;

  const api = modal.dataset.api;
  const token = modal.dataset.csrf || '';
  const notice = document.getElementById('rankingBodyManagerNotice');
  let bodies = [];

  function showNotice(message, isError = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${isError ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  function makeButton(label, action, body, destructive = false) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.action = action;
    button.dataset.id = String(body.id);
    button.className = `rounded-md border px-2.5 py-1 text-xs font-bold ${destructive ? 'border-red-300 text-red-800 hover:bg-red-50 dark:border-red-800 dark:text-red-200 dark:hover:bg-red-950' : 'border-gray-300 hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800'}`;
    button.textContent = label;
    return button;
  }

  function render() {
    list.replaceChildren();
    if (!bodies.length) {
      const empty = document.createElement('p');
      empty.className = 'rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-slate-800 dark:text-slate-400';
      empty.textContent = 'No ranking bodies found.';
      list.append(empty);
      return;
    }
    for (const body of bodies) {
      const row = document.createElement('article');
      row.className = 'rounded-lg border border-gray-200 p-3 dark:border-slate-700';
      const details = document.createElement('div');
      const name = document.createElement('p');
      name.className = 'font-bold';
      name.textContent = body.name;
      const usage = document.createElement('p');
      usage.className = 'text-xs text-gray-500 dark:text-slate-400';
      usage.textContent = `${body.short_name} · Order ${Number(body.sort_order ?? 100)} · ${Number(body.ranking_count)} ranking row(s) · ${Number(body.template_count)} template(s)`;
      details.append(name, usage);
      const actions = document.createElement('div');
      actions.className = 'mt-3 flex flex-wrap gap-2';
      actions.append(makeButton('Edit', 'edit', body), makeButton('Delete', 'delete', body, true));
      row.append(details, actions);
      list.append(row);
    }
  }

  async function load() {
    const response = await fetch(`${api}?resource=ranking_bodies`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'Unable to load ranking bodies.');
    bodies = payload;
    render();
  }

  function resetForm() {
    form.reset();
    form.elements.ranking_body_id.value = '';
    document.getElementById('rankingBodyManagerFormTitle').textContent = 'Add ranking body';
  }

  function open() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    resetForm();
    load().catch(error => showNotice(error.message, true));
  }

  function close() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
  }

  document.querySelectorAll('[data-ranking-body-manager-open]').forEach(button => button.addEventListener('click', open));
  modal.querySelectorAll('[data-ranking-body-manager-close]').forEach(button => button.addEventListener('click', close));
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  document.getElementById('rankingBodyManagerNew').addEventListener('click', resetForm);

  list.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const body = bodies.find(item => String(item.id) === button.dataset.id);
    if (!body) return;
    if (button.dataset.action === 'edit') {
      form.elements.ranking_body_id.value = body.id;
      form.elements.body_name.value = body.name;
      form.elements.short_name.value = body.short_name;
      form.elements.sort_order.value = body.sort_order ?? 100;
      document.getElementById('rankingBodyManagerFormTitle').textContent = `Edit ${body.name}`;
      form.elements.body_name.focus();
      return;
    }
    if (!window.confirm(`Delete ranking body "${body.name}"? Deletion is blocked while rankings or templates reference it.`)) return;
    const data = new FormData();
    data.set('action', 'delete-ranking-body');
    data.set('ranking_body_id', String(body.id));
    data.set('_csrf', token);
    button.disabled = true;
    try {
      const response = await fetch(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to delete ranking body.');
      showNotice('Ranking body deleted.');
      resetForm();
      await load();
      document.dispatchEvent(new Event('iris:ranking-bodies-changed'));
    } catch (error) {
      showNotice(error.message, true);
      button.disabled = false;
    }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const wasEditing = Boolean(form.elements.ranking_body_id.value);
    const data = new FormData(form);
    data.set('action', 'save-ranking-body');
    data.set('_csrf', token);
    try {
      const response = await fetch(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to save ranking body.');
      showNotice(wasEditing ? 'Ranking body updated.' : 'Ranking body added.');
      resetForm();
      await load();
      document.dispatchEvent(new Event('iris:ranking-bodies-changed'));
    } catch (error) { showNotice(error.message, true); }
  });
})();

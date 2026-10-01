(() => {
  const modal = document.getElementById('accountManagerModal');
  const list = document.getElementById('accountManagerList');
  const form = document.getElementById('accountManagerForm');
  if (!modal || !list || !form) return;

  const api = modal.dataset.api;
  const token = modal.dataset.csrf || '';
  const notice = document.getElementById('accountManagerNotice');
  const officeField = document.getElementById('accountManagerOfficeField');
  const roleSelect = form.elements.role;
  const passwordInput = form.elements.password;
  const resetButton = document.getElementById('accountManagerResetPassword');
  let accounts = [];

  function showNotice(message, isError = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${isError ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  function renderAccounts() {
    list.replaceChildren();
    if (!accounts.length) {
      const empty = document.createElement('p');
      empty.className = 'rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-slate-800 dark:text-slate-400';
      empty.textContent = 'No office or viewer accounts yet.';
      list.append(empty);
      return;
    }
    for (const account of accounts) {
      const row = document.createElement('article');
      row.className = 'rounded-lg border border-gray-200 p-3 dark:border-slate-700';
      const details = document.createElement('div');
      const name = document.createElement('p');
      name.className = 'font-bold';
      name.textContent = account.username;
      const subtitle = document.createElement('p');
      subtitle.className = 'text-xs text-gray-500 dark:text-slate-400';
      subtitle.textContent = [account.email, account.role, account.office_name, Number(account.is_active) ? 'Active' : 'Inactive'].filter(Boolean).join(' · ');
      details.append(name, subtitle);
      const actions = document.createElement('div');
      actions.className = 'mt-3 flex flex-wrap gap-2';
      for (const [label, action] of [['Edit', 'edit'], [Number(account.is_active) ? 'Deactivate' : 'Activate', Number(account.is_active) ? 'deactivate' : 'activate']]) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rounded-md border border-gray-300 px-2.5 py-1 text-xs font-bold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800';
        button.textContent = label;
        button.dataset.action = action;
        button.dataset.id = String(account.id);
        actions.append(button);
      }
      row.append(details, actions);
      list.append(row);
    }
  }

  async function loadAccounts() {
    const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error('Unable to load accounts.');
    accounts = await response.json();
    renderAccounts();
  }

  function resetForm() {
    form.reset();
    form.elements.id.value = '';
    document.getElementById('accountManagerFormTitle').textContent = 'Create account';
    passwordInput.required = true;
    resetButton.classList.add('hidden');
    officeField.classList.toggle('hidden', roleSelect.value !== 'admin');
  }

  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    resetForm();
    loadAccounts().catch(error => showNotice(error.message, true));
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
  }

  document.querySelectorAll('[data-account-manager-open]').forEach(button => button.addEventListener('click', openModal));
  modal.querySelectorAll('[data-account-manager-close]').forEach(button => button.addEventListener('click', closeModal));
  modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
  document.getElementById('accountManagerNew').addEventListener('click', resetForm);
  roleSelect.addEventListener('change', () => officeField.classList.toggle('hidden', roleSelect.value !== 'admin'));

  list.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const account = accounts.find(item => String(item.id) === button.dataset.id);
    if (!account) return;
    try {
      if (button.dataset.action === 'edit') {
        form.elements.id.value = account.id;
        form.elements.username.value = account.username;
        form.elements.email.value = account.email;
        roleSelect.value = account.role;
        form.elements.office_name.value = account.office_name || '';
        document.getElementById('accountManagerFormTitle').textContent = `Edit ${account.username}`;
        passwordInput.required = false;
        resetButton.classList.remove('hidden');
        officeField.classList.toggle('hidden', roleSelect.value !== 'admin');
        passwordInput.value = '';
        return;
      }
      const response = await fetch(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        body: JSON.stringify({ action: button.dataset.action, id: account.id })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to update account.');
      showNotice(`Account ${button.dataset.action === 'activate' ? 'activated' : 'deactivated'}.`);
      await loadAccounts();
    } catch (error) { showNotice(error.message, true); }
  });

  async function send(data) {
    const response = await fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to save account.');
    return result;
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = 'save';
    if (!data.password) delete data.password;
    try {
      await send(data);
      showNotice('Account saved.');
      resetForm();
      await loadAccounts();
    } catch (error) { showNotice(error.message, true); }
  });

  resetButton.addEventListener('click', async () => {
    const password = passwordInput.value;
    if (password.length < 8) {
      showNotice('Enter a new password of at least 8 characters.', true);
      passwordInput.focus();
      return;
    }
    try {
      await send({ action: 'reset-password', id: form.elements.id.value, password });
      passwordInput.value = '';
      showNotice('Password reset.');
    } catch (error) { showNotice(error.message, true); }
  });
})();
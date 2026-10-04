(() => {
  const purposeSelect = document.getElementById('officeUploadPurpose');
  const templateSelect = document.getElementById('officeTemplateSelect');
  const templateControl = document.querySelector('[data-template-control]');
  const templateHelp = document.querySelector('[data-template-filter-help]');
  if (purposeSelect && templateSelect) {
    const options = [...templateSelect.querySelectorAll('option[data-purpose]')];
    const importDestinations = ['summary_cards', 'ranking_history'];
    const updateTemplateOptions = () => {
      const purpose = purposeSelect.value;
      const hasImportProfile = importDestinations.includes(purpose);
      templateSelect.value = '';
      options.forEach(option => { option.hidden = !purpose || option.dataset.purpose !== purpose; });
      const available = options.some(option => option.dataset.purpose === purpose);
      templateSelect.required = false;
      templateSelect.disabled = !purpose || hasImportProfile;
      if (templateControl) templateControl.hidden = !purpose || hasImportProfile;
      templateSelect.options[0].textContent = purpose
        ? (purpose === 'analytics'
          ? 'General (uncategorized — template can be assigned later)'
          : 'Active destination profile is used automatically')
        : 'Choose a purpose first';
      if (templateHelp) templateHelp.textContent = purpose
        ? (purpose === 'analytics'
          ? (available
            ? 'Choose a template, or keep General to submit without one. The Super Admin can configure it later.'
            : 'No templates are available yet. General lets you submit now; the Super Admin can configure a template later.')
          : 'The active destination profile will be used automatically.')
        : 'Choose a destination to see its import profile.';
    };
    purposeSelect.addEventListener('change', updateTemplateOptions);
    updateTemplateOptions();
  }

  const officeUploadForm = document.querySelector('form[action*="/admin/upload_process.php"]');
  officeUploadForm?.addEventListener('submit', event => {
    if (!officeUploadForm.reportValidity()) {
      event.preventDefault();
      return;
    }
    if (officeUploadForm.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    officeUploadForm.dataset.submitting = 'true';
    officeUploadForm.setAttribute('aria-busy', 'true');
    const submitButton = officeUploadForm.querySelector('[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute('aria-disabled', 'true');
    }
  });

  const list = document.getElementById('activeTemplatesList');
  if (!list) return;

  const api = list.dataset.api;
  const downloadBase = list.dataset.downloadBase;
  const activeTemplateDestination = document.getElementById('activeTemplateDestination');
  const groups = [
    { destination: 'analytics', title: 'Data & Report Visualization' },
    { destination: 'summary_cards', title: 'Summary Cards' },
    { destination: 'ranking_history', title: 'Ranking History' }
  ];
  let activeTemplates = [];

  function render(templates = activeTemplates) {
    activeTemplates = Array.isArray(templates) ? templates : [];
    const selectedDestination = groups.some(group => group.destination === activeTemplateDestination?.value)
      ? activeTemplateDestination.value
      : groups[0].destination;
    const selectedGroup = groups.find(group => group.destination === selectedDestination) || groups[0];
    const templatesForGroup = activeTemplates.filter(template =>
      (groups.some(group => group.destination === template.import_destination) ? template.import_destination : 'analytics') === selectedDestination
    );
    list.replaceChildren();
    const heading = document.createElement('div');
    heading.className = 'flex items-center justify-between gap-2 border-b border-gray-200 py-3 dark:border-slate-800';
    const title = document.createElement('h3');
    title.className = 'text-sm font-bold text-gray-900 dark:text-white';
    title.textContent = selectedGroup.title;
    const count = document.createElement('span');
    count.className = 'shrink-0 text-xs font-medium text-gray-700 dark:text-slate-300';
    count.textContent = `${templatesForGroup.length} template${templatesForGroup.length === 1 ? '' : 's'}`;
    heading.append(title, count);
    list.append(heading);
    if (!templatesForGroup.length) {
      const empty = document.createElement('p');
      empty.className = 'py-4 text-sm text-gray-700 dark:text-slate-300';
      empty.textContent = 'No active templates in this category.';
      list.append(empty);
      return;
    }
    const rows = document.createElement('div');
    rows.className = 'divide-y divide-gray-200 dark:divide-slate-800';
    for (const template of templatesForGroup) {
      const row = document.createElement('div');
      row.className = 'flex min-w-0 flex-col items-start justify-between gap-2 py-3 sm:flex-row sm:items-center';
      const details = document.createElement('div');
      details.className = 'min-w-0';
      const name = document.createElement('p');
      name.className = 'office-active-template-title break-words';
      name.textContent = template.name;
      const originalName = document.createElement('p');
      originalName.className = 'office-active-template-filename break-all';
      originalName.textContent = template.original_filename;
      details.append(name, originalName);
      const download = document.createElement('a');
      download.className = 'inline-flex shrink-0 items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800';
      download.href = template.is_profile_workbook
        ? `${downloadBase}?destination=${encodeURIComponent(template.import_destination)}`
        : `${downloadBase}?id=${encodeURIComponent(template.id)}`;
      download.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i>Download';
      row.append(details, download);
      rows.append(row);
    }
    list.append(rows);
  }

  activeTemplateDestination?.addEventListener('change', () => render());

  async function refresh() {
    if (document.visibilityState !== 'visible') return;
    try {
      const [response, profileResponse] = await Promise.all([
        fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' }),
        fetch(`${api}?resource=office_import_profiles`, { headers: { Accept: 'application/json' }, cache: 'no-store' })
      ]);
      const [payload, profilePayload] = await Promise.all([response.json(), profileResponse.json()]);
      if (!response.ok || !Array.isArray(payload) || !profileResponse.ok || !Array.isArray(profilePayload)) {
        throw new Error('Active templates are temporarily unavailable.');
      }
      render([...payload, ...profilePayload]);
    } catch (error) {
      console.warn('Template list refresh failed:', error);
    }
  }

  window.addEventListener('focus', refresh);
  document.addEventListener('visibilitychange', refresh);
  window.setInterval(refresh, 5000);
  refresh();
})();

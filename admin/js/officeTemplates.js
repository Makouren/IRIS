(() => {
  const themeToggle = document.getElementById('officeThemeToggle');
  if (themeToggle) {
    const themeIcon = themeToggle.querySelector('i');
    const updateThemeControl = () => {
      const isDark = document.documentElement.classList.contains('dark');
      themeIcon?.classList.toggle('fa-moon', !isDark);
      themeIcon?.classList.toggle('fa-sun', isDark);
      themeToggle.setAttribute('aria-pressed', String(isDark));
      const label = isDark ? 'Switch to light theme' : 'Switch to dark theme';
      themeToggle.setAttribute('aria-label', label);
      themeToggle.title = label;
    };
    updateThemeControl();
    themeToggle.addEventListener('click', () => {
      const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      document.documentElement.classList.toggle('dark', nextTheme === 'dark');
      localStorage.setItem('color-theme', nextTheme);
      localStorage.setItem('iris-theme', nextTheme);
      updateThemeControl();
    });
  }

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
      templateSelect.required = purpose === 'analytics';
      templateSelect.disabled = !purpose || hasImportProfile || !available;
      if (templateControl) templateControl.hidden = !purpose || hasImportProfile;
      templateSelect.options[0].textContent = purpose
        ? (purpose === 'analytics'
          ? (available ? 'Choose a template' : 'No templates available')
          : 'Active destination profile is used automatically')
        : 'Choose a purpose first';
      if (templateHelp) templateHelp.textContent = purpose
        ? (purpose === 'analytics'
          ? (available ? 'Choose a template for general data and report visualization.' : 'No general data templates are available. Ask the Super Admin to add one.')
          : 'The active destination profile will be used automatically.')
        : 'Choose a destination to see its import profile.';
    };
    purposeSelect.addEventListener('change', updateTemplateOptions);
    updateTemplateOptions();
  }

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
    count.className = 'shrink-0 text-xs text-gray-500 dark:text-slate-400';
    count.textContent = `${templatesForGroup.length} template${templatesForGroup.length === 1 ? '' : 's'}`;
    heading.append(title, count);
    list.append(heading);
    if (!templatesForGroup.length) {
      const empty = document.createElement('p');
      empty.className = 'py-4 text-sm text-gray-500 dark:text-slate-400';
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
      name.className = 'break-words text-sm font-semibold text-gray-900 dark:text-white';
      name.textContent = template.name;
      const originalName = document.createElement('p');
      originalName.className = 'break-all text-xs text-gray-500 dark:text-slate-400';
      originalName.textContent = template.original_filename;
      details.append(name, originalName);
      const download = document.createElement('a');
      download.className = 'inline-flex shrink-0 items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800';
      download.href = `${downloadBase}?id=${encodeURIComponent(template.id)}`;
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
      const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || !Array.isArray(payload)) throw new Error('Active templates are temporarily unavailable.');
      render(payload);
    } catch (error) {
      console.warn('Template list refresh failed:', error);
    }
  }

  window.addEventListener('focus', refresh);
  document.addEventListener('visibilitychange', refresh);
  window.setInterval(refresh, 5000);
  refresh();
})();

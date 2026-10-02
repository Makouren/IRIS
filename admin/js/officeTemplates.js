(() => {
  const purposeSelect = document.getElementById('officeUploadPurpose');
  const templateSelect = document.getElementById('officeTemplateSelect');
  const templateControl = document.querySelector('[data-template-control]');
  const activeProfileControl = document.querySelector('[data-active-profile-control]');
  const summaryProfileName = document.querySelector('[data-summary-profile-name]');
  const summaryProfileHelp = document.querySelector('[data-summary-profile-help]');
  const activeProfileLabel = document.querySelector('[data-active-profile-label]');
  const templateHelp = document.querySelector('[data-template-filter-help]');
  const profileDownloadLink = document.querySelector('[data-profile-download-link]');
  if (purposeSelect && templateSelect) {
    const options = [...templateSelect.querySelectorAll('option[data-purpose]')];
    const importDestinations = ['summary_cards', 'ranking_history'];
    const updateTemplateOptions = () => {
      const purpose = purposeSelect.value;
      const hasImportProfile = importDestinations.includes(purpose);
      if (profileDownloadLink) {
        profileDownloadLink.classList.toggle('hidden', !hasImportProfile);
        profileDownloadLink.classList.toggle('inline-flex', hasImportProfile);
        if (hasImportProfile) profileDownloadLink.href = `${profileDownloadLink.href.split('?')[0]}?destination=${encodeURIComponent(purpose)}`;
      }
      templateSelect.value = '';
      options.forEach(option => { option.hidden = !purpose || option.dataset.purpose !== purpose; });
      const available = options.some(option => option.dataset.purpose === purpose);
      templateSelect.required = purpose === 'analytics';
      templateSelect.disabled = !purpose || !available;
      if (templateControl) templateControl.hidden = !purpose;
      if (activeProfileControl) activeProfileControl.classList.toggle('hidden', !hasImportProfile);
      templateSelect.options[0].textContent = purpose
        ? (purpose === 'analytics'
          ? (available ? 'Choose a template' : 'No templates available')
          : 'Use active destination profile')
        : 'Choose a purpose first';
      if (templateHelp) templateHelp.textContent = purpose
        ? (purpose === 'analytics'
          ? (available ? 'Choose a template for general data and report visualization.' : 'No general data templates are available. Ask the Super Admin to add one.')
          : 'Choose an optional template profile, or leave this blank to use the active destination profile.')
        : 'Choose a destination to see its import profile.';
      if (activeProfileLabel && hasImportProfile) activeProfileLabel.textContent = purpose === 'ranking_history' ? 'Active Ranking History profile' : 'Active Summary Cards profile';
      if (purpose) refreshActiveProfile();
    };
    purposeSelect.addEventListener('change', updateTemplateOptions);
    updateTemplateOptions();
  }

  const list = document.getElementById('activeTemplatesList');
  if (!list) return;

  const api = list.dataset.api;
  const downloadBase = list.dataset.downloadBase;
  const emptyMessage = 'No active templates are available.';

  function render(templates) {
    list.replaceChildren();
    const visibleTemplates = templates;
    if (!visibleTemplates.length) {
      const empty = document.createElement('p');
      empty.className = 'px-4 py-6 text-sm text-gray-500 dark:text-slate-400';
      empty.textContent = emptyMessage;
      list.append(empty);
      return;
    }
    for (const template of visibleTemplates) {
      const row = document.createElement('div');
      row.className = 'flex flex-wrap items-center justify-between gap-3 px-4 py-3';
      const details = document.createElement('div');
      const name = document.createElement('p');
      name.className = 'font-semibold';
      name.textContent = template.name;
      const originalName = document.createElement('p');
      originalName.className = 'text-xs text-gray-500 dark:text-slate-400';
      originalName.textContent = template.original_filename;
      details.append(name, originalName);
      const download = document.createElement('a');
      download.className = 'inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800';
      download.href = `${downloadBase}?id=${encodeURIComponent(template.id)}`;
      download.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i>Download';
      row.append(details, download);
      list.append(row);
    }
  }

  async function refreshActiveProfile() {
    const destination = purposeSelect?.value;
    if (!activeProfileControl || !['summary_cards', 'ranking_history'].includes(destination)) return;
    try {
      const response = await fetch(`${activeProfileControl.dataset.api}?resource=active_import_profile&destination=${encodeURIComponent(destination)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const profile = await response.json();
      if (!response.ok) throw new Error(profile.error || 'Active import profile is unavailable.');
      summaryProfileName.textContent = profile.profile_name;
      summaryProfileHelp.textContent = profile.original_filename
        ? `Template: ${profile.template_name} · ${profile.original_filename}`
        : `Profile: ${profile.profile_name}`;
    } catch (error) {
      summaryProfileName.textContent = error.message || 'Active import profile is unavailable.';
      summaryProfileHelp.textContent = 'Ask the Super Admin to choose an active profile for this destination.';
    }
  }

  async function refresh() {
    if (document.visibilityState !== 'visible') return;
    try {
      const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || !Array.isArray(payload)) throw new Error('Active templates are temporarily unavailable.');
      render(payload);
      await refreshActiveProfile();
    } catch (error) {
      console.warn('Template list refresh failed:', error);
    }
  }

  window.addEventListener('focus', refresh);
  document.addEventListener('visibilitychange', refresh);
  window.setInterval(refresh, 5000);
  refresh();
})();

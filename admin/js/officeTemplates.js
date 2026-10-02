(() => {
  const purposeSelect = document.getElementById('officeUploadPurpose');
  const templateSelect = document.getElementById('officeTemplateSelect');
  const templateControl = document.querySelector('[data-template-control]');
  const summaryProfileControl = document.querySelector('[data-summary-profile-control]');
  const summaryProfileName = document.querySelector('[data-summary-profile-name]');
  const summaryProfileHelp = document.querySelector('[data-summary-profile-help]');
  const templateHelp = document.querySelector('[data-template-filter-help]');
  if (purposeSelect && templateSelect) {
    const options = [...templateSelect.querySelectorAll('option[data-purpose]')];
    const updateTemplateOptions = () => {
      const purpose = purposeSelect.value;
      const summaryCards = purpose === 'summary_cards';
      templateSelect.value = '';
      options.forEach(option => { option.hidden = !purpose || option.dataset.purpose !== purpose; });
      const available = options.some(option => option.dataset.purpose === purpose);
      templateSelect.required = Boolean(purpose && !summaryCards);
      templateSelect.disabled = summaryCards || !purpose || !available;
      if (templateControl) templateControl.hidden = summaryCards;
      if (summaryProfileControl) summaryProfileControl.classList.toggle('hidden', !summaryCards);
      templateSelect.options[0].textContent = purpose
        ? (available ? 'Choose a matching template' : 'No active templates for this purpose')
        : 'Choose a purpose first';
      if (templateHelp) templateHelp.textContent = summaryCards
        ? 'Uses the currently active Summary Card import profile.'
        : purpose
        ? (available ? 'Only templates configured for this purpose are shown.' : 'Ask the Super Admin to activate a matching template.')
        : 'Choose a purpose to see its upload requirements.';
      if (summaryCards) refreshActiveSummaryProfile();
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
    const visibleTemplates = templates.filter(item => item.import_destination !== 'summary_cards');
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

  async function refreshActiveSummaryProfile() {
    if (!summaryProfileControl || purposeSelect?.value !== 'summary_cards') return;
    try {
      const response = await fetch(`${summaryProfileControl.dataset.api}?resource=active_summary_card_profile`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const profile = await response.json();
      if (!response.ok) throw new Error(profile.error || 'Active Summary Card profile is unavailable.');
      summaryProfileName.textContent = profile.profile_name;
      summaryProfileHelp.textContent = profile.original_filename
        ? `Template: ${profile.template_name} · ${profile.original_filename}`
        : `Profile: ${profile.profile_name}`;
    } catch (error) {
      summaryProfileName.textContent = error.message || 'Active Summary Card profile is unavailable.';
      summaryProfileHelp.textContent = 'Ask the Super Admin to choose an active Summary Card import profile.';
    }
  }

  async function refresh() {
    if (document.visibilityState !== 'visible') return;
    try {
      const response = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || !Array.isArray(payload)) throw new Error('Active templates are temporarily unavailable.');
      render(payload);
      await refreshActiveSummaryProfile();
    } catch (error) {
      console.warn('Template list refresh failed:', error);
    }
  }

  window.addEventListener('focus', refresh);
  document.addEventListener('visibilitychange', refresh);
  window.setInterval(refresh, 5000);
  refresh();
})();

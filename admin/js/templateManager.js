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
  const summaryProfileSelect = modal.querySelector('[data-summary-profile-select]');
  const summaryProfileName = modal.querySelector('[data-summary-profile-name]');
  const summaryProfileEditor = modal.querySelector('[data-summary-profile-json]');
  const activeSummaryProfileLabel = modal.querySelector('[data-active-summary-profile]');
  const summaryProfileActivate = modal.querySelector('[data-summary-profile-activate]');
  const summaryProfileSave = modal.querySelector('[data-summary-profile-save]');
  const rankingProfileSelect = modal.querySelector('[data-ranking-profile-select]');
  const rankingProfileName = modal.querySelector('[data-ranking-profile-name]');
  const rankingProfileEditor = modal.querySelector('[data-ranking-profile-json]');
  const activeRankingProfileLabel = modal.querySelector('[data-active-ranking-profile]');
  const rankingProfileActivate = modal.querySelector('[data-ranking-profile-activate]');
  const rankingProfileSave = modal.querySelector('[data-ranking-profile-save]');
  let templates = [];
  let rankingBodies = [];
  let summaryCards = [];
  let summaryImportProfiles = [];
  let activeSummaryProfileId = null;
  let rankingImportProfiles = [];
  let activeRankingProfileId = null;
  const profileActionsPending = { summaryActivate: false, summarySave: false, rankingActivate: false, rankingSave: false };

  function showNotice(message, isError = false) {
    notice.textContent = message;
    notice.className = `mb-4 rounded-lg p-3 text-sm ${isError ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
  }

  function updateProfileActionAvailability() {
    summaryProfileActivate.disabled = profileActionsPending.summaryActivate || !summaryProfileSelect.value;
    summaryProfileSave.disabled = profileActionsPending.summarySave || !summaryProfileSelect.value;
    rankingProfileActivate.disabled = profileActionsPending.rankingActivate || !rankingProfileSelect.value;
    rankingProfileSave.disabled = profileActionsPending.rankingSave || !rankingProfileSelect.value;
  }

  async function requestJson(url, options) {
    let response;
    try { response = await fetch(url, options); }
    catch { throw new Error('Server error. Check the PHP log.'); }
    let result;
    try { result = JSON.parse(await response.text()); }
    catch { throw new Error('Server error. Check the PHP log.'); }
    return { response, result };
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
      const profileSection = document.createElement('details');
      profileSection.className = 'mt-3 border-t border-gray-200 pt-3 dark:border-slate-700';
      const profileTitle = document.createElement('summary');
      profileTitle.className = 'cursor-pointer text-sm font-bold';
      profileTitle.textContent = template.import_destination ? `Import profile: ${template.import_destination.replace('_', ' ')}` : 'Configure import profile';
      const profileDestination = document.createElement('select');
      profileDestination.className = 'mt-2 w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800';
      profileDestination.dataset.profileDestination = String(template.id);
      profileDestination.add(new Option('Choose destination', ''));
      profileDestination.add(new Option('Ranking History', 'ranking_history'));
      profileDestination.add(new Option('Summary Cards', 'summary_cards'));
      profileDestination.value = template.import_destination || '';
      const profileEditor = document.createElement('textarea');
      profileEditor.className = 'mt-2 block w-full rounded-md border border-gray-300 bg-gray-50 p-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-800';
      profileEditor.rows = 8;
      profileEditor.dataset.profileJson = String(template.id);
      profileEditor.title = 'Map destination fields to exact worksheet headers using mapping_rules. Header aliases are used for matching. Defaults fill unmapped fields.';
      const profile = template.import_destination ? {
        sheet_selector: template.sheet_selector,
        header_aliases: template.header_aliases,
        required_columns: template.required_columns,
        identity_fields: template.identity_fields,
        mapping_rules: template.mapping_rules,
        custom_fields: template.custom_fields,
        defaults: template.defaults_json
      } : {};
      profileEditor.value = JSON.stringify(profile, null, 2);
      profileEditor.placeholder = template.import_destination === 'ranking_history'
        ? '{\n  "sheet_selector": "Ranking History",\n  "header_aliases": {"organization": ["Organization"], "ranking_type": ["Ranking Type"], "year": ["Year"], "global_rank": ["Rank"]},\n  "identity_fields": ["organization", "ranking_type", "year"],\n  "required_columns": ["organization", "ranking_type", "year", "global_rank"],\n  "mapping_rules": {"organization": "Organization", "ranking_type": "Ranking Type", "year": "Year", "global_rank": "Rank", "ph_rank": "Philippine Rank", "source": "Source"},\n  "defaults": {}\n}'
        : '{\n  "sheet_selector": null,\n  "identity_fields": ["import_key", "source", "metric", "category", "record_type"],\n  "header_aliases": {"import_key": ["Global Label", "Card Key"], "period_key": ["Period"], "main_value": ["Rank"]},\n  "required_columns": ["import_key", "period_key", "main_value"],\n  "mapping_rules": {"import_key": "Global Label", "source": "Source", "metric": "Metric", "category": "Category", "record_type": "Record Type", "period_key": "Period", "main_value": "Rank"},\n  "defaults": {}\n}';
      const keyGuide = document.createElement('p');
      keyGuide.className = 'mt-1 text-xs text-gray-500 dark:text-slate-400';
      keyGuide.textContent = template.import_destination === 'summary_cards'
        ? `Card keys: ${summaryCards.map(card => `${card.import_key || card.id} = ${card.title}`).join(' · ') || 'No cards found'}`
        : 'Organization, Ranking Type, Year, and Rank are required. Organization is resolved from each row.';
      const profileSave = button('Save import profile', 'save-import-profile', template.id);
      profileSave.classList.add('mt-2');
      profileSection.append(profileTitle, profileDestination, profileEditor, keyGuide, profileSave);
      row.append(title, detail, actions, profileSection);
      list.append(row);
    }
  }

  async function loadTemplates() {
    const { response, result } = await requestJson(api, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error(result.error || 'Unable to load templates.');
    templates = result;
    renderTemplates();
  }

  async function loadRankingBodies() {
    const { response, result } = await requestJson(`${api}?resource=ranking_bodies`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error(result.error || 'Unable to load ranking bodies.');
    rankingBodies = result;
    rankingBodySelect.replaceChildren(new Option('Not linked', ''));
    for (const body of rankingBodies) rankingBodySelect.add(new Option(`${body.name} (${body.short_name})`, String(body.id)));
  }

  async function loadSummaryCards() {
    const endpoint = api.replace(/templates\.php(?:\?.*)?$/, 'iris.php?resource=summary_cards');
    const { response, result } = await requestJson(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error(result.error || 'Unable to load summary-card keys.');
    summaryCards = result;
  }

  async function loadSummaryProfileDetails(profileId) {
    if (!profileId) return;
    const { response, result: profile } = await requestJson(`${api}?resource=summary_card_profile&profile_id=${encodeURIComponent(profileId)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error(profile.error || 'Unable to load the Summary Card profile.');
    summaryProfileName.value = profile.profile_name || '';
    summaryProfileEditor.value = JSON.stringify({
      sheet_selector: profile.sheet_selector,
      identity_fields: profile.identity_fields,
      header_aliases: profile.header_aliases,
      required_columns: profile.required_columns,
      mapping_rules: profile.mapping_rules,
      custom_fields: profile.custom_fields,
      defaults: profile.defaults,
      workbook_header_row: profile.workbook_header_row,
      workbook_headers: profile.workbook_headers
    }, null, 2);
  }

  async function loadSummaryProfiles(selectActive = true) {
    let result;
    try {
      const request = await requestJson(`${api}?resource=summary_card_profiles`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!request.response.ok || !Array.isArray(request.result.profiles)) throw new Error(request.result.error || 'Unable to load Summary Card profiles.');
      result = request.result;
    } catch (error) {
      summaryProfileSelect.replaceChildren(new Option('Profiles unavailable. Reopen Manage Templates to retry.', ''));
      summaryProfileSelect.disabled = false;
      updateProfileActionAvailability();
      showNotice(error.message, true);
      return;
    }
    summaryImportProfiles = result.profiles;
    activeSummaryProfileId = result.active_profile_id ? String(result.active_profile_id) : '';
    summaryProfileSelect.replaceChildren();
    for (const profile of summaryImportProfiles) {
      const option = new Option(`${profile.profile_name}${profile.is_active ? ' · Active' : ''}${profile.original_filename ? ` · ${profile.original_filename}` : ''}`, String(profile.id));
      summaryProfileSelect.add(option);
    }
    if (!summaryImportProfiles.length) {
      summaryProfileSelect.add(new Option('No profiles available. Run the pending migrations.', ''));
      activeSummaryProfileLabel.textContent = 'No Summary Card import profile is configured. Run the pending migrations.';
      updateProfileActionAvailability();
      return;
    }
    if (selectActive && activeSummaryProfileId) summaryProfileSelect.value = activeSummaryProfileId;
    else if (!summaryProfileSelect.value) summaryProfileSelect.value = String(summaryImportProfiles[0].id);
    const active = summaryImportProfiles.find(profile => String(profile.id) === activeSummaryProfileId);
    activeSummaryProfileLabel.textContent = active
      ? `Currently active: ${active.profile_name}${active.original_filename ? ` · ${active.original_filename}` : ''}`
      : 'No active Summary Card profile is configured.';
    try { await loadSummaryProfileDetails(summaryProfileSelect.value); }
    catch (error) { showNotice(error.message, true); }
    finally { updateProfileActionAvailability(); }
  }

  async function loadRankingProfileDetails(profileId) {
    if (!profileId) return;
    const { response, result: profile } = await requestJson(`${api}?resource=import_profile&destination=ranking_history&profile_id=${encodeURIComponent(profileId)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error(profile.error || 'Unable to load the Ranking History profile.');
    rankingProfileName.value = profile.profile_name || '';
    rankingProfileEditor.value = JSON.stringify({
      sheet_selector: profile.sheet_selector,
      identity_fields: profile.identity_fields,
      header_aliases: profile.header_aliases,
      required_columns: profile.required_columns,
      mapping_rules: profile.mapping_rules,
      custom_fields: profile.custom_fields,
      defaults: profile.defaults,
      workbook_header_row: profile.workbook_header_row,
      workbook_headers: profile.workbook_headers
    }, null, 2);
  }

  async function loadRankingProfiles(selectActive = true) {
    let result;
    try {
      const request = await requestJson(`${api}?resource=import_profiles&destination=ranking_history`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!request.response.ok || !Array.isArray(request.result.profiles)) throw new Error(request.result.error || 'Unable to load Ranking History profiles.');
      result = request.result;
    } catch (error) {
      rankingProfileSelect.replaceChildren(new Option('Profiles unavailable. Reopen Manage Templates to retry.', ''));
      rankingProfileSelect.disabled = false;
      updateProfileActionAvailability();
      showNotice(error.message, true);
      return;
    }
    rankingImportProfiles = result.profiles;
    activeRankingProfileId = result.active_profile_id ? String(result.active_profile_id) : '';
    rankingProfileSelect.replaceChildren();
    for (const profile of rankingImportProfiles) {
      rankingProfileSelect.add(new Option(`${profile.profile_name}${profile.is_active ? ' · Active' : ''}${profile.original_filename ? ` · ${profile.original_filename}` : ''}`, String(profile.id)));
    }
    if (!rankingImportProfiles.length) {
      rankingProfileSelect.add(new Option('No profiles available. Run the pending migrations.', ''));
      activeRankingProfileLabel.textContent = 'No Ranking History import profile is configured. Run the pending migrations.';
      updateProfileActionAvailability();
      return;
    }
    if (selectActive && activeRankingProfileId) rankingProfileSelect.value = activeRankingProfileId;
    else if (!rankingProfileSelect.value) rankingProfileSelect.value = String(rankingImportProfiles[0].id);
    const active = rankingImportProfiles.find(profile => String(profile.id) === activeRankingProfileId);
    activeRankingProfileLabel.textContent = active
      ? `Currently active: ${active.profile_name}${active.original_filename ? ` · ${active.original_filename}` : ''}`
      : 'No active Ranking History profile is configured.';
    try { await loadRankingProfileDetails(rankingProfileSelect.value); }
    catch (error) { showNotice(error.message, true); }
    finally { updateProfileActionAvailability(); }
  }

  async function postSummaryProfileAction(action, values) {
    const data = new FormData();
    data.set('action', action);
    data.set('_csrf', token);
    for (const [key, value] of Object.entries(values)) data.set(key, value);
    const { response, result } = await requestJson(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
    if (!response.ok) throw new Error(result.error || 'Unable to update the Summary Card profile.');
    return result;
  }

  async function postProfileSettingsAction(action, values) {
    const data = new FormData();
    data.set('action', action);
    data.set('_csrf', token);
    for (const [key, value] of Object.entries(values)) data.set(key, value);
    const { response, result } = await requestJson(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
    if (!response.ok) throw new Error(result.error || 'Unable to update the import profile.');
    return result;
  }

  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    window.IRISProfileWorkbookMapper?.refresh();
    Promise.all([loadTemplates(), loadRankingBodies(), loadSummaryCards(), loadSummaryProfiles(), loadRankingProfiles()]).then(() => {
      renderTemplates();
      window.IRISProfileWorkbookMapper?.refresh();
    }).catch(error => showNotice(error.message, true));
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
  summaryProfileSelect.addEventListener('change', () => {
    updateProfileActionAvailability();
    window.IRISProfileWorkbookMapper?.clear('summary_cards');
    window.IRISProfileWorkbookMapper?.refresh();
    loadSummaryProfileDetails(summaryProfileSelect.value)
      .catch(error => showNotice(error.message, true))
      .finally(updateProfileActionAvailability);
  });
  rankingProfileSelect.addEventListener('change', () => {
    updateProfileActionAvailability();
    window.IRISProfileWorkbookMapper?.clear('ranking_history');
    window.IRISProfileWorkbookMapper?.refresh();
    loadRankingProfileDetails(rankingProfileSelect.value)
      .catch(error => showNotice(error.message, true))
      .finally(updateProfileActionAvailability);
  });
  summaryProfileName.addEventListener('input', () => {
    summaryProfileName.setCustomValidity('');
    updateProfileActionAvailability();
  });
  rankingProfileName.addEventListener('input', () => {
    rankingProfileName.setCustomValidity('');
    updateProfileActionAvailability();
  });
  updateProfileActionAvailability();
  summaryProfileActivate.addEventListener('click', async () => {
    if (!summaryProfileSelect.value) return;
    profileActionsPending.summaryActivate = true;
    updateProfileActionAvailability();
    try {
      const result = await postSummaryProfileAction('activate-summary-card-profile', { profile_id: summaryProfileSelect.value });
      await loadSummaryProfiles();
      showNotice(`Active Summary Card profile: ${result.profile_name}`);
    } catch (error) { showNotice(error.message, true); }
    finally { profileActionsPending.summaryActivate = false; updateProfileActionAvailability(); }
  });
  summaryProfileSave.addEventListener('click', async () => {
    if (!summaryProfileSelect.value) { showNotice('Choose a Summary Card profile first.', true); return; }
    if (!summaryProfileName.value.trim()) {
      summaryProfileName.setCustomValidity('Enter a profile name.');
      summaryProfileName.reportValidity();
      updateProfileActionAvailability();
      return;
    }
    let profile;
    try {
      profile = JSON.parse(summaryProfileEditor.value || '{}');
      if (!profile || typeof profile !== 'object' || Array.isArray(profile)) throw new Error('Profile must be a JSON object.');
    } catch (error) { showNotice(`Invalid Summary Card profile JSON: ${error.message}`, true); return; }
    profileActionsPending.summarySave = true;
    updateProfileActionAvailability();
    try {
      const workbookToken = window.IRISProfileWorkbookMapper?.workbookToken('summary_cards', summaryProfileSelect.value) || '';
      const values = {
        destination: 'summary_cards',
        profile_id: summaryProfileSelect.value,
        profile_name: summaryProfileName.value,
        profile: JSON.stringify(profile)
      };
      if (workbookToken) {
        values.workbook_token = workbookToken;
        values.workbook_header_row = window.IRISProfileWorkbookMapper?.workbookHeaderRow('summary_cards') || '';
      }
      const result = await postProfileSettingsAction('save-import-profile-settings', values);
      if (workbookToken) window.IRISProfileWorkbookMapper?.clear('summary_cards');
      await loadSummaryProfiles(false);
      window.IRISProfileWorkbookMapper?.refresh();
      showNotice(`Saved Summary Card profile: ${result.profile_name}`);
    } catch (error) { showNotice(error.message, true); }
    finally { profileActionsPending.summarySave = false; updateProfileActionAvailability(); }
  });
  rankingProfileActivate.addEventListener('click', async () => {
    if (!rankingProfileSelect.value) return;
    profileActionsPending.rankingActivate = true;
    updateProfileActionAvailability();
    try {
      const result = await postProfileSettingsAction('activate-import-profile', { destination: 'ranking_history', profile_id: rankingProfileSelect.value });
      await loadRankingProfiles();
      showNotice(`Active Ranking History profile: ${result.profile_name}`);
    } catch (error) { showNotice(error.message, true); }
    finally { profileActionsPending.rankingActivate = false; updateProfileActionAvailability(); }
  });
  rankingProfileSave.addEventListener('click', async () => {
    if (!rankingProfileSelect.value) { showNotice('Choose a Ranking History profile first.', true); return; }
    if (!rankingProfileName.value.trim()) {
      rankingProfileName.setCustomValidity('Enter a profile name.');
      rankingProfileName.reportValidity();
      updateProfileActionAvailability();
      return;
    }
    let profile;
    try {
      profile = JSON.parse(rankingProfileEditor.value || '{}');
      if (!profile || typeof profile !== 'object' || Array.isArray(profile)) throw new Error('Profile must be a JSON object.');
    } catch (error) { showNotice(`Invalid Ranking History profile JSON: ${error.message}`, true); return; }
    profileActionsPending.rankingSave = true;
    updateProfileActionAvailability();
    try {
      const workbookToken = window.IRISProfileWorkbookMapper?.workbookToken('ranking_history', rankingProfileSelect.value) || '';
      const values = {
        destination: 'ranking_history',
        profile_id: rankingProfileSelect.value,
        profile_name: rankingProfileName.value,
        profile: JSON.stringify(profile)
      };
      if (workbookToken) {
        values.workbook_token = workbookToken;
        values.workbook_header_row = window.IRISProfileWorkbookMapper?.workbookHeaderRow('ranking_history') || '';
      }
      const result = await postProfileSettingsAction('save-import-profile-settings', {
        ...values
      });
      if (workbookToken) window.IRISProfileWorkbookMapper?.clear('ranking_history');
      await loadRankingProfiles(false);
      window.IRISProfileWorkbookMapper?.refresh();
      showNotice(`Saved Ranking History profile: ${result.profile_name}`);
    } catch (error) { showNotice(error.message, true); }
    finally { profileActionsPending.rankingSave = false; updateProfileActionAvailability(); }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton?.disabled) return;
    if (submitButton) submitButton.disabled = true;
    const data = new FormData(form);
    data.set('action', 'upload');
    data.set('_csrf', token);
    try {
      const { response, result } = await requestJson(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
      if (!response.ok) throw new Error(result.error || 'Unable to upload template.');
      form.reset();
      showNotice('Template uploaded and activated.');
      await loadTemplates();
      renderTemplates();
    } catch (error) { showNotice(error.message, true); }
    finally { if (submitButton) submitButton.disabled = false; }
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
    if (target.dataset.action === 'save-import-profile') {
      const destination = list.querySelector(`[data-profile-destination="${CSS.escape(target.dataset.id)}"]`)?.value || '';
      const editor = list.querySelector(`[data-profile-json="${CSS.escape(target.dataset.id)}"]`);
      data.set('destination', destination);
      try {
        const profile = JSON.parse(editor?.value || '{}');
        if (!profile || typeof profile !== 'object' || Array.isArray(profile)) throw new Error('Profile must be a JSON object.');
        data.set('profile', JSON.stringify(profile));
      } catch (error) {
        showNotice(`Invalid import profile JSON: ${error.message}`, true);
        return;
      }
    }
    target.disabled = true;
    try {
      const { response, result } = await requestJson(api, { method: 'POST', headers: { 'X-CSRF-Token': token, Accept: 'application/json' }, body: data });
      if (!response.ok) throw new Error(result.error || 'Unable to update template.');
      showNotice(target.dataset.action === 'delete' ? 'Template deleted.' : target.dataset.action === 'set-ranking-body' ? 'Ranking body link saved.' : target.dataset.action === 'save-import-profile' ? 'Import profile saved.' : `Template ${target.dataset.action === 'activate' ? 'reactivated' : 'deactivated'}.`);
      await loadTemplates();
    } catch (error) {
      showNotice(error.message, true);
      target.disabled = false;
    }
  });
})();

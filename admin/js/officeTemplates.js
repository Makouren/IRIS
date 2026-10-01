(() => {
  const list = document.getElementById('activeTemplatesList');
  if (!list) return;

  const api = list.dataset.api;
  const downloadBase = list.dataset.downloadBase;
  const emptyMessage = 'No active templates are available.';

  function render(templates) {
    list.replaceChildren();
    if (!templates.length) {
      const empty = document.createElement('p');
      empty.className = 'px-4 py-6 text-sm text-gray-500 dark:text-slate-400';
      empty.textContent = emptyMessage;
      list.append(empty);
      return;
    }
    for (const template of templates) {
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

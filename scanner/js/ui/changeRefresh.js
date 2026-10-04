/**
 * Purpose: Notify views about persisted data changes and refresh clean views safely.
 * Loaded by: PHP change-refresh partial on admin, office, and Observatory pages.
 * Inputs/outputs: Reads script data attributes and change-signal responses; updates the page.
 * Dependencies: api/change_signal.php, BroadcastChannel, and page form controls.
 * Load order: Deferred after page markup; one instance is enabled by the data attribute.
 */
(() => {
  const script = document.querySelector('script[data-iris-change-refresh]');
  if (!script) return;

  const interval = script.dataset.role === 'super_admin' ? 1000 : 5000;
  const isSuperAdmin = script.dataset.role === 'super_admin';
  const isPublicView = script.dataset.view === 'public';
  const baseline = new WeakMap();
  const touched = new Set();
  const channel = (isSuperAdmin || isPublicView) && 'BroadcastChannel' in window ? new BroadcastChannel('iris-data-change') : null;
  let version = null;
  let pending = false;
  let submittedForm = null;
  let polling = false;

  function valueOf(field) {
    if (field.type === 'checkbox' || field.type === 'radio') return field.checked;
    if (field.type === 'file') return Array.from(field.files || [], file => `${file.name}:${file.size}`);
    if (field.multiple) return Array.from(field.selectedOptions, option => option.value);
    return field.value;
  }

  function rememberFields(root = document) {
    root.querySelectorAll('input, select, textarea').forEach(field => baseline.set(field, JSON.stringify(valueOf(field))));
  }

  function isDirty() {
    if (window.IRIS_STUDIO_DIRTY) return true;
    for (const field of touched) {
      if (baseline.get(field) !== JSON.stringify(valueOf(field))) return true;
    }
    return false;
  }

  function showPendingRefresh() {
    if (pending) return;
    pending = true;
    const notice = document.createElement('div');
    notice.className = 'fixed inset-x-4 bottom-4 z-[1400] mx-auto flex max-w-xl items-center justify-between gap-4 rounded-lg border border-amber-300 bg-white p-4 text-sm shadow-xl dark:border-amber-700 dark:bg-slate-900';
    notice.setAttribute('role', 'status');
    const message = document.createElement('span');
    message.textContent = 'New data is available. Refresh after saving or discarding your edits.';
    const refresh = document.createElement('button');
    refresh.type = 'button';
    refresh.className = 'shrink-0 rounded-md bg-emerald-700 px-3 py-2 font-bold text-white';
    refresh.textContent = 'Refresh now';
    refresh.addEventListener('click', () => window.location.reload());
    notice.append(message, refresh);
    document.body.append(notice);
  }

  function acceptVersion(nextVersion, announce = false) {
    nextVersion = String(nextVersion);
    if (version === null) {
      version = nextVersion;
      return;
    }
    if (version === nextVersion) return;
    version = nextVersion;
    if (announce) channel?.postMessage({ version });
    if (isPublicView) {
      if (isDirty()) {
        window.dispatchEvent(new CustomEvent('iris:data-changed', { detail: { version: nextVersion } }));
        showPendingRefresh();
      } else window.location.reload();
      return;
    }
    if (isSuperAdmin) {
      return;
    }
    if (isDirty()) showPendingRefresh();
    else window.location.reload();
  }

  /** Poll for newer server state and defer reload while local form edits are dirty. */
  async function poll() {
    if (document.visibilityState !== 'visible' || polling) return;
    polling = true;
    try {
      const response = await fetch(script.dataset.endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (response.ok) {
        const state = await response.json();
        acceptVersion(state.version, true);
      }
    } catch (error) {
      console.warn('IRIS refresh check failed:', error);
    } finally {
      polling = false;
    }
  }

  rememberFields();
  document.addEventListener('focusin', event => {
    if (event.target.matches?.('input, select, textarea') && !baseline.has(event.target)) {
      baseline.set(event.target, JSON.stringify(valueOf(event.target)));
    }
  });
  document.addEventListener('input', event => { if (baseline.has(event.target)) touched.add(event.target); });
  document.addEventListener('change', event => { if (baseline.has(event.target)) touched.add(event.target); });
  document.addEventListener('submit', event => { submittedForm = event.target; }, true);
  document.addEventListener('reset', event => window.setTimeout(() => rememberFields(event.target), 0));
  document.addEventListener('visibilitychange', poll);
  channel?.addEventListener('message', event => {
    if (event.data?.version !== undefined) acceptVersion(event.data.version);
  });
  const originalFetch = window.fetch.bind(window);
  window.fetch = async (input, init = {}) => {
    const method = String(init.method || input?.method || 'GET').toUpperCase();
    const response = await originalFetch(input, init);
    if (response.ok && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      if (submittedForm) {
        rememberFields(submittedForm);
        submittedForm = null;
      }
      void poll();
    }
    return response;
  };
  window.setInterval(poll, interval);
  poll();
})();
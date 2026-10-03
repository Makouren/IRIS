import { $, all } from '../utils/helpers.js';

const MAX_UPLOAD_SIZE_BYTES = Number(window.IRIS_MAX_UPLOAD_BYTES);
if (!Number.isSafeInteger(MAX_UPLOAD_SIZE_BYTES) || MAX_UPLOAD_SIZE_BYTES <= 0) {
  throw new Error('The configured upload size limit is unavailable.');
}
const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];

const PENDING_DB_NAME = 'IRIS_Pending_Uploads';
const PENDING_STORE_NAME = 'files';

function openPendingDb() {
  return new Promise((resolve, reject) => {
    if (typeof window === 'undefined' || !window.indexedDB) {
      return reject(new Error('IndexedDB unavailable'));
    }
    const req = indexedDB.open(PENDING_DB_NAME, 1);
    req.onupgradeneeded = e => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(PENDING_STORE_NAME)) {
        db.createObjectStore(PENDING_STORE_NAME, { autoIncrement: true });
      }
    };
    req.onsuccess = e => resolve(e.target.result);
    req.onerror = e => reject(e.target.error);
  });
}

export async function savePendingUploads(files) {
  const items = await Promise.all(Array.from(files).map(async file => {
    const buffer = await file.arrayBuffer();
    return {
      name: file.name,
      type: file.type,
      size: file.size,
      lastModified: file.lastModified,
      buffer
    };
  }));
  const db = await openPendingDb();
  return new Promise((resolve, reject) => {
    let tx;
    try {
      tx = db.transaction(PENDING_STORE_NAME, 'readwrite');
      tx.oncomplete = () => {
        db.close();
        resolve(true);
      };
      tx.onerror = () => {
        db.close();
        reject(tx.error || new Error('Unable to queue upload'));
      };
      tx.onabort = () => {
        db.close();
        reject(tx.error || new Error('Upload queue transaction was aborted'));
      };
      const store = tx.objectStore(PENDING_STORE_NAME);
      for (const item of items) {
        store.add(item);
      }
    } catch (error) {
      if (tx) tx.abort();
      db.close();
      reject(error);
    }
  });
}

export async function getAndClearPendingUploads() {
  try {
    const db = await openPendingDb();
    const tx = db.transaction(PENDING_STORE_NAME, 'readwrite');
    const store = tx.objectStore(PENDING_STORE_NAME);
    const req = store.getAll();
    return new Promise(resolve => {
      req.onsuccess = () => {
        const items = req.result || [];
        if (items.length) {
          store.clear();
        }
        const files = items.map(item => new File([item.buffer], item.name, { type: item.type, lastModified: item.lastModified }));
        resolve(files);
      };
      req.onerror = () => resolve([]);
    });
  } catch (err) {
    return [];
  }
}

export function initFileIngestion(ctx) {
  const input = [
    /* UPLOAD MOVED TO ADMIN ROLE: document.getElementById('adminInlineFileInput'), */
    /* UPLOAD MOVED TO ADMIN ROLE: document.getElementById('adminWidgetFileInput'), */
    document.getElementById('scannerUploadFileInput'),
    document.getElementById('fileInput'),
    document.querySelector('#inlineUploadDropzone input[type="file"]'),
    document.querySelector('#uploadWidgetModal input[type="file"]')
  ].find(Boolean) || null;

  const browse = [
    /* UPLOAD MOVED TO ADMIN ROLE: document.getElementById('adminInlineBrowseBtn'), */
    /* UPLOAD MOVED TO ADMIN ROLE: document.getElementById('adminWidgetBrowseBtn'), */
    document.getElementById('scannerUploadBrowseBtn'),
    document.getElementById('btnInlineBrowse'),
    document.getElementById('btnBrowse')
  ].find(Boolean) || null;

  const dropzone = $('dropzone') || $('inlineUploadDropzone');
  /* UPLOAD MOVED TO ADMIN ROLE: const trigger = $('uploadWidgetTrigger'); const modal = $('uploadWidgetModal'); const closeBtn = $('closeUploadWidget'); */
  const trigger = null; const modal = null; const closeBtn = null;
  const progressCard = $('progressCard'); const workspace = $('workspaceGrid');
  const status = $('progressStatus'); const percent = $('progressPercent'); const fill = $('progressFill');

  let lastFocusedElement = null;

  const showWarning = messageText => {
    const existing = document.getElementById('irisUploadSizeWarning');
    if (existing) existing.remove();

    const wrapper = document.createElement('div');
    wrapper.id = 'irisUploadSizeWarning';
    wrapper.setAttribute('role', 'dialog');
    wrapper.setAttribute('aria-modal', 'true');
    wrapper.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.72);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;z-index:99999;padding:1rem;';

    const card = document.createElement('div');
    card.style.cssText = 'width:min(720px,calc(100vw - 1.25rem));background:#232b31;border:1px solid rgba(148,163,184,0.35);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.38);color:#edf6ff;font-family:Inter,sans-serif;overflow:hidden;';

    const header = document.createElement('div');
    header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:1.2rem 1.4rem;border-bottom:1px solid rgba(148,163,184,0.25);';

    const title = document.createElement('div');
    title.textContent = 'Upload Validation Error';
    title.style.cssText = 'font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#dfe7f1;';

    const modalCloseBtn = document.createElement('button');
    modalCloseBtn.textContent = '×';
    modalCloseBtn.setAttribute('aria-label', 'Close error dialog');
    modalCloseBtn.style.cssText = 'background:transparent;border:none;color:#f8fafc;font-size:2rem;line-height:1;cursor:pointer;padding:0;margin:0;';
    modalCloseBtn.addEventListener('click', () => wrapper.remove());

    header.appendChild(title);
    header.appendChild(modalCloseBtn);

    const body = document.createElement('div');
    body.style.padding = '1.4rem 1.4rem 1.1rem';

    const message = document.createElement('p');
    message.textContent = messageText;
    message.style.cssText = 'margin:0;color:#edf6ff;font-size:1.02rem;line-height:1.6;white-space:pre-line;';

    const footer = document.createElement('div');
    footer.style.cssText = 'display:flex;justify-content:flex-end;padding:0 1.4rem 1.2rem;';

    const okBtn = document.createElement('button');
    okBtn.textContent = 'OK';
    okBtn.style.cssText = 'background:#f3f4f6;border:none;border-radius:9999px;color:#111827;font-size:1.1rem;font-weight:700;cursor:pointer;padding:0.72rem 1.8rem;min-width:88px;';
    okBtn.addEventListener('click', () => wrapper.remove());

    footer.appendChild(okBtn);
    body.appendChild(message);
    card.appendChild(header);
    card.appendChild(body);
    card.appendChild(footer);
    wrapper.appendChild(card);
    wrapper.addEventListener('click', event => { if (event.target === wrapper) wrapper.remove(); });
    document.body.appendChild(wrapper);
  };

  const isAllowedFile = file => {
    const name = file?.name || '';
    const ext = name.split('.').pop().toLowerCase();
    return ALLOWED_EXTENSIONS.includes(ext);
  };

  const validateFiles = files => {
    const selected = Array.from(files || []);
    const invalidType = selected.filter(file => !isAllowedFile(file));
    const tooLarge = selected.filter(file => Number(file?.size || 0) > MAX_UPLOAD_SIZE_BYTES);

    if (invalidType.length || tooLarge.length) {
      const messages = [];
      if (invalidType.length) {
        const names = invalidType.map(f => f.name).join(', ');
        messages.push(`Unsupported format for: ${names}.\nPlease upload a Spreadsheet (.xlsx, .xls, .csv).`);
      }
      if (tooLarge.length) {
        const names = tooLarge.map(f => f.name).join(', ');
        const limitMb = MAX_UPLOAD_SIZE_BYTES / (1024 * 1024);
        messages.push(`File size exceeds the ${limitMb} MB limit for: ${names}.\nPlease choose a file no larger than ${limitMb} MB.`);
      }
      showWarning(messages.join('\n\n'));
      return [];
    }

    return selected;
  };

  const openModal = () => {
    if (!modal) return;
    lastFocusedElement = document.activeElement;
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    const focusables = Array.from(modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(el => !el.disabled && el.offsetParent !== null);
    if (focusables.length) focusables[0].focus();
  };

  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
      try { lastFocusedElement.focus(); } catch (e) {}
    }
  };

  const updateProgress = (text, value) => {
    if (status) status.textContent = text;
    if (percent) percent.textContent = `${value}%`;
    if (fill) fill.style.width = `${value}%`;
  };

  const handleFilesOnIngestion = async (selected) => {
    if (!selected || !selected.length) return;

    const hasRestoredEntry = ctx.state.queue.some(item => item.source === 'restored');
    if (hasRestoredEntry) {
      ctx.state.queue = [];
      ctx.state.activeScan = null;
    }

    closeModal();
    if (progressCard) progressCard.style.display = 'block';
    const inlineDropzone = $('inlineUploadDropzone');
    if (inlineDropzone) inlineDropzone.style.display = 'none';
    if (workspace) workspace.style.display = 'grid';

    for (let index = 0; index < selected.length; index += 1) {
      const file = selected[index];
      try {
        updateProgress(`Scanning ${file.name} (${index + 1}/${selected.length})...`, 10);
        const result = await ctx.scanner.scanFile(file, progress => updateProgress(progress.status, progress.progress));
        ctx.state.queue.unshift(result);
        ctx.api.renderQueue();
        await ctx.api.setActiveScan(result);
      } catch (error) {
        console.error('Scan Error:', error);
        showWarning(`${file.name}: ${error.message || 'Unknown error'}`);
      }
    }
    setTimeout(() => {
      if (progressCard) progressCard.style.display = 'none';
      updateProgress('Scan complete!', 100);
    }, 800);
  };

  const processFiles = async (files) => {
    const selected = validateFiles(files);
    if (!selected.length) {
      if (input) input.value = '';
      return;
    }

    if (window.IRIS_STUDIO_DIRTY) {
      const confirmLeave = confirm('You have unsaved changes in the Review Editor. Uploading a file will redirect to File Ingestion. Continue?');
      if (!confirmLeave) return;
    }

    const isIngestionPage = Boolean($('scannerWorkspaceView'));

    if (!isIngestionPage) {
      try {
        await savePendingUploads(selected);
        window.IRIS_STUDIO_DIRTY = false;
        closeModal();
        window.location.href = window.base_url ? window.base_url('admin/dashboard.php') : 'dashboard.php';
      } catch (err) {
        console.error('Failed to hand off upload:', err);
        showWarning(`Unable to queue upload: ${err.message || err}. Please try again.`);
      }
      return;
    }

    await handleFilesOnIngestion(selected);
  };

  ctx.api.handleFiles = processFiles;
  ctx.api.updateProgress = updateProgress;
  ctx.api.openUploadModal = openModal;
  window.IRIS_OPEN_UPLOAD_MODAL = openModal;

  /* UPLOAD MOVED TO ADMIN ROLE: trigger?.addEventListener('click', openModal); */
  /* UPLOAD MOVED TO ADMIN ROLE: closeBtn?.addEventListener('click', closeModal); */
  /* UPLOAD MOVED TO ADMIN ROLE: modal?.addEventListener('click', event => { if (event.target === modal) closeModal(); }); */

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && modal?.classList.contains('active')) {
      closeModal();
    }
  });

  /* UPLOAD MOVED TO ADMIN ROLE: modal?.addEventListener('keydown', event => {
    if (event.key === 'Tab') {
      const focusables = Array.from(modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(el => !el.disabled && el.offsetParent !== null);
      if (!focusables.length) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  }); */

  [browse, document.getElementById('btnInlineBrowse'), document.getElementById('scannerUploadBrowseBtn')].filter(Boolean).forEach(button => {
    button.addEventListener('click', () => {
      input?.click();
    });
  });

  input?.addEventListener('change', event => {
    const selected = Array.from(event.target.files || []);
    if (!selected.length) return;
    processFiles(selected);
  });

  const dropzoneElements = [dropzone, $('inlineUploadDropzone')].filter(Boolean);
  dropzoneElements.forEach(dz => {
    ['dragenter', 'dragover'].forEach(name => dz.addEventListener(name, event => { event.preventDefault(); dz.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(name => dz.addEventListener(name, event => { event.preventDefault(); dz.classList.remove('dragover'); }));
    dz.addEventListener('drop', event => {
      event.preventDefault();
      dz.classList.remove('dragover');
      processFiles(Array.from(event.dataTransfer?.files || []));
    });
  });

  all('.sample-btn').forEach(button => button.addEventListener('click', () => {
    const type = button.getAttribute('data-sample');
    const generator = window.SampleGenerator;
    let file = null;
    if (type === 'payroll' || type === 'iao') file = generator?.createSampleExcelFile();
    if (file) processFiles([file]);
  }));

  // On page load, if on Ingestion page, check for pending handoff files from IndexedDB:
  if ($('scannerWorkspaceView')) {
    getAndClearPendingUploads().then(pendingFiles => {
      if (pendingFiles && pendingFiles.length > 0) {
        handleFilesOnIngestion(pendingFiles);
      }
    });
  }
}

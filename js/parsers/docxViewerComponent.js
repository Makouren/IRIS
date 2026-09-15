/**
 * IRIS AI - Microsoft Word (DOCX) Document Viewer Component
 * Renders .docx files with real fidelity — fonts, tables, images,
 * bold/italic, and page breaks — using the docx-preview library (docx.renderAsync).
 * Mirrors the API pattern of PdfViewerComponent so the rest of the app
 * can treat them identically.
 *
 * Requires: docx-preview (loaded as window.docx via CDN)
 * CDN: https://cdn.jsdelivr.net/npm/docx-preview@latest/dist/docx-preview.min.js
 */

class DocxViewerComponent {
  /**
   * @param {HTMLElement|string} container  - Mount point element or CSS selector
   * @param {Object} options
   * @param {boolean} [options.showToolbar=true]
   * @param {Function} [options.onReady]      - Fired when render completes
   * @param {Function} [options.onError]      - Fired on render failure
   */
  constructor(container, options = {}) {
    if (!container) throw new Error('DocxViewerComponent requires a valid container element.');

    this.container = typeof container === 'string' ? document.querySelector(container) : container;
    this.options = {
      showToolbar: options.showToolbar !== false,
      onReady: options.onReady || null,
      onError: options.onError || null,
      ...options
    };

    this.fileName = 'Document.docx';
    this._renderCancel = false;

    this._initUI();
  }

  // ─── UI Shell ────────────────────────────────────────────────────────────────

  _initUI() {
    this.container.innerHTML = '';
    this.container.classList.add('docx-viewer-root');

    if (this.options.showToolbar) {
      this._toolbarEl = document.createElement('div');
      this._toolbarEl.className = 'docx-viewer-toolbar';
      this._toolbarEl.innerHTML = `
        <div class="docx-tb-left">
          <span class="docx-brand-badge">DOCX</span>
          <span class="docx-tb-filename" id="docxViewerFileName">${this._escapeHtml(this.fileName)}</span>
        </div>
        <div class="docx-tb-right">
          <span id="docxViewerStatus" class="docx-status-chip">Ready</span>
        </div>
      `;
      this.container.appendChild(this._toolbarEl);
    }

    // Scrollable render area — docx-preview injects its own page wrappers here
    this._viewportEl = document.createElement('div');
    this._viewportEl.className = 'docx-viewer-viewport';
    this._viewportEl.innerHTML = `
      <div class="docx-loading-state" id="docxViewerLoadingState">
        <div class="docx-spinner"></div>
        <p>Ready to load Word document...</p>
      </div>
      <div id="docxViewerRenderTarget" class="docx-render-target"></div>
    `;
    this.container.appendChild(this._viewportEl);

    this._renderTarget = this._viewportEl.querySelector('#docxViewerRenderTarget');
    this._loadingState = this._viewportEl.querySelector('#docxViewerLoadingState');
  }

  // ─── Public API ──────────────────────────────────────────────────────────────

  /**
   * Load and render a .docx document.
   * @param {ArrayBuffer|Blob|File} source  - Raw document data
   * @param {string} [fileName]
   */
  async loadDocument(source, fileName = 'Document.docx') {
    if (typeof docx === 'undefined' || typeof docx.renderAsync !== 'function') {
      const msg = 'docx-preview library is not loaded (window.docx.renderAsync missing).';
      console.error(msg);
      this._showError(msg);
      return;
    }

    this._renderCancel = false;
    this.fileName = fileName;
    this._setFileName(fileName);
    this._showLoading(`Loading ${fileName}…`);

    try {
      let arrayBuffer;

      if (source instanceof ArrayBuffer || source instanceof Uint8Array) {
        arrayBuffer = source instanceof Uint8Array ? source.buffer : source;
      } else if (source instanceof Blob || source instanceof File) {
        arrayBuffer = await source.arrayBuffer();
      } else if (typeof source === 'string') {
        // URL or blob URL
        const res = await fetch(source);
        if (!res.ok) throw new Error(`Failed to fetch document: HTTP ${res.status}`);
        arrayBuffer = await res.arrayBuffer();
      } else {
        throw new Error('Unsupported source type. Provide ArrayBuffer, File, Blob, or URL string.');
      }

      if (this._renderCancel) return;

      // Clear previous render
      this._renderTarget.innerHTML = '';
      this._hideLoading();

      this._setStatus('Rendering…');

      await docx.renderAsync(arrayBuffer, this._renderTarget, undefined, {
        className: 'docx-content',   // wrapper class applied by library
        inWrapper: true,             // wrap pages in a container element
        ignoreWidth: false,          // preserve original page width
        ignoreHeight: false,         // preserve original page height (paginated look)
        breakPages: true,            // render real page breaks like Word
        experimental: true,          // better table / image support
        useBase64URL: true,          // inline images as base64 (no CORS issues)
        renderChanges: false,        // skip tracked-changes rendering
        renderComments: false,       // skip comments
      });

      this._setStatus('Ready');

      if (typeof this.options.onReady === 'function') {
        this.options.onReady(this.fileName);
      }

    } catch (err) {
      console.error('DocxViewerComponent render error:', err);
      this._showError(err.message || 'Failed to render Word document');
      if (typeof this.options.onError === 'function') {
        this.options.onError(err);
      }
    }
  }

  /** Destroy and clean up */
  destroy() {
    this._renderCancel = true;
    if (this.container) this.container.innerHTML = '';
  }

  // ─── Private Helpers ─────────────────────────────────────────────────────────

  _setFileName(name) {
    const el = this.container.querySelector('#docxViewerFileName');
    if (el) el.textContent = this._escapeHtml(name);
  }

  _setStatus(text) {
    const el = this.container.querySelector('#docxViewerStatus');
    if (el) el.textContent = text;
  }

  _showLoading(msg = 'Rendering document…') {
    if (this._loadingState) {
      this._loadingState.innerHTML = `<div class="docx-spinner"></div><p>${this._escapeHtml(msg)}</p>`;
      this._loadingState.style.display = 'flex';
    }
    if (this._renderTarget) this._renderTarget.style.display = 'none';
  }

  _hideLoading() {
    if (this._loadingState) this._loadingState.style.display = 'none';
    if (this._renderTarget) this._renderTarget.style.display = '';
  }

  _showError(errMsg) {
    if (this._loadingState) this._loadingState.style.display = 'none';
    if (this._renderTarget) {
      this._renderTarget.style.display = '';
      this._renderTarget.innerHTML = `
        <div class="docx-error-state">
          <span style="font-size:1.8rem;margin-bottom:0.5rem;display:block;">⚠️</span>
          <p style="color:#EF4444;font-weight:700;margin-bottom:0.35rem;">Document Viewer Notice</p>
          <p style="font-size:0.8rem;color:#94A3B8;">${this._escapeHtml(errMsg)}</p>
        </div>
      `;
    }
    this._setStatus('Error');
  }

  _escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

if (typeof window !== 'undefined') {
  window.DocxViewerComponent = DocxViewerComponent;
}

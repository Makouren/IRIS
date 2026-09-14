/**
 * IRIS AI - PDF Document Viewer Component (Mozilla PDF.js)
 * Standalone, pure client-side PDF document viewer with exact layout preservation,
 * text layer rendering, click-to-copy, page navigation, continuous/single-page scroll modes,
 * and dynamic zoom controls.
 */

class PdfViewerComponent {
  constructor(container, options = {}) {
    if (!container) {
      throw new Error('PdfViewerComponent requires a valid container element.');
    }

    this.container = typeof container === 'string' ? document.querySelector(container) : container;
    this.options = {
      initialZoom: options.initialZoom || 1.0,
      minZoom: 0.5,
      maxZoom: 2.5,
      zoomStep: 0.15,
      scrollMode: options.scrollMode || 'continuous', // 'continuous' or 'single'
      onTextSelect: options.onTextSelect || null,
      onPageChange: options.onPageChange || null,
      showToolbar: options.showToolbar !== false,
      ...options
    };

    this.pdfDoc = null;
    this.currentPage = 1;
    this.totalPages = 0;
    this.currentZoom = this.options.initialZoom;
    this.renderedPages = new Map(); // pageNum -> { canvas, textLayer, isRendered }
    this.isRendering = false;
    this.renderQueue = [];
    this.fileName = 'Document.pdf';

    // Ensure PDF.js worker
    if (typeof pdfjsLib !== 'undefined' && !pdfjsLib.GlobalWorkerOptions.workerSrc) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    this.initUI();
  }

  /**
   * Build minimal viewer chrome UI
   */
  initUI() {
    this.container.innerHTML = '';
    this.container.classList.add('pdf-viewer-root');

    // Toolbar (if enabled)
    if (this.options.showToolbar) {
      this.toolbarEl = document.createElement('div');
      this.toolbarEl.className = 'pdf-viewer-toolbar';
      this.toolbarEl.innerHTML = `
        <div class="pdf-tb-left">
          <span class="pdf-brand-badge">PDF</span>
          <span class="pdf-tb-filename" id="pdfViewerFileName">${this.escapeHtml(this.fileName)}</span>
        </div>

        <div class="pdf-tb-center">
          <button type="button" class="pdf-btn" id="pdfBtnPrevPage" title="Previous Page">▲</button>
          <div class="pdf-page-indicator">
            <input type="number" id="pdfPageInput" class="pdf-page-num-input" value="1" min="1" max="1">
            <span>/</span>
            <span id="pdfTotalPagesSpan">1</span>
          </div>
          <button type="button" class="pdf-btn" id="pdfBtnNextPage" title="Next Page">▼</button>
        </div>

        <div class="pdf-tb-right">
          <button type="button" class="pdf-btn mode-btn active" id="pdfBtnContinuousMode" title="Continuous Scroll Mode">📜</button>
          <button type="button" class="pdf-btn mode-btn" id="pdfBtnSingleMode" title="Single Page Mode">📄</button>
          <span class="pdf-tb-sep">|</span>
          <button type="button" class="pdf-btn" id="pdfBtnZoomOut" title="Zoom Out">−</button>
          <span id="pdfZoomLabel" class="pdf-zoom-val">${Math.round(this.currentZoom * 100)}%</span>
          <button type="button" class="pdf-btn" id="pdfBtnZoomIn" title="Zoom In">+</button>
          <button type="button" class="pdf-btn" id="pdfBtnFitWidth" title="Fit to Width">↔</button>
          <span id="pdfCopyFeedback" class="pdf-copy-toast"></span>
        </div>
      `;
      this.container.appendChild(this.toolbarEl);
      this.bindToolbarEvents();
    }

    // Scrollable Viewport Canvas Area
    this.viewportEl = document.createElement('div');
    this.viewportEl.className = 'pdf-viewer-viewport';
    this.viewportEl.innerHTML = `
      <div class="pdf-pages-stack" id="pdfPagesStack">
        <div class="pdf-loading-state">
          <div class="pdf-spinner"></div>
          <p>Ready to load PDF document...</p>
        </div>
      </div>
    `;
    this.container.appendChild(this.viewportEl);
    this.pagesStackEl = this.viewportEl.querySelector('#pdfPagesStack');

    // Viewport Scroll Tracker for continuous mode
    this.viewportEl.addEventListener('scroll', () => this.handleViewportScroll(), { passive: true });

    // Text Selection & Click-to-copy handler
    this.viewportEl.addEventListener('mouseup', (e) => this.handleTextSelection(e));
  }

  /**
   * Bind event listeners on the toolbar
   */
  bindToolbarEvents() {
    if (!this.toolbarEl) return;

    const btnPrev = this.toolbarEl.querySelector('#pdfBtnPrevPage');
    const btnNext = this.toolbarEl.querySelector('#pdfBtnNextPage');
    const pageInput = this.toolbarEl.querySelector('#pdfPageInput');
    const btnZoomIn = this.toolbarEl.querySelector('#pdfBtnZoomIn');
    const btnZoomOut = this.toolbarEl.querySelector('#pdfBtnZoomOut');
    const btnFitWidth = this.toolbarEl.querySelector('#pdfBtnFitWidth');
    const btnContinuous = this.toolbarEl.querySelector('#pdfBtnContinuousMode');
    const btnSingle = this.toolbarEl.querySelector('#pdfBtnSingleMode');

    if (btnPrev) btnPrev.onclick = () => this.prevPage();
    if (btnNext) btnNext.onclick = () => this.nextPage();

    if (pageInput) {
      pageInput.onchange = (e) => {
        const val = parseInt(e.target.value, 10);
        if (!isNaN(val) && val >= 1 && val <= this.totalPages) {
          this.goToPage(val);
        } else {
          pageInput.value = this.currentPage;
        }
      };
      pageInput.onkeydown = (e) => {
        if (e.key === 'Enter') pageInput.blur();
      };
    }

    if (btnZoomIn) btnZoomIn.onclick = () => this.zoomIn();
    if (btnZoomOut) btnZoomOut.onclick = () => this.zoomOut();
    if (btnFitWidth) btnFitWidth.onclick = () => this.fitWidth();

    if (btnContinuous && btnSingle) {
      btnContinuous.onclick = () => {
        this.setScrollMode('continuous');
        btnContinuous.classList.add('active');
        btnSingle.classList.remove('active');
      };
      btnSingle.onclick = () => {
        this.setScrollMode('single');
        btnSingle.classList.add('active');
        btnContinuous.classList.remove('active');
      };
    }
  }

  /**
   * Load a PDF document from File, Blob, ArrayBuffer, base64, URL, or pdfDocReference
   */
  async loadDocument(source, fileName = 'Document.pdf') {
    if (typeof pdfjsLib === 'undefined') {
      throw new Error('Mozilla PDF.js library (pdfjs-dist) is not loaded in the window.');
    }

    this.fileName = fileName;
    const fileNameEl = this.container.querySelector('#pdfViewerFileName');
    if (fileNameEl) fileNameEl.textContent = fileName;

    this.showLoadingState(`Loading ${fileName}...`);

    try {
      let loadingTask = null;

      if (source && typeof source === 'object' && source._pdfInfo) {
        // Direct PDF.js document reference
        this.pdfDoc = source;
      } else if (source instanceof File || source instanceof Blob) {
        const arrayBuffer = await source.arrayBuffer();
        loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
        this.pdfDoc = await loadingTask.promise;
      } else if (source instanceof ArrayBuffer || source instanceof Uint8Array) {
        loadingTask = pdfjsLib.getDocument({ data: source });
        this.pdfDoc = await loadingTask.promise;
      } else if (typeof source === 'string') {
        if (source.startsWith('data:application/pdf;base64,') || source.startsWith('data:application/octet-stream;base64,')) {
          const rawBase64 = source.split(',')[1];
          const byteChars = atob(rawBase64);
          const byteNumbers = new Array(byteChars.length);
          for (let i = 0; i < byteChars.length; i++) {
            byteNumbers[i] = byteChars.charCodeAt(i);
          }
          const uint8Array = new Uint8Array(byteNumbers);
          loadingTask = pdfjsLib.getDocument({ data: uint8Array });
          this.pdfDoc = await loadingTask.promise;
        } else {
          // URL
          loadingTask = pdfjsLib.getDocument(source);
          this.pdfDoc = await loadingTask.promise;
        }
      } else {
        throw new Error('Unsupported PDF input source type.');
      }

      this.totalPages = this.pdfDoc.numPages;
      this.currentPage = 1;

      this.updatePageIndicatorUI();
      await this.renderDocument();

    } catch (err) {
      console.error('PdfViewerComponent failed to load document:', err);
      this.showErrorState(`Failed to render PDF: ${err.message || 'Unknown error'}`);
    }
  }

  /**
   * Render all pages or single page according to scrollMode
   */
  async renderDocument() {
    if (!this.pdfDoc || !this.pagesStackEl) return;

    this.pagesStackEl.innerHTML = '';
    this.renderedPages.clear();

    if (this.options.scrollMode === 'single') {
      await this.renderSinglePage(this.currentPage);
    } else {
      await this.renderContinuousPages();
    }
  }

  /**
   * Render continuous stacked pages
   */
  async renderContinuousPages() {
    this.pagesStackEl.classList.remove('single-mode');
    this.pagesStackEl.classList.add('continuous-mode');

    for (let pageNum = 1; pageNum <= this.totalPages; pageNum++) {
      const pageWrapper = document.createElement('div');
      pageWrapper.className = 'pdf-page-card';
      pageWrapper.id = `pdfPageCard_${pageNum}`;
      pageWrapper.setAttribute('data-page', pageNum);

      const canvas = document.createElement('canvas');
      canvas.className = 'pdf-canvas';

      const textLayerDiv = document.createElement('div');
      textLayerDiv.className = 'textLayer';

      const pageWatermark = document.createElement('div');
      pageWatermark.className = 'pdf-page-footer-tag';
      pageWatermark.textContent = `Page ${pageNum} of ${this.totalPages}`;

      pageWrapper.appendChild(canvas);
      pageWrapper.appendChild(textLayerDiv);
      pageWrapper.appendChild(pageWatermark);
      this.pagesStackEl.appendChild(pageWrapper);

      this.renderedPages.set(pageNum, {
        wrapper: pageWrapper,
        canvas,
        textLayer: textLayerDiv,
        isRendered: false
      });

      // Render page contents
      await this.renderPageContent(pageNum);
    }
  }

  /**
   * Render a single page only
   */
  async renderSinglePage(pageNum) {
    this.pagesStackEl.classList.remove('continuous-mode');
    this.pagesStackEl.classList.add('single-mode');
    this.pagesStackEl.innerHTML = '';

    const pageWrapper = document.createElement('div');
    pageWrapper.className = 'pdf-page-card single-page-view';
    pageWrapper.id = `pdfPageCard_${pageNum}`;
    pageWrapper.setAttribute('data-page', pageNum);

    const canvas = document.createElement('canvas');
    canvas.className = 'pdf-canvas';

    const textLayerDiv = document.createElement('div');
    textLayerDiv.className = 'textLayer';

    const pageWatermark = document.createElement('div');
    pageWatermark.className = 'pdf-page-footer-tag';
    pageWatermark.textContent = `Page ${pageNum} of ${this.totalPages}`;

    pageWrapper.appendChild(canvas);
    pageWrapper.appendChild(textLayerDiv);
    pageWrapper.appendChild(pageWatermark);
    this.pagesStackEl.appendChild(pageWrapper);

    this.renderedPages.set(pageNum, {
      wrapper: pageWrapper,
      canvas,
      textLayer: textLayerDiv,
      isRendered: false
    });

    await this.renderPageContent(pageNum);
  }

  /**
   * Render Canvas and Text Layer for a specific page using PDF.js
   */
  async renderPageContent(pageNum) {
    const pageItem = this.renderedPages.get(pageNum);
    if (!pageItem || !this.pdfDoc) return;

    try {
      const page = await this.pdfDoc.getPage(pageNum);
      const viewport = page.getViewport({ scale: this.currentZoom });

      // Match canvas dimensions to high DPI display
      const outputScale = window.devicePixelRatio || 1;
      const canvas = pageItem.canvas;
      const context = canvas.getContext('2d');

      canvas.width = Math.floor(viewport.width * outputScale);
      canvas.height = Math.floor(viewport.height * outputScale);
      canvas.style.width = `${Math.floor(viewport.width)}px`;
      canvas.style.height = `${Math.floor(viewport.height)}px`;

      pageItem.wrapper.style.width = `${Math.floor(viewport.width)}px`;

      const transform = outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null;

      const renderContext = {
        canvasContext: context,
        transform: transform,
        viewport: viewport
      };

      await page.render(renderContext).promise;

      // Render Text Layer for native selection and copying
      const textLayerDiv = pageItem.textLayer;
      textLayerDiv.innerHTML = '';
      textLayerDiv.style.width = `${Math.floor(viewport.width)}px`;
      textLayerDiv.style.height = `${Math.floor(viewport.height)}px`;

      const textContent = await page.getTextContent();

      if (typeof pdfjsLib.renderTextLayer === 'function') {
        await pdfjsLib.renderTextLayer({
          textContentSource: textContent,
          container: textLayerDiv,
          viewport: viewport,
          textDivs: []
        }).promise;
      }

      pageItem.isRendered = true;

    } catch (err) {
      console.warn(`Could not render PDF page ${pageNum}:`, err);
    }
  }

  /**
   * Handle text selection & click-to-copy on the text layer
   */
  handleTextSelection(e) {
    const selection = window.getSelection();
    if (!selection) return;

    const selectedText = selection.toString().trim();
    if (selectedText && selectedText.length > 0) {
      // 1. Fire onTextSelect callback
      if (typeof this.options.onTextSelect === 'function') {
        this.options.onTextSelect(selectedText, e);
      }

      // 2. Dispatch custom DOM event
      const customEvent = new CustomEvent('iris-pdf-text-select', {
        bubbles: true,
        detail: { text: selectedText, page: this.currentPage }
      });
      this.container.dispatchEvent(customEvent);

      // 3. Optional visual feedback toast
      this.showCopyFeedback(selectedText);
    }
  }

  /**
   * Visual feedback when text is selected/copied
   */
  showCopyFeedback(text) {
    const feedbackEl = this.container.querySelector('#pdfCopyFeedback');
    if (!feedbackEl) return;

    const preview = text.length > 20 ? text.substring(0, 20) + '...' : text;
    feedbackEl.textContent = `✓ Selected: "${preview}"`;
    feedbackEl.classList.add('visible');

    if (this._feedbackTimer) clearTimeout(this._feedbackTimer);
    this._feedbackTimer = setTimeout(() => {
      feedbackEl.classList.remove('visible');
    }, 2200);
  }

  /**
   * Navigation methods
   */
  goToPage(pageNum) {
    if (pageNum < 1 || pageNum > this.totalPages) return;
    this.currentPage = pageNum;
    this.updatePageIndicatorUI();

    if (this.options.scrollMode === 'single') {
      this.renderSinglePage(this.currentPage);
    } else {
      const targetCard = this.container.querySelector(`#pdfPageCard_${pageNum}`);
      if (targetCard) {
        targetCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    if (typeof this.options.onPageChange === 'function') {
      this.options.onPageChange(this.currentPage, this.totalPages);
    }
  }

  nextPage() {
    if (this.currentPage < this.totalPages) {
      this.goToPage(this.currentPage + 1);
    }
  }

  prevPage() {
    if (this.currentPage > 1) {
      this.goToPage(this.currentPage - 1);
    }
  }

  /**
   * Zoom controls
   */
  setZoom(newZoom) {
    this.currentZoom = Math.max(this.options.minZoom, Math.min(this.options.maxZoom, newZoom));
    const label = this.container.querySelector('#pdfZoomLabel');
    if (label) label.textContent = `${Math.round(this.currentZoom * 100)}%`;
    this.renderDocument();
  }

  zoomIn() {
    this.setZoom(this.currentZoom + this.options.zoomStep);
  }

  zoomOut() {
    this.setZoom(this.currentZoom - this.options.zoomStep);
  }

  fitWidth() {
    if (!this.viewportEl || !this.pdfDoc) return;
    const viewportWidth = this.viewportEl.clientWidth - 40; // padding
    this.pdfDoc.getPage(1).then(page => {
      const pageView = page.getViewport({ scale: 1.0 });
      const targetZoom = viewportWidth / pageView.width;
      this.setZoom(targetZoom);
    });
  }

  fitPage() {
    if (!this.viewportEl || !this.pdfDoc) return;
    const viewportHeight = this.viewportEl.clientHeight - 50;
    this.pdfDoc.getPage(1).then(page => {
      const pageView = page.getViewport({ scale: 1.0 });
      const targetZoom = viewportHeight / pageView.height;
      this.setZoom(targetZoom);
    });
  }

  /**
   * Toggle between continuous stacked scroll and single page view
   */
  setScrollMode(mode) {
    if (mode !== 'continuous' && mode !== 'single') return;
    this.options.scrollMode = mode;
    this.renderDocument();
  }

  /**
   * Scroll observer in continuous mode
   */
  handleViewportScroll() {
    if (this.options.scrollMode !== 'continuous' || !this.viewportEl) return;

    const pageCards = this.viewportEl.querySelectorAll('.pdf-page-card');
    const containerTop = this.viewportEl.scrollTop;

    pageCards.forEach(card => {
      const cardTop = card.offsetTop - this.viewportEl.offsetTop;
      if (containerTop >= cardTop - 150) {
        const p = parseInt(card.getAttribute('data-page'), 10);
        if (!isNaN(p) && p !== this.currentPage) {
          this.currentPage = p;
          this.updatePageIndicatorUI();
          if (typeof this.options.onPageChange === 'function') {
            this.options.onPageChange(this.currentPage, this.totalPages);
          }
        }
      }
    });
  }

  updatePageIndicatorUI() {
    const input = this.container.querySelector('#pdfPageInput');
    const totalSpan = this.container.querySelector('#pdfTotalPagesSpan');

    if (input) {
      input.value = this.currentPage;
      input.max = this.totalPages;
    }
    if (totalSpan) {
      totalSpan.textContent = this.totalPages;
    }
  }

  showLoadingState(msg = 'Rendering document...') {
    if (!this.pagesStackEl) return;
    this.pagesStackEl.innerHTML = `
      <div class="pdf-loading-state">
        <div class="pdf-spinner"></div>
        <p>${this.escapeHtml(msg)}</p>
      </div>
    `;
  }

  showErrorState(errMsg) {
    if (!this.pagesStackEl) return;
    this.pagesStackEl.innerHTML = `
      <div class="pdf-error-state">
        <span style="font-size: 1.8rem; margin-bottom: 0.5rem; display: block;">⚠️</span>
        <p style="color: #EF4444; font-weight: 700; margin-bottom: 0.35rem;">Document Viewer Notice</p>
        <p style="font-size: 0.8rem; color: #94A3B8;">${this.escapeHtml(errMsg)}</p>
      </div>
    `;
  }

  escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  destroy() {
    if (this._feedbackTimer) clearTimeout(this._feedbackTimer);
    if (this.container) this.container.innerHTML = '';
    this.pdfDoc = null;
    this.renderedPages.clear();
  }
}

if (typeof window !== 'undefined') {
  window.PdfViewerComponent = PdfViewerComponent;
}

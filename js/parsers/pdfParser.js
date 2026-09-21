/**
 * IRIS AI - PDF Document Parser
 * Parses PDF files using Mozilla PDF.js.
 * Extracts multi-page text, embedded metadata, document outlines, and renders pages to canvas.
 * [NOTE]: Embedded image extraction and OCR are commented out and slated for review/revision.
 */

class PdfParser {
  constructor() {
    // Ensure pdfjsLib worker is configured
    if (typeof pdfjsLib !== 'undefined' && !pdfjsLib.GlobalWorkerOptions.workerSrc) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
  }

  async parse(file, onProgress = () => {}) {
    onProgress({ status: 'Loading PDF document stream...', progress: 15 });

    const arrayBuffer = await file.arrayBuffer();

    if (typeof pdfjsLib === 'undefined') {
      throw new Error('PDF.js library is not loaded.');
    }

    onProgress({ status: 'Initializing PDF worker & reading structure...', progress: 30 });

    const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
    const pdfDoc = await loadingTask.promise;
    const numPages = pdfDoc.numPages;

    onProgress({ status: `Extracting text layer from ${numPages} page(s)...`, progress: 45 });

    let pagesData = [];
    let fullText = '';

    for (let pageNum = 1; pageNum <= numPages; pageNum++) {
      const page = await pdfDoc.getPage(pageNum);
      const textContent = await page.getTextContent();
      const pageText = textContent.items.map(item => item.str).join(' ');

      pagesData.push({
        pageNumber: pageNum,
        text: pageText,
        itemCount: textContent.items.length
      });

      fullText += `\n--- Page ${pageNum} ---\n` + pageText;

      const progress = 45 + Math.round((pageNum / numPages) * 35);
      onProgress({ status: `Parsing text layer: page ${pageNum} of ${numPages}...`, progress });
    }

    let embeddedImageOcr = { text: '', count: 0, sources: [] };
    if (typeof ImageOcrPipeline !== 'undefined') {
      onProgress({ status: 'Scanning pages for embedded images & scanned content (OCR)...', progress: 60 });
      const ocrPipeline = new ImageOcrPipeline();
      const ocrResults = await ocrPipeline.extractFromPdf(pdfDoc, pagesData, (p) => {
        onProgress({ status: p.status, progress: 60 + Math.round(p.progress * 0.2) });
      });
      embeddedImageOcr = ocrPipeline.mergeOcrResults(ocrResults);
      if (embeddedImageOcr.text) {
        fullText += '\n\n--- EMBEDDED IMAGE OCR RESULTS ---' + embeddedImageOcr.text;
      }
    }

    onProgress({ status: 'Extracting PDF metadata & document catalog...', progress: 90 });

    let metadata = {
      pageCount: numPages,
      title: 'Untitled Document',
      author: 'Unknown',
      creator: 'Unknown',
      producer: 'Unknown',
      creationDate: null,
      encrypted: false
    };

    try {
      const meta = await pdfDoc.getMetadata();
      if (meta && meta.info) {
        metadata = {
          ...metadata,
          title: meta.info.Title || file.name,
          author: meta.info.Author || 'Not specified',
          creator: meta.info.Creator || 'Not specified',
          producer: meta.info.Producer || 'Not specified',
          creationDate: meta.info.CreationDate || null,
          keywords: meta.info.Keywords || '',
          pdfVersion: meta.info.PDFFormatVersion || '1.7'
        };
      }
    } catch (metaErr) {
      console.warn('Could not read PDF metadata info:', metaErr);
    }

    onProgress({ status: 'Finalizing PDF analysis...', progress: 100 });

    return {
      name: file.name,
      type: 'pdf',
      size: file.size,
      rawText: fullText.trim(),
      metadata,
      pages: pagesData,
      pdfBuffer: arrayBuffer,
      pdfDocReference: pdfDoc,
      embeddedImageOcr
    };
  }

  /**
   * Render a specific page to a canvas element
   */
  async renderPageToCanvas(pdfDoc, pageNumber, canvas, targetWidth = 800) {
    if (!pdfDoc || !canvas) return;
    const page = await pdfDoc.getPage(pageNumber);
    const viewport = page.getViewport({ scale: 1.0 });
    const scale = targetWidth / viewport.width;
    const scaledViewport = page.getViewport({ scale });

    canvas.width = scaledViewport.width;
    canvas.height = scaledViewport.height;

    const ctx = canvas.getContext('2d');
    const renderContext = {
      canvasContext: ctx,
      viewport: scaledViewport
    };

    await page.render(renderContext).promise;
  }
}

if (typeof window !== 'undefined') {
  window.PdfParser = PdfParser;
}

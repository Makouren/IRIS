/**
 * IRIS AI - Unified Embedded Image OCR Pipeline
 * Extracts and OCR-scans embedded images from ANY file type (PDF pages, DOCX inserts, XLSX charts).
 * All extracted image text is merged into the parent document's rawText for PII scanning.
 */

class ImageOcrPipeline {
  constructor() {
    this.ocrAvailable = typeof Tesseract !== 'undefined';
  }

  /**
   * Run OCR on a single image source (canvas, blob, img element, or base64 data URL)
   * Returns { text, confidence, source }
   */
  async ocrFromSource(imageSource, sourceLabel = 'Embedded Image') {
    if (!this.ocrAvailable) {
      return { text: '', confidence: 0, source: sourceLabel, error: 'Tesseract.js not loaded' };
    }

    try {
      const worker = await Tesseract.createWorker('eng', 1);
      const result = await worker.recognize(imageSource);
      await worker.terminate();

      const text = (result.data.text || '').trim();
      return {
        text,
        confidence: Math.round(result.data.confidence || 0),
        source: sourceLabel,
        wordCount: text ? text.split(/\s+/).length : 0
      };
    } catch (err) {
      console.warn(`OCR failed for ${sourceLabel}:`, err);
      return { text: '', confidence: 0, source: sourceLabel, error: err.message };
    }
  }

  /**
   * OCR a canvas element (used for PDF page rendering)
   */
  async ocrFromCanvas(canvas, sourceLabel = 'Rendered Page') {
    return this.ocrFromSource(canvas, sourceLabel);
  }

  /**
   * OCR from a Blob or File object
   */
  async ocrFromBlob(blob, sourceLabel = 'Embedded Image') {
    const url = URL.createObjectURL(blob);
    try {
      const result = await this.ocrFromSource(url, sourceLabel);
      return result;
    } finally {
      URL.revokeObjectURL(url);
    }
  }

  /**
   * OCR from a base64 data URL string (e.g. "data:image/png;base64,...")
   */
  async ocrFromDataUrl(dataUrl, sourceLabel = 'Inline Image') {
    return this.ocrFromSource(dataUrl, sourceLabel);
  }

  /**
   * Extract embedded images from a PDF document and OCR each page.
   * Renders each page to an offscreen canvas and runs OCR — catches scanned/image-based PDFs.
   * Only OCRs pages where the text layer is thin (< threshold words), indicating scanned content.
   */
  async extractFromPdf(pdfDoc, existingPagesData = [], onProgress = () => {}) {
    const results = [];
    const numPages = pdfDoc.numPages;
    const TEXT_THRESHOLD = 15; // Pages with fewer words than this are likely scanned images

    for (let i = 1; i <= numPages; i++) {
      const existingPage = existingPagesData.find(p => p.pageNumber === i);
      const existingWordCount = existingPage ? (existingPage.text || '').split(/\s+/).filter(Boolean).length : 0;

      // Only OCR pages that have very little selectable text (scanned/image pages)
      if (existingWordCount < TEXT_THRESHOLD) {
        onProgress({ status: `OCR scanning page ${i} of ${numPages} (image-based)...`, progress: Math.round((i / numPages) * 100) });

        try {
          const page = await pdfDoc.getPage(i);
          const viewport = page.getViewport({ scale: 2.0 }); // Higher scale = better OCR accuracy
          const canvas = document.createElement('canvas');
          canvas.width = viewport.width;
          canvas.height = viewport.height;
          const ctx = canvas.getContext('2d');

          await page.render({ canvasContext: ctx, viewport }).promise;

          const ocrResult = await this.ocrFromCanvas(canvas, `PDF Page ${i} (Image OCR)`);
          if (ocrResult.text.length > 10) {
            results.push(ocrResult);
          }
        } catch (pageErr) {
          console.warn(`Failed to OCR PDF page ${i}:`, pageErr);
        }
      }
    }

    return results;
  }

  /**
   * Extract embedded images from a DOCX file using JSZip.
   * DOCX is a ZIP archive with images stored in word/media/*.
   */
  async extractFromDocx(arrayBuffer, onProgress = () => {}) {
    const results = [];

    if (typeof JSZip === 'undefined') {
      console.warn('JSZip not available — skipping DOCX embedded image extraction.');
      return results;
    }

    try {
      const zip = await JSZip.loadAsync(arrayBuffer);
      const mediaFiles = [];

      zip.forEach((relativePath, zipEntry) => {
        if (/^word\/media\//i.test(relativePath) && /\.(png|jpg|jpeg|gif|bmp|tiff|webp)$/i.test(relativePath)) {
          mediaFiles.push({ path: relativePath, entry: zipEntry });
        }
      });

      if (mediaFiles.length === 0) return results;

      for (let i = 0; i < mediaFiles.length; i++) {
        const { path, entry } = mediaFiles[i];
        onProgress({
          status: `OCR scanning embedded DOCX image ${i + 1} of ${mediaFiles.length}...`,
          progress: Math.round(((i + 1) / mediaFiles.length) * 100)
        });

        try {
          const blob = await entry.async('blob');
          const ocrResult = await this.ocrFromBlob(blob, `DOCX Image: ${path.split('/').pop()}`);
          if (ocrResult.text.length > 5) {
            results.push(ocrResult);
          }
        } catch (imgErr) {
          console.warn(`Failed to OCR DOCX image ${path}:`, imgErr);
        }
      }
    } catch (zipErr) {
      console.warn('Failed to unzip DOCX for image extraction:', zipErr);
    }

    return results;
  }

  /**
   * Extract embedded images from an XLSX file using JSZip.
   * XLSX is a ZIP archive with images stored in xl/media/*.
   */
  async extractFromExcel(arrayBuffer, onProgress = () => {}) {
    const results = [];

    if (typeof JSZip === 'undefined') {
      console.warn('JSZip not available — skipping Excel embedded image extraction.');
      return results;
    }

    try {
      const zip = await JSZip.loadAsync(arrayBuffer);
      const mediaFiles = [];

      zip.forEach((relativePath, zipEntry) => {
        if (/^xl\/media\//i.test(relativePath) && /\.(png|jpg|jpeg|gif|bmp|tiff|webp)$/i.test(relativePath)) {
          mediaFiles.push({ path: relativePath, entry: zipEntry });
        }
      });

      if (mediaFiles.length === 0) return results;

      for (let i = 0; i < mediaFiles.length; i++) {
        const { path, entry } = mediaFiles[i];
        onProgress({
          status: `OCR scanning embedded Excel image ${i + 1} of ${mediaFiles.length}...`,
          progress: Math.round(((i + 1) / mediaFiles.length) * 100)
        });

        try {
          const blob = await entry.async('blob');
          const ocrResult = await this.ocrFromBlob(blob, `Excel Image: ${path.split('/').pop()}`);
          if (ocrResult.text.length > 5) {
            results.push(ocrResult);
          }
        } catch (imgErr) {
          console.warn(`Failed to OCR Excel image ${path}:`, imgErr);
        }
      }
    } catch (zipErr) {
      console.warn('Failed to unzip XLSX for image extraction:', zipErr);
    }

    return results;
  }

  /**
   * Merge an array of OCR results into a single text block
   */
  mergeOcrResults(ocrResults) {
    if (!ocrResults || ocrResults.length === 0) return { text: '', count: 0, sources: [] };

    const mergedText = ocrResults
      .filter(r => r.text && r.text.length > 0)
      .map(r => `\n--- ${r.source} (Confidence: ${r.confidence}%) ---\n${r.text}`)
      .join('\n');

    return {
      text: mergedText,
      count: ocrResults.filter(r => r.text.length > 0).length,
      sources: ocrResults.map(r => ({ source: r.source, confidence: r.confidence, words: r.wordCount || 0 }))
    };
  }
}

if (typeof window !== 'undefined') {
  window.ImageOcrPipeline = ImageOcrPipeline;
}

/**
 * IRIS AI - Unified Embedded Image OCR Pipeline
 * Extracts and OCR-scans embedded images from ANY file type (PDF pages, DOCX inserts, XLSX charts).
 * All extracted image text is merged into the parent document's rawText for PII scanning.
 */

class ImageOcrPipeline {
  constructor() {
    this.ocrAvailable = typeof Tesseract !== 'undefined';
    this.lowConfidenceThreshold = 60;
  }

  async preprocessImageSource(imageSource) {
    if (typeof document === 'undefined') return imageSource;

    let image = imageSource;
    if (!(typeof HTMLCanvasElement !== 'undefined' && imageSource instanceof HTMLCanvasElement)) {
      let objectUrl = null;
      image = await new Promise((resolve, reject) => {
        const element = new Image();
        element.onload = () => resolve(element);
        element.onerror = () => reject(new Error('Could not load image for OCR preprocessing'));
        objectUrl = typeof imageSource === 'string' ? null : URL.createObjectURL(imageSource);
        element.src = typeof imageSource === 'string' ? imageSource : objectUrl;
      });
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    }

    const scale = 2;
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round((image.naturalWidth || image.width) * scale));
    canvas.height = Math.max(1, Math.round((image.naturalHeight || image.height) * scale));
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
    for (let index = 0; index < pixels.data.length; index += 4) {
      const gray = Math.round(pixels.data[index] * 0.299 + pixels.data[index + 1] * 0.587 + pixels.data[index + 2] * 0.114);
      const contrast = Math.max(0, Math.min(255, Math.round((gray - 128) * 1.35 + 128)));
      pixels.data[index] = contrast;
      pixels.data[index + 1] = contrast;
      pixels.data[index + 2] = contrast;
    }
    context.putImageData(pixels, 0, 0);
    return canvas;
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
      const preprocessedImage = await this.preprocessImageSource(imageSource);
      const worker = await Tesseract.createWorker('eng', 1);
      const result = await worker.recognize(preprocessedImage);
      await worker.terminate();

      const text = (result.data.text || '').trim();
      const words = (result.data.words || []).map(word => ({
        text: String(word.text || '').trim(),
        confidence: Math.round(word.confidence || 0),
        lowConfidence: (word.confidence || 0) < this.lowConfidenceThreshold
      })).filter(word => word.text);
      const lowConfidenceWords = words.filter(word => word.lowConfidence);
      return {
        text,
        confidence: Math.round(result.data.confidence || 0),
        source: sourceLabel,
        wordCount: text ? text.split(/\s+/).length : 0,
        words,
        lowConfidenceWords,
        warnings: lowConfidenceWords.length > 0 ? ['Some OCR words fell below the confidence threshold.'] : []
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
          const viewport = page.getViewport({ scale: 3.0 }); // Higher resolution improves small-character recognition
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

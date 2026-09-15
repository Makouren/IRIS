/**
 * IRIS AI - Microsoft Word (DOCX) Parser
 * Extracts structured HTML, raw text, headings, and formatting with Mammoth.js.
 * [NOTE]: Embedded image extraction and OCR are commented out and slated for review/revision.
 */

class DocxParser {
  async parse(file, onProgress = () => {}) {
    onProgress({ status: 'Loading DOCX document stream...', progress: 20 });

    const arrayBuffer = await file.arrayBuffer();

    onProgress({ status: 'Extracting Word document structure & formatting...', progress: 50 });

    if (typeof mammoth === 'undefined') {
      throw new Error('Mammoth.js library is not loaded.');
    }

    // Extract HTML formatting for rich viewer
    const htmlResult = await mammoth.convertToHtml({ arrayBuffer });
    const rawResult = await mammoth.extractRawText({ arrayBuffer });

    onProgress({ status: 'Analyzing document structure & outlines...', progress: 85 });

    let rawText = (rawResult.value || '').trim();
    const html = htmlResult.value || '<p>No content extracted</p>';
    
    /* [SLATED FOR REVIEW & REVISION]: Embedded Image OCR extraction disabled
    let embeddedImageOcr = { text: '', count: 0, sources: [] };
    if (typeof ImageOcrPipeline !== 'undefined') {
      onProgress({ status: 'Scanning DOCX archive for embedded images (OCR)...', progress: 85 });
      const ocrPipeline = new ImageOcrPipeline();
      const ocrResults = await ocrPipeline.extractFromDocx(arrayBuffer, (p) => {
        onProgress({ status: p.status, progress: 85 + Math.round(p.progress * 0.1) });
      });
      embeddedImageOcr = ocrPipeline.mergeOcrResults(ocrResults);

      if (embeddedImageOcr.text) {
        rawText += '\n\n--- EMBEDDED IMAGE OCR RESULTS ---' + embeddedImageOcr.text;
      }
    }
    */

    const words = rawText ? rawText.split(/\s+/).filter(Boolean) : [];
    const wordCount = words.length;
    const readingTimeMin = Math.max(1, Math.ceil(wordCount / 220));

    // Extract headings for outline navigation
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    const headingElements = tempDiv.querySelectorAll('h1, h2, h3, h4');
    const outline = Array.from(headingElements).map((el, i) => ({
      id: `heading_${i + 1}`,
      level: el.tagName.toLowerCase(),
      text: el.textContent.trim()
    }));

    onProgress({ status: 'Finalizing Word document parsing...', progress: 100 });

    const metadata = {
      wordCount,
      charCount: rawText.length,
      readingTimeMinutes: readingTimeMin,
      paragraphCount: tempDiv.querySelectorAll('p').length,
      tableCount: tempDiv.querySelectorAll('table').length,
      headingCount: outline.length,
      outline
    };

    return {
      name: file.name,
      type: 'docx',
      size: file.size,
      rawText,
      formattedHtml: html,
      docxBuffer: arrayBuffer,  // Raw ArrayBuffer for docx-preview renderAsync
      metadata,
      warnings: htmlResult.messages || []
    };
  }
}

if (typeof window !== 'undefined') {
  window.DocxParser = DocxParser;
}

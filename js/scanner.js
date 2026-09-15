/**
 * IRIS AI - Central Dispatcher & Scanner Orchestrator
 * Integrates Document Parsers (Excel, DOCX, PDF), Graph Engine, and Database Manager.
 * [NOTE]: Standalone image and embedded media OCR scanning are commented out and slated for review/revision.
 */

class ScannerOrchestrator {
  constructor() {
    /* [SLATED FOR REVIEW & REVISION]: Standalone Image OCR Parser
    this.imageParser = new ImageParser();
    */
    this.excelParser = new ExcelParser();
    this.docxParser = new DocxParser();
    this.pdfParser = new PdfParser();
    this.graphEngine = new GraphEngine();
    this.dbManager = new DatabaseManager();
  }

  /**
   * Determine file type from extension / mime
   */
  getFileCategory(file) {
    const name = file.name.toLowerCase();
    const type = file.type.toLowerCase();

    /* [SLATED FOR REVIEW & REVISION]: Standalone image format detection disabled
    if (type.startsWith('image/') || /\.(png|jpg|jpeg|webp|gif|svg|bmp)$/i.test(name)) {
      return 'image';
    }
    */
    if (type.includes('spreadsheet') || type.includes('excel') || type.includes('csv') || /\.(xlsx|xls|csv)$/i.test(name)) {
      return 'excel';
    }
    if (type.includes('word') || type.includes('document') || /\.(docx|doc)$/i.test(name)) {
      return 'docx';
    }
    if (type.includes('pdf') || /\.pdf$/i.test(name)) {
      return 'pdf';
    }
    return 'unknown';
  }

  /**
   * Scan single file and return aggregated draft report
   */
  async scanFile(file, onProgress = () => {}) {
    const category = this.getFileCategory(file);

    if (category === 'unknown') {
      throw new Error(`Unsupported file format: ${file.name}. Supported formats: Spreadsheets (.xlsx, .xls, .csv), Word (.docx), and PDF documents.`);
    }

    onProgress({ status: `Initializing parser for ${file.name}...`, progress: 10 });

    let parsedResult = null;

    // 1. Format-specific parsing
    switch (category) {
      /* [SLATED FOR REVIEW & REVISION]: Standalone image OCR scanning disabled
      case 'image':
        parsedResult = await this.imageParser.parse(file, p => onProgress({ status: p.status, progress: 15 + Math.round(p.progress * 0.45) }));
        break;
      */
      case 'excel':
        parsedResult = await this.excelParser.parse(file, p => onProgress({ status: p.status, progress: 15 + Math.round(p.progress * 0.45) }));
        break;
      case 'docx':
        parsedResult = await this.docxParser.parse(file, p => onProgress({ status: p.status, progress: 15 + Math.round(p.progress * 0.45) }));
        break;
      case 'pdf':
        parsedResult = await this.pdfParser.parse(file, p => onProgress({ status: p.status, progress: 15 + Math.round(p.progress * 0.45) }));
        break;
    }

    // 2. Data Graph Visualization Draft & Suggestions Engine
    onProgress({ status: 'Drafting visualization suggestions (Bar, Line, Pie)...', progress: 75 });
    const graphDrafts = this.graphEngine.generateGraphDrafts({
      name: file.name,
      type: category,
      rawText: parsedResult.rawText,
      sheetsData: parsedResult.sheetsData,
      metadata: parsedResult.metadata
    });

    onProgress({ status: 'Compiling extracted draft package...', progress: 90 });

    // Aggregate Full Scan Package
    const scanPackage = {
      id: `scan_${Date.now()}_${Math.random().toString(36).substr(2, 6)}`,
      name: file.name,
      type: category,
      size: file.size,
      scannedAt: new Date().toISOString(),
      rawText: parsedResult.rawText,
      metadata: parsedResult.metadata,
      formattedHtml: parsedResult.formattedHtml,
      previewUrl: parsedResult.previewUrl,
      sheetsData: parsedResult.sheetsData,
      pages: parsedResult.pages,
      pdfBuffer: parsedResult.pdfBuffer,
      pdfDocReference: parsedResult.pdfDocReference,
      docxBuffer: parsedResult.docxBuffer,   // Raw ArrayBuffer for docx-preview
      graphDrafts,
      status: 'Pending Review'
    };

    // Save automatically to Database Manager
    await this.dbManager.saveRecord(scanPackage);

    onProgress({ status: 'Scan complete!', progress: 100 });

    return scanPackage;
  }
}

if (typeof window !== 'undefined') {
  window.ScannerOrchestrator = ScannerOrchestrator;
}

/**
 * IRIS AI - Central Dispatcher & Scanner Orchestrator
 * Integrates Document Parsers, PII Scanner, Graph Engine, Gemini AI, and Database Manager.
 */

class ScannerOrchestrator {
  constructor() {
    this.imageParser = new ImageParser();
    this.excelParser = new ExcelParser();
    this.docxParser = new DocxParser();
    this.pdfParser = new PdfParser();
    this.piiScanner = new PIIScanner();
    this.geminiService = new GeminiService();
    this.graphEngine = new GraphEngine();
    this.dbManager = new DatabaseManager();
  }

  /**
   * Determine file type from extension / mime
   */
  getFileCategory(file) {
    const name = file.name.toLowerCase();
    const type = file.type.toLowerCase();

    if (type.startsWith('image/') || /\.(png|jpg|jpeg|webp|gif|svg|bmp)$/i.test(name)) {
      return 'image';
    }
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
   * Scan single file and return aggregated audit report
   */
  async scanFile(file, onProgress = () => {}) {
    const category = this.getFileCategory(file);

    if (category === 'unknown') {
      throw new Error(`Unsupported file format: ${file.name}. Supported formats: Images, Excel (.xlsx, .csv), DOCX, and PDF.`);
    }

    onProgress({ status: `Initializing scanner for ${file.name}...`, progress: 5 });

    let parsedResult = null;

    // 1. Format-specific parsing
    switch (category) {
      case 'image':
        parsedResult = await this.imageParser.parse(file, p => onProgress({ status: p.status, progress: 10 + Math.round(p.progress * 0.4) }));
        break;
      case 'excel':
        parsedResult = await this.excelParser.parse(file, p => onProgress({ status: p.status, progress: 10 + Math.round(p.progress * 0.4) }));
        break;
      case 'docx':
        parsedResult = await this.docxParser.parse(file, p => onProgress({ status: p.status, progress: 10 + Math.round(p.progress * 0.4) }));
        break;
      case 'pdf':
        parsedResult = await this.pdfParser.parse(file, p => onProgress({ status: p.status, progress: 10 + Math.round(p.progress * 0.4) }));
        break;
    }

    // 2. High-Precision PII & Sensitive Data Audit
    onProgress({ status: 'Auditing sensitive data & PII leaks...', progress: 60 });
    const piiResult = this.piiScanner.scanText(parsedResult.rawText, file.name);

    // 3. AI Analysis & Summarization (Local / Gemini Cloud)
    onProgress({ status: 'Generating AI executive summary & doc classification...', progress: 75 });
    const aiAnalysis = await this.geminiService.analyzeWithGemini({
      name: file.name,
      type: category,
      rawText: parsedResult.rawText,
      metadata: parsedResult.metadata
    });

    // 4. Data Graph Visualization Draft & Suggestions Engine
    onProgress({ status: 'Drafting data visualization graphs & chart suggestions...', progress: 90 });
    const graphDrafts = this.graphEngine.generateGraphDrafts({
      name: file.name,
      type: category,
      rawText: parsedResult.rawText,
      sheetsData: parsedResult.sheetsData,
      metadata: parsedResult.metadata,
      piiResult
    });

    onProgress({ status: 'Finalizing audit record & saving to Admin Database...', progress: 95 });

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
      pdfDocReference: parsedResult.pdfDocReference,
      ocrData: parsedResult.ocrData,
      piiResult,
      aiAnalysis,
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

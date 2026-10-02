# Browser Parsers and Viewers

V5.3.0 office uploads separate general Data and Report Visualization from Ranking History and Summary Card imports, with destination-specific server-side PHP profiles. The browser parsers documented here continue to power Scanner ingestion and viewing.

Parser modules convert browser-selected files into scan data used by the Scanner UI and PHP-backed record persistence. Libraries are loaded by the scanner page; this directory does not contain a separate parser service.

Parsing and upload handoff do not publish data. Parsed files enter the Scanner review workflow; record status and saved-graph publication are controlled separately by authenticated admin workflows.

## Parsers

- `excelParser.js` reads XLSX, XLS, and CSV workbook data through SheetJS and returns sheets, headers, rows, numeric statistics, metadata, and text.
- `docxParser.js` extracts text, formatted content, headings, and metadata through Mammoth.
- `pdfParser.js` extracts PDF text and metadata through PDF.js and retains page/document references for viewing.
- `imageParser.js` contains image parsing support; `imageOcrPipeline.js` provides OCR helpers used for embedded/scanned content where applicable.

## Viewers

- `docxViewerComponent.js` renders DOCX buffers through `docx-preview`.
- `pdfViewerComponent.js` renders PDFs through PDF.js with page navigation, zoom, scroll behavior, and text selection.
- `../modules/viewerTab.js` and `../modules/documentViewer.js` own Scanner UI integration.

The scanner page loads these browser dependencies from CDNs. The active server-side application is plain PHP/PDO; there is no `scanner_service/` HTTP service in this repository.

## Parser Contract

Parser output may include:

```js
{
  name,
  type,
  size,
  rawText,
  metadata,
  sheetsData,
  formattedHtml,
  pages,
  pdfBuffer,
  pdfDocReference,
  docxBuffer
}
```

Preserve the fields consumed by `ScannerOrchestrator`, overview rendering, document viewing, graph drafting, and record persistence when changing a parser. Pending upload files are handed off through IndexedDB; prepare file buffers before opening the write transaction so it remains active while records are added.

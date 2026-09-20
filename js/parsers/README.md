# Parsers and Document Viewers

The parser layer converts uploaded files into a common scan package consumed by the frontend and database manager.

## Parsers

- `excelParser.js` reads XLSX, XLS, and CSV-compatible workbook data through SheetJS. It returns sheets, headers, rows, numeric statistics, formulas, metadata, and combined text.
- `docxParser.js` extracts formatted HTML, raw text, document metadata, headings, and the source buffer through Mammoth.
- `pdfParser.js` reads PDF text and metadata through PDF.js and preserves page data and the PDF reference for viewing.
- `imageParser.js` remains available for legacy/secondary flows but image scanning is disabled in the active browser upload controls.

## Viewer components

- `docxViewerComponent.js` renders DOCX buffers through `docx-preview`.
- `pdfViewerComponent.js` renders PDF documents through PDF.js with page navigation, zoom, scroll modes, and text selection.

The scanner normalizes parser output in [../scanner.js](../scanner.js). UI-specific rendering is handled by `../modules/viewerTab.js` and `../modules/documentViewer.js`.

## Common parser contract

A parser returns an object containing, where applicable:

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

When changing a parser, preserve the fields used by `ScannerOrchestrator`, overview rendering, document viewing, graph generation, and record persistence.

## Integration boundary

Parser output is an application-neutral scan package at the browser boundary. The active frontend still owns upload orchestration and persistence, while `scanner_service/` exposes a separate HTTP version of the same broad parse-and-suggest concern. v7 documents these contracts without connecting the two execution paths.

/**
 * IRIS AI - Excel & Spreadsheet Parser
 * Analyzes XLSX, XLS, and CSV workbooks with SheetJS.
 * Extracts tabular data, formulas, statistical distributions, and structured text.
 */

class ExcelParser {
  async parse(file, onProgress = () => {}) {
    onProgress({ status: 'Reading spreadsheet binary data...', progress: 20 });

    const arrayBuffer = await file.arrayBuffer();

    onProgress({ status: 'Parsing workbook structure & sheets...', progress: 40 });

    if (typeof XLSX === 'undefined') {
      throw new Error('SheetJS (XLSX) library is not loaded.');
    }

    const workbook = XLSX.read(arrayBuffer, {
      type: 'array',
      cellFormula: true,
      cellStyles: true,
      cellDates: true
    });

    const sheetNames = workbook.SheetNames || [];
    if (sheetNames.length === 0) {
      throw new Error('No worksheets found in this spreadsheet.');
    }

    onProgress({ status: `Analyzing ${sheetNames.length} sheet(s) & computing statistics...`, progress: 65 });

    const sheetsData = {};
    let totalRows = 0;
    let totalCells = 0;
    let totalFormulas = 0;
    let combinedTextParts = [];

    sheetNames.forEach(sheetName => {
      const worksheet = workbook.Sheets[sheetName];
      const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
      const rowCount = jsonData.length;
      const colCount = jsonData.reduce((max, row) => Math.max(max, row.length), 0);

      // Count formulas & inspect cells
      let sheetFormulaCount = 0;
      let numericColumns = {};

      const range = XLSX.utils.decode_range(worksheet['!ref'] || 'A1:A1');
      for (let R = range.s.r; R <= range.e.r; ++R) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
          const cellAddress = XLSX.utils.encode_cell({ c: C, r: R });
          const cell = worksheet[cellAddress];
          if (cell) {
            totalCells++;
            if (cell.f) {
              sheetFormulaCount++;
              totalFormulas++;
            }
          }
        }
      }

      // Headers and data rows
      const headers = jsonData.length > 0 ? jsonData[0].map((h, i) => String(h || `Column ${i + 1}`).trim()) : [];
      const rows = jsonData.slice(1);

      // Compute basic numeric column statistics
      headers.forEach((header, colIndex) => {
        const numericValues = rows
          .map(r => r[colIndex])
          .filter(v => typeof v === 'number' && !isNaN(v));

        if (numericValues.length > 0 && numericValues.length >= rows.length * 0.4) {
          const sum = numericValues.reduce((a, b) => a + b, 0);
          const min = Math.min(...numericValues);
          const max = Math.max(...numericValues);
          const avg = sum / numericValues.length;
          numericColumns[header] = {
            count: numericValues.length,
            sum: Number(sum.toFixed(2)),
            min: Number(min.toFixed(2)),
            max: Number(max.toFixed(2)),
            avg: Number(avg.toFixed(2))
          };
        }
      });

      totalRows += rowCount;

      sheetsData[sheetName] = {
        name: sheetName,
        rowCount,
        colCount,
        formulaCount: sheetFormulaCount,
        headers,
        rows: rows.slice(0, 500), // First 500 rows for display performance
        numericStats: numericColumns
      };

      // Generate textual representation of sheet for AI & PII scan
      const textRows = jsonData.slice(0, 100).map(row => row.join(' | '));
      combinedTextParts.push(`--- Worksheet: ${sheetName} (${rowCount} rows, ${colCount} cols) ---`);
      combinedTextParts.push(textRows.join('\n'));
    });

    // --- Embedded Image OCR Pipeline ---
    let embeddedImageOcr = { text: '', count: 0, sources: [] };
    if (typeof ImageOcrPipeline !== 'undefined') {
      onProgress({ status: 'Scanning spreadsheet archive for embedded images & charts (OCR)...', progress: 85 });
      const ocrPipeline = new ImageOcrPipeline();
      const ocrResults = await ocrPipeline.extractFromExcel(arrayBuffer, (p) => {
        onProgress({ status: p.status, progress: 85 + Math.round(p.progress * 0.1) });
      });
      embeddedImageOcr = ocrPipeline.mergeOcrResults(ocrResults);

      if (embeddedImageOcr.text) {
        combinedTextParts.push('\n--- EMBEDDED EXCEL IMAGE OCR RESULTS ---' + embeddedImageOcr.text);
      }
    }

    onProgress({ status: 'Compiling spreadsheet audit metrics...', progress: 100 });

    const metadata = {
      sheetCount: sheetNames.length,
      sheets: sheetNames,
      totalRows,
      totalCells,
      totalFormulas,
      activeSheet: sheetNames[0],
      embeddedImagesScanned: embeddedImageOcr.count,
      embeddedImageSources: embeddedImageOcr.sources
    };

    return {
      name: file.name,
      type: 'excel',
      size: file.size,
      rawText: combinedTextParts.join('\n\n'),
      metadata,
      sheetsData,
      embeddedImageOcr
    };
  }
}

if (typeof window !== 'undefined') {
  window.ExcelParser = ExcelParser;
}

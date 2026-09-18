/**
 * IRIS AI - Data Graph Visualization Draft & Suggestions Engine
 * Detects numerical patterns in spreadsheets, tables, and document text,
 * generates visualization drafts, and provides chart type recommendations (Bar, Line, Pie).
 */

class GraphEngine {
  constructor() {
    this.colorPalettes = [
      { border: '#146C36', bg: 'rgba(20, 108, 54, 0.45)', name: 'CLSU Forest Green' },
      { border: '#F59E0B', bg: 'rgba(245, 158, 11, 0.45)', name: 'CLSU Harvest Gold' },
      { border: '#0D9488', bg: 'rgba(13, 148, 136, 0.45)', name: 'Teal Green' },
      { border: '#10B981', bg: 'rgba(16, 185, 129, 0.45)', name: 'Emerald' },
      { border: '#D97706', bg: 'rgba(217, 119, 6, 0.45)', name: 'Amber Gold' },
      { border: '#2563EB', bg: 'rgba(37, 99, 235, 0.45)', name: 'Institutional Blue' }
    ];
  }

  /**
   * Analyze scanned file data and generate graph visualization drafts & suggestions
   */
  generateGraphDrafts(fileData) {
    const drafts = [];

    // Case 1: Excel / Spreadsheet Data
    if (fileData.type === 'excel' && fileData.sheetsData) {
      Object.keys(fileData.sheetsData).forEach(sheetName => {
        const sheet = fileData.sheetsData[sheetName];
        if (sheet.headers && sheet.rows && sheet.rows.length > 0) {
          const sheetDrafts = this.analyzeTableForGraphs(sheet.headers, sheet.rows, `Spreadsheet: ${sheetName}`);
          drafts.push(...sheetDrafts);
        }
      });
    }

    // Case 2: Numbers detected in text (Docx, PDF, Image OCR)
    if (drafts.length === 0 && fileData.rawText) {
      const textDrafts = this.extractNumbersFromText(fileData.rawText, fileData.name);
      drafts.push(...textDrafts);
    }

    // Fallback: If no numerical table was automatically detected, create a summary draft
    if (drafts.length === 0) {
      drafts.push(this.createFallbackMetricDraft(fileData));
    }

    return drafts;
  }

  /**
   * Analyze headers and row data to build smart chart drafts
   */
  analyzeTableForGraphs(headers, rows, sourceLabel) {
    const drafts = [];
    if (!headers || headers.length < 2 || !rows || rows.length === 0) return drafts;

    // Identify string/label column and numeric columns
    let labelColIndex = -1;
    const numericColIndices = [];

    headers.forEach((h, colIdx) => {
      let numCount = 0;
      let strCount = 0;
      const sampleSize = Math.min(rows.length, 30);

      for (let r = 0; r < sampleSize; r++) {
        const cell = rows[r] ? rows[r][colIdx] : null;
        if (typeof cell === 'number' || (!isNaN(parseFloat(cell)) && isFinite(cell))) {
          numCount++;
        } else if (cell && String(cell).trim().length > 0) {
          strCount++;
        }
      }

      if (numCount >= sampleSize * 0.5) {
        numericColIndices.push(colIdx);
      } else if (labelColIndex === -1 && strCount >= sampleSize * 0.4) {
        labelColIndex = colIdx;
      }
    });

    if (labelColIndex === -1) labelColIndex = 0;

    if (numericColIndices.length > 0) {
      const slicedRows = rows.slice(0, 15);
      const labels = slicedRows.map(r => String(r[labelColIndex] || 'Item').trim());

      numericColIndices.slice(0, 4).forEach((numColIdx, datasetIndex) => {
        const headerName = headers[numColIdx] || `Metric ${datasetIndex + 1}`;
        const rankSemantic = window.ChartMapping?.isRankField?.(headerName) === true;
        const parseValue = rankSemantic ? window.ChartMapping.parseRankValue : value => parseFloat(value);
        const dataValues = slicedRows.map(r => {
          const val = parseValue(r[numColIdx]);
          return val === null || !Number.isFinite(val) ? 0 : val;
        });
        const rankValueMin = rankSemantic ? Math.min(...dataValues) : undefined;
        const rankValueMax = rankSemantic ? Math.max(...dataValues) : undefined;

        const palette = this.colorPalettes[datasetIndex % this.colorPalettes.length];

        // Determine recommended graph type
        let primaryType = 'bar';
        let recommendation = 'Bar Chart recommended to compare distinct categories side-by-side.';

        const temporalKeywords = ['year', 'month', 'date', 'quarter', 'semester', 'time', 'day', 'period', 'yr'];
        const isTemporal = temporalKeywords.some(kw => String(headers[labelColIndex]).toLowerCase().includes(kw));

        const percentageKeywords = ['%', 'percent', 'share', 'ratio', 'distribution', 'rate', 'portion'];
        const isPercentage = percentageKeywords.some(kw => headerName.toLowerCase().includes(kw));

        if (isTemporal) {
          primaryType = 'line';
          recommendation = 'Line Chart recommended to observe chronological trends over time.';
        } else if (isPercentage && labels.length <= 7) {
          primaryType = 'pie';
          recommendation = 'Pie Chart recommended to visualize proportional composition.';
        }

        drafts.push({
          id: `draft_${Date.now()}_${datasetIndex}`,
          title: `${headerName} — ${sourceLabel}`,
          source: sourceLabel,
          primaryType,
          recommendation,
          isDraft: true,
          chartData: {
            labels,
            rankSemantic,
            rankValueMin,
            rankValueMax,
            valueAxisMin: rankSemantic ? 0 : undefined,
            valueAxisMax: rankSemantic ? rankValueMax - rankValueMin : undefined,
            datasets: [{
              label: headerName,
              data: dataValues,
              backgroundColor: primaryType === 'pie' ? this.colorPalettes.map(c => c.bg) : palette.bg,
              borderColor: primaryType === 'pie' ? this.colorPalettes.map(c => c.border) : palette.border,
              borderWidth: 2,
              tension: 0.35,
              fill: primaryType === 'line'
            }]
          }
        });
      });
    }

    return drafts;
  }

  /**
   * Extract numbers and entities from unstructured document text
   */
  extractNumbersFromText(text, docName) {
    const drafts = [];
    const pattern = /([A-Za-z\s\(\)\-\/]{3,35})\s*[:\-\=]\s*([0-9\.,]+)\s*([%\w]*)/g;
    const matches = [];
    let match;

    while ((match = pattern.exec(text)) !== null && matches.length < 12) {
      const label = match[1].trim();
      const val = parseFloat(match[2].replace(/,/g, ''));
      const unit = match[3] ? match[3].trim() : '';

      if (!isNaN(val) && label.length > 2) {
        matches.push({ label: `${label} ${unit ? `(${unit})` : ''}`, val });
      }
    }

    if (matches.length >= 2) {
      const labels = matches.map(m => m.label);
      const data = matches.map(m => m.val);
      drafts.push({
        id: `draft_text_${Date.now()}`,
        title: `Key Extracted Metrics — ${docName}`,
        source: 'Document Text & Key Values',
        primaryType: matches.length <= 6 ? 'pie' : 'bar',
        recommendation: matches.length <= 6 ? 'Pie/Doughnut Chart recommended for key metric breakdown.' : 'Bar Chart recommended for metric comparisons.',
        isDraft: true,
        chartData: {
          labels,
          datasets: [{
            label: 'Extracted Value',
            data,
            backgroundColor: this.colorPalettes.map(c => c.bg),
            borderColor: this.colorPalettes.map(c => c.border),
            borderWidth: 2,
            tension: 0.3
          }]
        }
      });
    }

    return drafts;
  }

  /**
   * Fallback draft summarizing document structure stats
   */
  createFallbackMetricDraft(fileData) {
    const rawLen = fileData.rawText ? fileData.rawText.length : 0;
    const wordCount = fileData.rawText ? fileData.rawText.split(/\s+/).filter(Boolean).length : 0;
    const linesCount = fileData.rawText ? fileData.rawText.split('\n').filter(Boolean).length : 0;

    return {
      id: `draft_metric_${Date.now()}`,
      title: `Document Content Metrics — ${fileData.name}`,
      source: 'Document Structural Properties',
      primaryType: 'bar',
      recommendation: 'Baseline Bar chart summarizing document content volume and density.',
      isDraft: true,
      chartData: {
        labels: ['Word Count (/10)', 'Character Length (/100)', 'Text Lines', 'Sections Detected'],
        datasets: [{
          label: 'Metric Value',
          data: [
            Math.round(wordCount / 10),
            Math.round(rawLen / 100),
            linesCount,
            Math.max(1, Math.round(linesCount / 8))
          ],
          backgroundColor: 'rgba(139, 92, 246, 0.45)',
          borderColor: '#8B5CF6',
          borderWidth: 2
        }]
      }
    };
  }
}

if (typeof window !== 'undefined') {
  window.GraphEngine = GraphEngine;
}

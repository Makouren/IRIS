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
      return drafts;
    }

    // Case 2: Numbers detected in text (Docx, PDF, Image OCR)
    if (drafts.length === 0 && fileData.rawText) {
      const textDrafts = this.extractNumbersFromText(fileData.rawText, fileData.name);
      drafts.push(...textDrafts);
    }

    return drafts;
  }

  /**
   * Analyze headers and row data to build smart chart drafts
   */
  analyzeTableForGraphs(headers, rows, sourceLabel) {
    const drafts = [];
    if (!headers || headers.length < 2 || !rows || rows.length === 0) return drafts;
    const validRows = rows.filter(row => Array.isArray(row) && row.some(value => value !== null && value !== undefined && String(value).trim() !== ''));
    if (!validRows.length) return drafts;

    const columns = headers.map((header, index) => {
      const values = validRows.map(row => row[index]);
      const present = values.filter(value => value !== null && value !== undefined && String(value).trim() !== '');
      const temporalValues = present.map(value => this.parseTemporalValue(value));
      const temporalCount = temporalValues.filter(value => value !== null).length;
      const temporal = present.length > 0 && temporalCount / present.length >= 0.8 && new Set(temporalValues.filter(value => value !== null).map(value => value.order)).size > 1;
      const numericValues = present.map(value => this.parseNumericValue(value));
      const numericCount = numericValues.filter(value => value !== null).length;
      const numeric = present.length > 0 && numericCount / present.length >= 0.6;
      return { header: String(header || `Column ${index + 1}`), index, values, present, temporal, numeric };
    });
    const isIdentifierColumn = column => {
      const header = column.header.toLowerCase().replace(/[_-]+/g, ' ');
      if (/(^|\b)(id|identifier|uuid|guid|student\s*(no|number|id)|learner\s*(no|number|id)|matric(ulation)?\s*(no|number|id)?|registration\s*(no|number|id)|serial\s*(no|number|id)|code|account\s*(no|number))\b/.test(header)) return true;
      const values = column.present.map(value => this.parseNumericValue(value)).filter(value => value !== null);
      if (values.length < 3 || values.length / column.present.length < 0.9) return false;
      const uniqueRatio = new Set(values).size / values.length;
      const allIntegers = values.every(Number.isInteger);
      const largeValues = values.filter(value => Math.abs(value) >= 10000).length / values.length >= 0.8;
      return uniqueRatio >= 0.95 && allIntegers && largeValues;
    };
    const usableNumericColumns = columns.filter(column => column.numeric && !column.temporal && !isIdentifierColumn(column));
    if (!usableNumericColumns.length) return drafts;

    const temporalColumn = columns.find(column => column.temporal && !isIdentifierColumn(column));
    const categoryColumn = temporalColumn || columns.find(column => !column.numeric && !isIdentifierColumn(column) && new Set(column.present.map(value => String(value).trim())).size > 1);
    if (!categoryColumn) return drafts;

    usableNumericColumns.forEach((metric, datasetIndex) => {
      const chartRows = validRows.map((row, sourceIndex) => ({
        sourceIndex,
        label: this.formatCategoryValue(row[categoryColumn.index]),
        order: temporalColumn ? this.parseTemporalValue(row[categoryColumn.index])?.order : sourceIndex,
        value: this.parseNumericValue(row[metric.index])
      })).filter(row => row.label !== '' && row.value !== null);
      if (!chartRows.length) return;
      if (temporalColumn) chartRows.sort((left, right) => left.order - right.order);

      const labels = chartRows.map(row => row.label);
      const values = chartRows.map(row => row.value);
      const total = values.reduce((sum, value) => sum + value, 0);
      const isWhole = values.length > 1 && values.every(value => value >= 0) && total > 0 &&
        (Math.abs(total - 1) <= 0.01 || Math.abs(total - 100) <= 1);
      const primaryType = temporalColumn ? 'line' : isWhole ? 'pie' : 'bar';
      const recommendation = temporalColumn
        ? 'Line Chart recommended because the category values form a chronological sequence.'
        : isWhole
          ? 'Pie Chart recommended because the category values form a complete non-negative whole.'
          : 'Bar Chart recommended to compare numerical values across categories.';
      const headerName = metric.header;
      const palette = this.colorPalettes[datasetIndex % this.colorPalettes.length];

      drafts.push({
        id: `draft_${Date.now()}_${datasetIndex}`,
        title: `${headerName} — ${sourceLabel}`,
        source: sourceLabel,
        primaryType,
        recommendation,
        isDraft: true,
        chartData: this.buildEChartsOption(primaryType, labels, values, headerName, palette)
      });
    });

    return drafts;
  }

  parseNumericValue(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    if (value === null || value === undefined) return null;
    const text = String(value).trim().replace(/,/g, '');
    if (!text || !/^[-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?%?$/i.test(text)) return null;
    const parsed = Number(text.replace(/%$/, ''));
    return Number.isFinite(parsed) ? parsed : null;
  }

  parseTemporalValue(value) {
    if (value instanceof Date && Number.isFinite(value.getTime())) {
      return { order: value.getTime(), label: this.formatCategoryValue(value) };
    }
    if (typeof value === 'number' && Number.isInteger(value) && value >= 1000 && value <= 9999 && value >= 1900 && value <= 2100) {
      return { order: value, label: String(value) };
    }
    if (value === null || value === undefined) return null;
    const text = String(value).trim();
    if (/^(?:19|20|21)\d{2}$/.test(text)) return { order: Number(text), label: text };
    const quarter = text.match(/^(\d{4})\s*[- ]?Q([1-4])$/i) || text.match(/^Q([1-4])\s*[- ]?(\d{4})$/i);
    if (quarter) {
      const year = /^Q/i.test(text) ? Number(quarter[2]) : Number(quarter[1]);
      const quarterNumber = Number(/^Q/i.test(text) ? quarter[1] : quarter[2]);
      return { order: year * 4 + quarterNumber, label: text };
    }
    if (/^(?:19|20|21)\d{2}[-/]\d{1,2}(?:[-/]\d{1,2})?$/.test(text) || /^\d{1,2}[-/](?:\d{1,2})[-/](?:19|20|21)\d{2}$/.test(text)) {
      const timestamp = Date.parse(text);
      if (Number.isFinite(timestamp)) return { order: timestamp, label: this.formatCategoryValue(value) };
    }
    const month = text.match(/^([A-Za-z]+)\s+(\d{4})$/);
    if (month) {
      const timestamp = Date.parse(`${month[1]} 1, ${month[2]}`);
      if (Number.isFinite(timestamp)) return { order: timestamp, label: text };
    }
    return null;
  }

  formatCategoryValue(value) {
    if (value instanceof Date && Number.isFinite(value.getTime())) {
      return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
    }
    return value === null || value === undefined ? '' : String(value).trim();
  }

  buildEChartsOption(type, labels, values, seriesName, palette = this.colorPalettes[0]) {
    const option = {
      color: [palette.border],
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      legend: { data: [seriesName] },
      series: []
    };
    if (type === 'pie' || type === 'doughnut') {
      option.tooltip = { trigger: 'item', formatter: '{b}: {c} ({d}%)' };
      option.legend = { data: labels, type: 'scroll', bottom: 0 };
      option.series = [{
        name: seriesName,
        type: 'pie',
        radius: type === 'doughnut' ? ['45%', '72%'] : '68%',
        data: labels.map((name, index) => ({ name, value: values[index] })),
        label: { show: true, formatter: '{b}: {d}%' },
        itemStyle: { borderColor: '#FFFFFF', borderWidth: 2 }
      }];
      return option;
    }
    option.xAxis = { type: 'category', data: labels, axisLabel: { interval: 0 } };
    option.yAxis = { type: 'value', name: seriesName };
    option.series = [{
      name: seriesName,
      type: type === 'line' ? 'line' : 'bar',
      data: values,
      smooth: type === 'line',
      itemStyle: { color: palette.border },
      lineStyle: type === 'line' ? { color: palette.border, width: 3 } : undefined
    }];
    return option;
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
        primaryType: 'bar',
        recommendation: 'Bar Chart recommended to compare extracted values.',
        isDraft: true,
        chartData: this.buildEChartsOption('bar', labels, data, 'Extracted Value', this.colorPalettes[0])
      });
    }

    return drafts;
  }

}

if (typeof window !== 'undefined') {
  window.GraphEngine = GraphEngine;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = GraphEngine;
}
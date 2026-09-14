/**
 * IRIS AI - Data Graph Visualization Draft & Suggestions Engine
 * Detects numerical patterns in spreadsheets, tables, and document text,
 * generates visualization drafts, and provides AI graph type recommendations.
 */

class GraphEngine {
  constructor() {
    this.colorPalettes = [
      { border: '#8B5CF6', bg: 'rgba(139, 92, 246, 0.45)', name: 'Neon Violet' },
      { border: '#06B6D4', bg: 'rgba(6, 182, 212, 0.45)', name: 'Cyber Cyan' },
      { border: '#10B981', bg: 'rgba(16, 185, 129, 0.45)', name: 'Emerald' },
      { border: '#F59E0B', bg: 'rgba(245, 158, 11, 0.45)', name: 'Amber' },
      { border: '#EC4899', bg: 'rgba(236, 72, 153, 0.45)', name: 'Hot Pink' },
      { border: '#3B82F6', bg: 'rgba(59, 130, 246, 0.45)', name: 'Royal Blue' }
    ];
  }

  /**
   * Analyze scanned file data and generate graph visualization drafts & AI suggestions
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

    // Fallback: If no numerical table was automatically detected, create a synthetic metric draft (e.g. Risk & Text Distribution)
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
      // Limit to top 15 rows for readable chart visualization
      const slicedRows = rows.slice(0, 15);
      const labels = slicedRows.map(r => String(r[labelColIndex] || 'Item').trim());

      numericColIndices.slice(0, 4).forEach((numColIdx, datasetIndex) => {
        const headerName = headers[numColIdx] || `Metric ${datasetIndex + 1}`;
        const dataValues = slicedRows.map(r => {
          const val = parseFloat(r[numColIdx]);
          return isNaN(val) ? 0 : val;
        });

        const palette = this.colorPalettes[datasetIndex % this.colorPalettes.length];

        // Determine recommended graph type
        let primaryType = 'bar';
        let recommendation = 'Bar Chart recommended to compare distinct categories side-by-side.';
        const isTimeSeries = labels.some(l => /jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|q1|q2|q3|q4|202\d|201\d/i.test(l));

        if (isTimeSeries) {
          primaryType = 'line';
          recommendation = 'Line Graph recommended because labels indicate a time-series sequence or trend.';
        } else if (dataValues.length <= 6 && dataValues.every(v => v >= 0)) {
          primaryType = 'doughnut';
          recommendation = 'Doughnut Chart recommended for displaying proportional parts of a whole.';
        }

        drafts.push({
          id: `draft_${Date.now()}_${datasetIndex}`,
          title: `${headerName} Visualization Draft`,
          source: sourceLabel,
          recommendedType: primaryType,
          recommendationReason: recommendation,
          suggestedTypes: ['bar', 'line', 'doughnut', 'radar', 'polarArea'],
          config: {
            type: primaryType,
            data: {
              labels: labels,
              datasets: [{
                label: headerName,
                data: dataValues,
                borderColor: palette.border,
                backgroundColor: primaryType === 'line' ? 'rgba(139, 92, 246, 0.15)' : palette.bg,
                borderWidth: 2,
                borderRadius: 6,
                fill: primaryType === 'line'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { labels: { color: '#E2E8F0', font: { family: 'Outfit' } } },
                title: { display: true, text: `${headerName} Analysis`, color: '#F8FAFC' }
              },
              scales: primaryType !== 'doughnut' && primaryType !== 'radar' ? {
                x: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                y: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(255,255,255,0.08)' } }
              } : {}
            }
          }
        });
      });
    }

    return drafts;
  }

  /**
   * Extract key-value numerical pairs from unstructured text (Docx/PDF/OCR)
   */
  extractNumbersFromText(text, fileName) {
    const drafts = [];
    const kvRegex = /([A-Za-z\s]{3,25})[:=]\s*\$?\s*([0-9]+(?:\.[0-9]+)?)/g;
    let match;
    const labels = [];
    const values = [];

    while ((match = kvRegex.exec(text)) !== null && labels.length < 10) {
      const label = match[1].trim();
      const val = parseFloat(match[2]);
      if (label && !isNaN(val) && val > 0) {
        labels.push(label);
        values.push(val);
      }
    }

    if (labels.length >= 3) {
      drafts.push({
        id: `draft_text_${Date.now()}`,
        title: `Extracted Key Numerical Metrics (${fileName})`,
        source: 'Document Text Parser',
        recommendedType: 'bar',
        recommendationReason: 'Bar Chart recommended for displaying extracted numeric attributes.',
        suggestedTypes: ['bar', 'doughnut', 'line'],
        config: {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: 'Extracted Value',
              data: values,
              backgroundColor: ['#8B5CF6', '#06B6D4', '#10B981', '#F59E0B', '#EC4899', '#3B82F6'],
              borderRadius: 6
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              title: { display: true, text: 'Extracted Numerical Metrics', color: '#F8FAFC' }
            },
            scales: {
              x: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
              y: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(255,255,255,0.08)' } }
            }
          }
        }
      });
    }

    return drafts;
  }

  /**
   * Fallback draft summarizing risk parameters & document stats
   */
  createFallbackMetricDraft(fileData) {
    const rawLen = (fileData.rawText || '').length;
    const words = rawLen ? fileData.rawText.split(/\s+/).length : 0;
    const piiCount = fileData.piiResult ? fileData.piiResult.totalFindings : 0;
    const riskScore = fileData.piiResult ? fileData.piiResult.riskScore : 0;

    return {
      id: `draft_fallback_${Date.now()}`,
      title: `Document Content & Security Distribution`,
      source: 'IRIS AI Intelligence Audit',
      recommendedType: 'radar',
      recommendationReason: 'Radar Chart recommended for comparing multi-variable security and content dimensions.',
      suggestedTypes: ['radar', 'polarArea', 'bar'],
      config: {
        type: 'radar',
        data: {
          labels: ['Word Volume (x100)', 'Risk Score', 'PII Exposure', 'Structure Score', 'Readability Index'],
          datasets: [{
            label: fileData.name,
            data: [
              Math.min(100, Math.round(words / 50)),
              riskScore,
              Math.min(100, piiCount * 20),
              fileData.metadata ? 85 : 40,
              70
            ],
            backgroundColor: 'rgba(6, 182, 212, 0.35)',
            borderColor: '#06B6D4',
            pointBackgroundColor: '#8B5CF6',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: '#E2E8F0' } },
            title: { display: true, text: 'Audit Profile & Metric Dimensions', color: '#F8FAFC' }
          },
          scales: {
            r: {
              angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
              grid: { color: 'rgba(255, 255, 255, 0.1)' },
              pointLabels: { color: '#94A3B8', font: { size: 11 } },
              ticks: { display: false }
            }
          }
        }
      }
    };
  }

  /**
   * Render chart into canvas element using Chart.js
   */
  renderChart(canvasElement, chartConfig) {
    if (!canvasElement || typeof Chart === 'undefined') {
      console.warn('Canvas element or Chart.js missing.');
      return null;
    }

    // Destroy existing instance if attached
    if (canvasElement.chartInstance) {
      canvasElement.chartInstance.destroy();
    }

    const ctx = canvasElement.getContext('2d');
    const chart = new Chart(ctx, JSON.parse(JSON.stringify(chartConfig)));
    canvasElement.chartInstance = chart;
    return chart;
  }
}

if (typeof window !== 'undefined') {
  window.GraphEngine = GraphEngine;
}

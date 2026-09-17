(function (root) {
  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeGraphExportItem(graphData, recordId) {
    const source = graphData || {};
    const chartData = source.chartData || {};
    const labels = Array.isArray(source.labels)
      ? source.labels
      : (Array.isArray(chartData.labels) ? chartData.labels : []);
    const valuesData = Array.isArray(source.values_data)
      ? source.values_data
      : (Array.isArray(source.valuesData) ? source.valuesData : []);

    const numericSeries = Array.isArray(chartData.datasets) && chartData.datasets[0]
      ? (Array.isArray(chartData.datasets[0].data) ? chartData.datasets[0].data : valuesData)
      : valuesData;

    const normalized = {
      record_id: source.record_id || source.recordId || recordId || null,
      title: source.title || source.name || 'Saved Graph Export',
      chart_type: source.chart_type || source.chartType || source.primaryType || 'bar',
      labels: labels.length ? labels : (Array.isArray(chartData.labels) ? chartData.labels : []),
      values_data: numericSeries.length ? numericSeries : (Array.isArray(source.data) ? source.data : [])
    };

    if (!normalized.record_id) {
      throw new Error('Graph export requires a record_id before saving to MySQL.');
    }

    return normalized;
  }

  function buildPrintableGraphSheet(graph, context = {}) {
    const recordName = context.recordName || 'IRIS Report';
    const title = graph.title || 'Saved Graph';
    const chartType = graph.chart_type || graph.chartType || 'bar';
    const labels = Array.isArray(graph.labels) ? graph.labels : [];
    const values = Array.isArray(graph.values_data) ? graph.values_data : [];

    const rows = labels.map((label, index) => `
      <tr>
        <td>${escapeHtml(label || `Item ${index + 1}`)}</td>
        <td>${escapeHtml(values[index] ?? '')}</td>
      </tr>
    `).join('');

    return `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8" />
        <title>${escapeHtml(title)} - Printable Sheet</title>
        <style>
          body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 32px;
          }
          .sheet {
            max-width: 840px;
            margin: 0 auto;
            background: white;
            border: 1px solid #dfe6ee;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            padding: 28px;
          }
          .brand {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 20px;
          }
          .brand strong {
            color: #146c36;
            font-size: 1.3rem;
          }
          .meta {
            color: #475569;
            font-size: 0.9rem;
          }
          h1 {
            margin: 0 0 8px;
            font-size: 2rem;
            color: #0f172a;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
          }
          th, td {
            border: 1px solid #dfe6ee;
            padding: 10px 12px;
            text-align: left;
          }
          th {
            background: #f1f5f9;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
          }
          .actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
          }
          .print-btn {
            background: #146c36;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            cursor: pointer;
            font-weight: 700;
          }
          @media print {
            body { background: white; padding: 0; }
            .sheet { box-shadow: none; border: none; max-width: none; }
            .actions { display: none; }
          }
        </style>
      </head>
      <body>
        <div class="sheet">
          <div class="brand">
            <div>
              <strong>IRIS • CLSU Observatory</strong>
              <div class="meta">${escapeHtml(recordName)}</div>
            </div>
            <div class="meta">Chart Type: ${escapeHtml(chartType.toUpperCase())}</div>
          </div>
          <h1>${escapeHtml(title)}</h1>
          <div class="meta">Printable export created from saved and cleaned graph data.</div>
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th>Value</th>
              </tr>
            </thead>
            <tbody>
              ${rows || '<tr><td colspan="2">No data available for this graph.</td></tr>'}
            </tbody>
          </table>
          <div class="actions">
            <button class="print-btn" onclick="window.print();">Print Sheet</button>
          </div>
        </div>
      </body>
      </html>
    `;
  }

  const api = {
    normalizeGraphExportItem,
    buildPrintableGraphSheet
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }

  root.GraphExport = api;
})(typeof window !== 'undefined' ? window : globalThis);

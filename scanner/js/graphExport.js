  (function (root) {
    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function normalizeChartType(type, fallback = 'bar') {
      if (type === undefined || type === null || String(type).trim() === '') return fallback;
      const normalized = String(type).trim();
      const lower = normalized.toLowerCase();
      if (lower === 'bar' || lower === 'column' || lower === 'vertical' || lower === 'barh' || lower === 'horizontal') return 'bar';
      if (lower === 'line') return 'line';
      if (lower === 'stackedarea' || lower === 'stacked-area') return 'stackedArea';
      if (lower === 'pie') return 'pie';
      if (lower === 'doughnut') return 'doughnut';
      if (lower === 'nestedpie' || lower === 'nested-pie') return 'nestedPie';
      if (lower === 'radar') return 'line';
      return fallback;
    }

    function resolveChartType(graphData, fallback = 'bar') {
      const source = graphData || {};
      const chartData = source.chartData || {};
      const candidates = [
        source.chart_type,
        source.chartType,
        source.primaryType,
        source.type,
        source.series?.[0]?.type,
        chartData.chart_type,
        chartData.chartType,
        chartData.primaryType,
        chartData.type,
        chartData.series?.[0]?.type,
        source.chartData?.series?.[0]?.type,
        source.chartData?.datasets?.[0]?.type,
        source.chartData?.datasets?.[0]?.series?.[0]?.type
      ];
      for (const candidate of candidates) {
        const next = normalizeChartType(candidate, fallback);
        if (next !== fallback || candidate !== undefined) {
          const resolved = normalizeChartType(candidate, fallback);
          if (resolved && resolved !== fallback) return resolved;
        }
      }
      return fallback;
    }

    function chartLabels(chartData) {
      if (Array.isArray(chartData?.labels)) return chartData.labels;
      const axis = Array.isArray(chartData?.xAxis) ? chartData.xAxis[0] : chartData?.xAxis;
      if (Array.isArray(axis?.data)) return axis.data;
      if (Array.isArray(chartData?.angleAxis?.data)) return chartData.angleAxis.data;
      const points = chartData?.series?.[0]?.data;
      return Array.isArray(points) && points.some(point => point && typeof point === 'object' && point.name !== undefined)
        ? points.map(point => point.name)
        : [];
    }

    function chartValues(chartData) {
      if (Array.isArray(chartData?.datasets?.[0]?.data)) return chartData.datasets[0].data;
      const points = chartData?.series?.[0]?.data;
      return Array.isArray(points) ? points.map(point => point && typeof point === 'object' ? point.value : point) : [];
    }

    function formatChartTypeLabel(chartType) {
      const normalized = resolveChartType({ chart_type: chartType }, 'bar');
      const labels = {
        pie: 'pie chart',
        doughnut: 'Doughnut chart',
        stackedArea: 'Stacked Area chart',
        nestedPie: 'Nested Pie chart',
        line: 'Line chart',
        bar: 'Bar chart'
      };
      return labels[normalized] || `${String(normalized).charAt(0).toUpperCase()}${String(normalized).slice(1)} chart`;
    }

    function readChartConfig(rawConfig) {
      if (!rawConfig || typeof rawConfig !== 'object') return {};
      if (Array.isArray(rawConfig)) return rawConfig[0] && typeof rawConfig[0] === 'object' ? rawConfig[0] : {};
      if (rawConfig.chartData && typeof rawConfig.chartData === 'object') return rawConfig.chartData;
      if (rawConfig.option && typeof rawConfig.option === 'object') return rawConfig.option;
      return rawConfig;
    }

    function buildSavedChartOption(graph, options = {}) {
      return root.IRISChartBuilder?.buildSavedGraphOption(graph, {
        width: options.width || 0,
        theme: options.theme || { dark: typeof document !== 'undefined' && document.documentElement?.classList?.contains('dark') },
        colors: options.colors,
        fieldColors: options.fieldColors || root.IRISFieldColors || {}
      }) || {};
    }

    function normalizeGraphExportItem(graphData, recordId) {
      const source = graphData || {};
      const chartData = readChartConfig(source.chart_data || source.chartData || {});
      const labels = Array.isArray(source.labels) ? source.labels : chartLabels(chartData);
      const valuesData = Array.isArray(source.values_data)
        ? source.values_data
        : (Array.isArray(source.valuesData) ? source.valuesData : []);

      const numericSeries = Array.isArray(source.values_data) || Array.isArray(source.valuesData)
        ? valuesData
        : ((chartData.datasets?.[0] || chartData.series?.[0]) ? chartValues(chartData) : (Array.isArray(source.data) ? source.data : []));

      const normalized = {
        record_id: source.record_id || source.recordId || recordId || null,
        title: source.title || source.name || 'Saved Graph Export',
        chart_type: resolveChartType({ ...source, chartData }, 'bar'),
        rankSemantic: source.rankSemantic === true || source.rank_semantic === true || source.rank_semantic === 1 || chartData.rankSemantic === true,
        rankValueMin: source.rankValueMin ?? chartData.rankValueMin,
        rankValueMax: source.rankValueMax ?? chartData.rankValueMax,
        orientation: source.orientation || chartData.irisConfig?.orientation || 'vertical',
        valueAxisReversed: source.valueAxisReversed ?? source.value_axis_reversed ?? chartData.irisConfig?.reverseOrder ?? false,
        valueAxisMin: source.valueAxisMin ?? source.value_axis_min ?? chartData.irisConfig?.valueAxisMin,
        valueAxisMax: source.valueAxisMax ?? source.value_axis_max ?? chartData.irisConfig?.valueAxisMax,
        labels: labels.length ? labels : (Array.isArray(chartData.labels) ? chartData.labels : []),
        values_data: numericSeries.length ? numericSeries : (Array.isArray(source.data) ? source.data : []),
        colors: Array.isArray(source.colors) && source.colors.every(color => typeof color === 'string' && /^#[0-9A-Fa-f]{6}$/.test(color)) ? source.colors : null,
        chart_data: chartData && Object.keys(chartData).length ? chartData : undefined
      };

      if (!normalized.record_id) {
        throw new Error('Graph export requires a record_id before saving to MySQL.');
      }

      return normalized;
    }

    function buildPrintableGraphSheet(graph, context = {}) {
      const recordName = context.recordName || 'IRIS Report';
      const title = graph.title || 'Saved Graph';
      const chartType = resolveChartType(graph, 'bar');
      const chartData = graph.chart_data || graph.chartData || {};
      const rankSemantic = graph.rank_semantic === 1 || graph.rank_semantic === true || graph.rankSemantic === true || chartData.rankSemantic === true;
      const labels = Array.isArray(graph.labels) ? graph.labels : chartLabels(chartData);
      const values = Array.isArray(graph.values_data) ? graph.values_data : chartValues(chartData);
      const nestedGroups = chartData.irisConfig?.nestedGroups || [];
      const nestedRows = nestedGroups.flatMap(group => group.children.map(child => ({ group: group.label, label: child.label, value: child.rawValue ?? child.value })));

      const rows = chartType === 'nestedPie' && nestedRows.length ? nestedRows.map(row => `
        <tr><td>${escapeHtml(row.group)}</td><td>${escapeHtml(row.label)}</td><td>${escapeHtml(row.value ?? '')}</td></tr>
      `).join('') : labels.map((label, index) => `
        <tr>
          <td>${escapeHtml(label || `Item ${index + 1}`)}</td>
          <td>${escapeHtml(values[index] ?? '')}</td>
        </tr>
      `).join('');

      function buildChartSvg() {
        const width = 760;
        const height = 320;
        const colors = ['#146C36', '#F59E0B', '#0D9488', '#10B981', '#D97706', '#2563EB'];
        const rankValues = rankSemantic ? values.map(value => root.ChartMapping?.parseRankValue?.(value) ?? (Number(value) || 0)) : [];
        const rankMaximum = rankSemantic ? Number(graph.rank_value_max ?? graph.rankValueMax ?? Math.max(...rankValues, 0)) : 0;
        const numericValues = rankSemantic ? rankValues.map(value => rankMaximum - value) : values.map(value => Number(value) || 0);
        const maxValue = Math.max(...numericValues, 1);
        const safeType = String(chartType).toLowerCase();
        const chartLabel = formatChartTypeLabel(chartType);

        if (safeType === 'pie' || safeType === 'doughnut') {
          const total = numericValues.reduce((sum, value) => sum + Math.max(value, 0), 0) || 1;
          const centerX = 220;
          const centerY = 160;
          const radius = 105;
          let angle = -Math.PI / 2;
          const slices = numericValues.map((value, index) => {
            const nextAngle = angle + (Math.max(value, 0) / total) * Math.PI * 2;
            const largeArc = nextAngle - angle > Math.PI ? 1 : 0;
            const startX = centerX + radius * Math.cos(angle);
            const startY = centerY + radius * Math.sin(angle);
            const endX = centerX + radius * Math.cos(nextAngle);
            const endY = centerY + radius * Math.sin(nextAngle);
            const path = `M ${centerX} ${centerY} L ${startX} ${startY} A ${radius} ${radius} 0 ${largeArc} 1 ${endX} ${endY} Z`;
            angle = nextAngle;
            return `<path d="${path}" fill="${colors[index % colors.length]}" stroke="#ffffff" stroke-width="2"><title>${escapeHtml(labels[index] || `Item ${index + 1}`)}: ${escapeHtml(value)}</title></path>`;
          }).join('');
          const legend = labels.map((label, index) => `<g transform="translate(390 ${55 + index * 28})"><rect width="14" height="14" fill="${colors[index % colors.length]}"/><text x="22" y="12" font-size="13" fill="#334155">${escapeHtml(label || `Item ${index + 1}`)}: ${escapeHtml(numericValues[index])}</text></g>`).join('');
          return `<svg class="chart-preview" viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeHtml(chartLabel)}">${slices}${safeType === 'doughnut' ? '<circle cx="220" cy="160" r="52" fill="white"/>' : ''}${legend}</svg>`;
        }

        const left = 55;
        const bottom = 265;
        const plotWidth = 670;
        const plotHeight = 210;
        const step = numericValues.length > 1 ? plotWidth / (numericValues.length - 1) : plotWidth;
        const labelsSvg = labels.map((label, index) => `<text x="${left + step * index}" y="292" text-anchor="middle" font-size="11" fill="#475569">${escapeHtml(String(label || `Item ${index + 1}`).slice(0, 16))}</text>`).join('');
        const grid = [0, 0.5, 1].map(ratio => `<line x1="${left}" y1="${bottom - plotHeight * ratio}" x2="${left + plotWidth}" y2="${bottom - plotHeight * ratio}" stroke="#E2E8F0"/><text x="8" y="${bottom - plotHeight * ratio + 4}" font-size="11" fill="#64748B">${rankSemantic ? Math.round(rankMaximum - maxValue * ratio) : Math.round(maxValue * ratio)}</text>`).join('');
        if (safeType === 'line') {
          const points = numericValues.map((value, index) => `${left + step * index},${bottom - (value / maxValue) * plotHeight}`).join(' ');
          const dots = numericValues.map((value, index) => `<circle cx="${left + step * index}" cy="${bottom - (value / maxValue) * plotHeight}" r="4" fill="#146C36"/>`).join('');
          return `<svg class="chart-preview" viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeHtml(chartLabel)}">${grid}<polyline points="${points}" fill="none" stroke="#146C36" stroke-width="3"/>${dots}${labelsSvg}</svg>`;
        }
        const barWidth = Math.min(52, plotWidth / Math.max(numericValues.length, 1) * 0.65);
        const bars = numericValues.map((value, index) => { const x = left + (plotWidth / Math.max(numericValues.length, 1)) * index + 12; const barHeight = (value / maxValue) * plotHeight; return `<rect x="${x}" y="${bottom - barHeight}" width="${barWidth}" height="${barHeight}" fill="#146C36"><title>${escapeHtml(labels[index] || `Item ${index + 1}`)}: ${escapeHtml(value)}</title></rect>`; }).join('');
        return `<svg class="chart-preview" viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeHtml(chartLabel)}">${grid}${bars}${labelsSvg}</svg>`;
      }

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
            .chart-preview {
              display: block;
              width: 100%;
              height: 320px;
              margin: 20px 0 24px;
              background: #ffffff;
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
              <div class="meta">Chart Type: ${escapeHtml(String(chartType).toUpperCase())}</div>
            </div>
            <h1>${escapeHtml(title)}</h1>
            <div class="meta">Printable export created from saved and cleaned graph data.</div>
            ${buildChartSvg()}
            <table>
              <thead>
                <tr>
                  ${chartType === 'nestedPie' && nestedRows.length ? '<th>Group</th>' : ''}
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

    function buildPrintableGraphSheets(graphs, context = {}) {
      const sheets = (graphs || []).map(graph => {
        const recordName = typeof context.recordNameForGraph === 'function'
          ? context.recordNameForGraph(graph)
          : (context.recordName || 'IRIS Report');
        const document = new DOMParser().parseFromString(buildPrintableGraphSheet(graph, { recordName }), 'text/html');
        const sheet = document.querySelector('.sheet');
        const actions = sheet?.querySelector('.actions');
        if (actions) actions.remove();
        return sheet ? sheet.outerHTML : '';
      }).filter(Boolean).join('\n');
      const template = new DOMParser().parseFromString(buildPrintableGraphSheet({}, context), 'text/html');
      const style = template.querySelector('style')?.outerHTML || '';

      return `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8" /><title>IRIS Saved Graphs - Printable Sheets</title>${style}<style>.sheet { margin-bottom: 32px; page-break-after: always; } .sheet:last-child { page-break-after: auto; }</style></head><body>${sheets}<div class="actions"><button class="print-btn" onclick="window.print();">Print All</button></div></body></html>`;
    }

    const api = {
      normalizeGraphExportItem,
      buildSavedChartOption,
      buildPrintableGraphSheet,
      buildPrintableGraphSheets
    };

    if (typeof module !== 'undefined' && module.exports) {
      module.exports = api;
    }

    root.GraphExport = api;
  })(typeof window !== 'undefined' ? window : globalThis);

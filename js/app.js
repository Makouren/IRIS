/**
 * IRIS AI - Main Web Application Controller & User Interface Core
 * Clean, lightweight, focused on AI File Scanning, Draft Chart Suggestions, and Admin Data Editor.
 */

document.addEventListener('DOMContentLoaded', async () => {
  // Initialize Core Services
  const scanner = new ScannerOrchestrator();
  const dbManager = scanner.dbManager;

  // State Management
  let queue = [];
  let activeScan = null;
  let chartInstances = {};

  // DOM Elements - Navigation & Views
  const navScannerBtn = document.getElementById('navScannerBtn');
  const navAdminBtn = document.getElementById('navAdminBtn');
  const scannerWorkspaceView = document.getElementById('scannerWorkspaceView');
  const adminDatabaseView = document.getElementById('adminDatabaseView');

  // DOM Elements - Dropzone & Progress
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const btnBrowse = document.getElementById('btnBrowse');
  const progressCard = document.getElementById('progressCard');
  const progressStatus = document.getElementById('progressStatus');
  const progressPercent = document.getElementById('progressPercent');
  const progressFill = document.getElementById('progressFill');

  // DOM Elements - Workspace & Tabs
  const workspaceGrid = document.getElementById('workspaceGrid');
  const queueCount = document.getElementById('queueCount');
  const queueList = document.getElementById('queueList');
  const btnClearQueue = document.getElementById('btnClearQueue');
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabPanels = document.querySelectorAll('.tab-panel');

  // DOM Elements - Overview Tab
  const summaryDocTitle = document.getElementById('summaryDocTitle');
  const docFormatBadge = document.getElementById('docFormatBadge');
  const executiveSummaryText = document.getElementById('executiveSummaryText');
  const extractedFieldsGrid = document.getElementById('extractedFieldsGrid');
  const takeawayList = document.getElementById('takeawayList');
  const btnOpenInEditor = document.getElementById('btnOpenInEditor');
  const draftsCountBadge = document.getElementById('draftsCountBadge');

  // DOM Elements - Viewer Tab
  const viewerContentArea = document.getElementById('viewerContentArea');
  const viewerFileMeta = document.getElementById('viewerFileMeta');
  const sheetSelectorContainer = document.getElementById('sheetSelectorContainer');
  const sheetSelect = document.getElementById('sheetSelect');

  // DOM Elements - Graphs Tab
  const graphDraftsContainer = document.getElementById('graphDraftsContainer');

  // DOM Elements - Admin Portal
  const statTotalDb = document.getElementById('statTotalDb');
  const statPendingDb = document.getElementById('statPendingDb');
  const statVerifiedDb = document.getElementById('statVerifiedDb');
  const statTablesDb = document.getElementById('statTablesDb');
  const adminSearchInput = document.getElementById('adminSearchInput');
  const adminStatusFilter = document.getElementById('adminStatusFilter');
  const adminRecordsTableBody = document.getElementById('adminRecordsTableBody');

  // Record Edit Modal
  const recordEditModal = document.getElementById('recordEditModal');
  const btnCloseRecordModal = document.getElementById('btnCloseRecordModal');
  const recordEditBody = document.getElementById('recordEditBody');
  const recordEditTitle = document.getElementById('recordEditTitle');

  // ----------------------------------------------------
  // Navigation View Switching
  // ----------------------------------------------------
  navScannerBtn.addEventListener('click', () => {
    navScannerBtn.classList.add('active');
    navAdminBtn.classList.remove('active');
    scannerWorkspaceView.style.display = 'block';
    adminDatabaseView.style.display = 'none';
  });

  navAdminBtn.addEventListener('click', async () => {
    navAdminBtn.classList.add('active');
    navScannerBtn.classList.remove('active');
    scannerWorkspaceView.style.display = 'none';
    adminDatabaseView.style.display = 'block';
    await renderAdminPortal();
  });

  // ----------------------------------------------------
  // Dropzone & File Input Handlers
  // ----------------------------------------------------
  btnBrowse.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', (e) => {
    if (e.target.files && e.target.files.length > 0) {
      handleFiles(Array.from(e.target.files));
    }
  });

  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
    });
  });

  dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
      handleFiles(Array.from(dt.files));
    }
  });

  // Sample Buttons Listener
  document.querySelectorAll('.sample-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const sampleType = btn.getAttribute('data-sample');
      let sampleFile = null;

      if (sampleType === 'payroll') {
        sampleFile = SampleGenerator.createSampleExcelFile();
      } else if (sampleType === 'contract') {
        sampleFile = SampleGenerator.createSampleDocxFile();
      } else if (sampleType === 'pdf') {
        sampleFile = SampleGenerator.createSamplePdfFile();
      }

      if (sampleFile) {
        handleFiles([sampleFile]);
      }
    });
  });

  // ----------------------------------------------------
  // Batch File Ingestion Pipeline
  // ----------------------------------------------------
  async function handleFiles(files) {
    if (!files || files.length === 0) return;

    progressCard.style.display = 'block';
    workspaceGrid.style.display = 'grid';

    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      try {
        updateProgress(`Scanning ${file.name} (${i + 1}/${files.length})...`, 10);

        const scanResult = await scanner.scanFile(file, (progressObj) => {
          updateProgress(progressObj.status, progressObj.progress);
        });

        queue.unshift(scanResult);
        renderQueue();
        setActiveScan(scanResult);

      } catch (err) {
        console.error('Scan Error:', err);
        alert(`Failed to scan file ${file.name}: ${err.message}`);
      }
    }

    setTimeout(() => {
      progressCard.style.display = 'none';
      updateProgress('Scan complete!', 100);
    }, 800);
  }

  function updateProgress(statusText, percent) {
    progressStatus.textContent = statusText;
    progressPercent.textContent = `${percent}%`;
    progressFill.style.width = `${percent}%`;
  }

  // ----------------------------------------------------
  // Workspace Sidebar Queue
  // ----------------------------------------------------
  function renderQueue() {
    queueCount.textContent = queue.length;
    queueList.innerHTML = '';

    queue.forEach(item => {
      const div = document.createElement('div');
      div.className = `queue-item ${activeScan && activeScan.id === item.id ? 'active' : ''}`;
      
      const typeIcons = {
        excel: '📊',
        pdf: '📄',
        docx: '📝',
        image: '🖼️',
        unknown: '📁'
      };
      const icon = typeIcons[item.type] || '📁';

      div.innerHTML = `
        <div class="queue-icon">${icon}</div>
        <div class="queue-info">
          <div class="queue-name" title="${item.name}">${item.name}</div>
          <div class="queue-meta">
            <span>${(item.size / 1024).toFixed(1)} KB</span>
            <span class="queue-badge low">Draft</span>
          </div>
        </div>
      `;

      div.addEventListener('click', () => setActiveScan(item));
      queueList.appendChild(div);
    });
  }

  btnClearQueue.addEventListener('click', () => {
    queue = [];
    activeScan = null;
    workspaceGrid.style.display = 'none';
    renderQueue();
  });

  // ----------------------------------------------------
  // Set Active Scan & Render Tabs
  // ----------------------------------------------------
  function setActiveScan(scan) {
    activeScan = scan;
    renderQueue();

    // 1. Render Overview Tab
    renderOverviewTab(scan);

    // 2. Render Viewer Tab
    renderViewerTab(scan);

    // 3. Render Graph Drafts Tab
    renderGraphsTab(scan);
  }

  // TAB 1: Overview & Fields Renderer
  function renderOverviewTab(scan) {
    summaryDocTitle.textContent = scan.name;
    docFormatBadge.textContent = scan.type.toUpperCase();
    docFormatBadge.className = `format-chip ${scan.type}`;

    const rawLen = scan.rawText ? scan.rawText.length : 0;
    const words = scan.rawText ? scan.rawText.split(/\s+/).filter(Boolean).length : 0;

    executiveSummaryText.textContent = `Document parsed successfully. Identified ${words} words, ${rawLen} characters, with ${(scan.graphDrafts || []).length} draft visualization suggestions.`;

    // Render Extracted Fields Grid
    extractedFieldsGrid.innerHTML = '';
    const fields = extractKeyFields(scan);
    
    if (fields.length > 0) {
      fields.forEach(f => {
        const card = document.createElement('div');
        card.style.cssText = 'background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 0.75rem 1rem;';
        card.innerHTML = `
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">${f.label}</div>
          <div style="font-size: 1.1rem; font-weight: 800; color: var(--accent-cyan); font-family: var(--font-mono);">${f.value}</div>
        `;
        extractedFieldsGrid.appendChild(card);
      });
    } else {
      extractedFieldsGrid.innerHTML = '<div style="color: var(--text-dim); font-size: 0.85rem;">No discrete tabular fields identified.</div>';
    }

    // Render Structure Takeaways
    takeawayList.innerHTML = '';
    const takeaways = generateTakeaways(scan);
    takeaways.forEach(t => {
      const li = document.createElement('li');
      li.className = 'takeaway-item';
      li.innerHTML = `<span>🔹</span> <div>${t}</div>`;
      takeawayList.appendChild(li);
    });

    draftsCountBadge.textContent = (scan.graphDrafts || []).length;
  }

  function extractKeyFields(scan) {
    const fields = [];

    if (scan.type === 'excel' && scan.sheetsData) {
      Object.keys(scan.sheetsData).forEach(sheetName => {
        const s = scan.sheetsData[sheetName];
        fields.push({ label: `Sheet: ${sheetName}`, value: `${s.rows ? s.rows.length : 0} Rows` });
        if (s.headers) {
          s.headers.slice(0, 3).forEach(h => {
            if (h) fields.push({ label: 'Column Header', value: h });
          });
        }
      });
    } else if (scan.rawText) {
      const pattern = /([A-Za-z\s\(\)\-\/]{3,30})\s*[:\-\=]\s*([0-9\.,]+%?)/g;
      let m;
      let count = 0;
      while ((m = pattern.exec(scan.rawText)) !== null && count < 6) {
        fields.push({ label: m[1].trim(), value: m[2].trim() });
        count++;
      }
    }

    if (fields.length === 0) {
      fields.push({ label: 'Format Type', value: scan.type.toUpperCase() });
      fields.push({ label: 'File Size', value: `${(scan.size / 1024).toFixed(1)} KB` });
    }

    return fields;
  }

  function generateTakeaways(scan) {
    const items = [];
    items.push(`Source Format: <strong>${scan.type.toUpperCase()}</strong> (${(scan.size / 1024).toFixed(1)} KB).`);
    
    if (scan.type === 'excel' && scan.sheetsData) {
      const sheetCount = Object.keys(scan.sheetsData).length;
      items.push(`Multi-sheet spreadsheet containing <strong>${sheetCount} worksheet(s)</strong>.`);
    } else if (scan.type === 'pdf') {
      items.push(`Layout-aware PDF parsing completed with structured stat-card extraction.`);
    } else if (scan.type === 'docx') {
      items.push(`Word document paragraphs and embedded media unpacked.`);
    } else if (scan.type === 'image') {
      items.push(`Optical Character Recognition (OCR) extracted text and metric indicators.`);
    }

    items.push(`Draft status assigned as <strong>Pending Admin Review</strong> before publication to ECharts dashboard.`);
    return items;
  }

  btnOpenInEditor.addEventListener('click', () => {
    if (activeScan) {
      navAdminBtn.click();
      openRecordEditModal(activeScan.id);
    }
  });

  // TAB 2: Viewer Tab Renderer
  function renderViewerTab(scan) {
    viewerFileMeta.textContent = `${scan.name} • ${scan.type.toUpperCase()} • ${(scan.size / 1024).toFixed(1)} KB`;
    viewerContentArea.innerHTML = '';
    sheetSelectorContainer.style.display = 'none';

    if (scan.type === 'image' && scan.previewUrl) {
      const img = document.createElement('img');
      img.src = scan.previewUrl;
      img.style.maxWidth = '100%';
      img.style.borderRadius = 'var(--radius-md)';
      img.style.border = '1px solid var(--border-light)';
      viewerContentArea.appendChild(img);

    } else if (scan.type === 'excel' && scan.sheetsData) {
      sheetSelectorContainer.style.display = 'flex';
      sheetSelect.innerHTML = '';

      Object.keys(scan.sheetsData).forEach(sheetName => {
        const opt = document.createElement('option');
        opt.value = sheetName;
        opt.textContent = sheetName;
        sheetSelect.appendChild(opt);
      });

      function displaySheet(sheetName) {
        const sheet = scan.sheetsData[sheetName];
        if (!sheet) return;

        let html = '<div class="table-container" style="max-height: 500px;"><table class="data-table"><thead><tr>';
        (sheet.headers || []).forEach(h => {
          html += `<th>${h || ''}</th>`;
        });
        html += '</tr></thead><tbody>';

        (sheet.rows || []).slice(0, 50).forEach(row => {
          html += '<tr>';
          (row || []).forEach(cell => {
            html += `<td>${cell !== null && cell !== undefined ? cell : ''}</td>`;
          });
          html += '</tr>';
        });

        html += '</tbody></table></div>';
        viewerContentArea.innerHTML = html;
      }

      sheetSelect.onchange = (e) => displaySheet(e.target.value);
      displaySheet(sheetSelect.value);

    } else if (scan.type === 'docx' && scan.docxBuffer && typeof DocxViewerComponent !== 'undefined') {
      // Rich DOCX preview via docx-preview (fonts, tables, images, page breaks)
      const docxViewerWrap = document.createElement('div');
      docxViewerWrap.style.cssText = 'width:100%;height:100%;min-height:400px;';
      viewerContentArea.appendChild(docxViewerWrap);

      const docxViewer = new DocxViewerComponent(docxViewerWrap, { showToolbar: true });
      docxViewer.loadDocument(scan.docxBuffer, scan.name || 'document.docx');

    } else if (scan.formattedHtml) {
      // Fallback: mammoth.js HTML dump (legacy)
      const div = document.createElement('div');
      div.className = 'docx-reader-container';
      div.innerHTML = scan.formattedHtml;
      viewerContentArea.appendChild(div);

    } else {
      const pre = document.createElement('pre');
      pre.style.cssText = 'background: rgba(15, 23, 42, 0.7); padding: 1.25rem; border-radius: var(--radius-md); font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-light); overflow-x: auto; white-space: pre-wrap;';
      pre.textContent = scan.rawText || 'No readable text extracted.';
      viewerContentArea.appendChild(pre);
    }
  }

  // TAB 3: Graph Drafts Tab Renderer
  function renderGraphsTab(scan) {
    graphDraftsContainer.innerHTML = '';
    
    // Dispose previous chart instances before rebuilding the analyst controls.
    Object.values(chartInstances).forEach(c => c && c.dispose && c.dispose());
    chartInstances = {};

    const drafts = scan.graphDrafts || [];

    if (drafts.length === 0) {
      graphDraftsContainer.innerHTML = '<div style="color: var(--text-muted); padding: 2rem; text-align: center;">No numerical series detected to build chart drafts.</div>';
      return;
    }

    const tables = Object.entries(scan.sheetsData || {}).filter(([, table]) => table && table.headers && table.rows);

    drafts.forEach((draft, idx) => {
      const card = document.createElement('div');
      card.className = 'graph-card';
      const chartId = `chart_canvas_${idx}`;
      const defaultTableIndex = Math.max(0, tables.findIndex(([, table]) => table.headers.includes(draft.chartData && draft.chartData.xAxis)));

      card.innerHTML = `
        <div class="graph-card-header">
          <div>
            <div class="graph-card-title">${draft.title}</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">Source: ${draft.source}</div>
          </div>
          <span class="badge badge-low">Analyst Draft</span>
        </div>

        <div style="font-size: 0.82rem; color: var(--accent-cyan); margin-bottom: 1rem;">
          💡 <strong>AI Recommendation:</strong> ${draft.recommendation}
        </div>

        <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-3" data-chart-controls="${idx}">
          ${createChartSelect(`chart-table-${idx}`, 'Source table', tables.map(([name], tableIndex) => ({ value: tableIndex, label: name })), defaultTableIndex, 'chart-table-select')}
          <label class="block text-sm font-medium text-slate-700">Chart type
            <select data-draft-idx="${idx}" class="form-input chart-type-select mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600">
              <option value="bar" ${draft.primaryType === 'bar' ? 'selected' : ''}>Bar</option>
              <option value="line" ${draft.primaryType === 'line' ? 'selected' : ''}>Line</option>
              <option value="pie" ${draft.primaryType === 'pie' ? 'selected' : ''}>Pie</option>
              <option value="doughnut" ${draft.primaryType === 'doughnut' ? 'selected' : ''}>Doughnut</option>
              <option value="polar" ${draft.primaryType === 'polar' ? 'selected' : ''}>Polar area</option>
              <option value="scatter">Scatter</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">Sort rows
            <select data-draft-idx="${idx}" class="form-input chart-sort-select mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600">
              <option value="none">Source order</option>
              <option value="asc">Value: low to high</option>
              <option value="desc">Value: high to low</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">Rows to show
            <input data-draft-idx="${idx}" class="form-input chart-limit-input mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600" type="number" min="1" max="100" value="10">
          </label>
          <label class="block text-sm font-medium text-slate-700">Find matching data
            <input data-draft-idx="${idx}" class="form-input chart-filter-input mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600" type="search" placeholder="Search labels or values">
          </label>
          <label class="block text-sm font-medium text-slate-700">Minimum value
            <input data-draft-idx="${idx}" class="form-input chart-min-input mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600" type="number" step="any" placeholder="No minimum">
          </label>
          <label class="block text-sm font-medium text-slate-700">Maximum value
            <input data-draft-idx="${idx}" class="form-input chart-max-input mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600" type="number" step="any" placeholder="No maximum">
          </label>
          <label class="chart-category-field block text-sm font-medium text-slate-700"><span class="category-field-label">Category / X axis</span>
            <select data-draft-idx="${idx}" class="form-input chart-category-select mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600"></select>
          </label>
          <label class="chart-value-field block text-sm font-medium text-slate-700"><span class="value-field-label">Metric / Y axis</span>
            <select data-draft-idx="${idx}" class="form-input chart-value-select mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600"></select>
          </label>
        </div>

        <div class="graph-canvas-container" style="height: 360px; position: relative;">
          <div id="${chartId}" class="h-full w-full"></div>
        </div>
      `;

      graphDraftsContainer.appendChild(card);

      const tableSelect = card.querySelector('.chart-table-select');
      const categorySelect = card.querySelector('.chart-category-select');
      const valueSelect = card.querySelector('.chart-value-select');
      const syncControls = () => {
        const table = tables[Number(tableSelect.value)]?.[1];
        const headers = table ? table.headers : [];
        populateSelect(categorySelect, headers, draft.chartData?.xAxis);
        populateSelect(valueSelect, headers, draft.chartData?.yAxis || headers[1]);
        updateChartFieldSemantics(card);
        renderEChart(card, chartId, tables, idx);
      };

      [tableSelect, categorySelect, valueSelect].forEach(control => control.addEventListener('change', syncControls));
      card.querySelectorAll('.chart-type-select, .chart-sort-select, .chart-limit-input, .chart-filter-input, .chart-min-input, .chart-max-input').forEach(control => control.addEventListener('input', () => {
        updateChartFieldSemantics(card);
        renderEChart(card, chartId, tables, idx);
      }));
      syncControls();
    });
  }

  function createChartSelect(id, label, options, selectedValue, className) {
    return `<label class="block text-sm font-medium text-slate-700">${label}<select id="${id}" class="${className} form-input mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-green-600 focus:ring-green-600">${options.length ? options.map(option => `<option value="${option.value}" ${String(option.value) === String(selectedValue) ? 'selected' : ''}>${option.label}</option>`).join('') : '<option value="0">No tables available</option>'}</select></label>`;
  }

  function populateSelect(select, headers, preferred) {
    select.innerHTML = headers.map((header, index) => `<option value="${index}" ${header === preferred ? 'selected' : ''}>${header}</option>`).join('');
  }

  function updateChartFieldSemantics(card) {
    const type = card.querySelector('.chart-type-select').value;
    const isPartOfWhole = ['pie', 'doughnut', 'polar'].includes(type);
    card.querySelector('.category-field-label').textContent = isPartOfWhole ? 'Category / slice label' : 'Category / X axis';
    card.querySelector('.value-field-label').textContent = isPartOfWhole ? 'Value / slice size' : 'Metric / Y axis';
  }

  function renderEChart(card, chartId, tables, draftIndex) {
    if (typeof echarts === 'undefined') return;
    const tableIndex = Number(card.querySelector('.chart-table-select').value);
    const table = tables[tableIndex]?.[1];
    if (!table) return;

    const categoryIndex = Number(card.querySelector('.chart-category-select').value || 0);
    const valueIndex = Number(card.querySelector('.chart-value-select').value || 1);
    const chartType = card.querySelector('.chart-type-select').value;
    const sortMode = card.querySelector('.chart-sort-select').value;
    const limit = Math.max(1, Math.min(100, Number(card.querySelector('.chart-limit-input').value) || 10));
    const query = card.querySelector('.chart-filter-input').value.trim().toLowerCase();
    const minimum = Number(card.querySelector('.chart-min-input').value);
    const maximum = Number(card.querySelector('.chart-max-input').value);
    let points = table.rows.map((row, rowIndex) => ({
      label: row[categoryIndex] === undefined || row[categoryIndex] === null ? `Row ${rowIndex + 1}` : String(row[categoryIndex]),
      value: Number(String(row[valueIndex] ?? 0).replace(/[%,$,]/g, ''))
    })).filter(point => Number.isFinite(point.value))
      .filter(point => !query || `${point.label} ${point.value}`.toLowerCase().includes(query))
      .filter(point => !Number.isFinite(minimum) || point.value >= minimum)
      .filter(point => !Number.isFinite(maximum) || point.value <= maximum);

    if (sortMode !== 'none') points.sort((a, b) => sortMode === 'asc' ? a.value - b.value : b.value - a.value);
    points = points.slice(0, limit);
    const element = document.getElementById(chartId);
    if (!element) return;
    if (chartInstances[chartId]) chartInstances[chartId].dispose();
    const chart = echarts.init(element);
    chartInstances[chartId] = chart;
    const isCircular = ['pie', 'doughnut', 'polar'].includes(chartType);
    const series = chartType === 'pie' || chartType === 'doughnut'
      ? [{ type: 'pie', radius: chartType === 'doughnut' ? ['42%', '72%'] : '68%', data: points.map(point => ({ name: point.label, value: point.value })) }]
      : chartType === 'polar'
        ? [{ type: 'bar', coordinateSystem: 'polar', data: points.map(point => point.value), itemStyle: { color: '#146C36' } }]
      : [{ type: chartType, smooth: chartType === 'line', data: points.map(point => chartType === 'scatter' ? [point.label, point.value] : point.value), itemStyle: { color: '#146C36' } }];
    chart.setOption({
      animationDuration: 500,
      tooltip: { trigger: isCircular ? 'item' : 'axis' },
      legend: { show: chartType === 'pie' || chartType === 'doughnut', bottom: 0 },
      grid: { left: 48, right: 24, top: 24, bottom: 48, containLabel: true },
      xAxis: isCircular ? undefined : { type: 'category', data: points.map(point => point.label), axisLabel: { rotate: points.length > 6 ? 30 : 0 } },
      yAxis: isCircular ? undefined : { type: 'value' },
      polar: chartType === 'polar' ? {} : undefined,
      angleAxis: chartType === 'polar' ? { type: 'category', data: points.map(point => point.label) } : undefined,
      radiusAxis: chartType === 'polar' ? {} : undefined,
      series
    });
    window.addEventListener('resize', () => chart.resize(), { once: true });
  }

  // ----------------------------------------------------
  // Tab Switching Logic
  // ----------------------------------------------------
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      tabPanels.forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const targetTab = btn.getAttribute('data-tab');
      const targetPanel = document.getElementById(targetTab);
      if (targetPanel) targetPanel.classList.add('active');
    });
  });

  // State Management for Dashboard Studio Workbench
  let studioActiveRecord = null;
  let studioChartInstance = null;
  let studioActiveSheetName = '';

  // ----------------------------------------------------
  // Admin Portal & Live Dashboard Studio
  // ----------------------------------------------------
  async function renderAdminPortal() {
    const records = await dbManager.getAllRecords();

    // Stats
    statTotalDb.textContent = records.length;
    statPendingDb.textContent = records.filter(r => r.status === 'Pending Review' || !r.status).length;
    statVerifiedDb.textContent = records.filter(r => r.status === 'Approved' || r.status === 'Verified & Approved').length;
    
    let totalTables = 0;
    records.forEach(r => {
      if (r.extractedData && typeof r.extractedData === 'object') {
        totalTables += Object.keys(r.extractedData).length;
      }
    });
    statTablesDb.textContent = totalTables;

    // Populate Studio Record Dropdown
    const studioRecordSelect = document.getElementById('studioRecordSelect');
    if (studioRecordSelect) {
      const currentSelectedId = studioActiveRecord ? studioActiveRecord.id : (records[0] ? records[0].id : null);
      studioRecordSelect.innerHTML = '';
      records.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = `${r.fileName} (${(r.fileType || '').toUpperCase()})`;
        if (currentSelectedId && r.id === currentSelectedId) opt.selected = true;
        studioRecordSelect.appendChild(opt);
      });

      studioRecordSelect.onchange = (e) => {
        const selected = records.find(r => r.id === e.target.value);
        if (selected) {
          studioActiveRecord = selected;
          renderStudioWorkbench(selected);
        }
      };
    }

    // Set and Render Studio Active Record
    if (records.length > 0) {
      if (!studioActiveRecord || !records.some(r => r.id === studioActiveRecord.id)) {
        studioActiveRecord = records[0];
      } else {
        studioActiveRecord = records.find(r => r.id === studioActiveRecord.id) || records[0];
      }
      renderStudioWorkbench(studioActiveRecord);
    } else {
      const studioActiveFileName = document.getElementById('studioActiveFileName');
      if (studioActiveFileName) studioActiveFileName.textContent = 'No Scanned Datasets Available';
    }

    // Filter and Render Archive Table Rows
    const searchTerm = (adminSearchInput.value || '').toLowerCase();
    const statusFilter = adminStatusFilter.value;

    const filtered = records.filter(r => {
      const matchSearch = (r.fileName || '').toLowerCase().includes(searchTerm) ||
                          (r.docType || '').toLowerCase().includes(searchTerm) ||
                          (r.rawText || '').toLowerCase().includes(searchTerm);
      const matchStatus = statusFilter === 'all' || r.status === statusFilter || (statusFilter === 'Pending Review' && !r.status);
      return matchSearch && matchStatus;
    });

    adminRecordsTableBody.innerHTML = '';

    if (filtered.length === 0) {
      adminRecordsTableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--text-dim); padding: 2rem;">No matching scanned records in database.</td></tr>`;
      return;
    }

    filtered.forEach(r => {
      const tr = document.createElement('tr');
      const suggestedChart = r.graphDrafts && r.graphDrafts.length > 0 ? r.graphDrafts[0].primaryType.toUpperCase() : 'NONE';
      const statusClass = (r.status === 'Approved' || r.status === 'Verified & Approved') ? 'badge-low' : 'badge-medium';

      tr.innerHTML = `
        <td style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-dim);">${r.id.substr(0, 14)}...</td>
        <td style="font-weight: 600;">${r.fileName}</td>
        <td><span class="format-chip ${(r.fileType || '').toLowerCase()}">${(r.fileType || 'UNKNOWN').toUpperCase()}</span></td>
        <td>${r.docType || 'General'}</td>
        <td><span class="badge badge-low" style="background: rgba(20, 108, 54, 0.1); color: var(--clsu-green); border: 1px solid var(--border-green);">${suggestedChart}</span></td>
        <td><span class="badge ${statusClass}">${r.status || 'Pending Review'}</span></td>
        <td style="font-size: 0.8rem; color: var(--text-muted);">${new Date(r.scannedAt).toLocaleDateString()}</td>
        <td>
          <div style="display: flex; gap: 0.4rem;">
            <button class="btn-table-load-studio" data-id="${r.id}" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; background: var(--clsu-green); border: none; border-radius: var(--radius-sm); color: #fff; cursor: pointer; font-weight: 700;">
              🎨 Load in Studio
            </button>
            <button class="btn-table-delete" data-id="${r.id}" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: var(--radius-sm); color: #EF4444; cursor: pointer;">
              🗑️
            </button>
          </div>
        </td>
      `;

      adminRecordsTableBody.appendChild(tr);
    });

    // Wire Load in Studio Buttons
    document.querySelectorAll('.btn-table-load-studio').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const target = records.find(r => r.id === id);
        if (target) {
          studioActiveRecord = target;
          const select = document.getElementById('studioRecordSelect');
          if (select) select.value = id;
          renderStudioWorkbench(target);
          document.getElementById('studioContainer').scrollIntoView({ behavior: 'smooth' });
        }
      });
    });

    // Wire Delete Buttons
    document.querySelectorAll('.btn-table-delete').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        if (confirm('Are you sure you want to delete this scanned record?')) {
          await dbManager.deleteRecord(id);
          await renderAdminPortal();
        }
      });
    });
  }

  // ----------------------------------------------------
  // Live Dashboard Studio Renderer
  // ----------------------------------------------------
  function getStudioActiveSheet(record) {
    if (!record || !record.extractedData || typeof record.extractedData !== 'object') return null;
    const keys = Object.keys(record.extractedData);
    if (keys.length === 0) return null;

    let targetKey = docWindowActiveSheetKey;
    if (!targetKey || !record.extractedData[targetKey]) {
      targetKey = keys[0];
      docWindowActiveSheetKey = targetKey;
    }
    return {
      name: targetKey,
      data: record.extractedData[targetKey]
    };
  }

  function renderStudioWorkbench(record) {
    if (!record) return;

    const studioActiveFileName = document.getElementById('studioActiveFileName');
    const studioDocTypeInput = document.getElementById('studioDocTypeInput');
    const studioStatusSelect = document.getElementById('studioStatusSelect');
    const studioNotesInput = document.getElementById('studioNotesInput');

    if (studioActiveFileName) studioActiveFileName.textContent = `${record.fileName} (${(record.fileType || '').toUpperCase()})`;
    if (studioDocTypeInput) studioDocTypeInput.value = record.docType || 'General Institutional Data';
    if (studioStatusSelect) studioStatusSelect.value = record.status || 'Pending Review';
    if (studioNotesInput) studioNotesInput.value = record.adminNotes || '';

    // 1. Render Left Column: Scanned Document Window Screen (sets active sheet if spreadsheet)
    renderDocumentWindow(record);

    // 2. Render Right Column: Live Editable Table Grid & Chart for Active Sheet
    ensureTableDataStructure(record);
    const activeSheetInfo = getStudioActiveSheet(record);
    if (activeSheetInfo && activeSheetInfo.data) {
      updateFieldSelectOptions(activeSheetInfo.data);
      renderStudioTableGrid(record);
      renderStudioChart(record);
    }
  }

  // ----------------------------------------------------
  // Adobe Acrobat Style Document & File Viewer Engine
  // ----------------------------------------------------
  let docWindowActiveView = 'sheet'; // 'sheet' or 'text'
  let docWindowActiveSheetKey = '';
  let docWindowSearchQuery = '';
  let acrobatZoomLevel = 100;
  let acrobatCurrentPage = 1;
  let acrobatTotalPages = 1;

  function renderDocumentWindow(record) {
    const docWindowTitle = document.getElementById('docWindowTitle');
    const acrobatDocBadge = document.getElementById('acrobatDocBadge');
    const docContentArea = document.getElementById('studioDocContentArea');
    const sheetSelectorContainer = document.getElementById('studioDocSheetSelectorContainer');
    const sheetSelect = document.getElementById('studioDocSheetSelect');
    const acrobatPageNavControls = document.getElementById('acrobatPageNavControls');
    const acrobatZoomControlsGroup = document.getElementById('acrobatZoomControlsGroup');

    if (!record || !docContentArea) return;

    if (docWindowTitle) {
      docWindowTitle.textContent = `${record.fileName || 'document'}`;
      docWindowTitle.title = record.fileName || '';
    }

    // Determine File Type Badge
    const fType = ((record.fileType || '') + ' ' + (record.fileName || '')).toLowerCase();
    if (acrobatDocBadge) {
      if (fType.includes('xls')) {
        acrobatDocBadge.textContent = 'XLSX';
        acrobatDocBadge.style.background = '#107C41';
      } else if (fType.includes('pdf')) {
        acrobatDocBadge.textContent = 'PDF';
        acrobatDocBadge.style.background = '#E5252A';
      } else if (fType.includes('doc')) {
        acrobatDocBadge.textContent = 'DOCX';
        acrobatDocBadge.style.background = '#E5252A';
      } else if (fType.includes('png') || fType.includes('jpg') || fType.includes('jpeg') || fType.includes('img')) {
        acrobatDocBadge.textContent = 'IMG';
        acrobatDocBadge.style.background = '#7C3AED';
      } else {
        acrobatDocBadge.textContent = 'DOC';
        acrobatDocBadge.style.background = '#E5252A';
      }
    }

    const hasSheets = record.extractedData && typeof record.extractedData === 'object' && Object.keys(record.extractedData).length > 0;
    const sheetKeys = hasSheets ? Object.keys(record.extractedData).filter(k => {
      const item = record.extractedData[k];
      return item && Array.isArray(item.headers) && Array.isArray(item.rows);
    }) : [];

    if (sheetKeys.length > 0) {
      docWindowActiveView = 'sheet';
      if (!docWindowActiveSheetKey || !sheetKeys.includes(docWindowActiveSheetKey)) {
        docWindowActiveSheetKey = sheetKeys[0];
      }
      if (sheetSelectorContainer) sheetSelectorContainer.style.display = 'flex';
      if (acrobatPageNavControls) acrobatPageNavControls.style.display = 'none';
      if (acrobatZoomControlsGroup) acrobatZoomControlsGroup.style.display = 'none';

      if (sheetSelect) {
        sheetSelect.innerHTML = '';
        sheetKeys.forEach(k => {
          const opt = document.createElement('option');
          opt.value = k;
          opt.textContent = k;
          if (k === docWindowActiveSheetKey) opt.selected = true;
          sheetSelect.appendChild(opt);
        });

        sheetSelect.onchange = (e) => {
          docWindowActiveSheetKey = e.target.value;
          renderDocWindowBody(record);

          // Synchronize Right Column (Fields, Table Grid, and Chart) with the newly selected sheet
          const activeSheetInfo = getStudioActiveSheet(record);
          if (activeSheetInfo && activeSheetInfo.data) {
            updateFieldSelectOptions(activeSheetInfo.data);
            renderStudioTableGrid(record);
            renderStudioChart(record);
          }
        };
      }
    } else {
      docWindowActiveView = 'text';
      if (sheetSelectorContainer) sheetSelectorContainer.style.display = 'none';
      if (acrobatPageNavControls) acrobatPageNavControls.style.display = 'flex';
      if (acrobatZoomControlsGroup) acrobatZoomControlsGroup.style.display = 'flex';
    }

    wireAcrobatToolbarEvents();
    renderDocWindowBody(record);
  }

  function wireAcrobatToolbarEvents() {
    const btnPrev = document.getElementById('btnAcrobatPrevPage');
    const btnNext = document.getElementById('btnAcrobatNextPage');
    const pageInput = document.getElementById('acrobatCurrentPageInput');
    const btnZoomIn = document.getElementById('btnAcrobatZoomIn');
    const btnZoomOut = document.getElementById('btnAcrobatZoomOut');
    const btnFitWidth = document.getElementById('btnAcrobatFitWidth');
    const zoomValLabel = document.getElementById('acrobatZoomValue');

    if (btnPrev) {
      btnPrev.onclick = () => {
        if (acrobatCurrentPage > 1) {
          jumpToAcrobatPage(acrobatCurrentPage - 1);
        }
      };
    }

    if (btnNext) {
      btnNext.onclick = () => {
        if (acrobatCurrentPage < acrobatTotalPages) {
          jumpToAcrobatPage(acrobatCurrentPage + 1);
        }
      };
    }

    if (pageInput) {
      pageInput.onchange = (e) => {
        const val = parseInt(e.target.value, 10);
        if (!isNaN(val) && val >= 1 && val <= acrobatTotalPages) {
          jumpToAcrobatPage(val);
        } else {
          pageInput.value = acrobatCurrentPage;
        }
      };
    }

    if (btnZoomIn) {
      btnZoomIn.onclick = () => {
        if (acrobatZoomLevel < 150) {
          acrobatZoomLevel = Math.min(150, acrobatZoomLevel + 15);
          applyAcrobatZoom(zoomValLabel);
        }
      };
    }

    if (btnZoomOut) {
      btnZoomOut.onclick = () => {
        if (acrobatZoomLevel > 70) {
          acrobatZoomLevel = Math.max(70, acrobatZoomLevel - 15);
          applyAcrobatZoom(zoomValLabel);
        }
      };
    }

    if (btnFitWidth) {
      btnFitWidth.onclick = () => {
        acrobatZoomLevel = 100;
        applyAcrobatZoom(zoomValLabel);
      };
    }
  }

  function applyAcrobatZoom(labelEl) {
    if (labelEl) labelEl.textContent = `${acrobatZoomLevel}%`;
    const stack = document.getElementById('acrobatPagesStack');
    if (stack) {
      stack.style.transform = `scale(${acrobatZoomLevel / 100})`;
    }
  }

  function jumpToAcrobatPage(pageNum) {
    acrobatCurrentPage = Math.max(1, Math.min(pageNum, acrobatTotalPages));
    const pageInput = document.getElementById('acrobatCurrentPageInput');
    if (pageInput) pageInput.value = acrobatCurrentPage;

    const targetCard = document.getElementById(`acrobatDocPage_${acrobatCurrentPage}`);
    if (targetCard) {
      targetCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function renderDocWindowBody(record) {
    const docContentArea = document.getElementById('studioDocContentArea');
    const docWindowPageCount = document.getElementById('docWindowPageCount');
    const docWindowWordCount = document.getElementById('docWindowWordCount');
    const acrobatTotalPagesSpan = document.getElementById('acrobatTotalPagesSpan');
    const acrobatCurrentPageInput = document.getElementById('acrobatCurrentPageInput');
    const copyStatus = document.getElementById('docWindowCopyStatus');
    if (!docContentArea) return;

    if (docWindowActiveView === 'sheet' && record.extractedData && record.extractedData[docWindowActiveSheetKey]) {
      docContentArea.classList.add('sheet-mode');
      const sheet = record.extractedData[docWindowActiveSheetKey];
      const headers = sheet.headers || [];
      const rows = sheet.rows || [];

      if (docWindowPageCount) docWindowPageCount.textContent = `Sheet: ${docWindowActiveSheetKey}`;
      if (docWindowWordCount) docWindowWordCount.textContent = `${rows.length} rows • ${headers.length} cols`;

      // Filter rows if search active
      const filteredRows = rows.map((row, origIdx) => ({ row, origIdx })).filter(item => {
        if (!docWindowSearchQuery) return true;
        return item.row.some(cell => String(cell || '').toLowerCase().includes(docWindowSearchQuery));
      });

      if (filteredRows.length === 0) {
        docContentArea.innerHTML = `<div style="color: #94A3B8; font-size: 0.82rem; padding: 2rem; text-align: center;">No matching rows for "${docWindowSearchQuery}"</div>`;
        return;
      }

      let html = `<div class="doc-sheet-wrapper"><table class="mini-sheet-table"><thead><tr>`;
      html += `<th style="width: 38px; text-align: center; position: sticky; left: 0; z-index: 15; background: #E2E8F0;">#</th>`;
      headers.forEach((h, cIdx) => {
        const colLetter = String.fromCharCode(65 + (cIdx % 26));
        html += `<th><span style="font-size: 0.65rem; color: #64748B; margin-right: 4px;">${colLetter}</span> ${escapeHtml(h || `Col ${cIdx + 1}`)}</th>`;
      });
      html += `</tr></thead><tbody>`;

      filteredRows.forEach(({ row, origIdx }) => {
        html += `<tr>`;
        html += `<td class="row-num">${origIdx + 1}</td>`;
        headers.forEach((h, cIdx) => {
          const val = row[cIdx] !== undefined && row[cIdx] !== null ? row[cIdx] : '';
          const strVal = String(val);
          const safeVal = strVal.replace(/"/g, '&quot;');
          html += `<td class="mini-sheet-cell" data-val="${safeVal}" title="Click to copy: ${safeVal}">${escapeHtml(strVal)}</td>`;
        });
        html += `</tr>`;
      });

      html += `</tbody></table></div>`;
      docContentArea.innerHTML = html;

      // Wire 1-Click Copy on Table Cells
      docContentArea.querySelectorAll('.mini-sheet-cell').forEach(cell => {
        cell.onclick = (e) => {
          const val = cell.getAttribute('data-val');
          if (val !== undefined && val !== null) {
            navigator.clipboard.writeText(val).then(() => {
              cell.classList.add('copied-flash');
              if (copyStatus) {
                copyStatus.textContent = `✓ Copied: "${val.length > 25 ? val.substr(0, 25) + '...' : val}"`;
                setTimeout(() => { if (copyStatus) copyStatus.textContent = ''; }, 2000);
              }
              setTimeout(() => { cell.classList.remove('copied-flash'); }, 700);
            });
          }
        };
      });

    } else {
      docContentArea.classList.remove('sheet-mode');

      // 1. Direct Mozilla PDF.js rendering if PDF data is present
      if ((record.type === 'pdf' || record.fileType === 'pdf') && (record.pdfBuffer || record.pdfDocReference || record.previewUrl) && typeof PdfViewerComponent !== 'undefined') {
        const pdfViewer = new PdfViewerComponent(docContentArea, {
          showToolbar: false,
          scrollMode: 'continuous',
          onTextSelect: (text) => {
            navigator.clipboard.writeText(text).catch(() => {});
            if (copyStatus) {
              copyStatus.textContent = `✓ Copied: "${text.length > 25 ? text.substr(0, 25) + '...' : text}"`;
              setTimeout(() => { if (copyStatus) copyStatus.textContent = ''; }, 2000);
            }
          },
          onPageChange: (current, total) => {
            acrobatCurrentPage = current;
            acrobatTotalPages = total;
            if (acrobatCurrentPageInput) {
              acrobatCurrentPageInput.value = String(current);
              acrobatCurrentPageInput.max = String(total);
            }
            if (acrobatTotalPagesSpan) acrobatTotalPagesSpan.textContent = String(total);
            if (docWindowPageCount) docWindowPageCount.textContent = `${total} page${total > 1 ? 's' : ''}`;
          }
        });

        pdfViewer.loadDocument(record.pdfBuffer || record.pdfDocReference || record.previewUrl, record.fileName || 'document.pdf');
        return;
      }

      // 2. Rich DOCX rendering via docx-preview when raw buffer is available
      if ((record.type === 'docx' || record.fileType === 'docx') && record.docxBuffer && typeof DocxViewerComponent !== 'undefined') {
        const docxViewer = new DocxViewerComponent(docContentArea, {
          showToolbar: false,
          onReady: () => {
            if (docWindowPageCount) docWindowPageCount.textContent = 'Word Document';
            if (docWindowWordCount) {
              const wc = record.metadata && record.metadata.wordCount ? record.metadata.wordCount : (record.rawText || '').split(/\s+/).filter(Boolean).length;
              docWindowWordCount.textContent = `${wc.toLocaleString()} words`;
            }
          }
        });
        docxViewer.loadDocument(record.docxBuffer, record.fileName || 'document.docx');
        return;
      }

      // 3. Authentic Adobe Acrobat Page Card Layout for Word DOCX / Text / Scanned Documents
      const rawText = record.rawText || '';
      const words = rawText.split(/\s+/).filter(Boolean);
      const totalWords = words.length;

      if (!rawText.trim()) {
        acrobatTotalPages = 1;
        acrobatCurrentPage = 1;
        if (acrobatTotalPagesSpan) acrobatTotalPagesSpan.textContent = '1';
        if (acrobatCurrentPageInput) acrobatCurrentPageInput.value = '1';
        if (docWindowPageCount) docWindowPageCount.textContent = `Page 1 of 1`;
        if (docWindowWordCount) docWindowWordCount.textContent = `0 words`;
        docContentArea.innerHTML = `<div class="acrobat-page-card"><p style="color: #94A3B8; text-align: center;">No document content available.</p></div>`;
        return;
      }

      // Group into paragraphs by blank lines or carriage returns
      const rawParagraphs = rawText.split(/\n\s*\n/).map(p => p.trim()).filter(Boolean);
      
      // Paginate into authentic PDF pages (~220 words per page card or at least 1 page)
      const pages = [];
      let currentPageParas = [];
      let currentWordCount = 0;

      rawParagraphs.forEach((para) => {
        const paraWords = para.split(/\s+/).filter(Boolean).length;
        if (currentWordCount > 0 && (currentWordCount + paraWords > 230)) {
          pages.push(currentPageParas);
          currentPageParas = [para];
          currentWordCount = paraWords;
        } else {
          currentPageParas.push(para);
          currentWordCount += paraWords;
        }
      });
      if (currentPageParas.length > 0) {
        pages.push(currentPageParas);
      }
      if (pages.length === 0) pages.push(['No content available.']);

      acrobatTotalPages = pages.length;
      acrobatCurrentPage = 1;

      if (acrobatTotalPagesSpan) acrobatTotalPagesSpan.textContent = String(acrobatTotalPages);
      if (acrobatCurrentPageInput) {
        acrobatCurrentPageInput.value = '1';
        acrobatCurrentPageInput.max = String(acrobatTotalPages);
      }
      if (docWindowPageCount) docWindowPageCount.textContent = `${acrobatTotalPages} page${acrobatTotalPages > 1 ? 's' : ''}`;
      if (docWindowWordCount) docWindowWordCount.textContent = `${totalWords.toLocaleString()} words`;

      let docHtml = `<div class="acrobat-pages-container" id="acrobatPagesStack" style="transform: scale(${acrobatZoomLevel / 100});">`;

      pages.forEach((pageParas, pageIdx) => {
        const pageNum = pageIdx + 1;
        docHtml += `
          <div class="acrobat-page-card" id="acrobatDocPage_${pageNum}" data-page="${pageNum}">
        `;

        pageParas.forEach(para => {
          const lines = para.split('\n').map(l => l.trim()).filter(Boolean);
          
          lines.forEach(line => {
            const isHeading = line.length < 80 && (line.endsWith(':') || line.toUpperCase() === line || /^(\d+\.|\b(Section|Chapter|Title|Summary|Overview|Background|Techniques|Methodology|Backlog|Findings)\b)/i.test(line));
            const isNumberedItem = /^\d+[\.\)]\s+/.test(line);

            if (isHeading) {
              docHtml += `<h4 style="font-size: 0.95rem; font-weight: 800; color: #0F172A; margin: 1.1rem 0 0.4rem 0; line-height: 1.35;">${escapeHtml(line)}</h4>`;
            } else if (isNumberedItem) {
              const match = line.match(/^(\d+[\.\)]\s+[^—\-\:]+[\—\-\:])(.*)$/);
              if (match) {
                docHtml += `<p style="margin-bottom: 0.65rem; padding-left: 0.75rem; text-indent: -0.75rem;"><strong>${escapeHtml(match[1])}</strong>${escapeHtml(match[2])}</p>`;
              } else {
                docHtml += `<p style="margin-bottom: 0.65rem; padding-left: 0.75rem; text-indent: -0.75rem;">${escapeHtml(line)}</p>`;
              }
            } else {
              docHtml += `<p style="margin-bottom: 0.75rem; text-align: justify; text-justify: inter-word;">${escapeHtml(line)}</p>`;
            }
          });
        });

        docHtml += `
            <div class="acrobat-page-number-tag">Page ${pageNum} of ${acrobatTotalPages}</div>
          </div>
        `;
      });

      docHtml += `</div>`;
      docContentArea.innerHTML = docHtml;

      // Enable text selection and click-to-copy on paragraphs
      docContentArea.querySelectorAll('.acrobat-page-card p, .acrobat-page-card h4').forEach(el => {
        el.style.cursor = 'text';
      });

      // Scroll listener to update page counter as user scrolls down
      docContentArea.onscroll = () => {
        const cards = docContentArea.querySelectorAll('.acrobat-page-card');
        const containerTop = docContentArea.scrollTop;
        cards.forEach(card => {
          const cardTop = card.offsetTop - docContentArea.offsetTop;
          if (containerTop >= cardTop - 120) {
            const p = parseInt(card.getAttribute('data-page'), 10);
            if (!isNaN(p) && p !== acrobatCurrentPage) {
              acrobatCurrentPage = p;
              if (acrobatCurrentPageInput) acrobatCurrentPageInput.value = String(p);
            }
          }
        });
      };
    }
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function ensureTableDataStructure(record) {
    if (!record.extractedData || typeof record.extractedData !== 'object') {
      record.extractedData = {};
    }

    const keys = Object.keys(record.extractedData);
    if (keys.length === 0 || !record.extractedData[keys[0]].headers) {
      // Build default table from raw text or draft if empty
      const draft = (record.graphDrafts && record.graphDrafts[0]) ? record.graphDrafts[0] : null;
      let headers = ['Item / Metric', 'Value'];
      let rows = [];

      if (draft && draft.chartData && draft.chartData.labels) {
        const ds = draft.chartData.datasets[0] || { label: 'Value', data: [] };
        headers = ['Item / Category', ds.label || 'Value'];
        rows = draft.chartData.labels.map((lbl, i) => [lbl, ds.data[i] !== undefined ? ds.data[i] : 0]);
      } else {
        rows = [
          ['Category A', 92.5],
          ['Category B', 88.0],
          ['Category C', 95.4]
        ];
      }

      record.extractedData['Main_Metrics'] = {
        name: 'Main_Metrics',
        headers,
        rows
      };
    }
  }

  function updateFieldSelectOptions(sheet, selectedLabelCol = null, selectedValueCol = null) {
    const labelSelect = document.getElementById('studioLabelColSelect');
    const valueSelect = document.getElementById('studioValueColSelect');
    if (!labelSelect || !valueSelect || !sheet || !sheet.headers) return;

    const currentLabelVal = selectedLabelCol !== null ? String(selectedLabelCol) : (labelSelect.value || '0');
    const currentValueVal = selectedValueCol !== null ? String(selectedValueCol) : (valueSelect.value || '1');

    labelSelect.innerHTML = '';
    valueSelect.innerHTML = '';

    sheet.headers.forEach((h, colIdx) => {
      const optL = document.createElement('option');
      optL.value = colIdx;
      optL.textContent = `${h || `Column ${colIdx + 1}`} (Col ${colIdx + 1})`;
      labelSelect.appendChild(optL);

      const optV = document.createElement('option');
      optV.value = colIdx;
      optV.textContent = `${h || `Column ${colIdx + 1}`} (Col ${colIdx + 1})`;
      valueSelect.appendChild(optV);
    });

    // Determine smart defaults if not set
    let defaultLabelCol = parseInt(currentLabelVal, 10);
    if (isNaN(defaultLabelCol) || defaultLabelCol >= sheet.headers.length) defaultLabelCol = 0;

    let defaultValueCol = parseInt(currentValueVal, 10);
    if (isNaN(defaultValueCol) || defaultValueCol >= sheet.headers.length || defaultValueCol === defaultLabelCol) {
      defaultValueCol = sheet.headers.length > 1 ? 1 : 0;
      for (let c = 0; c < sheet.headers.length; c++) {
        const hasNumbers = (sheet.rows || []).some(r => typeof r[c] === 'number' || (!isNaN(parseFloat(r[c])) && isFinite(r[c])));
        if (hasNumbers && c !== defaultLabelCol) {
          defaultValueCol = c;
          break;
        }
      }
    }

    labelSelect.value = String(defaultLabelCol);
    valueSelect.value = String(defaultValueCol);
  }

  function renderStudioTableGrid(record) {
    const container = document.getElementById('studioTableContainer');
    if (!container || !record) return;

    const activeSheetInfo = getStudioActiveSheet(record);
    if (!activeSheetInfo || !activeSheetInfo.data) return;

    const sheetName = activeSheetInfo.name;
    const sheet = activeSheetInfo.data;

    const labelSelect = document.getElementById('studioLabelColSelect');
    const valueSelect = document.getElementById('studioValueColSelect');
    const activeLabelCol = labelSelect ? parseInt(labelSelect.value, 10) : 0;
    const activeValueCol = valueSelect ? parseInt(valueSelect.value, 10) : 1;

    let tHtml = `<table class="data-table"><thead><tr>`;
    sheet.headers.forEach((h, colIdx) => {
      const isX = colIdx === activeLabelCol;
      const isY = colIdx === activeValueCol;
      const badge = isX ? `<span class="axis-indicator-badge x-axis">🏷️ X-Axis</span>` : (isY ? `<span class="axis-indicator-badge y-axis">📈 Y-Axis</span>` : '');

      tHtml += `<th>
        <div class="header-cell-box">
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
            ${badge}
            ${sheet.headers.length > 1 ? `<button type="button" class="btn-delete-col" data-col="${colIdx}" style="background: none; border: none; color: #EF4444; cursor: pointer; font-size: 0.85rem; font-weight: 800;" title="Delete this field / column">✕</button>` : ''}
          </div>
          <input type="text" class="header-rename-input" data-col="${colIdx}" value="${String(h || '').replace(/"/g, '&quot;')}" placeholder="Field Name..." title="Click to rename this field header">
        </div>
      </th>`;
    });
    tHtml += `<th style="width: 50px; text-align: center;">Action</th></tr></thead><tbody>`;

    (sheet.rows || []).forEach((row, rIdx) => {
      tHtml += `<tr>`;
      sheet.headers.forEach((h, cIdx) => {
        const cellVal = row[cIdx] !== undefined && row[cIdx] !== null ? row[cIdx] : '';
        tHtml += `<td>
          <input type="text" class="studio-cell-input" data-row="${rIdx}" data-col="${cIdx}" value="${String(cellVal).replace(/"/g, '&quot;')}">
        </td>`;
      });
      tHtml += `<td style="text-align: center;">
        <button type="button" class="studio-delete-row" data-row="${rIdx}" style="background: none; border: none; color: #EF4444; cursor: pointer; font-size: 1.1rem; font-weight: 800;" title="Delete Row">✕</button>
      </td></tr>`;
    });

    tHtml += `</tbody></table>`;
    container.innerHTML = tHtml;

    // Real-time live chart update on cell input
    document.querySelectorAll('.studio-cell-input').forEach(input => {
      input.addEventListener('input', () => {
        const r = parseInt(input.getAttribute('data-row'), 10);
        const c = parseInt(input.getAttribute('data-col'), 10);
        let val = input.value.trim();
        if (!isNaN(parseFloat(val)) && isFinite(val)) {
          val = parseFloat(val);
        }
        if (sheet.rows[r]) {
          sheet.rows[r][c] = val;
        }
        updateStudioChart();
      });
    });

    // Rename Header input
    document.querySelectorAll('.header-rename-input').forEach(input => {
      input.addEventListener('input', () => {
        const c = parseInt(input.getAttribute('data-col'), 10);
        const newHeader = input.value.trim() || `Field_${c + 1}`;
        sheet.headers[c] = newHeader;
        updateFieldSelectOptions(sheet, labelSelect ? labelSelect.value : null, valueSelect ? valueSelect.value : null);
        updateStudioChart();
      });
    });

    // Delete Column
    document.querySelectorAll('.btn-delete-col').forEach(btn => {
      btn.addEventListener('click', () => {
        const c = parseInt(btn.getAttribute('data-col'), 10);
        if (sheet.headers.length <= 1) return;
        if (confirm(`Are you sure you want to delete the field "${sheet.headers[c]}"?`)) {
          sheet.headers.splice(c, 1);
          sheet.rows.forEach(r => r.splice(c, 1));
          updateFieldSelectOptions(sheet);
          renderStudioTableGrid(record);
          updateStudioChart();
        }
      });
    });

    // Delete Row
    document.querySelectorAll('.studio-delete-row').forEach(btn => {
      btn.addEventListener('click', () => {
        const r = parseInt(btn.getAttribute('data-row'), 10);
        sheet.rows.splice(r, 1);
        renderStudioTableGrid(record);
        updateStudioChart();
      });
    });
  }

  // ----------------------------------------------------
  // Studio Live Chart Engine
  // ----------------------------------------------------
  function renderStudioChart(record) {
    const canvas = document.getElementById('studioChartCanvas');
    const chartTypeSelect = document.getElementById('studioChartTypeSelect');
    const chartTitleInput = document.getElementById('studioChartTitleInput');
    const chartSubtitleDisplay = document.getElementById('studioChartSubtitleDisplay');
    const labelSelect = document.getElementById('studioLabelColSelect');
    const valueSelect = document.getElementById('studioValueColSelect');
    const labelCaption = document.getElementById('studioLabelFieldCaption');
    const valueCaption = document.getElementById('studioValueFieldCaption');
    const swapAxesButton = document.getElementById('studioBtnSwapAxes');
    const filterInput = document.getElementById('studioDataFilterInput');
    const minInput = document.getElementById('studioDataMinInput');
    const maxInput = document.getElementById('studioDataMaxInput');
    const sortSelect = document.getElementById('studioDataSortSelect');
    const limitInput = document.getElementById('studioDataLimitInput');
    if (!canvas || !chartTypeSelect || !record) return;

    if (studioChartInstance) {
      studioChartInstance.dispose();
      studioChartInstance = null;
    }

    const activeSheetInfo = getStudioActiveSheet(record);
    if (!activeSheetInfo || !activeSheetInfo.data) return;

    const sheetName = activeSheetInfo.name;
    const sheet = activeSheetInfo.data;
    if (!sheet || !sheet.headers || !sheet.rows || sheet.rows.length === 0) return;

    let labelCol = labelSelect ? parseInt(labelSelect.value, 10) : 0;
    let numCol = valueSelect ? parseInt(valueSelect.value, 10) : (sheet.headers.length > 1 ? 1 : 0);

    if (isNaN(labelCol) || labelCol >= sheet.headers.length) labelCol = 0;
    if (isNaN(numCol) || numCol >= sheet.headers.length) numCol = sheet.headers.length > 1 ? 1 : 0;

    const currentType = chartTypeSelect.value || 'bar';
    const isCircular = ['pie', 'doughnut', 'polarArea'].includes(currentType);
    if (labelCaption) labelCaption.textContent = isCircular ? '🏷️ Category / Slice Label:' : '🏷️ X-Axis / Label Field:';
    if (valueCaption) valueCaption.textContent = isCircular ? '📊 Value / Slice Size:' : '📈 Y-Axis / Metric Field:';
    if (swapAxesButton) swapAxesButton.hidden = isCircular;
    const headerName = sheet.headers[numCol] || 'Metric Value';

    if (chartSubtitleDisplay) {
      chartSubtitleDisplay.textContent = `Live interactive rendering from: ${sheetName}`;
    }

    if (chartTitleInput && !chartTitleInput.getAttribute('data-customized')) {
      chartTitleInput.value = `${headerName} — ${sheetName}`;
    }

    const currentChartTitle = chartTitleInput ? chartTitleInput.value : `${headerName} — ${sheetName}`;

    const query = filterInput ? filterInput.value.trim().toLowerCase() : '';
    const minimum = minInput ? Number(minInput.value) : NaN;
    const maximum = maxInput ? Number(maxInput.value) : NaN;
    const sortMode = sortSelect ? sortSelect.value : 'none';
    const limit = limitInput ? Math.max(1, Math.min(100, Number(limitInput.value) || 30)) : 30;
    let points = sheet.rows.map((row, rowIndex) => ({
      label: String(row[labelCol] !== undefined && row[labelCol] !== null ? row[labelCol] : `Item ${rowIndex + 1}`).trim(),
      value: parseFloat(String(row[numCol] ?? '').replace(/[%,$,]/g, ''))
    })).filter(point => Number.isFinite(point.value))
      .filter(point => !query || `${point.label} ${point.value}`.toLowerCase().includes(query))
      .filter(point => !Number.isFinite(minimum) || point.value >= minimum)
      .filter(point => !Number.isFinite(maximum) || point.value <= maximum);
    if (sortMode !== 'none') points.sort((a, b) => sortMode === 'asc' ? a.value - b.value : b.value - a.value);
    points = points.slice(0, limit);
    const labels = points.map(point => point.label);
    const dataValues = points.map(point => point.value);

    studioChartInstance = echarts.init(canvas);
    studioChartInstance.setOption({
      title: { text: currentChartTitle, left: 'center', textStyle: { color: '#334155', fontSize: 14 } },
      tooltip: { trigger: isCircular ? 'item' : 'axis' },
      legend: { show: currentType === 'pie' || currentType === 'doughnut', bottom: 0 },
      grid: { left: 48, right: 24, top: 48, bottom: 48, containLabel: true },
      xAxis: isCircular ? undefined : { type: 'category', data: labels, axisLabel: { rotate: labels.length > 6 ? 30 : 0 } },
      yAxis: isCircular ? undefined : { type: 'value' },
      polar: currentType === 'polarArea' ? {} : undefined,
      angleAxis: currentType === 'polarArea' ? { type: 'category', data: labels } : undefined,
      radiusAxis: currentType === 'polarArea' ? {} : undefined,
      series: [currentType === 'pie' || currentType === 'doughnut'
        ? { type: 'pie', radius: currentType === 'doughnut' ? ['42%', '72%'] : '68%', data: labels.map((label, index) => ({ name: label, value: dataValues[index] })) }
        : currentType === 'polarArea'
          ? { type: 'bar', coordinateSystem: 'polar', data: dataValues, itemStyle: { color: '#146C36' } }
          : { type: currentType, smooth: currentType === 'line', data: dataValues, itemStyle: { color: '#146C36' } }]
    });
    window.addEventListener('resize', () => studioChartInstance && studioChartInstance.resize(), { once: true });
  }

  function updateStudioChart() {
    if (studioActiveRecord) {
      renderStudioChart(studioActiveRecord);
    }
  }

  // Chart Controls Event Listeners
  const studioChartTypeSelect = document.getElementById('studioChartTypeSelect');
  if (studioChartTypeSelect) {
    studioChartTypeSelect.addEventListener('change', updateStudioChart);
  }

  const studioLabelColSelect = document.getElementById('studioLabelColSelect');
  if (studioLabelColSelect) {
    studioLabelColSelect.addEventListener('change', () => {
      renderStudioTableGrid(studioActiveRecord);
      updateStudioChart();
    });
  }

  const studioValueColSelect = document.getElementById('studioValueColSelect');
  if (studioValueColSelect) {
    studioValueColSelect.addEventListener('change', () => {
      renderStudioTableGrid(studioActiveRecord);
      updateStudioChart();
    });
  }

  const studioChartTitleInput = document.getElementById('studioChartTitleInput');
  if (studioChartTitleInput) {
    studioChartTitleInput.addEventListener('input', () => {
      studioChartTitleInput.setAttribute('data-customized', 'true');
      updateStudioChart();
    });
  }

  ['studioDataFilterInput', 'studioDataMinInput', 'studioDataMaxInput', 'studioDataSortSelect', 'studioDataLimitInput'].forEach(id => {
    const control = document.getElementById(id);
    if (control) control.addEventListener('input', updateStudioChart);
    if (control && control.tagName === 'SELECT') control.addEventListener('change', updateStudioChart);
  });

  // Swap Axes Button
  const studioBtnSwapAxes = document.getElementById('studioBtnSwapAxes');
  if (studioBtnSwapAxes) {
    studioBtnSwapAxes.addEventListener('click', () => {
      if (!studioLabelColSelect || !studioValueColSelect || !studioActiveRecord) return;
      const temp = studioLabelColSelect.value;
      studioLabelColSelect.value = studioValueColSelect.value;
      studioValueColSelect.value = temp;
      renderStudioTableGrid(studioActiveRecord);
      updateStudioChart();
    });
  }

  // ➕ Add New Field / Column Button Handler
  const studioBtnAddField = document.getElementById('studioBtnAddField');
  if (studioBtnAddField) {
    studioBtnAddField.addEventListener('click', () => {
      if (!studioActiveRecord) return;
      const activeSheetInfo = getStudioActiveSheet(studioActiveRecord);
      if (!activeSheetInfo || !activeSheetInfo.data) return;
      const sheet = activeSheetInfo.data;

      const fieldName = prompt(`Enter new Field / Metric Name for sheet "${activeSheetInfo.name}":`, `Metric_${sheet.headers.length + 1}`);
      if (fieldName && fieldName.trim()) {
        sheet.headers.push(fieldName.trim());
        sheet.rows.forEach(r => r.push(''));
        updateFieldSelectOptions(sheet);
        renderStudioTableGrid(studioActiveRecord);
        updateStudioChart();
      }
    });
  }

  // ➕ Add Data Row Button Handler
  const studioBtnAddRow = document.getElementById('studioBtnAddRow');
  if (studioBtnAddRow) {
    studioBtnAddRow.addEventListener('click', () => {
      if (!studioActiveRecord) return;
      const activeSheetInfo = getStudioActiveSheet(studioActiveRecord);
      if (!activeSheetInfo || !activeSheetInfo.data) return;
      const sheet = activeSheetInfo.data;

      const emptyRow = sheet.headers.map((h, i) => i === 0 ? `New Item ${sheet.rows.length + 1}` : 0);
      sheet.rows.push(emptyRow);
      renderStudioTableGrid(studioActiveRecord);
      updateStudioChart();
    });
  }

  // 💾 Save Dashboard Changes Button Handler
  const studioBtnSave = document.getElementById('studioBtnSave');
  if (studioBtnSave) {
    studioBtnSave.addEventListener('click', async () => {
      if (!studioActiveRecord) return;
      await saveStudioData(false);
    });
  }

  // ✅ Approve for Observatory Button Handler
  const studioBtnApprove = document.getElementById('studioBtnApprove');
  if (studioBtnApprove) {
    studioBtnApprove.addEventListener('click', async () => {
      if (!studioActiveRecord) return;
      await saveStudioData(true);
    });
  }

  async function saveStudioData(forceApprove = false) {
    const studioDocTypeInput = document.getElementById('studioDocTypeInput');
    const studioStatusSelect = document.getElementById('studioStatusSelect');
    const studioNotesInput = document.getElementById('studioNotesInput');

    const updatedDocType = studioDocTypeInput ? studioDocTypeInput.value.trim() : studioActiveRecord.docType;
    const updatedStatus = forceApprove ? 'Approved' : (studioStatusSelect ? studioStatusSelect.value : studioActiveRecord.status);
    const updatedAdminNotes = studioNotesInput ? studioNotesInput.value.trim() : studioActiveRecord.adminNotes;

    // Harvest table cells from grid
    const sheetName = Object.keys(studioActiveRecord.extractedData)[0];
    const sheet = studioActiveRecord.extractedData[sheetName];
    document.querySelectorAll('.studio-cell-input').forEach(input => {
      const r = parseInt(input.getAttribute('data-row'), 10);
      const c = parseInt(input.getAttribute('data-col'), 10);
      let val = input.value.trim();
      if (!isNaN(parseFloat(val)) && isFinite(val)) val = parseFloat(val);
      if (sheet && sheet.rows && sheet.rows[r]) {
        sheet.rows[r][c] = val;
      }
    });

    // Update Draft Charts in record with current live state
    if (studioChartInstance) {
      const chartTypeSelect = document.getElementById('studioChartTypeSelect');
      const chartType = chartTypeSelect ? chartTypeSelect.value : 'bar';
      const chartTitleInput = document.getElementById('studioChartTitleInput');
      const finalTitle = chartTitleInput ? chartTitleInput.value : `${sheet.headers[1] || 'Metric'} — Observatory Draft`;

      studioActiveRecord.graphDrafts = [{
        id: `draft_${Date.now()}`,
        title: finalTitle,
        source: `Studio Data Editor: ${sheetName}`,
        primaryType: chartType,
        recommendation: `Administrator configured ${chartType} visualization.`,
        isDraft: !forceApprove,
        chartData: {
          labels: studioChartInstance ? (studioChartInstance.getOption().xAxis?.[0]?.data || []) : [],
          datasets: [{
            label: studioChartInstance ? (studioChartInstance.getOption().series?.[0]?.name || 'Value') : 'Value',
            data: studioChartInstance ? (studioChartInstance.getOption().series?.[0]?.data || []) : []
          }]
        }
      }];
    }

    const updated = await dbManager.updateRecord(studioActiveRecord.id, {
      docType: updatedDocType,
      status: updatedStatus,
      adminNotes: updatedAdminNotes,
      extractedData: studioActiveRecord.extractedData,
      graphDrafts: studioActiveRecord.graphDrafts
    });

    studioActiveRecord = { ...studioActiveRecord, ...updated };
    if (activeScan && activeScan.id === studioActiveRecord.id) {
      activeScan = { ...activeScan, ...updated };
      renderOverviewTab(activeScan);
      renderViewerTab(activeScan);
      renderGraphsTab(activeScan);
    }

    await renderAdminPortal();
    alert(`Dataset '${studioActiveRecord.fileName}' successfully saved to database!${forceApprove ? ' (Approved for Observatory)' : ''}`);
  }

  adminSearchInput.addEventListener('input', renderAdminPortal);
  adminStatusFilter.addEventListener('change', renderAdminPortal);

  // ----------------------------------------------------
  // Admin Live Data Editor Modal
  // ----------------------------------------------------
  async function openRecordEditModal(recordId) {
    const records = await dbManager.getAllRecords();
    const record = records.find(r => r.id === recordId);
    if (!record) return;

    recordEditTitle.textContent = `Edit Record: ${record.fileName}`;
    recordEditBody.innerHTML = '';

    // 1. Record Metadata Section
    const metaSection = document.createElement('div');
    metaSection.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; background: #F8FAF8; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);';

    metaSection.innerHTML = `
      <div>
        <label class="form-label" style="font-weight: 800; color: #334155; font-size: 0.8rem; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">File Name</label>
        <input type="text" id="editFileName" class="form-input" value="${record.fileName || ''}" style="font-weight: 600; color: #0F172A;">
      </div>
      <div>
        <label class="form-label" style="font-weight: 800; color: #334155; font-size: 0.8rem; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Category / Classification</label>
        <input type="text" id="editDocType" class="form-input" value="${record.docType || 'General Institutional Data'}" style="font-weight: 600; color: #0F172A;">
      </div>
      <div>
        <label class="form-label" style="font-weight: 800; color: #334155; font-size: 0.8rem; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Approval Status</label>
        <select id="editStatus" class="form-input" style="font-weight: 600; color: #0F172A;">
          <option value="Pending Review" ${(record.status === 'Pending Review' || !record.status) ? 'selected' : ''}>Pending Review</option>
          <option value="Approved" ${record.status === 'Approved' ? 'selected' : ''}>Approved for Dashboard</option>
          <option value="Needs Revision" ${record.status === 'Needs Revision' ? 'selected' : ''}>Needs Revision</option>
        </select>
      </div>
      <div style="grid-column: 1 / -1;">
        <label class="form-label" style="font-weight: 800; color: #334155; font-size: 0.8rem; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Admin Verification Notes & Logs</label>
        <textarea id="editAdminNotes" class="form-input" rows="2" placeholder="Add administrative verification notes..." style="font-weight: 500; color: #0F172A; line-height: 1.5;">${record.adminNotes || ''}</textarea>
      </div>
    `;
    recordEditBody.appendChild(metaSection);

    // 2. Tabular Data Editor (If extractedData has sheets)
    let hasTable = false;
    if (record.extractedData && typeof record.extractedData === 'object') {
      const sheetKeys = Object.keys(record.extractedData).filter(k => {
        const item = record.extractedData[k];
        return item && Array.isArray(item.headers) && Array.isArray(item.rows);
      });

      if (sheetKeys.length > 0) {
        hasTable = true;
        const activeSheetKey = sheetKeys[0];
        const sheet = record.extractedData[activeSheetKey];

        const tableSection = document.createElement('div');
        tableSection.style.cssText = 'margin-bottom: 1.5rem; background: #FFFFFF; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);';

        tableSection.innerHTML = `
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
            <div>
              <h4 style="font-size: 1rem; font-weight: 800; color: var(--clsu-green); display: flex; align-items: center; gap: 0.5rem;">
                <span>📊</span> Extracted Table Cells (${activeSheetKey})
              </h4>
              <p style="font-size: 0.82rem; color: #64748B;">Click inside any cell to edit its value directly.</p>
            </div>
            <button id="btnAddRowBtn" type="button" style="padding: 0.45rem 0.95rem; font-size: 0.82rem; background: #ECFDF5; border: 1.5px solid #10B981; color: #065F46; border-radius: var(--radius-sm); cursor: pointer; font-weight: 700;">
              ➕ Add Row
            </button>
          </div>
        `;

        const tableContainer = document.createElement('div');
        tableContainer.className = 'table-container';
        tableContainer.style.maxHeight = '320px';

        let tHtml = `<table class="data-table"><thead><tr>`;
        sheet.headers.forEach(h => {
          tHtml += `<th>${h || ''}</th>`;
        });
        tHtml += `<th style="width: 60px; text-align: center;">Action</th></tr></thead><tbody id="editableTableBody">`;

        (sheet.rows || []).slice(0, 50).forEach((row, rIdx) => {
          tHtml += `<tr>`;
          sheet.headers.forEach((h, cIdx) => {
            const cellVal = row[cIdx] !== undefined && row[cIdx] !== null ? row[cIdx] : '';
            tHtml += `<td><input type="text" class="cell-input" data-sheet="${activeSheetKey}" data-row="${rIdx}" data-col="${cIdx}" value="${String(cellVal).replace(/"/g, '&quot;')}"></td>`;
          });
          tHtml += `<td style="text-align: center;"><button type="button" class="btn-delete-row" data-sheet="${activeSheetKey}" data-row="${rIdx}" style="background: none; border: none; color: #EF4444; cursor: pointer; font-size: 1.1rem; font-weight: 800;" title="Delete row">✕</button></td></tr>`;
        });

        tHtml += `</tbody></table>`;
        tableContainer.innerHTML = tHtml;
        tableSection.appendChild(tableContainer);
        recordEditBody.appendChild(tableSection);

        // Wire Add Row & Delete Row in Table
        setTimeout(() => {
          const btnAddRow = document.getElementById('btnAddRowBtn');
          if (btnAddRow) {
            btnAddRow.addEventListener('click', () => {
              const emptyRow = sheet.headers.map(() => '');
              sheet.rows.unshift(emptyRow);
              openRecordEditModal(recordId);
            });
          }

          document.querySelectorAll('.btn-delete-row').forEach(btn => {
            btn.addEventListener('click', () => {
              const rIdx = parseInt(btn.getAttribute('data-row'), 10);
              sheet.rows.splice(rIdx, 1);
              openRecordEditModal(recordId);
            });
          });
        }, 50);
      }
    }

    // 3. Raw Content / Text Editor (Always available for fine-tuning text/OCR/prompts)
    const textSection = document.createElement('div');
    textSection.style.cssText = 'background: #FFFFFF; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);';
    textSection.innerHTML = `
      <label class="form-label" style="font-weight: 800; color: #334155; font-size: 0.8rem; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">
        📝 Extracted Text Content
      </label>
      <textarea id="editRawText" class="form-input" rows="6" style="background: #FAFCFA; border: 1.5px solid #CBD5E1; color: #0F172A; font-family: var(--font-mono); font-size: 0.88rem; font-weight: 500; line-height: 1.6;">${record.rawText || ''}</textarea>
    `;
    recordEditBody.appendChild(textSection);

    // 4. Modal Action Buttons Footer
    const actionRow = document.createElement('div');
    actionRow.style.cssText = 'display: flex; justify-content: flex-end; gap: 0.85rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-light);';

    actionRow.innerHTML = `
      <button id="btnCancelEdit" type="button" class="btn-cancel">Cancel</button>
      <button id="btnSaveRecordChanges" type="button" class="btn-save-modal">💾 Save Changes</button>
      <button id="btnApproveDraft" type="button" class="btn-approve-modal">✅ Approve for Dashboard</button>
    `;

    recordEditBody.appendChild(actionRow);

    // Wire Save Action
    document.getElementById('btnSaveRecordChanges').addEventListener('click', async () => {
      await saveModalData(record, recordId, false);
    });

    document.getElementById('btnApproveDraft').addEventListener('click', async () => {
      await saveModalData(record, recordId, true);
    });

    document.getElementById('btnCancelEdit').addEventListener('click', () => {
      recordEditModal.classList.remove('active');
    });

    recordEditModal.classList.add('active');
  }

  async function saveModalData(record, recordId, forceApprove = false) {
    const updatedFileName = document.getElementById('editFileName').value.trim();
    const updatedDocType = document.getElementById('editDocType').value.trim();
    const updatedStatus = forceApprove ? 'Approved' : document.getElementById('editStatus').value;
    const updatedAdminNotes = document.getElementById('editAdminNotes').value.trim();
    const updatedRawText = document.getElementById('editRawText').value;

    // Harvest cell input edits if available
    const cellInputs = document.querySelectorAll('.cell-input');
    if (cellInputs.length > 0 && record.extractedData) {
      cellInputs.forEach(input => {
        const sheetName = input.getAttribute('data-sheet');
        const r = parseInt(input.getAttribute('data-row'), 10);
        const c = parseInt(input.getAttribute('data-col'), 10);
        let val = input.value.trim();
        if (!isNaN(parseFloat(val)) && isFinite(val)) {
          val = parseFloat(val);
        }
        if (record.extractedData[sheetName] && record.extractedData[sheetName].rows && record.extractedData[sheetName].rows[r]) {
          record.extractedData[sheetName].rows[r][c] = val;
        }
      });
    }

    const updated = await dbManager.updateRecord(recordId, {
      fileName: updatedFileName,
      docType: updatedDocType,
      status: updatedStatus,
      adminNotes: updatedAdminNotes,
      rawText: updatedRawText,
      extractedData: record.extractedData
    });

    if (activeScan && activeScan.id === recordId) {
      activeScan = { ...activeScan, ...updated };
      renderOverviewTab(activeScan);
      renderViewerTab(activeScan);
    }

    recordEditModal.classList.remove('active');
    await renderAdminPortal();
  }

  btnCloseRecordModal.addEventListener('click', () => {
    recordEditModal.classList.remove('active');
  });

  document.getElementById('btnCancelEdit') && document.getElementById('btnCancelEdit').addEventListener('click', () => {
    recordEditModal.classList.remove('active');
  });

});


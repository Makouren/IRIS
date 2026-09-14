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

    } else if (scan.formattedHtml) {
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
    
    // Destroy previous Chart.js instances
    Object.values(chartInstances).forEach(c => c && c.destroy && c.destroy());
    chartInstances = {};

    const drafts = scan.graphDrafts || [];

    if (drafts.length === 0) {
      graphDraftsContainer.innerHTML = '<div style="color: var(--text-muted); padding: 2rem; text-align: center;">No numerical series detected to build chart drafts.</div>';
      return;
    }

    drafts.forEach((draft, idx) => {
      const card = document.createElement('div');
      card.className = 'graph-card';
      const canvasId = `chart_canvas_${idx}`;

      card.innerHTML = `
        <div class="graph-card-header">
          <div>
            <div class="graph-card-title">${draft.title}</div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">Source: ${draft.source}</div>
          </div>
          <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span class="badge badge-low">Draft Suggestion</span>
            <select class="form-input chart-type-select" data-draft-idx="${idx}" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
              <option value="bar" ${draft.primaryType === 'bar' ? 'selected' : ''}>Bar Chart</option>
              <option value="line" ${draft.primaryType === 'line' ? 'selected' : ''}>Line Chart</option>
              <option value="pie" ${draft.primaryType === 'pie' ? 'selected' : ''}>Pie Chart</option>
            </select>
          </div>
        </div>

        <div style="font-size: 0.82rem; color: var(--accent-cyan); margin-bottom: 1rem;">
          💡 <strong>AI Recommendation:</strong> ${draft.recommendation}
        </div>

        <div class="graph-canvas-container" style="height: 320px; position: relative;">
          <canvas id="${canvasId}"></canvas>
        </div>
      `;

      graphDraftsContainer.appendChild(card);

      // Render Chart using Chart.js
      setTimeout(() => {
        const ctx = document.getElementById(canvasId);
        if (ctx) {
          chartInstances[canvasId] = createChart(ctx, draft.primaryType, draft.chartData);
        }
      }, 50);
    });

    // Chart Type Selector Switcher
    document.querySelectorAll('.chart-type-select').forEach(select => {
      select.addEventListener('change', (e) => {
        const draftIdx = e.target.getAttribute('data-draft-idx');
        const newType = e.target.value;
        const canvasId = `chart_canvas_${draftIdx}`;
        const draft = drafts[draftIdx];

        if (chartInstances[canvasId]) {
          chartInstances[canvasId].destroy();
        }

        const ctx = document.getElementById(canvasId);
        if (ctx && draft) {
          chartInstances[canvasId] = createChart(ctx, newType, draft.chartData);
        }
      });
    });
  }

  function createChart(ctx, type, chartData) {
    return new Chart(ctx, {
      type: type,
      data: JSON.parse(JSON.stringify(chartData)),
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: type === 'pie',
            labels: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } }
          }
        },
        scales: type === 'pie' ? {} : {
          x: {
            ticks: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            ticks: { color: '#94A3B8', font: { family: 'Outfit, sans-serif' } },
            grid: { color: 'rgba(255,255,255,0.05)' }
          }
        }
      }
    });
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

  // ----------------------------------------------------
  // Admin Portal & Data Editor
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

    // Filter and Render Table Rows
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
        <td><span class="badge badge-low" style="background: rgba(139, 92, 246, 0.2); color: #C4B5FD;">${suggestedChart}</span></td>
        <td><span class="badge ${statusClass}">${r.status || 'Pending Review'}</span></td>
        <td style="font-size: 0.8rem; color: var(--text-muted);">${new Date(r.scannedAt).toLocaleDateString()}</td>
        <td>
          <div style="display: flex; gap: 0.4rem;">
            <button class="btn-table-edit" data-id="${r.id}" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; background: var(--accent-violet); border: none; border-radius: var(--radius-sm); color: #fff; cursor: pointer;">
              ✏️ Edit Data
            </button>
            <button class="btn-table-delete" data-id="${r.id}" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; background: rgba(244, 63, 94, 0.2); border: 1px solid var(--accent-rose); border-radius: var(--radius-sm); color: var(--accent-rose); cursor: pointer;">
              🗑️
            </button>
          </div>
        </td>
      `;

      adminRecordsTableBody.appendChild(tr);
    });

    // Wire Edit Buttons
    document.querySelectorAll('.btn-table-edit').forEach(btn => {
      btn.addEventListener('click', () => openRecordEditModal(btn.getAttribute('data-id')));
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
    metaSection.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; background: var(--bg-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);';

    metaSection.innerHTML = `
      <div>
        <label class="form-label" style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.35rem; display: block;">File Name</label>
        <input type="text" id="editFileName" class="form-input" value="${record.fileName || ''}">
      </div>
      <div>
        <label class="form-label" style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.35rem; display: block;">Category / Classification</label>
        <input type="text" id="editDocType" class="form-input" value="${record.docType || 'General Institutional Data'}">
      </div>
      <div>
        <label class="form-label" style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.35rem; display: block;">Approval Status</label>
        <select id="editStatus" class="form-input">
          <option value="Pending Review" ${(record.status === 'Pending Review' || !record.status) ? 'selected' : ''}>Pending Review</option>
          <option value="Approved" ${record.status === 'Approved' ? 'selected' : ''}>Approved for Dashboard</option>
          <option value="Needs Revision" ${record.status === 'Needs Revision' ? 'selected' : ''}>Needs Revision</option>
        </select>
      </div>
      <div style="grid-column: 1 / -1;">
        <label class="form-label" style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.35rem; display: block;">Admin Verification Notes & Logs</label>
        <textarea id="editAdminNotes" class="form-input" rows="2" placeholder="Add administrative verification notes...">${record.adminNotes || ''}</textarea>
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
        tableSection.style.cssText = 'margin-bottom: 1.25rem; background: var(--bg-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);';

        tableSection.innerHTML = `
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <div>
              <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--accent-cyan); display: flex; align-items: center; gap: 0.5rem;">
                <span>📊</span> Extracted Table Cells (${activeSheetKey})
              </h4>
              <p style="font-size: 0.78rem; color: var(--text-muted);">Click inside any cell to edit its value directly.</p>
            </div>
            <button id="btnAddRowBtn" type="button" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: rgba(16, 185, 129, 0.2); border: 1px solid var(--accent-emerald); color: var(--accent-emerald); border-radius: var(--radius-sm); cursor: pointer; font-weight: 600;">
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
        tHtml += `<th style="width: 50px;">Action</th></tr></thead><tbody id="editableTableBody">`;

        (sheet.rows || []).slice(0, 50).forEach((row, rIdx) => {
          tHtml += `<tr>`;
          sheet.headers.forEach((h, cIdx) => {
            const cellVal = row[cIdx] !== undefined && row[cIdx] !== null ? row[cIdx] : '';
            tHtml += `<td><input type="text" class="cell-input" data-sheet="${activeSheetKey}" data-row="${rIdx}" data-col="${cIdx}" value="${String(cellVal).replace(/"/g, '&quot;')}" style="background: rgba(15,23,42,0.6); border: 1px solid var(--border-light); color: var(--text-light); padding: 0.35rem 0.6rem; border-radius: var(--radius-sm); width: 100%; font-size: 0.82rem; font-family: var(--font-mono);"></td>`;
          });
          tHtml += `<td style="text-align: center;"><button type="button" class="btn-delete-row" data-sheet="${activeSheetKey}" data-row="${rIdx}" style="background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 1rem;" title="Delete row">✕</button></td></tr>`;
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
    textSection.style.cssText = 'background: var(--bg-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);';
    textSection.innerHTML = `
      <label class="form-label" style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.35rem; display: block;">
        📝 Extracted Text & OCR Content
      </label>
      <textarea id="editRawText" class="form-input" rows="6" style="font-family: var(--font-mono); font-size: 0.82rem; line-height: 1.5;">${record.rawText || ''}</textarea>
    `;
    recordEditBody.appendChild(textSection);

    // 4. Modal Action Buttons Footer
    const actionRow = document.createElement('div');
    actionRow.style.cssText = 'display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-light);';

    actionRow.innerHTML = `
      <button id="btnCancelEdit" type="button" class="btn-icon" style="background: transparent; border: 1px solid var(--border-light); cursor: pointer;">Cancel</button>
      <button id="btnSaveRecordChanges" type="button" class="btn-icon" style="background: var(--accent-violet); border: none; cursor: pointer;">💾 Save Changes</button>
      <button id="btnApproveDraft" type="button" class="btn-icon" style="background: var(--accent-emerald); border: none; color: #fff; cursor: pointer;">✅ Approve for Dashboard</button>
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

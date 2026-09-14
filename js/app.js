/**
 * IRIS AI - Main Web Application Controller & User Interface Core
 */

document.addEventListener('DOMContentLoaded', async () => {
  // Initialize Core Services
  const scanner = new ScannerOrchestrator();
  const dbManager = scanner.dbManager;

  // State Management
  let queue = [];
  let activeScan = null;
  let isMasked = true;
  let currentChatHistory = [];

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
  const riskGauge = document.getElementById('riskGauge');
  const riskScoreVal = document.getElementById('riskScoreVal');
  const riskLevelBadge = document.getElementById('riskLevelBadge');
  const executiveSummaryText = document.getElementById('executiveSummaryText');
  const takeawayList = document.getElementById('takeawayList');
  const aiModelBadge = document.getElementById('aiModelBadge');
  const btnRedactCopy = document.getElementById('btnRedactCopy');
  const btnExportJson = document.getElementById('btnExportJson');
  const btnExportMd = document.getElementById('btnExportMd');

  // DOM Elements - Viewer Tab
  const viewerContentArea = document.getElementById('viewerContentArea');
  const viewerFileMeta = document.getElementById('viewerFileMeta');
  const sheetSelectorContainer = document.getElementById('sheetSelectorContainer');
  const sheetSelect = document.getElementById('sheetSelect');

  // DOM Elements - Security Tab
  const piiBadgeCount = document.getElementById('piiBadgeCount');
  const piiFindingsList = document.getElementById('piiFindingsList');
  const btnMaskToggle = document.getElementById('btnMaskToggle');

  // DOM Elements - Graphs Tab
  const graphDraftsContainer = document.getElementById('graphDraftsContainer');

  // DOM Elements - Chat Tab
  const chatMessages = document.getElementById('chatMessages');
  const chatInput = document.getElementById('chatInput');
  const btnSendChat = document.getElementById('btnSendChat');

  // DOM Elements - Settings Modal
  const btnSettings = document.getElementById('btnSettings');
  const settingsModal = document.getElementById('settingsModal');
  const btnCloseSettings = document.getElementById('btnCloseSettings');
  const btnSaveSettings = document.getElementById('btnSaveSettings');
  const geminiApiKeyInput = document.getElementById('geminiApiKeyInput');
  const geminiModelSelect = document.getElementById('geminiModelSelect');

  // DOM Elements - Admin Portal
  const statTotalDb = document.getElementById('statTotalDb');
  const statPendingDb = document.getElementById('statPendingDb');
  const statVerifiedDb = document.getElementById('statVerifiedDb');
  const statLeaksDb = document.getElementById('statLeaksDb');
  const adminSearchInput = document.getElementById('adminSearchInput');
  const adminStatusFilter = document.getElementById('adminStatusFilter');
  const adminRecordsTableBody = document.getElementById('adminRecordsTableBody');
  const btnExportDbJson = document.getElementById('btnExportDbJson');
  const btnExportDbCsv = document.getElementById('btnExportDbCsv');
  const btnExportDbSql = document.getElementById('btnExportDbSql');

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

      if (sampleType === 'invoice') {
        sampleFile = await SampleGenerator.createSampleInvoiceImage();
      } else if (sampleType === 'payroll') {
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
  // Scan Execution Pipeline
  // ----------------------------------------------------
  async function handleFiles(files) {
    progressCard.style.display = 'block';
    workspaceGrid.style.display = 'grid';

    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      try {
        const scanPackage = await scanner.scanFile(file, ({ status, progress }) => {
          progressStatus.textContent = `[${i + 1}/${files.length}] ${status}`;
          progressPercent.textContent = `${progress}%`;
          progressFill.style.width = `${progress}%`;
        });

        queue.unshift(scanPackage);
        renderQueue();
        setActiveScan(scanPackage);
      } catch (err) {
        alert(`Error scanning ${file.name}: ${err.message}`);
      }
    }

    setTimeout(() => {
      progressCard.style.display = 'none';
    }, 1200);
  }

  // Render Queue List
  function renderQueue() {
    queueCount.textContent = queue.length;
    queueList.innerHTML = '';

    queue.forEach(item => {
      const div = document.createElement('div');
      div.className = `queue-item ${activeScan && activeScan.id === item.id ? 'active' : ''}`;
      
      const badgeClass = (item.piiResult.riskLevel || 'SAFE').toLowerCase();

      div.innerHTML = `
        <div class="queue-item-header">
          <span class="queue-file-name" title="${item.name}">${item.name}</span>
          <span class="queue-badge ${badgeClass}">${item.piiResult.riskLevel}</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); display: flex; justify-content: space-between;">
          <span>${item.type.toUpperCase()} • ${(item.size / 1024).toFixed(1)} KB</span>
          <span>${item.piiResult.totalFindings} Leaks</span>
        </div>
      `;

      div.addEventListener('click', () => setActiveScan(item));
      queueList.appendChild(div);
    });
  }

  btnClearQueue.addEventListener('click', () => {
    queue = [];
    activeScan = null;
    renderQueue();
    workspaceGrid.style.display = 'none';
  });

  // ----------------------------------------------------
  // Set Active Scan & Render Workspace Tabs
  // ----------------------------------------------------
  function setActiveScan(scan) {
    activeScan = scan;
    currentChatHistory = [];
    renderQueue();

    // Render Tab 1 Overview
    const score = scan.piiResult.riskScore;
    riskScoreVal.textContent = score;
    
    let color = '#10B981';
    if (score >= 70) color = '#EF4444';
    else if (score >= 40) color = '#F59E0B';
    else if (score >= 15) color = '#06B6D4';

    riskGauge.style.background = `conic-gradient(${color} ${score * 3.6}deg, rgba(30,41,59,0.9) ${score * 3.6}deg)`;
    riskLevelBadge.textContent = `${scan.piiResult.riskLevel} RISK (${score}/100)`;
    riskLevelBadge.className = `badge badge-${scan.piiResult.riskLevel.toLowerCase()}`;

    executiveSummaryText.textContent = scan.aiAnalysis.summary;
    aiModelBadge.textContent = scan.aiAnalysis.source || 'IRIS Local AI';

    takeawayList.innerHTML = '';
    (scan.aiAnalysis.takeaways || []).forEach(t => {
      const li = document.createElement('li');
      li.className = 'takeaway-item';
      li.textContent = t;
      takeawayList.appendChild(li);
    });

    // Security PII Tab Badge
    piiBadgeCount.textContent = scan.piiResult.totalFindings;
    renderPIIFindings();

    // Render Data & Content Viewer
    renderViewer();

    // Render Graph Drafts
    renderGraphDrafts();

    // Reset Chat
    chatMessages.innerHTML = `
      <div class="chat-bubble ai">
        Ready to analyze <strong>${scan.name}</strong>. Ask any question about extracted values, compliance, or insights.
      </div>
    `;
  }

  // ----------------------------------------------------
  // TAB 2: Data & Content Viewer Renderer
  // ----------------------------------------------------
  function renderViewer() {
    if (!activeScan) return;

    viewerFileMeta.textContent = `${activeScan.name} (${activeScan.type.toUpperCase()}) • Scanned ${new Date(activeScan.scannedAt).toLocaleTimeString()}`;
    sheetSelectorContainer.style.display = 'none';
    viewerContentArea.innerHTML = '';

    if (activeScan.type === 'image') {
      const imgContainer = document.createElement('div');
      imgContainer.style.textAlign = 'center';
      
      const img = document.createElement('img');
      img.src = activeScan.previewUrl;
      img.style.maxWidth = '100%';
      img.style.maxHeight = '420px';
      img.style.borderRadius = 'var(--radius-md)';
      img.style.border = '1px solid var(--border-light)';

      const ocrBox = document.createElement('div');
      ocrBox.style.marginTop = '1rem';
      ocrBox.style.textAlign = 'left';
      ocrBox.style.background = 'var(--bg-card)';
      ocrBox.style.padding = '1rem';
      ocrBox.style.borderRadius = 'var(--radius-md)';
      ocrBox.style.fontFamily = 'var(--font-mono)';
      ocrBox.style.fontSize = '0.85rem';
      ocrBox.innerHTML = `<strong>OCR Extracted Text (Confidence: ${activeScan.metadata.ocrConfidence}%):</strong><br><br>${(activeScan.rawText || 'No text recognized').replace(/\n/g, '<br>')}`;

      imgContainer.appendChild(img);
      imgContainer.appendChild(ocrBox);
      viewerContentArea.appendChild(imgContainer);

    } else if (activeScan.type === 'excel') {
      const sheets = activeScan.sheetsData || {};
      const sheetNames = Object.keys(sheets);

      if (sheetNames.length > 0) {
        sheetSelectorContainer.style.display = 'block';
        sheetSelect.innerHTML = '';
        sheetNames.forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = `${name} (${sheets[name].rowCount} rows)`;
          sheetSelect.appendChild(opt);
        });

        const renderSheetTable = (sheetName) => {
          const sheet = sheets[sheetName];
          if (!sheet) return;

          let html = `
            <div class="table-container" style="max-height: 480px;">
              <table class="data-table">
                <thead>
                  <tr>
                    <th style="width: 50px;">#</th>
                    ${(sheet.headers || []).map(h => `<th>${h}</th>`).join('')}
                  </tr>
                </thead>
                <tbody>
                  ${(sheet.rows || []).map((row, rIdx) => `
                    <tr>
                      <td style="color: var(--text-dim);">${rIdx + 1}</td>
                      ${row.map((cell, cIdx) => `<td class="editable-cell" data-sheet="${sheetName}" data-row="${rIdx}" data-col="${cIdx}">${cell}</td>`).join('')}
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `;
          viewerContentArea.innerHTML = html;

          // Attach inline edit handlers
          viewerContentArea.querySelectorAll('.editable-cell').forEach(cellEl => {
            cellEl.addEventListener('dblclick', () => {
              const currentVal = cellEl.textContent;
              const newVal = prompt('Edit cell value:', currentVal);
              if (newVal !== null && newVal !== currentVal) {
                cellEl.textContent = newVal;
                const r = parseInt(cellEl.getAttribute('data-row'), 10);
                const c = parseInt(cellEl.getAttribute('data-col'), 10);
                const s = cellEl.getAttribute('data-sheet');
                dbManager.updateDataCell(activeScan.id, s, r, c, newVal);
              }
            });
          });
        };

        sheetSelect.onchange = (e) => renderSheetTable(e.target.value);
        renderSheetTable(sheetNames[0]);
      }

    } else if (activeScan.type === 'docx') {
      const docDiv = document.createElement('div');
      docDiv.style.background = 'var(--bg-card)';
      docDiv.style.padding = '1.5rem';
      docDiv.style.borderRadius = 'var(--radius-md)';
      docDiv.style.maxHeight = '480px';
      docDiv.style.overflowY = 'auto';
      docDiv.innerHTML = activeScan.formattedHtml || `<pre>${activeScan.rawText}</pre>`;
      viewerContentArea.appendChild(docDiv);

    } else if (activeScan.type === 'pdf') {
      const pdfWrapper = document.createElement('div');
      pdfWrapper.style.textAlign = 'center';

      const canvas = document.createElement('canvas');
      canvas.style.maxWidth = '100%';
      canvas.style.borderRadius = 'var(--radius-md)';
      canvas.style.border = '1px solid var(--border-light)';

      const controls = document.createElement('div');
      controls.style.marginTop = '1rem';
      controls.style.display = 'flex';
      controls.style.justifyContent = 'center';
      controls.style.gap = '1rem';
      controls.style.alignItems = 'center';

      let currentPage = 1;
      const totalPages = activeScan.metadata.pageCount || 1;

      const pageIndicator = document.createElement('span');
      pageIndicator.style.fontSize = '0.85rem';
      pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;

      const prevBtn = document.createElement('button');
      prevBtn.className = 'btn-icon';
      prevBtn.textContent = '◀ Previous';
      prevBtn.onclick = () => {
        if (currentPage > 1) {
          currentPage--;
          pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;
          scanner.pdfParser.renderPageToCanvas(activeScan.pdfDocReference, currentPage, canvas);
        }
      };

      const nextBtn = document.createElement('button');
      nextBtn.className = 'btn-icon';
      nextBtn.textContent = 'Next ▶';
      nextBtn.onclick = () => {
        if (currentPage < totalPages) {
          currentPage++;
          pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;
          scanner.pdfParser.renderPageToCanvas(activeScan.pdfDocReference, currentPage, canvas);
        }
      };

      controls.appendChild(prevBtn);
      controls.appendChild(pageIndicator);
      controls.appendChild(nextBtn);

      pdfWrapper.appendChild(canvas);
      pdfWrapper.appendChild(controls);
      viewerContentArea.appendChild(pdfWrapper);

      if (activeScan.pdfDocReference) {
        scanner.pdfParser.renderPageToCanvas(activeScan.pdfDocReference, 1, canvas);
      }
    }
  }

  // ----------------------------------------------------
  // TAB 3: Security & PII Audit Findings
  // ----------------------------------------------------
  function renderPIIFindings() {
    if (!activeScan) return;
    piiFindingsList.innerHTML = '';

    const findings = activeScan.piiResult.findings || [];

    if (findings.length === 0) {
      piiFindingsList.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: var(--accent-emerald);">
          <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛡️</div>
          <h3 style="font-size: 1.1rem; font-weight: 700;">No Sensitive Data Leaks Detected</h3>
          <p style="font-size: 0.85rem; color: var(--text-muted);">This document is clean of credit card numbers, SSNs, API tokens, and passwords.</p>
        </div>
      `;
      return;
    }

    findings.forEach(item => {
      const div = document.createElement('div');
      div.className = 'pii-card';

      const displayVal = isMasked ? item.masked : item.raw;
      const severityClass = `badge-${item.severity}`;

      div.innerHTML = `
        <div class="pii-info">
          <div class="pii-title">
            <span>${item.name}</span>
            <span class="badge ${severityClass}">${item.severity.toUpperCase()}</span>
            <span style="font-size: 0.72rem; color: var(--text-dim); font-weight: 400;">(${item.compliance})</span>
          </div>
          <div style="font-family: var(--font-mono); font-size: 0.9rem; color: var(--accent-cyan); margin-top: 0.2rem;">
            ${displayVal}
          </div>
          <div class="pii-context">
            Context: "${item.context}"
          </div>
        </div>
      `;

      piiFindingsList.appendChild(div);
    });
  }

  btnMaskToggle.addEventListener('click', () => {
    isMasked = !isMasked;
    btnMaskToggle.innerHTML = isMasked ? '<span>👁️</span> Mask / Reveal Sensitive Values' : '<span>🔒</span> Re-Mask Sensitive Values';
    renderPIIFindings();
  });

  btnRedactCopy.addEventListener('click', () => {
    if (activeScan && activeScan.piiResult.redactedText) {
      navigator.clipboard.writeText(activeScan.piiResult.redactedText);
      alert('Redacted text copied to clipboard!');
    }
  });

  btnExportJson.addEventListener('click', () => {
    if (!activeScan) return;
    const blob = new Blob([JSON.stringify(activeScan, null, 2)], { type: 'application/json' });
    dbManager.triggerDownload(blob, `${activeScan.name}_audit.json`);
  });

  btnExportMd.addEventListener('click', () => {
    if (!activeScan) return;
    let md = `# IRIS AI File Audit Report: ${activeScan.name}\n`;
    md += `- **Format**: ${activeScan.type.toUpperCase()}\n`;
    md += `- **Risk Score**: ${activeScan.piiResult.riskScore}/100 (${activeScan.piiResult.riskLevel})\n`;
    md += `- **Doc Type**: ${activeScan.aiAnalysis.docType}\n\n`;
    md += `## Executive Summary\n${activeScan.aiAnalysis.summary}\n\n`;
    md += `## Sensitive Data Findings (${activeScan.piiResult.totalFindings})\n`;
    (activeScan.piiResult.findings || []).forEach(f => {
      md += `- **${f.name}** [${f.severity.toUpperCase()}]: ${f.masked} (${f.compliance})\n`;
    });
    const blob = new Blob([md], { type: 'text/markdown' });
    dbManager.triggerDownload(blob, `${activeScan.name}_report.md`);
  });

  // ----------------------------------------------------
  // TAB 4: Data Graph Visualization Drafts
  // ----------------------------------------------------
  function renderGraphDrafts() {
    if (!activeScan) return;
    graphDraftsContainer.innerHTML = '';

    const drafts = activeScan.graphDrafts || [];

    if (drafts.length === 0) {
      graphDraftsContainer.innerHTML = `<p style="color: var(--text-muted);">No numerical data tables available for charting.</p>`;
      return;
    }

    drafts.forEach((draft, idx) => {
      const card = document.createElement('div');
      card.className = 'graph-card';

      const canvasId = `chart_canvas_${idx}`;

      card.innerHTML = `
        <div class="graph-header">
          <div>
            <h4 style="font-size: 1.1rem; font-weight: 700;">${draft.title}</h4>
            <div style="font-size: 0.8rem; color: var(--accent-cyan); margin-top: 0.2rem;">
              💡 <strong>AI Graph Recommendation:</strong> ${draft.recommendationReason}
            </div>
          </div>
          <div style="display: flex; gap: 0.5rem; align-items: center;">
            <label style="font-size: 0.8rem; color: var(--text-muted);">Graph Type:</label>
            <select class="chart-type-select form-input" data-draft-idx="${idx}" style="width: auto; padding: 0.3rem 0.6rem;">
              ${(draft.suggestedTypes || ['bar', 'line', 'doughnut', 'radar']).map(t => `
                <option value="${t}" ${t === draft.config.type ? 'selected' : ''}>${t.toUpperCase()}</option>
              `).join('')}
            </select>
          </div>
        </div>
        <div class="chart-canvas-container">
          <canvas id="${canvasId}"></canvas>
        </div>
      `;

      graphDraftsContainer.appendChild(card);

      setTimeout(() => {
        const canvasEl = document.getElementById(canvasId);
        scanner.graphEngine.renderChart(canvasEl, draft.config);

        // Chart Type Switcher Listener
        card.querySelector('.chart-type-select').addEventListener('change', (e) => {
          draft.config.type = e.target.value;
          scanner.graphEngine.renderChart(canvasEl, draft.config);
        });
      }, 50);
    });
  }

  // ----------------------------------------------------
  // TAB 5: Interactive Chat with Document
  // ----------------------------------------------------
  async function sendChatMessage() {
    const q = chatInput.value.trim();
    if (!q || !activeScan) return;

    chatInput.value = '';

    // Append User Bubble
    const userDiv = document.createElement('div');
    userDiv.className = 'chat-bubble user';
    userDiv.textContent = q;
    chatMessages.appendChild(userDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Append AI Thinking Bubble
    const aiDiv = document.createElement('div');
    aiDiv.className = 'chat-bubble ai';
    aiDiv.textContent = 'Analyzing document content...';
    chatMessages.appendChild(aiDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    const answer = await scanner.geminiService.askQuestion(q, activeScan, currentChatHistory);
    aiDiv.innerHTML = answer.replace(/\n/g, '<br>');
    chatMessages.scrollTop = chatMessages.scrollHeight;

    currentChatHistory.push({ sender: 'user', text: q });
    currentChatHistory.push({ sender: 'ai', text: answer });
  }

  btnSendChat.addEventListener('click', sendChatMessage);
  chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendChatMessage();
  });

  // ----------------------------------------------------
  // Workspace Tab Switcher
  // ----------------------------------------------------
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      tabPanels.forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const targetId = btn.getAttribute('data-tab');
      document.getElementById(targetId).classList.add('active');

      if (targetId === 'tabGraphs') {
        renderGraphDrafts();
      }
    });
  });

  // ----------------------------------------------------
  // Settings Modal Handlers
  // ----------------------------------------------------
  btnSettings.addEventListener('click', () => {
    geminiApiKeyInput.value = scanner.geminiService.apiKey;
    geminiModelSelect.value = scanner.geminiService.selectedModel;
    settingsModal.classList.add('active');
  });

  btnCloseSettings.addEventListener('click', () => {
    settingsModal.classList.remove('active');
  });

  btnSaveSettings.addEventListener('click', () => {
    scanner.geminiService.setApiKey(geminiApiKeyInput.value);
    scanner.geminiService.setModel(geminiModelSelect.value);
    settingsModal.classList.remove('active');
    alert('Settings saved successfully!');
  });

  // ----------------------------------------------------
  // ADMIN DATABASE PORTAL RENDERER & ACTIONS
  // ----------------------------------------------------
  async function renderAdminPortal() {
    const records = await dbManager.getAllRecords();

    // Update Stats
    statTotalDb.textContent = records.length;
    statPendingDb.textContent = records.filter(r => r.status === 'Pending Review').length;
    statVerifiedDb.textContent = records.filter(r => r.status === 'Verified & Approved').length;
    statLeaksDb.textContent = records.filter(r => r.riskScore >= 40).length;

    // Filter Logic
    const searchTerm = adminSearchInput.value.toLowerCase().trim();
    const statusFilter = adminStatusFilter.value;

    const filtered = records.filter(r => {
      const matchesSearch = !searchTerm || 
        r.fileName.toLowerCase().includes(searchTerm) || 
        r.docType.toLowerCase().includes(searchTerm) || 
        r.rawText.toLowerCase().includes(searchTerm);

      const matchesStatus = statusFilter === 'all' || r.status === statusFilter;
      return matchesSearch && matchesStatus;
    });

    adminRecordsTableBody.innerHTML = '';

    if (filtered.length === 0) {
      adminRecordsTableBody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 2rem;">
            No records found in database matching criteria.
          </td>
        </tr>
      `;
      return;
    }

    filtered.forEach(r => {
      const tr = document.createElement('tr');

      tr.innerHTML = `
        <td style="font-family: var(--font-mono); font-size: 0.78rem;">${r.id}</td>
        <td style="font-weight: 600;">${r.fileName}</td>
        <td><span class="format-chip ${r.fileType}">${r.fileType.toUpperCase()}</span></td>
        <td>${r.docType}</td>
        <td><span class="badge badge-${r.riskLevel.toLowerCase()}">${r.riskScore}/100</span></td>
        <td>
          <select class="admin-status-select form-input" data-id="${r.id}" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">
            <option value="Pending Review" ${r.status === 'Pending Review' ? 'selected' : ''}>Pending Review</option>
            <option value="Verified & Approved" ${r.status === 'Verified & Approved' ? 'selected' : ''}>Verified & Approved</option>
            <option value="Flagged / Needs Revision" ${r.status === 'Flagged / Needs Revision' ? 'selected' : ''}>Flagged / Needs Revision</option>
          </select>
        </td>
        <td style="font-size: 0.78rem; color: var(--text-muted);">${new Date(r.scannedAt).toLocaleDateString()}</td>
        <td>
          <button class="btn-icon btn-edit-record" data-id="${r.id}" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">✏️ Edit</button>
          <button class="btn-icon btn-delete-record" data-id="${r.id}" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; border-color: rgba(239,68,68,0.4);">🗑️</button>
        </td>
      `;

      adminRecordsTableBody.appendChild(tr);
    });

    // Attach Status Selector Change Handlers
    document.querySelectorAll('.admin-status-select').forEach(sel => {
      sel.addEventListener('change', async (e) => {
        const id = sel.getAttribute('data-id');
        await dbManager.updateRecord(id, { status: e.target.value });
        await renderAdminPortal();
      });
    });

    // Attach Edit Handlers
    document.querySelectorAll('.btn-edit-record').forEach(btn => {
      btn.addEventListener('click', () => openRecordEditModal(btn.getAttribute('data-id')));
    });

    // Attach Delete Handlers
    document.querySelectorAll('.btn-delete-record').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        if (confirm(`Are you sure you want to delete database record ${id}?`)) {
          await dbManager.deleteRecord(id);
          await renderAdminPortal();
        }
      });
    });
  }

  adminSearchInput.addEventListener('input', renderAdminPortal);
  adminStatusFilter.addEventListener('change', renderAdminPortal);

  // Admin Database Exports
  btnExportDbJson.addEventListener('click', () => dbManager.exportDatabase('json'));
  btnExportDbCsv.addEventListener('click', () => dbManager.exportDatabase('csv'));
  btnExportDbSql.addEventListener('click', () => dbManager.exportDatabase('sql'));

  // ----------------------------------------------------
  // Admin Record Detail & Inline Table Data Editor Modal
  // ----------------------------------------------------
  async function openRecordEditModal(id) {
    const records = await dbManager.getAllRecords();
    const record = records.find(r => r.id === id);
    if (!record) return;

    recordEditTitle.textContent = `Admin Record Editor - ${record.fileName} (${record.id})`;

    recordEditBody.innerHTML = `
      <div class="form-group">
        <label class="form-label">Document Title / File Name</label>
        <input type="text" id="editFileName" class="form-input" value="${record.fileName}">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Document Category</label>
          <input type="text" id="editDocType" class="form-input" value="${record.docType}">
        </div>
        <div class="form-group">
          <label class="form-label">Review Status</label>
          <select id="editStatus" class="form-input">
            <option value="Pending Review" ${record.status === 'Pending Review' ? 'selected' : ''}>Pending Review</option>
            <option value="Verified & Approved" ${record.status === 'Verified & Approved' ? 'selected' : ''}>Verified & Approved</option>
            <option value="Flagged / Needs Revision" ${record.status === 'Flagged / Needs Revision' ? 'selected' : ''}>Flagged / Needs Revision</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Admin Audit Notes</label>
        <textarea id="editAdminNotes" class="form-input" rows="3" placeholder="Add custom admin verification notes...">${record.adminNotes || ''}</textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
        <button id="btnSaveRecordEdit" class="btn-icon" style="background: var(--accent-violet); border: none;">
          Save Record Changes
        </button>
      </div>
    `;

    recordEditModal.classList.add('active');

    document.getElementById('btnSaveRecordEdit').addEventListener('click', async () => {
      const updated = {
        fileName: document.getElementById('editFileName').value,
        docType: document.getElementById('editDocType').value,
        status: document.getElementById('editStatus').value,
        adminNotes: document.getElementById('editAdminNotes').value
      };

      await dbManager.updateRecord(id, updated);
      recordEditModal.classList.remove('active');
      await renderAdminPortal();
    });
  }

  btnCloseRecordModal.addEventListener('click', () => {
    recordEditModal.classList.remove('active');
  });

});

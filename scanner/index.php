<?php require_once __DIR__ . '/../includes/functions.php'; require_once __DIR__ . '/../includes/config/upload_limits.php'; require_once __DIR__ . '/../includes/assets/asset_bundles.php'; require_admin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CLSU Performance Observatory — IRIS AI File Scanner & Ingestion System</title>
  <meta name="description" content="Central Luzon State University (CLSU) IRIS AI File Scanner for Spreadsheets, PDFs, and Word DOCX with draft chart suggestions and Admin data editor.">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { corePlugins: { preflight: false } };
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite@2.5.2/dist/flowbite.min.css">
  <script>window.IRIS_MAX_UPLOAD_BYTES = <?= IRIS_MAX_UPLOAD_BYTES ?>;</script>
  
  <!-- CSS Stylesheet -->
  <?php render_iris_stylesheet_bundle(); ?>
  <link rel="stylesheet" href="css/tokens.css">

  <!-- External Parsing & Charting CDN Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>
  <script src="js/charts/chartConfig.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body>

  <!-- App Header — CLSU Institutional Style -->
  <header class="app-header">
    <div class="app-header-inner">
      <div class="brand-container">
        <div class="brand-logo-seal"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
        <div>
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span class="brand-title">CLSU Performance Observatory</span>
            <span class="brand-badge">IRIS File Ingestion</span>
          </div>
          <div class="brand-subline">Central Luzon State University • International Rapport Insight System</div>
        </div>
      </div>

      <nav class="header-nav">
        <a href="../user/dashboard.php" class="nav-btn"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Observatory</a>
        <button id="navScannerBtn" class="nav-btn active">
          <span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span> Review Workspace
        </button>
        <button id="navArchivesBtn" class="nav-btn" type="button">
          <span><i class="fa-solid fa-box-archive" aria-hidden="true"></i></span> File Archives
        </button>
      </nav>
    </div>
  </header>

  <button id="uploadWidgetTrigger" class="floating-upload-trigger" type="button" aria-label="Open institutional upload window">
    <span class="floating-upload-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
  </button>

  <div id="uploadWidgetModal" class="upload-widget-modal" aria-hidden="true">
    <div class="upload-widget-panel">
      <div class="upload-widget-header">
        <div>
          <div class="upload-widget-kicker">File Intake</div>
          <div class="upload-widget-title">Institutional Document Upload</div>
        </div>
        <button id="closeUploadWidget" class="upload-widget-close" type="button" aria-label="Close upload window">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>

      <div id="dropzone" class="dropzone-container upload-dropzone">
        <div class="dropzone-icon">
          <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
          </svg>
        </div>

        <h1 class="dropzone-title">Upload Spreadsheets</h1>
        <p class="dropzone-subtitle">Multi-sheet parsing, institutional text extraction, and draft visualization suggestions for university performance metrics</p>

        <div class="format-badges">
          <span class="format-chip excel"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Spreadsheets (XLSX, XLS, CSV)</span>
        </div>

        <p id="scannerUploadFileHelp" class="text-xs text-slate-500 dark:text-slate-400">Allowed: XLSX, XLS, or CSV. Maximum <?= e(iris_upload_limit_label()) ?> per file.</p>
        <input type="file" id="scannerUploadFileInput" multiple accept=".xlsx,.xls,.csv" aria-describedby="scannerUploadFileHelp" style="display: none;">

        <div style="margin-bottom: 1.5rem;">
          <button id="scannerUploadBrowseBtn" class="btn-icon" style="padding: 0.75rem 2rem; font-size: 0.95rem; margin: 0 auto;">
            <span><i class="fa-solid fa-folder" aria-hidden="true"></i></span> Browse Institutional Files
          </button>
        </div>

        <div class="samples-container flex items-center justify-center gap-3 pt-5 border-t border-slate-200 dark:border-slate-700/80 w-full overflow-hidden">
          <span class="samples-label shrink-0 whitespace-nowrap text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Test 1-Click Samples:</span>
          <div class="flex items-center gap-2 overflow-x-auto py-1 max-w-full no-scrollbar">
            <button class="sample-btn shrink-0" data-sample="iao" type="button">
              <span><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span> IAO Rankings Dataset (.xlsx)
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- App Main Container -->
  <main class="app-container">

    <!-- ================= SCANNER WORKSPACE VIEW ================= -->
    <section id="scannerWorkspaceView">

      <div class="clsu-section-title">
        <span><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span> University-Wide Overview & Ingestion
      </div>

      <!-- Real-time Progress Bar -->
      <div id="progressCard" class="progress-card">
        <div class="progress-header">
          <span id="progressStatus">Initializing scanner...</span>
          <span id="progressPercent">0%</span>
        </div>
        <div class="progress-track">
          <div id="progressFill" class="progress-fill"></div>
        </div>
      </div>

      <!-- Main Results Workspace Grid -->
      <div id="workspaceGrid" class="workspace-grid" style="display: none;">

        <!-- Left Batch Sidebar Queue (Hidden) -->
        <aside class="queue-sidebar" style="display: none !important;">
          <div class="sidebar-title">
            <span>Ingestion Queue (<span id="queueCount">0</span>)</span>
            <button id="btnClearQueue" style="background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 0.75rem; font-weight: 700;">Clear All</button>
          </div>
          <div id="queueList" class="queue-list">
            <!-- Queue Items dynamically populated -->
          </div>
        </aside>

        <!-- Right Main Inspection Panel -->
        <section class="content-workspace">

          <!-- Workspace Tabs -->
          <div class="workspace-tabs">
            <button class="tab-btn active" data-tab="tabOverview">
              <span><i class="fa-solid fa-clipboard" aria-hidden="true"></i></span> Extracted Fields & Overview
            </button>
            <button class="tab-btn" data-tab="tabViewer">
              <span><i class="fa-solid fa-eye" aria-hidden="true"></i></span> Document & Data Viewer
            </button>
            <button class="tab-btn" data-tab="tabGraphs">
              <span><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span> Draft Visualizations (<span id="draftsCountBadge">0</span>)
            </button>
          </div>

          <!-- TAB 1: EXTRACTED FIELDS & OVERVIEW -->
          <div id="tabOverview" class="tab-panel active">
            <div class="metrics-row">
              <div class="summary-card">
                <div class="summary-title">
                  <span id="summaryDocTitle">Extracted Document Analysis</span>
                  <span id="docFormatBadge" class="format-chip excel">Format</span>
                </div>
                <p id="executiveSummaryText" class="summary-text">Select or scan a file to inspect extracted fields.</p>
                
                <h4 style="font-size: 0.88rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.75rem; color: var(--clsu-green);">Extracted Data Fields & Key Metrics</h4>
                <div id="extractedFieldsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                  <!-- Dynamically populated field cards -->
                </div>

                <h4 style="font-size: 0.88rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem; color: var(--clsu-green);">Identified Structure Highlights</h4>
                <ul id="takeawayList" class="takeaway-list">
                  <!-- Highlights dynamically populated -->
                </ul>
              </div>
            </div>

            <!-- Quick Action Toolbar -->
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; padding-top: 1rem; border-top: 1px solid var(--border-light); justify-content: space-between; align-items: center;">
              <div style="font-size: 0.82rem; color: var(--text-muted);">
                Status: <span class="badge badge-low" style="display: inline-block;">Draft (Pending Admin Review)</span>
              </div>
              <div style="display: flex; gap: 0.75rem;">
                <button id="btnOpenInEditor" class="btn-icon">
                  <span><i class="fa-solid fa-pen" aria-hidden="true"></i></span> Open review editor
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 2: DATA & CONTENT VIEWER -->
          <div id="tabViewer" class="tab-panel">
            <div id="viewerControls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <span id="viewerFileMeta" style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">File Details</span>
              <div id="sheetSelectorContainer" style="display: none;">
                <label style="font-size: 0.82rem; margin-right: 0.5rem; color: var(--clsu-green); font-weight: 700;">Worksheet:</label>
                <select id="sheetSelect" class="form-input" style="width: auto; padding: 0.35rem 0.75rem; display: inline-block;"></select>
              </div>
            </div>

            <div id="viewerContentArea" style="min-height: 450px;">
              <!-- Dynamically renders Data Grid for Excel, Reader for DOCX, or PDF canvas -->
            </div>
          </div>

          <!-- TAB 3: DATA GRAPH VISUALIZATION DRAFTS -->
          <div id="tabGraphs" class="tab-panel">
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--clsu-green);">Draft Visualization Suggestions</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Institutional chart drafts (Bar, Line, Pie) pending Admin review & approval.</p>
              </div>
              <span class="badge badge-low">Draft Only — Not Auto-Published</span>
            </div>

            <div id="graphDraftsContainer">
              <!-- Graph Cards dynamically populated -->
            </div>
          </div>

        </section>
      </div>

    </section>

    <!-- ================= ADMIN DATA DASHBOARD VIEW ================= -->
    <section id="adminDatabaseView" style="display: none;">
      
      <div class="clsu-section-title">
        <span><i class="fa-solid fa-database" aria-hidden="true"></i></span> Review archive & record history
      </div>

      <div class="workspace-tabs" style="margin-bottom: 1.25rem;">
        <button class="admin-tab-btn active" data-admin-tab="adminRecordsPanel">
          <span><i class="fa-solid fa-clipboard" aria-hidden="true"></i></span> Records & Dashboard Studio
        </button>
        <button class="admin-tab-btn" data-admin-tab="adminSavedGraphsPanel">
          <span><i class="fa-solid fa-folder-tree" aria-hidden="true"></i></span> Saved Dashboard Graphs
        </button>
        <button class="admin-tab-btn" data-admin-tab="adminFileArchivesPanel">
          <span><i class="fa-solid fa-box-archive" aria-hidden="true"></i></span> File Archives
        </button>
      </div>

      <div id="adminSavedGraphsPanel" class="admin-tab-panel" style="display: none;">
        <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--clsu-green);">Saved Dashboard Graphs</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Saved charts and report visualizations from every file, grouped by record.</p>
          </div>
          <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <button id="savedGraphsViewAllBtn" type="button" class="saved-graphs-bulk-button">View All</button>
            <label style="font-size: 0.78rem; font-weight: 800; color: #334155; text-transform: uppercase;">File:</label>
            <select id="savedGraphsRecordSelect" class="form-input" style="width: auto; min-width: 220px;">
              <option value="">Loading files...</option>
            </select>
          </div>
        </div>

        <div id="savedGraphsBulkToolbar" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem 1rem; background: #F8FAF8; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
          <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 700; font-size: 0.82rem;">
            <input id="savedGraphsSelectAll" type="checkbox"> Select All
          </label>
          <span id="savedGraphsSelectionCount" style="font-size: 0.8rem; color: var(--text-muted);">0 selected</span>
          <button id="savedGraphsPrintAll" type="button" class="saved-graphs-bulk-button" disabled>Print All</button>
          <button id="savedGraphsExportSelected" type="button" class="saved-graphs-bulk-button" disabled>Export</button>
          <button id="savedGraphsPublishSelected" type="button" class="saved-graphs-bulk-button" disabled><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish</button>
          <button id="savedGraphsDeleteSelected" type="button" class="archive-delete-button" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
        </div>

        <div id="savedDashboardGraphsContainer" data-csrf="<?= e(csrf_token()) ?>">
          <!-- Saved graph cards dynamically populated -->
        </div>
      </div>

      <div id="adminRecordsPanel" class="admin-tab-panel active">

      <!-- Admin Header Banner -->
      <div style="background: #FFFFFF; border: 1px solid var(--border-light); border-left: 5px solid var(--clsu-green); border-radius: var(--radius-lg); padding: 1.5rem 2rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--card-shadow);">
        <div>
          <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--clsu-green);">Review record editor</h2>
          <p style="font-size: 0.88rem; color: var(--text-muted);">Review extracted fields, edit tabular cells, update draft status, and publish visualizations for the CLSU Observatory.</p>
        </div>
      </div>

      <!-- Admin Stats Summary Bar -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background: #FFFFFF; border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">TOTAL SCANNED FILES</div>
          <div id="statTotalDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-green);">0</div>
        </div>
        <div style="background: #FFFFFF; border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">PENDING DRAFTS</div>
          <div id="statPendingDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-gold-dark);">0</div>
        </div>
        <div style="background: #FFFFFF; border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">PUBLISHED</div>
          <div id="statVerifiedDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-green-light);">0</div>
        </div>
        <div style="background: #FFFFFF; border: 1px solid var(--border-light); padding: 1.1rem 1.25rem; border-radius: var(--radius-md); box-shadow: var(--card-shadow);">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">EXTRACTED TABLES</div>
          <div id="statTablesDb" style="font-size: 1.6rem; font-weight: 800; font-family: var(--font-mono); color: var(--clsu-green);">0</div>
        </div>
      </div>

      <!-- ================= LIVE DASHBOARD STUDIO WORKBENCH ================= -->
      <div class="studio-container" id="studioContainer" style="margin-bottom: 2rem;">
        
        <!-- Studio Header & Active Record Switcher -->
        <div class="studio-header-card">
          <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: space-between; width: 100%;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <span style="font-size: 1.4rem;"><i class="fa-solid fa-palette" aria-hidden="true"></i></span>
              <div>
                <div style="font-size: 0.75rem; font-weight: 800; color: var(--clsu-green); text-transform: uppercase; letter-spacing: 0.05em;">ACTIVE DASHBOARD STUDIO WORKBENCH</div>
                <div style="font-size: 1.2rem; font-weight: 800; color: #0F172A;" id="studioActiveFileName">Loading Scanned Dataset...</div>
              </div>
            </div>

            <!-- Quick Document Switcher -->
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <label style="font-size: 0.82rem; font-weight: 800; color: #334155; text-transform: uppercase;">Switch Dataset:</label>
              <select id="studioRecordSelect" class="form-input" style="width: auto; min-width: 250px; font-weight: 700; color: #0F172A;"></select>
            </div>
          </div>
        </div>

        <!-- Studio 2-Column Split View: Source Document (Left) vs Live Chart & Field Studio (Right) -->
        <div class="studio-grid">
          
          <!-- LEFT COLUMN: Scanned Document Window Screen (Side-by-Side Document Reader) -->
          <div class="studio-left-card">
            <div class="studio-card-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
              <span><i class="fa-solid fa-window-maximize" aria-hidden="true"></i> Scanned Source Document Window</span>
              <span class="badge badge-low" style="font-size: 0.68rem; background: #ECFDF5; color: #047857;">Live Ingestion View</span>
            </div>
            <p style="font-size: 0.78rem; color: #64748B; margin-bottom: 0.75rem;">
              Read the full file directly side-by-side. Click any cell or word to copy value directly into your dashboard fields.
            </p>

            <!-- Document Viewer Container (Adobe Acrobat Style Document Viewer) -->
            <div class="doc-viewer-container">
              <!-- Adobe Acrobat Style Dark Sleek Toolbar -->
              <div class="acrobat-toolbar">
                <div class="acrobat-title-group">
                  <span class="acrobat-badge-icon" id="acrobatDocBadge">PDF</span>
                  <span class="acrobat-filename" id="docWindowTitle">document.docx</span>
                </div>

                <!-- Page Navigator Controls -->
                <div class="acrobat-controls-center" id="acrobatPageNavControls">
                  <button type="button" id="btnAcrobatPrevPage" class="acrobat-tool-btn" title="Previous Page">▲</button>
                  <input type="number" id="acrobatCurrentPageInput" class="acrobat-page-input" value="1" min="1" max="1" title="Go to Page">
                  <span style="font-size: 0.72rem; color: #94A3B8;">/</span>
                  <span id="acrobatTotalPagesSpan" style="font-size: 0.72rem; color: #E2E8F0; font-weight: 600;">1</span>
                  <button type="button" id="btnAcrobatNextPage" class="acrobat-tool-btn" title="Next Page">▼</button>
                </div>

                <!-- Zoom & Sheet Controls -->
                <div class="acrobat-controls-right">
                  <!-- Sheet Selector (for spreadsheets) -->
                  <div id="studioDocSheetSelectorContainer" style="display: none; align-items: center; gap: 0.35rem;">
                    <span style="font-size: 0.72rem; color: #CBD5E1; font-weight: 600;">Sheet:</span>
                    <select id="studioDocSheetSelect" class="form-input doc-sheet-select" style="background: #202225 !important; color: #FFF !important; border-color: #4A4E53 !important;"></select>
                  </div>

                  <!-- Zoom Controls (for Word/PDF/OCR) -->
                  <div id="acrobatZoomControlsGroup" style="display: flex; align-items: center; gap: 0.25rem; background: #202225; padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid #3A3E42;">
                    <button type="button" id="btnAcrobatZoomOut" class="acrobat-tool-btn" title="Zoom Out">−</button>
                    <span id="acrobatZoomValue" class="acrobat-zoom-label">100%</span>
                    <button type="button" id="btnAcrobatZoomIn" class="acrobat-tool-btn" title="Zoom In">+</button>
                    <button type="button" id="btnAcrobatFitWidth" class="acrobat-tool-btn" title="Fit Width" style="font-size: 0.68rem; margin-left: 2px;">↔</button>
                  </div>

                  <!-- Hidden span for JS stats compatibility -->
                  <span id="docWindowPageCount" style="display: none;"></span>
                  <span id="docWindowWordCount" style="display: none;"></span>
                </div>
              </div>

              <!-- Acrobat Slate Viewport Canvas (Scroll Down Pages & Side Scroll Document) -->
              <div id="studioDocContentArea" class="acrobat-viewer-body">
                <div class="acrobat-page-card">
                  <p style="color: #64748B; text-align: center;">Loading document content...</p>
                </div>
              </div>
            </div>

            <!-- Quick copy helper note -->
            <div style="margin-top: 0.65rem; font-size: 0.74rem; color: #64748B; display: flex; align-items: center; justify-content: space-between;">
              <span><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> <strong>Tip:</strong> Highlight or click any text to copy directly.</span>
              <span id="docWindowCopyStatus" style="color: var(--clsu-green); font-weight: 700;"></span>
            </div>
          </div>

          <!-- RIGHT COLUMN: Live Chart & Field Editor -->
          <div class="studio-right-card">
            
            <!-- Live Chart Visualization Card -->
            <div class="studio-chart-box">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.75rem;">
                <div style="flex: 1; min-width: 250px;">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span style="font-size: 0.82rem; font-weight: 800; color: var(--clsu-green); text-transform: uppercase;">Chart Title:</span>
                    <input type="text" id="studioChartTitleInput" class="form-input" value="Observatory Draft" placeholder="Type chart title..." style="padding: 0.3rem 0.65rem; font-size: 0.95rem; font-weight: 800; color: var(--clsu-green); border: 1.5px solid #CBD5E1; background: #FFFFFF; flex: 1;" title="Click to edit the chart title">
                  </div>
                  <p id="studioChartSubtitleDisplay" style="font-size: 0.78rem; color: #64748B;">Live interactive rendering from data fields below</p>
                </div>
                
                <!-- Chart Type Selector -->
                <div class="studio-chart-type-group">
                  <label class="studio-field-mapping-label">Chart Type:</label>
                  <select id="studioChartTypeSelect" class="form-input studio-chart-type-select" style="font-size: 0.82rem; font-weight: 700; color: #0F172A;">
                    <option value="line">Line Chart</option>
                    <option value="stackedArea">Stacked Area Chart</option>
                    <option value="bar">Bar Chart</option>
                    <option value="pie">Pie Chart</option>
                    <option value="doughnut">Doughnut Chart</option>
                    <option value="nestedPie">Nested Pie</option>
                  </select>
                </div>
              </div>

              <!-- Field Mapping Controls (chart-type aware) -->
              <div id="studioFieldMappingRow" class="studio-field-mapping">
                <span class="studio-field-mapping-title"><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Field Mapping:</span>
                <div class="studio-field-mapping-group">
                  <label class="studio-field-mapping-label" id="studioCategoryLabel">Category (X-axis):</label>
                  <select id="studioCategoryCol" class="form-input studio-field-mapping-select" aria-label="Category column"></select>
                </div>
                <div id="studioSeriesFieldWrapper" class="studio-field-mapping-group" style="display: none;">
                  <label for="studioSeriesField" class="studio-field-mapping-label">Series (group by):</label>
                  <select id="studioSeriesField" class="form-input studio-field-mapping-select"></select>
                </div>
                <div class="studio-field-mapping-group">
                  <label class="studio-field-mapping-label" id="studioValueLabel">Value (Y-axis):</label>
                  <select id="studioValueCol" class="form-input studio-field-mapping-select" aria-label="Value column"></select>
                </div>
                <div id="studioGroupFieldWrapper" class="studio-field-mapping-group" style="display: none;">
                  <label for="studioGroupField" class="studio-field-mapping-label">Group (inner ring):</label>
                  <select id="studioGroupField" class="form-input studio-field-mapping-select"></select>
                </div>
                <div class="studio-field-mapping-group">
                  <label class="studio-field-mapping-label" id="studioValuePrecisionLabel">Display Precision:</label>
                  <select id="studioValuePrecisionSelect" class="form-input studio-field-mapping-select" aria-label="Display precision">
                    <option value="0">No decimals</option>
                    <option value="1">1 decimal</option>
                    <option value="2" selected>2 decimals</option>
                  </select>
                </div>
                <div id="studioYearWrapper" class="studio-field-mapping-group" style="display: none;">
                  <label class="studio-field-mapping-label" for="studioYearSelect">Year:</label>
                  <select id="studioYearSelect" class="form-input studio-field-mapping-select" aria-label="Filter chart by year"></select>
                </div>
                <div id="studioReverseOrderWrapper" class="studio-field-mapping-group" style="display: none;">
                  <label class="studio-field-mapping-label" for="studioReverseOrder">
                    <input id="studioReverseOrder" type="checkbox" aria-label="Reverse chart order">Reverse order
                  </label>
                </div>
                <div id="studioFieldWarning" style="display:none; font-size: 0.75rem; color: #DC2626; font-weight: 700; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 4px; padding: 0.2rem 0.6rem;"></div>
              </div>

              <div style="background: #F8FAF8; border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 0.65rem 1rem; margin-bottom: 0.85rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem;">
                <span style="font-size: 0.78rem; font-weight: 800; color: #334155; text-transform: uppercase;"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Filter extracted rows:</span>
                <select id="studioFilterField" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Filter data scope">
                  <option value="all">All selected data</option>
                  <option value="context">Context / label only</option>
                  <option value="value">Metric / value only</option>
                </select>
                <select id="studioFilterOperator" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Filter operator">
                  <option value="all">All rows</option>
                  <option value="contains">Contains</option>
                  <option value="starts-with">Starts with</option>
                  <option value="ends-with">Ends with</option>
                  <option value="equals">Equals</option>
                  <option value="not-equals">Does not equal</option>
                  <option value="greater-than">Value greater than</option>
                  <option value="less-than">Value less than</option>
                  <option value="between">Value between</option>
                </select>
                <input id="studioFilterValue" class="form-input" type="search" placeholder="Broad search across selected data..." style="min-width: 190px; flex: 1; padding: 0.3rem 0.65rem; font-size: 0.78rem;" aria-label="Filter value">
                <input id="studioFilterUpperValue" class="form-input" type="number" placeholder="Maximum" style="display: none; width: 6.5rem; padding: 0.3rem 0.65rem; font-size: 0.78rem;" aria-label="Filter maximum value">
                <select id="studioSortOrder" class="form-input" style="width: auto; padding: 0.3rem 0.55rem; font-size: 0.78rem;" aria-label="Sort chart rows">
                  <option value="source">Source order</option>
                  <option value="value-asc">Metric: low to high</option>
                  <option value="value-desc">Metric: high to low</option>
                  <option value="label-asc">Label: A to Z</option>
                  <option value="label-desc">Label: Z to A</option>
                </select>
                <label style="font-size: 0.78rem; color: #334155; font-weight: 700; white-space: nowrap;">Show <input id="studioRowLimit" class="form-input" type="number" min="1" max="100" value="30" style="width: 4.5rem; display: inline-block; padding: 0.3rem 0.45rem; font-size: 0.78rem;"> rows</label>
                <label style="font-size: 0.78rem; color: #334155; font-weight: 700; white-space: nowrap;"><input id="studioGroupDuplicates" type="checkbox" checked style="accent-color: var(--clsu-green); margin-right: 0.25rem;"> Group duplicate labels</label>
              </div>

              <!-- Chart Canvas -->
              <div class="studio-chart-play-area">
                <div id="studioChartCanvas" style="height: 100%; width: 100%;"></div>
                <div id="studioChartEmptyState" style="display:none; position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:rgba(248,250,248,0.95); border-radius:var(--radius-sm); border:2px dashed #CBD5E1;">
                  <span style="font-size:2rem;"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span>
                  <p id="studioChartEmptyMsg" style="font-size:0.88rem; color:#64748B; font-weight:600; margin-top:0.5rem; text-align:center; max-width:280px;">Select a Category field and a numeric Value field above to render the chart.</p>
                </div>
              </div>
            </div>

            <!-- Field & Table Data Manager -->
            <div class="studio-data-manager">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                  <h4 style="font-size: 0.95rem; font-weight: 800; color: #0F172A;"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Editable Data Grid & Custom Fields</h4>
                  <p style="font-size: 0.78rem; color: #64748B;">Edit cell values directly, add new columns/metrics, or paste copied values.</p>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                  <button id="studioBtnAddField" type="button" class="btn-studio-action" style="background: #EFF6FF; border: 1.5px solid #3B82F6; color: #1D4ED8;">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Field / Column
                  </button>
                  <button id="studioBtnAddRow" type="button" class="btn-studio-action" style="background: #ECFDF5; border: 1.5px solid #10B981; color: #065F46;">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Row
                  </button>
                </div>
              </div>

              <!-- Live Editable Table Grid -->
              <div id="studioTableContainer" class="table-container" style="max-height: 280px; margin-bottom: 1.25rem;">
                <!-- Dynamically rendered editable data grid -->
              </div>

              <!-- Record Metadata & Status Settings -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; background: #F8FAF8; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);">
                <div>
                  <label class="form-label" for="studioDocTypeInput" style="font-weight: 800; font-size: 0.78rem; color: #334155; text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Classification Category</label>
                  <input type="text" id="studioDocTypeInput" class="form-input" required style="font-weight: 600; color: #0F172A;">
                </div>
                <div>
                  <label class="form-label" for="studioStatusSelect" style="font-weight: 800; font-size: 0.78rem; color: #334155; text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Publication Status</label>
                  <select id="studioStatusSelect" class="form-input" required style="font-weight: 600; color: #0F172A;">
                    <option value="Pending Review">Pending Review</option>
                    <option value="Approved">Published</option>
                    <option value="Needs Revision">Needs Revision</option>
                  </select>
                </div>
                <div style="grid-column: 1 / -1;">
                  <label class="form-label" style="font-weight: 800; font-size: 0.78rem; color: #334155; text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Admin Verification Notes</label>
                  <textarea id="studioNotesInput" class="form-input" rows="2" placeholder="Add verification logs and approval notes..." style="font-weight: 500; color: #0F172A; line-height: 1.5;"></textarea>
                </div>
              </div>

              <!-- Studio Action Footer -->
              <div style="display: flex; justify-content: flex-end; gap: 0.85rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                <button id="studioBtnSave" type="button" class="btn-save-modal" title="Save this chart to Saved Graphs and save dataset changes">
                  <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Graph
                </button>
                <button id="studioBtnApprove" type="button" class="btn-approve-modal">
                  <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Publish
                </button>
              </div>

            </div>

          </div>

        </div>

      </div>

      </div>

      <div id="adminFileArchivesPanel" class="admin-tab-panel" style="display: none;">
        <div class="clsu-section-title" style="margin-bottom: 1rem;">
          <span><i class="fa-solid fa-box-archive" aria-hidden="true"></i></span> File Archives
        </div>
        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: end;">
          <input type="search" id="fileArchiveSearchInput" class="form-input" placeholder="Search archived files..." aria-label="Search file archives" style="flex: 1; min-width: 250px;">
          <label class="grid gap-1 text-xs font-bold text-gray-600 dark:text-slate-300">Purpose
            <select id="fileArchivePurposeFilter" class="form-input" style="width: auto; min-width: 220px;">
              <option value="all">All purposes</option>
              <option value="analytics">Data &amp; Report Visualization</option>
              <option value="summary_cards">Summary Cards</option>
              <option value="ranking_history">Ranking History</option>
            </select>
          </label>
          <label class="grid gap-1 text-xs font-bold text-gray-600 dark:text-slate-300">Office
            <select id="fileArchiveOfficeFilter" class="form-input" style="width: auto; min-width: 150px;"><option value="all">All offices</option></select>
          </label>
          <label class="grid gap-1 text-xs font-bold text-gray-600 dark:text-slate-300">Status
            <select id="fileArchiveStatusFilter" class="form-input" style="width: auto; min-width: 150px;">
              <option value="all">All statuses</option>
              <option value="Pending Review">Pending Review</option>
              <option value="Approved">Published</option>
              <option value="Needs Revision">Needs Revision</option>
            </select>
          </label>
        </div>
        <div class="table-container" style="box-shadow: var(--card-shadow);">
          <div id="fileArchivesBulkActions" class="admin-bulk-actions summary-card-manager-actions" hidden style="display: flex; gap: 0.75rem; align-items: center; background: #f8fafc; padding: 0.75rem; border-bottom: 1px solid var(--border-light);">
            <span id="fileArchivesBulkSelectionCount" style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-right: auto;">0 records selected</span>
            <button id="fileArchivesClearSelection" type="button" class="export-cancel-button">Clear selection</button>
            <button id="fileArchivesBulkDelete" type="button" class="archive-delete-button" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
            <details class="summary-card-manager-actions-menu" style="position: relative;">
                <summary class="summary-card-manager-menu-toggle" style="cursor: pointer; padding: 0.4rem 0.75rem; border: 1px solid var(--border-light); border-radius: 0.375rem; background: #fff; font-size: 0.8rem; font-weight: 700;">
                    <i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Actions
                </summary>
                <div class="summary-card-manager-menu" aria-label="File archive actions" style="position: absolute; right: 0; top: 100%; margin-top: 0.25rem; background: #fff; border: 1px solid var(--border-light); border-radius: 0.375rem; padding: 0.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); z-index: 50; display: flex; flex-direction: column; min-width: 180px;">
                    <button id="fileArchivesBulkPublish" type="button" style="text-align: left; padding: 0.5rem; background: none; border: none; width: 100%; font-size: 0.8rem; cursor: pointer; border-radius: 0.25rem;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'" disabled><i class="fa-solid fa-circle-check" aria-hidden="true" style="margin-right: 0.4rem;"></i> Publish Selected</button>
                    <button id="fileArchivesBulkUnpublish" type="button" style="text-align: left; padding: 0.5rem; background: none; border: none; width: 100%; font-size: 0.8rem; cursor: pointer; border-radius: 0.25rem;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'" disabled><i class="fa-solid fa-circle-minus" aria-hidden="true" style="margin-right: 0.4rem;"></i> Unpublish Selected</button>
                </div>
            </details>
          </div>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead><tr>
                <th><input id="fileArchivesSelectAll" type="checkbox" aria-label="Select all visible archived files"></th>
                <th>Record ID</th><th>File Name</th><th>Uploaded By</th><th>Format</th><th>Review Status</th><th>Scanned Date</th><th>Actions</th>
              </tr></thead>
              <tbody id="fileArchivesTableBody"><tr><td colspan="8">Loading file archives...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

    </section>

  </main>
  <!-- JavaScript Modules in Order -->
  <script src="js/parsers/excelParser.js"></script>
  <script src="js/ai/graphEngine.js?v=iris-chart-builder-20261001"></script>
  <!-- Preserve classic global dependencies above the ES module; mtime versions invalidate asset caches. -->
  <script src="js/database/dbManager.js?v=<?= (int) filemtime(__DIR__.'/js/database/dbManager.js') ?>"></script>
  <script src="js/data/samples.js"></script>
  <script src="js/scanner.js"></script>
  <script src="js/tables/tableFilter.js"></script>
  <script src="js/charts/chartData.js?v=<?= (int) filemtime(__DIR__.'/js/charts/chartData.js') ?>"></script>
  <script src="js/charts/chartMapping.js"></script>
  <script src="js/ingestion/sourceIngestion.js"></script>
  <script src="js/viewer/documentPagination.js"></script>
  <script src="js/charts/graphExport.js?v=<?= (int) filemtime(__DIR__.'/js/charts/graphExport.js') ?>"></script>
  <script type="module" src="js/app.js?v=<?= (int) filemtime(__DIR__.'/js/app.js') ?>"></script>

</body>
</html>

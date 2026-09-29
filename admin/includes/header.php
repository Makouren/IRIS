<?php
require_once __DIR__.'/../../includes/functions.php';
require_admin();
$activeNav = $activeNav ?? 'ingestion';
$pageTitle = $pageTitle ?? 'IRIS Admin Control Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <script>
        (function () {
            try {
                const saved = localStorage.getItem('color-theme') || localStorage.getItem('iris-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = saved ? saved === 'dark' : prefersDark;
                document.documentElement.classList.toggle('dark', isDark);
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        clsu: {
                            green: '#009639',
                            cobra: '#1E6031',
                            yellow: '#FFD700',
                            gold: '#E0A70D',
                            ink: '#1F2A24',
                            gray: '#6A6A6A',
                            surface: '#F7F8F5'
                        },
                        brand: {
                            50: '#EEF6F0',
                            100: '#DCEBE0',
                            500: '#009639',
                            600: '#1E6031',
                            700: '#1E6031',
                            800: '#0D4A1F',
                            900: '#0D4A1F',
                            gold: '#E0A70D'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/styles.css')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/css/styles.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* TODO: Self-host Buttershine Serif for headings when the IAO provides the licensed font file. */
        body { font-family: 'Libre Franklin', 'Inter', 'Acumin Pro', sans-serif; }
        .admin-scanner-shell { width: 100%; }
        .admin-scanner-shell .app-container { max-width: 1600px !important; margin: 0 auto; }
        html.dark .studio-shell, html.dark .studio-data-manager, html.dark .studio-panel, html.dark .studio-chart-panel, html.dark .studio-graph-controls, html.dark .table-container, html.dark .data-table, html.dark .studio-data-manager .form-input, html.dark .studio-data-manager textarea, html.dark .studio-data-manager select { color: #f8fafc !important; }
        html.dark .studio-data-manager .form-input, html.dark .studio-data-manager textarea, html.dark .studio-data-manager select, html.dark .header-rename-input, html.dark .studio-cell-input { background: #273449 !important; border-color: #475569 !important; color: #f8fafc !important; }
        html.dark .form-input::placeholder, html.dark textarea::placeholder { color: #94a3b8 !important; }
        html.dark #studioFieldMappingRow { background: rgba(59, 130, 246, 0.1) !important; border-color: rgba(147, 197, 253, 0.35) !important; }
        html.dark #studioChartEmptyState { background: rgba(15, 23, 42, 0.88) !important; border-color: #475569 !important; }
        html.dark #studioChartEmptyState p { color: #cbd5e1 !important; }
        html.dark .data-table th { background: #172033 !important; color: #f8fafc !important; border-bottom-color: #10b981 !important; }
        html.dark .data-table td { background: transparent !important; color: #f8fafc !important; border-bottom-color: #334155 !important; }
        html.dark .data-table tr:hover td { background: rgba(16, 185, 129, 0.06) !important; }
        html.dark .table-container { background: #1e293b !important; border-color: #334155 !important; }
        html.dark .studio-data-manager h4, html.dark .studio-data-manager p, html.dark .studio-data-manager label, html.dark .studio-data-manager .form-label { color: #e2e8f0 !important; }
        html.dark .btn-studio-action { color: #e2e8f0 !important; }
        html.dark .header-rename-input { color: #34d399 !important; }
        html.dark .studio-cell-input:focus, html.dark .header-rename-input:focus, html.dark .form-input:focus { border-color: #10b981 !important; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important; }
        html.dark #studioChartTitleInput,
        html.dark #studioDocSheetSelect,
        html.dark #studioChartTypeSelect,
        html.dark #studioFilterField,
        html.dark #studioFilterOperator,
        html.dark #studioFilterValue,
        html.dark #studioFilterUpperValue,
        html.dark #studioSortOrder,
        html.dark #studioRowLimit,
        html.dark #studioCategoryCol,
        html.dark #studioValueCol,
        html.dark #studioValuePrecisionSelect,
        html.dark #studioRecordSelect,
        html.dark #studioStatusSelect,
        html.dark #studioNotesInput,
        html.dark #studioDocTypeInput,
        html.dark .form-input,
        html.dark textarea,
        html.dark select {
            background: #273449 !important;
            border-color: #475569 !important;
            color: #F8FAFC !important;
        }
        html.dark #studioChartTitleInput { color: #34D399 !important; }
        html.dark #savedDashboardGraphsContainer .chart-card { background: #1e293b !important; border-color: #334155 !important; color: #f8fafc !important; }
        html.dark #savedDashboardGraphsContainer h4 { color: #f8fafc !important; }
        html.dark #savedDashboardGraphsContainer select, html.dark #savedDashboardGraphsContainer input { background: #273449 !important; border-color: #475569 !important; color: #f8fafc !important; }
        html.dark body,
        html.dark .text-gray-500,
        html.dark .text-gray-600,
        html.dark .text-gray-700,
        html.dark .text-slate-400,
        html.dark .text-slate-500,
        html.dark .text-slate-600,
        html.dark .text-slate-700,
        html.dark .text-slate-300,
        html.dark .text-slate-200,
        html.dark .text-zinc-400,
        html.dark .text-zinc-500,
        html.dark .text-zinc-600 {
            color: #e2e8f0 !important;
        }
        html.dark .text-gray-800,
        html.dark .text-gray-900,
        html.dark .text-slate-800,
        html.dark .text-slate-900,
        html.dark .text-white {
            color: #f8fafc !important;
        }
        html.dark input,
        html.dark select,
        html.dark textarea,
        html.dark .form-input {
            background: #273449 !important;
            border-color: rgba(148,163,184,.32) !important;
            color: #f8fafc !important;
        }
        html.dark .btn-save-modal,
        html.dark .btn-approve-modal,
        html.dark .btn-studio-action,
        html.dark .export-cancel-button,
        html.dark button {
            color: #e2e8f0 !important;
        }
        html.dark #summaryCardEditorPanel {
            background: #172033 !important;
            border-color: #334155 !important;
        }
        html.dark #summaryCardEditorList > div {
            background: #273449 !important;
            border-color: #475569 !important;
        }
        html.dark #summaryCardEditorList .bg-emerald-100 {
            background: rgba(16, 185, 129, 0.18) !important;
            color: #6ee7b7 !important;
        }
        html.dark #summaryCardEditorList .bg-slate-200 {
            background: #334155 !important;
            color: #e2e8f0 !important;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <style>
        .admin-nav{background:linear-gradient(180deg, rgba(15,23,42,.98), rgba(15,23,42,.92))!important;border-bottom:1px solid rgba(148,163,184,.22)!important;box-shadow:0 10px 30px rgba(2,6,23,.24)!important;}
        .admin-nav-inner{min-height:76px;}
        .admin-brand-title{color:#f8fafc!important;}
        .admin-brand-sub{color:#cbd5e1!important;}
        .admin-nav-link{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem .9rem;border:1px solid rgba(148,163,184,.25);background:rgba(15,23,42,.52);color:#e2e8f0;border-radius:.7rem;font-size:.78rem;font-weight:700;transition:.2s;}
        .admin-nav-link:hover{background:rgba(16,185,129,.12);color:#ecfdf5;border-color:rgba(52,211,153,.45);}
        .admin-nav-link.scanner{background:linear-gradient(135deg,#059669,#10b981);border-color:rgba(52,211,153,.7);color:#fff;box-shadow:0 4px 14px rgba(16,185,129,.18);}
        .admin-nav-link.scanner:hover{background:linear-gradient(135deg,#10b981,#34d399);}
        .admin-theme-btn{color:#e2e8f0!important;background:rgba(30,41,59,.9)!important;border:1px solid rgba(148,163,184,.25)!important;}
        .admin-theme-btn:hover{color:#f8fafc!important;background:rgba(51,65,85,.9)!important;}
        .admin-profile-btn{background:rgba(30,41,59,.9)!important;border:1px solid rgba(148,163,184,.25)!important;color:#f8fafc!important;}
        .admin-profile-btn:hover{background:rgba(51,65,85,.9)!important;}
        .admin-dropdown{background:#111827!important;border:1px solid rgba(148,163,184,.22)!important;color:#e2e8f0!important;box-shadow:0 12px 30px rgba(2,6,23,.35)!important;}
        .admin-dropdown .dropdown-name{color:#f8fafc!important;}
        .admin-dropdown ul{margin:0;padding:.25rem 0!important;}
        .admin-dropdown li{display:flex!important;align-items:center!important;}
        .admin-dropdown a,
        .admin-dropdown .signout{display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:.6rem!important;width:100%!important;text-align:left!important;line-height:1.2!important;white-space:nowrap!important;}
        .admin-dropdown a{color:#dbeafe!important;padding:.7rem 1rem!important;}
        .admin-dropdown a:hover{background:rgba(16,185,129,.12)!important;color:#ecfdf5!important;}
        .admin-dropdown .signout{padding:.75rem 1rem!important;color:#fca5a5!important;border-radius:.75rem!important;transition:background .2s ease,color .2s ease;}
        .admin-dropdown .signout:hover{background:rgba(239,68,68,.12)!important;color:#fee2e2!important;}
        html:not(.dark) .admin-nav{background:#1E6031!important;border-bottom:3px solid #E0A70D!important;box-shadow:0 4px 12px rgba(0,0,0,0.08)!important;}
        html:not(.dark) .admin-brand-title{color:#ffffff!important;}
        html:not(.dark) .admin-brand-sub{color:rgba(255,255,255,0.80)!important;}
        html:not(.dark) .admin-nav-link{background:rgba(255,255,255,0.10)!important;color:#ffffff!important;border:1px solid rgba(255,255,255,0.20)!important;}
        html:not(.dark) .admin-nav-link:hover{background:rgba(255,255,255,0.20)!important;color:#ffffff!important;border-color:rgba(255,255,255,0.30)!important;}
        html:not(.dark) .admin-nav-link.scanner{background:rgba(255,255,255,0.20)!important;border-color:rgba(255,255,255,0.30)!important;color:#FFD700!important;box-shadow:none!important;}
        html:not(.dark) .admin-theme-btn{color:#ffffff!important;background:rgba(255,255,255,0.10)!important;border:1px solid rgba(255,255,255,0.20)!important;}
        html:not(.dark) .admin-theme-btn:hover{color:#ffffff!important;background:rgba(255,255,255,0.20)!important;}
        html:not(.dark) .admin-profile-btn{background:rgba(255,255,255,0.10)!important;border:1px solid rgba(255,255,255,0.20)!important;color:#ffffff!important;}
        html:not(.dark) .admin-profile-btn:hover{background:rgba(255,255,255,0.20)!important;}
        html:not(.dark) .admin-dropdown{background:#ffffff!important;border:1px solid rgba(30,96,49,0.12)!important;color:#1F2A24!important;box-shadow:0 12px 30px rgba(15,23,42,.08)!important;}
        html:not(.dark) .admin-dropdown .dropdown-name{color:#1F2A24!important;}
        html:not(.dark) .admin-dropdown a{color:#1F2A24!important;}
        html:not(.dark) .admin-dropdown a:hover{background:#EEF6F0!important;color:#1E6031!important;}
        html:not(.dark) .admin-dropdown .signout{color:#b91c1c!important;}
        html:not(.dark) .admin-dropdown .signout:hover{background:#fef2f2!important;color:#991b1b!important;}
        #page-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.68);backdrop-filter:blur(6px);z-index:10000;transition:opacity .3s ease,visibility .3s ease;}
        #page-loader.hidden{opacity:0;visibility:hidden;pointer-events:none;}
        .iris-loader{position:relative;width:72px;height:72px;border-radius:50%;background:conic-gradient(#10b981,#34d399,#fbbf24,#10b981);animation:spin 1s linear infinite;box-shadow:0 0 30px rgba(16,185,129,.5)}
        .iris-loader::before{content:"";position:absolute;inset:10px;border-radius:50%;background:rgba(15,23,42,.9);border:2px solid rgba(255,255,255,.18)}
        .iris-loader::after{content:"IRIS";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;letter-spacing:.12em;color:#d1fae5}
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>

    <div id="page-loader" aria-live="polite" aria-label="Loading page">
        <div class="iris-loader" aria-hidden="true"></div>
    </div>

    <!-- Navigation Bar -->
    <nav class="admin-nav sticky top-0 z-50 backdrop-blur-md bg-opacity-95">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="admin-nav-inner flex items-center justify-between gap-4">
                <a href="<?= e(base_url('admin/dashboard.php')) ?>" class="logo-refresh-trigger flex items-center gap-3 min-w-0" data-target="<?= e(base_url('admin/dashboard.php')) ?>">
                    <div class="w-52 h-11 flex items-center justify-center overflow-hidden shrink-0 rounded-lg bg-white px-3 py-1.5">
                        <img src="<?= e(base_url('images/iris-panel-logo.svg')) ?>" alt="IRIS SielMetrics+ Logo" class="h-10 w-full object-contain object-left">
                    </div>
                    <div class="min-w-0 hidden sm:block">
                        <div class="flex items-center gap-2">
                            <span class="admin-brand-title text-xl font-extrabold tracking-tight">IRIS Admin</span>
                            <span class="text-[10px] px-2 py-1 font-extrabold rounded-full bg-[#FFD700] text-[#1E6031] border border-[#E0A70D]">
                                <?= $activeNav === 'ingestion' ? 'FILE INGESTION' : ($activeNav === 'review' ? 'REVIEW EDITOR' : 'SAVED GRAPHS') ?>
                            </span>
                        </div>
                        <p class="admin-brand-sub text-[11px] font-semibold uppercase tracking-wider">International Affairs Office Control Panel</p>
                    </div>
                </a>

                <div class="admin-nav-actions flex items-center gap-2">
                    <a href="<?= e(base_url('user/dashboard.php')) ?>" class="admin-nav-link public-link" aria-label="Open Observatory" title="Open Observatory">
                        <i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span>Observatory</span>
                    </a>
                    <button id="theme-toggle" type="button" class="admin-theme-btn rounded-lg text-sm p-2.5" aria-label="Toggle theme">
                        <i id="theme-toggle-dark-icon" class="hidden fa-solid fa-moon text-base"></i>
                        <i id="theme-toggle-light-icon" class="hidden fa-solid fa-sun text-base text-amber-400"></i>
                    </button>
                    <div class="relative">
                        <button type="button" class="admin-profile-btn flex items-center gap-2 p-1.5 rounded-full focus:ring-2 focus:ring-emerald-500" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-emerald-500 to-amber-400 flex items-center justify-center text-white font-bold text-xs shadow">
                                <?= strtoupper(substr(($_SESSION['username'] ?? 'A'), 0, 2)) ?>
                            </div>
                            <span class="hidden sm:inline-block font-semibold text-xs px-1"><?= htmlspecialchars(($_SESSION['username'] ?? 'A')) ?></span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 mr-1"></i>
                        </button>
                        <div class="admin-dropdown z-50 hidden my-3 w-56 text-base list-none rounded-xl shadow-2xl" id="user-dropdown">
                            <div class="px-4 py-3 border-b border-slate-700">
                                <span class="dropdown-name block text-sm font-bold"><?= htmlspecialchars(($_SESSION['username'] ?? 'A')) ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-slate-900 mt-1">ADMINISTRATOR</span>
                            </div>
                            <ul class="py-2" aria-labelledby="user-menu-button">
                                <li><a href="<?= e(base_url('admin/dashboard.php')) ?>" class="block px-4 py-2 text-sm"><i class="fa-solid fa-cloud-arrow-up mr-2"></i> File Ingestion</a></li>
                                <li><a href="<?= e(base_url('admin/review_editor.php')) ?>" class="block px-4 py-2 text-sm"><i class="fa-solid fa-pen-to-square mr-2"></i> Review Editor</a></li>
                                <li><a href="<?= e(base_url('admin/saved_graphs.php')) ?>" class="block px-4 py-2 text-sm"><i class="fa-solid fa-chart-line mr-2"></i> Saved Graphs</a></li>
                                <li><a href="<?= e(base_url('user/dashboard.php')) ?>" class="block px-4 py-2 text-sm"><i class="fa-solid fa-globe mr-2"></i> Observatory View</a></li>
                            </ul>
                            <div class="py-1 border-t border-slate-700">
                                <form method="POST" action="<?= e(base_url('auth/logout.php')) ?>" class="w-full">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="signout w-full px-4 py-2 text-sm whitespace-nowrap">
                                        <i class="fa-solid fa-right-from-bracket flex-shrink-0"></i>
                                        <span class="whitespace-nowrap">Sign Out</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Shell -->
    <main class="admin-scanner-shell flex-1 w-full py-8">
        <div class="app-container">
            <button id="uploadWidgetTrigger" class="floating-upload-trigger" type="button" aria-label="Open institutional upload window">
                <span class="floating-upload-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
            </button>

            <div id="uploadWidgetModal" class="upload-widget-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="uploadWidgetTitle">
                <div class="upload-widget-panel">
                    <div class="upload-widget-header">
                        <div>
                            <div class="upload-widget-kicker">File Intake</div>
                            <div id="uploadWidgetTitle" class="upload-widget-title">Institutional Document Upload</div>
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

                        <h1 class="dropzone-title">Upload Institutional Spreadsheets</h1>
                        <p class="dropzone-subtitle">Multi-sheet parsing and draft visualization suggestions for university performance metrics</p>

                        <div class="format-badges">
                            <span class="format-chip excel"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Spreadsheets (XLSX, XLS, CSV)</span>
                        </div>

                        <input type="file" id="adminWidgetFileInput" multiple accept=".xlsx,.xls,.csv" style="display: none;">

                        <div style="margin: 0.5rem auto 1.25rem; text-align: center; font-size: 0.8rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;">
                            Files must be under 100 MB
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <button id="adminWidgetBrowseBtn" class="btn-icon" style="padding: 0.75rem 2rem; font-size: 0.95rem; margin: 0 auto;">
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

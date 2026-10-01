<?php
require_once __DIR__.'/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user']);
$fieldColors = [];
try {
    $storedFieldColors = db()->query('SELECT field_key, color FROM field_colors')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($storedFieldColors as $fieldKey => $color) {
        $key = strtolower(preg_replace('/\s+/', ' ', trim((string)$fieldKey)) ?? '');
        if ($key !== '' && is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1) $fieldColors[$key] = strtoupper($color);
    }
} catch (Throwable $e) {
    $fieldColors = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CLSU Performance Observatory - IRIS</title>
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
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>
        (function () {
            const originalFetch = window.fetch.bind(window);
            window.fetch = (input, init = {}) => {
                const method = String(init.method || 'GET').toUpperCase();
                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
                    const headers = new Headers(init.headers || {});
                    if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', document.querySelector('meta[name="csrf-token"]')?.content || '');
                    init = { ...init, headers };
                }
                return originalFetch(input, init);
            };
        })();
    </script>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                            gold: '#f59e0b'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Flowbite CSS & JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <!-- Apache ECharts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script src="<?= e(base_url('scanner/js/chartConfig.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/chartMapping.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/chartColors.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../scanner/js/chartColors.js') ?>"></script>
    <script>window.IRISFieldColors = <?= json_encode($fieldColors, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= e(base_url('scanner/js/graphExport.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../scanner/js/graphExport.js') ?>"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
    <style>
        body { font-family: 'Libre Franklin', 'Inter', sans-serif; }
        .admin-nav { background: linear-gradient(180deg, rgba(15,23,42,.98), rgba(15,23,42,.92)) !important; border-bottom: 1px solid rgba(148,163,184,.22) !important; box-shadow: 0 10px 30px rgba(2,6,23,.24) !important; }
        .admin-nav-inner { min-height: 76px; }
        .admin-brand-title { color: #f8fafc !important; }
        .admin-brand-sub { color: #cbd5e1 !important; }
        .admin-nav-link { display: inline-flex; align-items: center; gap: .5rem; padding: .65rem .9rem; border: 1px solid rgba(148,163,184,.25); background: rgba(15,23,42,.52); color: #e2e8f0; border-radius: .7rem; font-size: .78rem; font-weight: 700; transition: .2s; }
        .admin-nav-link:hover { background: rgba(16,185,129,.12); color: #ecfdf5; border-color: rgba(52,211,153,.45); }
        .admin-theme-btn, .admin-profile-btn { color: #e2e8f0 !important; background: rgba(30,41,59,.9) !important; border: 1px solid rgba(148,163,184,.25) !important; }
        .admin-theme-btn:hover, .admin-profile-btn:hover { color: #f8fafc !important; background: rgba(51,65,85,.9) !important; }
        .admin-dropdown { background: #111827 !important; border: 1px solid rgba(148,163,184,.22) !important; color: #e2e8f0 !important; box-shadow: 0 12px 30px rgba(2,6,23,.35) !important; }
        .admin-dropdown .dropdown-name { color: #f8fafc !important; }
        .admin-dropdown ul { margin: 0; padding: .25rem 0 !important; }
        .admin-dropdown li { display: flex !important; align-items: center !important; }
        .admin-dropdown a, .admin-dropdown .signout { display: flex !important; align-items: center !important; justify-content: flex-start !important; gap: .6rem !important; width: 100% !important; text-align: left !important; line-height: 1.2 !important; white-space: nowrap !important; }
        .admin-dropdown a { color: #dbeafe !important; padding: .7rem 1rem !important; }
        .admin-dropdown a:hover { background: rgba(16,185,129,.12) !important; color: #ecfdf5 !important; }
        .admin-dropdown .signout { padding: .75rem 1rem !important; color: #fca5a5 !important; border-radius: .75rem !important; transition: background .2s ease,color .2s ease; }
        .admin-dropdown .signout:hover { background: rgba(239,68,68,.12) !important; color: #fee2e2 !important; }
        html:not(.dark) .admin-nav { background: #1E6031 !important; border-bottom: 3px solid #E0A70D !important; box-shadow: 0 4px 12px rgba(0,0,0,.08) !important; }
        html:not(.dark) .admin-brand-title { color: #fff !important; }
        html:not(.dark) .admin-brand-sub { color: rgba(255,255,255,.8) !important; }
        html:not(.dark) .admin-nav-link { background: rgba(255,255,255,.1) !important; color: #fff !important; border: 1px solid rgba(255,255,255,.2) !important; }
        html:not(.dark) .admin-nav-link:hover { background: rgba(255,255,255,.2) !important; color: #fff !important; border-color: rgba(255,255,255,.3) !important; }
        html:not(.dark) .admin-theme-btn, html:not(.dark) .admin-profile-btn { color: #fff !important; background: rgba(255,255,255,.1) !important; border: 1px solid rgba(255,255,255,.2) !important; }
        html:not(.dark) .admin-theme-btn:hover, html:not(.dark) .admin-profile-btn:hover { color: #fff !important; background: rgba(255,255,255,.2) !important; }
        html:not(.dark) .admin-dropdown { background: #fff !important; border: 1px solid rgba(30,96,49,.12) !important; color: #1F2A24 !important; box-shadow: 0 12px 30px rgba(15,23,42,.08) !important; }
        html:not(.dark) .admin-dropdown .dropdown-name, html:not(.dark) .admin-dropdown a { color: #1F2A24 !important; }
        html:not(.dark) .admin-dropdown a:hover { background: #EEF6F0 !important; color: #1E6031 !important; }
        html:not(.dark) .admin-dropdown .signout { color: #b91c1c !important; }
        html:not(.dark) .admin-dropdown .signout:hover { background: #fef2f2 !important; color: #991b1b !important; }
        html:not(.dark) .admin-footer { background: #1E6031 !important; border-top: 3px solid #E0A70D !important; color: #fff !important; }
        html:not(.dark) .admin-footer span, html:not(.dark) .admin-footer div { color: rgba(255,255,255,.9) !important; }
        html:not(.dark) .admin-footer-title { color: #fff !important; }
        #page-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.68);backdrop-filter:blur(6px);z-index:10000;transition:opacity .3s ease,visibility .3s ease;}
        #page-loader.hidden{opacity:0;visibility:hidden;pointer-events:none;}
        .iris-loader{position:relative;width:72px;height:72px;border-radius:50%;background:conic-gradient(#10b981,#34d399,#fbbf24,#10b981);animation:spin 1s linear infinite;box-shadow:0 0 30px rgba(16,185,129,.5)}
        .iris-loader::before{content:"";position:absolute;inset:10px;border-radius:50%;background:rgba(15,23,42,.9);border:2px solid rgba(255,255,255,.18)}
        .iris-loader::after{content:"IRIS";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;letter-spacing:.12em;color:#d1fae5}
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body data-role="<?= e($_SESSION['role'] ?? 'user') ?>" class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <div id="page-loader" aria-live="polite" aria-label="Loading page">
        <div class="iris-loader" aria-hidden="true"></div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="admin-nav sticky top-0 z-50 backdrop-blur-md bg-opacity-95">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="admin-nav-inner flex items-center justify-between gap-4">
                <a href="<?= e(base_url('user/dashboard.php')) ?>" class="logo-refresh-trigger flex items-center gap-3 min-w-0" data-target="<?= e(base_url('user/dashboard.php')) ?>">
                    <div class="w-52 h-11 flex items-center justify-center overflow-hidden shrink-0 rounded-lg bg-white px-3 py-1.5">
                        <img src="<?= e(base_url('images/iris-panel-logo.svg')) ?>" alt="IRIS SielMetrics+ Logo" class="h-10 w-full object-contain object-left">
                    </div>
                    <div class="min-w-0 hidden sm:block">
                        <div class="flex items-center gap-2">
                            <span class="admin-brand-title text-xl font-extrabold tracking-tight">CLSU Observatory</span>
                            <span class="text-[10px] px-2 py-1 font-extrabold rounded-full bg-[#FFD700] text-[#1E6031] border border-[#E0A70D]">PUBLIC ANALYTICS</span>
                        </div>
                        <p class="admin-brand-sub text-[11px] font-semibold uppercase tracking-wider">International Affairs Office &bull; IRIS</p>
                    </div>
                </a>

                <div class="admin-nav-actions flex items-center gap-2">
                    <?php if (($_SESSION['role'] ?? null) === 'super_admin'): ?>
                        <a href="<?= e(base_url('admin/review_editor.php')) ?>" class="admin-nav-link public-link" aria-label="Edit Observatory data" title="Edit Observatory data">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Edit</span>
                        </a>
                        <div class="relative">
                            <button type="button" class="admin-profile-btn flex items-center justify-center p-2.5 rounded-lg focus:ring-2 focus:ring-emerald-500" id="observatory-menu-button" aria-expanded="false" data-dropdown-toggle="observatory-menu" data-dropdown-placement="bottom" aria-label="Open navigation menu">
                                <i class="fa-solid fa-bars text-base" aria-hidden="true"></i>
                            </button>
                            <div class="admin-dropdown z-50 hidden my-3 w-56 text-base list-none rounded-xl shadow-2xl" id="observatory-menu">
                                <ul class="py-2" aria-labelledby="observatory-menu-button">
                                    <li><a href="#scanner-published-graphs"><i class="fa-solid fa-chart-column"></i> Scanner Analytics</a></li>
                                    <li><a href="<?= e(base_url('admin/dashboard.php')) ?>"><i class="fa-solid fa-sliders"></i> Admin Portal</a></li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    <button id="theme-toggle" type="button" class="admin-theme-btn rounded-lg text-sm p-2.5" aria-label="Toggle theme">
                        <i id="theme-toggle-dark-icon" class="hidden fa-solid fa-moon text-base"></i>
                        <i id="theme-toggle-light-icon" class="hidden fa-solid fa-sun text-base text-amber-400"></i>
                    </button>
                    <div class="relative">
                        <button type="button" class="admin-profile-btn flex items-center gap-2 p-1.5 rounded-full focus:ring-2 focus:ring-emerald-500" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-emerald-500 to-amber-400 flex items-center justify-center text-white font-bold text-xs shadow">
                                <?= strtoupper(substr(($_SESSION['username'] ?? 'U'), 0, 2)) ?>
                            </div>
                            <span class="hidden sm:inline-block font-semibold text-xs px-1"><?= htmlspecialchars(($_SESSION['username'] ?? 'U')) ?></span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 mr-1" aria-hidden="true"></i>
                        </button>
                        <div class="admin-dropdown z-50 hidden my-3 w-56 text-base list-none rounded-xl shadow-2xl" id="user-dropdown">
                            <div class="px-4 py-3 border-b border-slate-700">
                                <span class="dropdown-name block text-sm font-bold"><?= htmlspecialchars(($_SESSION['username'] ?? 'U')) ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-slate-900 mt-1">
                                    <?= ($_SESSION['role'] ?? null) === 'super_admin' ? 'SUPER ADMIN' : (($_SESSION['role'] ?? null) === 'admin' ? 'OFFICE ADMIN' : 'VIEWER') ?>
                                </span>
                            </div>
                            <ul class="py-2" aria-labelledby="user-menu-button">
                                <?php if (($_SESSION['role'] ?? null) === 'super_admin'): ?>
                                    <li><a href="<?= e(base_url('admin/dashboard.php')) ?>"><i class="fa-solid fa-shield-halved"></i> Admin Portal</a></li>
                                <?php endif; ?>
                                <li><a href="<?= e(base_url('auth/change_password.php')) ?>"><i class="fa-solid fa-key"></i> Change password</a></li>
                            </ul>
                            <div class="py-1 border-t border-slate-700">
                                <form method="POST" action="<?= e(base_url('auth/logout.php')) ?>" class="w-full">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="signout w-full px-4 py-2 text-sm whitespace-nowrap">
                                        <i class="fa-solid fa-right-from-bracket flex-shrink-0" aria-hidden="true"></i>
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

    <!-- Main Container -->
    <style>
        :root {
            --iris-green: #1E6031;
            --iris-green-soft: #edf6ef;
            --iris-green-soft-strong: #dfeee3;
            --iris-gold: #E0A70D;
            --iris-gold-soft: #fff8db;
            --iris-border: #dfe7df;
            --iris-surface: #ffffff;
            --iris-surface-alt: #f7faf7;
            --iris-text: #1f2937;
            --iris-text-soft: #475569;
            --iris-text-faint: #64748b;
        }

        body { font-size: var(--iris-font-base); }
        .ranking-panel { background: var(--iris-surface); border: 1px solid var(--iris-border); box-shadow: 0 12px 32px rgba(30, 96, 49, 0.08); }
        .ranking-table th { color: var(--iris-text-faint); font-size: var(--iris-font-xs); letter-spacing: .08em; text-transform: uppercase; }
        .ranking-table td { color: var(--iris-text); font-size: var(--iris-font-sm); }
        .rank-change-up { color: #15803d; }
        .rank-change-down { color: #b91c1c; }
        .rank-change-same { color: var(--iris-text-faint); }

        html.dark {
            --iris-green: #6ee7b7;
            --iris-green-soft: rgba(16, 185, 129, 0.12);
            --iris-green-soft-strong: rgba(16, 185, 129, 0.2);
            --iris-gold: #fbbf24;
            --iris-gold-soft: rgba(251, 191, 36, 0.12);
            --iris-border: rgba(148, 163, 184, 0.26);
            --iris-surface: #111827;
            --iris-surface-alt: #172033;
            --iris-text: #e2e8f0;
            --iris-text-soft: #cbd5e1;
            --iris-text-faint: #94a3b8;
        }

        .summary-card-shell {
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(247,250,247,0.98) 100%);
            border: 1px solid var(--iris-border);
            box-shadow: 0 12px 32px rgba(30, 96, 49, 0.09);
        }

        html.dark .summary-card-shell {
            background: linear-gradient(180deg, rgba(17,24,39,0.98) 0%, rgba(23,32,51,0.97) 100%);
            border-color: rgba(110, 231, 183, 0.22);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.26);
        }

        .summary-card-shell .summary-card-title {
            color: var(--iris-green);
            letter-spacing: 0.13em;
        }

        .summary-card-shell .summary-card-year-badge {
            background: linear-gradient(180deg, rgba(255, 215, 84, 0.22), rgba(240, 180, 34, 0.15));
            border: 1px solid rgba(224, 167, 13, 0.4);
            color: #7a4c00;
        }

        html.dark .summary-card-shell .summary-card-year-badge {
            background: rgba(251, 191, 36, 0.12);
            border-color: rgba(251, 191, 36, 0.25);
            color: #fef3c7;
        }

        .summary-card-shell .summary-card-label {
            color: var(--iris-text-soft);
        }

        .summary-card-shell .summary-card-second-row {
            border-top-color: rgba(30, 96, 49, 0.12);
        }

        html.dark .summary-card-shell .summary-card-second-row {
            border-top-color: rgba(148, 163, 184, 0.2);
        }

        html.dark body,
        html.dark .bg-gray-50,
        html.dark .bg-gray-100,
        html.dark .bg-white,
        html.dark .bg-slate-100,
        html.dark .bg-slate-800,
        html.dark .bg-slate-900,
        html.dark [class*="bg-white"],
        html.dark [class*="bg-gray-"],
        html.dark [class*="bg-slate-"] {
            color: var(--iris-text);
        }

        html.dark .text-gray-500,
        html.dark .text-gray-600,
        html.dark .text-slate-500,
        html.dark .text-slate-600,
        html.dark .text-gray-400,
        html.dark .text-slate-400,
        html.dark .text-gray-700,
        html.dark .text-slate-700,
        html.dark .text-gray-900,
        html.dark .text-slate-900,
        html.dark .text-slate-300,
        html.dark .text-slate-200 {
            color: var(--iris-text-soft) !important;
        }

        html.dark .text-gray-800,
        html.dark .text-gray-700,
        html.dark .text-slate-800,
        html.dark .text-slate-700 {
            color: var(--iris-text) !important;
        }

        html.dark .border-gray-200,
        html.dark .border-gray-300,
        html.dark .border-slate-200,
        html.dark .border-slate-300,
        html.dark .border-slate-600,
        html.dark .border-slate-700,
        html.dark .dark\:border-gray-700,
        html.dark .dark\:border-slate-700 {
            border-color: rgba(148, 163, 184, 0.28) !important;
        }

        html.dark input,
        html.dark textarea,
        html.dark select,
        html.dark .form-input,
        html.dark .dark\:bg-gray-900,
        html.dark .dark\:bg-gray-800,
        html.dark .dark\:bg-slate-800,
        html.dark .dark\:bg-slate-900 {
            background-color: rgba(15, 23, 42, 0.9) !important;
            border-color: rgba(148, 163, 184, 0.3) !important;
            color: var(--iris-text) !important;
        }

        html.dark ::placeholder {
            color: #94a3b8 !important;
        }

        html.dark .bg-emerald-100,
        html.dark .bg-emerald-50,
        html.dark .dark\:bg-emerald-900\/60,
        html.dark .dark\:bg-gray-800\/60,
        html.dark .dark\:bg-gray-900\/60 {
            background-color: rgba(16, 185, 129, 0.12) !important;
        }

        html.dark .text-emerald-800,
        html.dark .text-emerald-700,
        html.dark .text-emerald-600,
        html.dark .dark\:text-emerald-300,
        html.dark .dark\:text-emerald-400 {
            color: #a7f3d0 !important;
        }

        html.dark .text-amber-800,
        html.dark .dark\:text-amber-300 {
            color: #fde68a !important;
        }
    </style>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8" id="overview">
        <?php if (flash('error')): ?>
            <div class="flex items-center p-4 text-red-800 rounded-xl bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-800" role="alert"><i class="fa-solid fa-circle-exclamation text-lg mr-3"></i><div class="text-sm font-medium"><?= htmlspecialchars(flash('error')) ?></div></div>
        <?php endif; ?>

        <section id="summary-card-section" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-chart-simple text-amber-500 mr-2"></i> Latest Performance Snapshot
                    </h2>
                </div>
                <?php if (($_SESSION['role'] ?? null) === 'super_admin'): ?>
                    <button type="button" id="toggleSummaryCardForm" class="inline-flex items-center px-3 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-500">
                        <i class="fa-solid fa-plus mr-2"></i> Add summary card
                    </button>
                <?php endif; ?>
            </div>

            <?php if (($_SESSION['role'] ?? null) === 'super_admin'): ?>
                <div id="summaryCardsAdminFormPanel" class="hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                    <form id="summaryCardsAdminForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="id" id="summaryCardId" />
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Title
                            <input type="text" name="title" id="summaryCardTitle" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" required>
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Main Value
                            <input type="text" name="main_value" id="summaryCardMainValue" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" required>
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Main Label
                            <input type="text" name="main_label" id="summaryCardMainLabel" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" required>
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Year / Date
                            <input type="text" name="year_date" id="summaryCardYearDate" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Secondary Label
                            <input type="text" name="secondary_label" id="summaryCardSecondaryLabel" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Secondary Value
                            <input type="text" name="secondary_value" id="summaryCardSecondaryValue" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 md:col-span-2">
                            Description
                            <textarea name="description" id="summaryCardDescription" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"></textarea>
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Display Order
                            <input type="number" name="display_order" id="summaryCardDisplayOrder" value="0" min="0" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Categories
                            <select name="category_ids[]" id="summaryCardCategory" multiple size="3" aria-describedby="summaryCardCategoryHelp" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"></select>
                            <span id="summaryCardCategoryHelp" class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">Select one or more; leave empty for Uncategorized.</span>
                            <span class="mt-2 flex items-center gap-2"><button type="button" id="summaryCardAddCategory" class="rounded-lg border border-green-800/30 px-2.5 py-1 text-xs font-semibold text-green-900 hover:bg-green-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">+ New category</button><input type="text" id="summaryCardNewCategory" maxlength="40" placeholder="Category name" class="hidden min-w-0 flex-1 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm"></span>
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Display Precision
                            <select name="display_precision" id="summaryCardDisplayPrecision" class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                                <option value="0">No decimals</option>
                                <option value="1">1 decimal</option>
                                <option value="2" selected>2 decimals</option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 md:col-span-2">
                            <input type="checkbox" id="summaryCardPublished" name="is_published" checked>
                            Publish this card
                        </label>
                        <div class="md:col-span-2 flex items-center justify-end gap-3">
                            <button type="button" id="cancelSummaryCardForm" class="rounded-xl border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save card</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div id="summaryCardCategoryFilter" class="hidden -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto" aria-label="Filter summary cards by category">
                <div id="summaryCardCategoryChips" class="flex w-max min-w-full items-center gap-2 pb-1" role="group" aria-label="Summary card categories"></div>
            </div>
            <div id="summaryCardsGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4"></div>
        </section>

        <section id="ranking-history" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-ranking-star text-amber-500 mr-2"></i> Ranking History
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Compare institutional rankings across available years.</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200" aria-label="Filter Ranking History">
                    <label class="flex items-center gap-1.5">Level
                        <select id="rankingLevelFilter" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="all">All levels</option></select>
                    </label>
                    <label class="flex items-center gap-1.5">Scope
                        <select id="rankingScopeFilter" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="all">All scopes</option></select>
                    </label>
                    <label class="flex items-center gap-1.5">Ranking type
                        <select id="rankingTypeFilter" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="all">All types</option></select>
                    </label>
                    <button id="rankingAllYears" type="button" class="rounded-lg border border-green-800 bg-green-800 px-3 py-2 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950" aria-pressed="true">All years</button>
                    <label class="flex items-center gap-1.5">From
                        <select id="rankingYearFrom" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900" disabled><option>Loading...</option></select>
                    </label>
                    <label class="flex items-center gap-1.5">To
                        <select id="rankingYearTo" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900" disabled><option>Loading...</option></select>
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                <div class="ranking-panel rounded-2xl p-5 xl:col-span-2">
                    <div id="rankingTableStatus" class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">Loading ranking data...</div>
                    <div class="overflow-x-auto">
                        <table id="rankingTable" class="ranking-table hidden min-w-[820px] w-full text-left">
                            <thead><tr><th class="pb-3 pr-4">Year / Edition</th><th class="pb-3 pr-4">Ranking type</th><th class="pb-3 pr-4">Level</th><th class="pb-3 pr-4">Scope / Category</th><th class="pb-3 pr-4">Rank</th><th class="pb-3">Change</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="ranking-panel rounded-2xl p-5 xl:col-span-3">
                    <div id="rankingChart" class="grid min-h-64 w-full grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3"></div>
                </div>
            </div>
        </section>

        <section id="star-rating-cards-section" class="hidden space-y-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-star text-amber-500" aria-hidden="true"></i>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">University Star Ratings</h2>
            </div>
            <div id="star-rating-category-filter" class="hidden -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Filter star rating cards by category">
                <div id="star-rating-category-chips" class="flex w-max min-w-full items-center gap-2 pb-1" role="group" aria-label="Star rating categories"></div>
            </div>
            <div id="star-rating-cards-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
        </section>

        <section id="scanner-published-graphs" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-chart-column text-emerald-500 mr-2"></i> Scanner-Published Analytics
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Published visualizations available from the IRIS Scanner</p>
                </div>
                <span id="publishedGraphCount" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                    <i class="fa-solid fa-circle-check mr-1"></i> Loading published graphs
                </span>
            </div>
            <div id="scannerPublishedGraphsGrid" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div id="scannerPublishedGraphsEmpty" class="lg:col-span-2 p-6 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 text-center text-sm text-gray-500 dark:text-gray-400">
                    <i class="fa-solid fa-chart-simple text-lg mr-1"></i> No published scanner graphs are available yet.
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="admin-footer bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 dark:text-gray-400 gap-4">
            <div class="flex items-center space-x-2">
                <span class="font-bold admin-footer-title text-gray-800 dark:text-gray-200">CLSU Observatory</span>
                <span>&bull; IAO'S INTERNATIONAL RAPPORT INSIGHT SYSTEM</span>
            </div>
            <div>
                Powered by Flowbite &amp; Tailwind CSS
            </div>
        </div>
    </footer>

    <!-- Theme Toggle & ECharts Script Initialization -->
    <script>
        (function () {
            const loader = document.getElementById('page-loader');
            const hideLoader = () => {
                if (loader) {
                    loader.classList.add('hidden');
                }
            };

            document.querySelectorAll('.logo-refresh-trigger').forEach((link) => {
                link.addEventListener('click', function (event) {
                    const target = this.getAttribute('data-target') || this.href;
                    event.preventDefault();
                    loader && loader.classList.remove('hidden');
                    const currentUrl = window.location.href.split('#')[0];
                    if (target && target.split('#')[0] === currentUrl.split('#')[0]) {
                        window.location.reload();
                        return;
                    }
                    window.location.href = target;
                });
            });

            setTimeout(hideLoader, 90);
            window.addEventListener('load', hideLoader);
        })();

        // --- Dark Mode Logic ---
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
        const themeToggleBtn = document.getElementById('theme-toggle');

        if (document.documentElement.classList.contains('dark')) {
            themeToggleLightIcon.classList.remove('hidden');
        } else {
            themeToggleDarkIcon.classList.remove('hidden');
        }

        themeToggleBtn.addEventListener('click', function() {
            themeToggleDarkIcon.classList.toggle('hidden');
            themeToggleLightIcon.classList.toggle('hidden');

            const isDarkNow = document.documentElement.classList.contains('dark');
            const nextMode = isDarkNow ? 'light' : 'dark';

            document.documentElement.classList.toggle('dark', nextMode === 'dark');
            localStorage.setItem('color-theme', nextMode);
            localStorage.setItem('iris-theme', nextMode);
            if (rankingRows.length) renderRankingHistoryFromControls();
            loadPublishedScannerGraphs();
        });

        function formatSummaryCardValue(value, precision) {
            const raw = String(value ?? '').trim();
            if (raw === '') return '';
            const normalized = raw.replace(/,/g, '');
            if (!/^-?(?:\d+|\d*\.\d+)$/.test(normalized)) {
                return raw;
            }
            const number = Number(normalized);
            if (!Number.isFinite(number)) {
                return raw;
            }
            const precisionValue = Number(precision);
            const digits = Number.isFinite(precisionValue)
                ? Math.max(0, Math.min(2, precisionValue))
                : 2;
            return Number(number).toLocaleString(undefined, {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            });
        }

        const dashboardBaseUrl = <?= json_encode(base_url('')) ?>;
        const ratingStarPath = 'M12 2.5 14.9 8.4l6.6 1-4.75 4.62 1.12 6.53L12 17.47l-5.87 3.08 1.12-6.53L2.5 9.4l6.6-1L12 2.5Z';
        let starRatingCardsData = [];
        let starRatingCategories = [];
        let activeStarRatingCategorySlug = '';

        function renderRatingStars(maxStars, score, prefix) {
            return Array.from({ length: maxStars }, (_, index) => {
                const remaining = score - index;
                const gradientId = `${prefix}-half-${index}`;
                const isHalf = remaining >= 0.5 && remaining < 1;
                const fill = remaining >= 1 ? '#E0A70D' : isHalf ? `url(#${gradientId})` : 'none';
                const gradient = isHalf ? `<defs><linearGradient id="${gradientId}"><stop offset="50%" stop-color="#E0A70D"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs>` : '';
                const stroke = remaining >= 0.5 ? '#E0A70D' : '#9CA3AF';
                return `<svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" aria-hidden="true">${gradient}<path d="${ratingStarPath}" fill="${fill}" stroke="${stroke}" stroke-width="1.5" stroke-linejoin="round"/></svg>`;
            }).join('');
        }

        function updateStarRatingCategoryUrl(slug, replace = false) {
            const url = new URL(window.location.href);
            if (slug) url.searchParams.set('star_category', slug);
            else url.searchParams.delete('star_category');
            window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
        }

        function renderStarRatingCategoryChips() {
            const filter = document.getElementById('star-rating-category-filter');
            const chips = document.getElementById('star-rating-category-chips');
            if (!filter || !chips) return;
            if (!starRatingCategories.length) {
                filter.classList.add('hidden');
                chips.innerHTML = '';
                return;
            }
            filter.classList.remove('hidden');
            const options = [{ slug: '', name: 'All' }, ...starRatingCategories];
            chips.innerHTML = options.map(category => {
                const selected = activeStarRatingCategorySlug === category.slug;
                return `<button type="button" class="shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 ${selected ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 hover:bg-green-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'}" data-star-category-slug="${escapeHtmlDashboard(category.slug)}" aria-pressed="${selected ? 'true' : 'false'}">${escapeHtmlDashboard(category.name)}</button>`;
            }).join('');
            chips.querySelectorAll('button[data-star-category-slug]').forEach(button => button.addEventListener('click', () => {
                const slug = button.dataset.starCategorySlug || '';
                if (slug === activeStarRatingCategorySlug) return;
                activeStarRatingCategorySlug = slug;
                updateStarRatingCategoryUrl(slug);
                renderStarRatingCategoryChips();
                renderStarRatingCards(starRatingCardsData);
            }));
        }

        function restoreStarRatingCategoryFromUrl(replaceUnknown = false) {
            const requested = new URL(window.location.href).searchParams.get('star_category') || '';
            const match = starRatingCategories.find(category => category.slug === requested);
            activeStarRatingCategorySlug = match ? match.slug : '';
            if (requested && !match && replaceUnknown) updateStarRatingCategoryUrl('', true);
            renderStarRatingCategoryChips();
            renderStarRatingCards(starRatingCardsData);
        }

        window.addEventListener('popstate', () => restoreStarRatingCategoryFromUrl(true));

        function renderStarRatingCards(cards) {
            const section = document.getElementById('star-rating-cards-section');
            const grid = document.getElementById('star-rating-cards-grid');
            if (!section || !grid) return;
            const publishedCards = Array.isArray(cards) ? cards.filter(card => card && (card.is_published === true || card.is_published === 1 || card.is_published === '1')) : [];
            if (!publishedCards.length) {
                section.classList.add('hidden');
                grid.innerHTML = '';
                return;
            }
            section.classList.remove('hidden');
            const visibleCards = publishedCards.filter(card => !activeStarRatingCategorySlug || (Array.isArray(card.category_slugs) && card.category_slugs.includes(activeStarRatingCategorySlug)));
            if (!visibleCards.length) {
                grid.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">No published star rating cards are available in this category.</div>';
                return;
            }
            grid.innerHTML = visibleCards.map((card, cardIndex) => {
                const title = escapeHtmlDashboard(card.title || 'Star ratings');
                const logo = card.logo_path ? `<img src="${escapeHtmlDashboard(dashboardBaseUrl + card.logo_path)}" alt="${title} logo" class="mb-2 max-h-14 max-w-40 object-contain">` : '';
                const year = card.year ? `<div class="mt-3 border-y border-gray-200 py-1.5 text-center text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">${escapeHtmlDashboard(card.year)}</div>` : '';
                const rows = (Array.isArray(card.rows) ? card.rows : []).map((row, rowIndex) => {
                    const maxStars = Math.max(1, Math.min(10, Math.trunc(Number(row.max_stars) || 1)));
                    const rawScore = Number(row.score);
                    const score = Math.max(0, Math.min(maxStars, Number.isFinite(rawScore) ? rawScore : 0));
                    const scoreText = Number.isInteger(score) ? String(score) : score.toFixed(1);
                    const label = String(row.label || 'Category');
                    const accessibleLabel = `${label}: ${scoreText} out of ${maxStars} stars`;
                    return `<div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-gray-200 py-2.5 dark:border-gray-700" aria-label="${escapeHtmlDashboard(accessibleLabel)}" title="${escapeHtmlDashboard(accessibleLabel)}"><div class="flex max-w-full flex-wrap items-center gap-0.5">${renderRatingStars(maxStars, score, `rating-${cardIndex}-${rowIndex}`)}<span class="ml-1 whitespace-nowrap text-xs font-medium text-gray-600 dark:text-gray-300">${scoreText} / ${maxStars}</span></div><span class="min-w-0 break-words text-sm font-semibold text-gray-800 dark:text-gray-100">${escapeHtmlDashboard(label)}</span></div>`;
                }).join('');
                return `<article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"><header class="flex min-h-20 flex-col items-center justify-center text-center">${logo}<h3 class="text-base font-bold text-gray-900 dark:text-white">${title}</h3></header>${year}<div class="mt-2">${rows}</div></article>`;
            }).join('');
        }

        const summaryCardsCsrfToken = <?= json_encode(csrf_token()) ?>;
        let summaryCardsData = [];
        let summaryCardCategories = [];
        let activeSummaryCategorySlug = '';

        function updateSummaryCategoryUrl(slug, replace = false) {
            const url = new URL(window.location.href);
            if (slug) url.searchParams.set('category', slug);
            else url.searchParams.delete('category');
            const method = replace ? 'replaceState' : 'pushState';
            window.history[method]({}, '', url);
        }

        function renderSummaryCategoryChips() {
            const filter = document.getElementById('summaryCardCategoryFilter');
            const chips = document.getElementById('summaryCardCategoryChips');
            if (!filter || !chips) return;
            if (summaryCardCategories.length === 0) {
                filter.classList.add('hidden');
                chips.innerHTML = '';
                return;
            }
            filter.classList.remove('hidden');
            const options = [{ slug: '', name: 'All' }, ...summaryCardCategories];
            chips.innerHTML = options.map(category => {
                const selected = activeSummaryCategorySlug === category.slug;
                return `<button type="button" class="shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 ${selected ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 hover:bg-green-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'}" data-category-slug="${escapeHtmlDashboard(category.slug)}" aria-pressed="${selected ? 'true' : 'false'}">${escapeHtmlDashboard(category.name)}</button>`;
            }).join('');
            chips.querySelectorAll('button[data-category-slug]').forEach(button => button.addEventListener('click', () => {
                const slug = button.dataset.categorySlug || '';
                if (slug === activeSummaryCategorySlug) return;
                activeSummaryCategorySlug = slug;
                updateSummaryCategoryUrl(slug);
                renderSummaryCategoryChips();
                renderSummaryCards(summaryCardsData);
            }));
        }

        function restoreSummaryCategoryFromUrl(replaceUnknown = false) {
            const requested = new URL(window.location.href).searchParams.get('category') || '';
            const category = summaryCardCategories.find(item => item.slug === requested);
            activeSummaryCategorySlug = category ? category.slug : '';
            if (requested && !category && replaceUnknown) updateSummaryCategoryUrl('', true);
            renderSummaryCategoryChips();
            renderSummaryCards(summaryCardsData);
        }

        window.addEventListener('popstate', () => restoreSummaryCategoryFromUrl());

        function renderSummaryCards(cards) {
            const grid = document.getElementById('summaryCardsGrid');
            if (!grid) return;
            const publishedCards = Array.isArray(cards) ? cards.filter(card => card && (card.is_published === true || card.is_published === 1 || card.is_published === '1')) : [];
            const visibleCards = publishedCards.filter(card => !activeSummaryCategorySlug || (Array.isArray(card.category_slugs) && card.category_slugs.includes(activeSummaryCategorySlug)));
            if (!visibleCards.length) {
                const message = activeSummaryCategorySlug
                    ? 'There are no published summary cards in this category yet.'
                    : 'No performance snapshot cards are currently published.';
                grid.innerHTML = `<div class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 text-center text-sm text-gray-500 dark:text-gray-400 py-10">${message}</div>`;
                return;
            }

            const isAdmin = <?= json_encode(($_SESSION['role'] ?? null) === 'super_admin') ?>;
            grid.innerHTML = visibleCards
                .slice()
                .sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0))
                .map(card => {
                    const mainValue = formatSummaryCardValue(card.main_value, card.display_precision ?? 2);
                    const secondaryValue = card.secondary_value ? formatSummaryCardValue(card.secondary_value, card.display_precision ?? 2) : '';
                    const secondaryLabel = card.secondary_label ? escapeHtmlDashboard(card.secondary_label) : '';
                    const description = card.description ? escapeHtmlDashboard(card.description) : '';
                    const mainLabel = escapeHtmlDashboard(card.main_label || 'Current snapshot');
                    const yearDate = escapeHtmlDashboard(card.year_date || '');
                    const adminControls = isAdmin ? `
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="summary-card-edit rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 dark:text-gray-200" data-id="${escapeHtmlDashboard(card.id)}">Edit</button>
                            <button type="button" class="summary-card-toggle rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 dark:text-gray-200" data-id="${escapeHtmlDashboard(card.id)}" data-published="${card.is_published ? '1' : '0'}">${card.is_published ? 'Unpublish' : 'Publish'}</button>
                            <button type="button" class="summary-card-delete rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-2.5 py-1.5 text-[11px] font-semibold text-red-700 dark:text-red-300" data-id="${escapeHtmlDashboard(card.id)}">Delete</button>
                        </div>` : '';
                    return `
                        <article class="summary-card-shell rounded-2xl p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="summary-card-title text-[10px] font-bold uppercase tracking-[0.14em]">${escapeHtmlDashboard(card.title || 'Performance Snapshot')}</div>
                                    <div class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">${escapeHtmlDashboard(mainValue)}</div>
                                </div>
                                ${yearDate ? `<span class="summary-card-year-badge rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide">${yearDate}</span>` : ''}
                            </div>
                            <div class="summary-card-label mt-2 text-xs font-semibold uppercase tracking-wide">${mainLabel}</div>
                            ${secondaryLabel || secondaryValue ? `<div class="summary-card-second-row mt-4 flex items-baseline justify-between gap-3 border-t pt-3 text-xs">
                                <span class="summary-card-label">${secondaryLabel}</span>
                                <span class="font-bold text-gray-900 dark:text-white">${escapeHtmlDashboard(secondaryValue)}</span>
                            </div>` : ''}
                            ${description ? `<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">${description}</p>` : ''}
                            ${adminControls}
                        </article>`;
                }).join('');

            if (isAdmin) {
                document.querySelectorAll('.summary-card-edit').forEach(button => {
                    button.addEventListener('click', () => {
                        const id = button.dataset.id;
                        const cardsLookup = Array.isArray(cards) ? cards : [];
                        const card = cardsLookup.find(item => String(item.id) === String(id));
                        if (!card) return;
                        document.getElementById('summaryCardId').value = card.id || '';
                        document.getElementById('summaryCardTitle').value = card.title || '';
                        document.getElementById('summaryCardMainValue').value = card.main_value || '';
                        document.getElementById('summaryCardMainLabel').value = card.main_label || '';
                        document.getElementById('summaryCardYearDate').value = card.year_date || '';
                        document.getElementById('summaryCardSecondaryLabel').value = card.secondary_label || '';
                        document.getElementById('summaryCardSecondaryValue').value = card.secondary_value || '';
                        document.getElementById('summaryCardDescription').value = card.description || '';
                        document.getElementById('summaryCardDisplayOrder').value = card.display_order ?? 0;
                        document.getElementById('summaryCardDisplayPrecision').value = String(card.display_precision ?? 2);
                        document.getElementById('summaryCardPublished').checked = !!card.is_published;
                        summaryCardsAdminFormPanel.classList.remove('hidden');
                        summaryCardsAdminForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                });

                document.querySelectorAll('.summary-card-toggle').forEach(button => {
                    button.addEventListener('click', async () => {
                        const id = button.dataset.id;
                        const published = button.dataset.published === '1';
                        await fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_cards&id=' + encodeURIComponent(id), {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': summaryCardsCsrfToken },
                            body: JSON.stringify({ is_published: !published })
                        });
                        if (typeof refreshSummaryCardsAdmin === 'function') {
                            refreshSummaryCardsAdmin();
                        }
                        loadSummaryCards();
                    });
                });

                document.querySelectorAll('.summary-card-delete').forEach(button => {
                    button.addEventListener('click', async () => {
                        const id = button.dataset.id;
                        if (!confirm('Delete this summary card?')) return;
                        await fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_cards&id=' + encodeURIComponent(id), {
                            method: 'DELETE',
                            headers: { Accept: 'application/json', 'X-CSRF-Token': summaryCardsCsrfToken }
                        });
                        if (typeof refreshSummaryCardsAdmin === 'function') {
                            refreshSummaryCardsAdmin();
                        }
                        loadSummaryCards();
                    });
                });
            }
        }

        async function loadSummaryCards() {
            try {
                const response = await fetch('<?= e(base_url('api/dashboard_graphs.php')) ?>', { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Unable to load summary cards');
                const payload = await response.json();
                summaryCardsData = payload.cards || [];
                summaryCardCategories = payload.categories || [];
                restoreSummaryCategoryFromUrl(true);
                starRatingCardsData = payload.star_rating_cards || [];
                starRatingCategories = payload.star_rating_categories || [];
                restoreStarRatingCategoryFromUrl(true);
            } catch (error) {
                const grid = document.getElementById('summaryCardsGrid');
                if (grid) {
                    grid.innerHTML = '<div class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200 px-4 py-6 text-sm text-center">Summary cards could not be loaded.</div>';
                }
            }
        }

        <?php if (($_SESSION['role'] ?? null) === 'super_admin'): ?>
            const summaryCardsAdminForm = document.getElementById('summaryCardsAdminForm');
            const summaryCardsAdminFormPanel = document.getElementById('summaryCardsAdminFormPanel');
            const toggleSummaryCardFormButton = document.getElementById('toggleSummaryCardForm');
            const cancelSummaryCardFormButton = document.getElementById('cancelSummaryCardForm');
            const summaryCardCategorySelect = document.getElementById('summaryCardCategory');
            const summaryCardNewCategory = document.getElementById('summaryCardNewCategory');
            const summaryCardAddCategory = document.getElementById('summaryCardAddCategory');

            async function refreshSummaryCardsAdmin() {
                const [response, categoryResponse] = await Promise.all([
                    fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_cards', { headers: { Accept: 'application/json' } }),
                    fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_card_categories', { headers: { Accept: 'application/json' } })
                ]);
                if (!response.ok || !categoryResponse.ok) throw new Error('Unable to load summary cards');
                const cards = await response.json();
                const categories = await categoryResponse.json();
                const selectedCategories = [...summaryCardCategorySelect.selectedOptions].map(option => option.value);
                summaryCardCategorySelect.innerHTML = categories.map(category => `<option value="${escapeHtmlDashboard(category.id)}">${escapeHtmlDashboard(category.name)}</option>`).join('');
                [...summaryCardCategorySelect.options].forEach(option => { option.selected = selectedCategories.includes(option.value); });
                const container = document.getElementById('summaryCardAdminList');
                if (!container) return;
                if (!Array.isArray(cards) || cards.length === 0) {
                    container.innerHTML = '<div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 px-4 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">No summary cards yet.</div>';
                    return;
                }
                container.innerHTML = cards
                    .sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0))
                    .map(card => `
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">${escapeHtmlDashboard(card.title || 'Snapshot Card')}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtmlDashboard(card.main_value || '')} · ${escapeHtmlDashboard(card.main_label || '')}</div>
                                    <span class="mt-1 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-900 dark:bg-green-900/30 dark:text-green-200">${escapeHtmlDashboard(card.category_name || 'Uncategorized')}</span>
                                </div>
                                <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${card.is_published ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}">${card.is_published ? 'Published' : 'Draft'}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="summary-card-edit rounded-lg border border-gray-300 dark:border-gray-700 px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200" data-id="${escapeHtmlDashboard(card.id)}">Edit</button>
                                <button type="button" class="summary-card-toggle rounded-lg border border-gray-300 dark:border-gray-700 px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200" data-id="${escapeHtmlDashboard(card.id)}" data-published="${card.is_published ? '1' : '0'}">${card.is_published ? 'Unpublish' : 'Publish'}</button>
                                <button type="button" class="summary-card-delete rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:text-red-300" data-id="${escapeHtmlDashboard(card.id)}">Delete</button>
                            </div>
                        </div>
                    `).join('');

                document.querySelectorAll('.summary-card-edit').forEach(button => {
                    button.addEventListener('click', async () => {
                        const id = button.dataset.id;
                        const card = cards.find(item => item.id === id);
                        if (!card) return;
                        document.getElementById('summaryCardId').value = card.id;
                        document.getElementById('summaryCardTitle').value = card.title || '';
                        document.getElementById('summaryCardMainValue').value = card.main_value || '';
                        document.getElementById('summaryCardMainLabel').value = card.main_label || '';
                        document.getElementById('summaryCardYearDate').value = card.year_date || '';
                        document.getElementById('summaryCardSecondaryLabel').value = card.secondary_label || '';
                        document.getElementById('summaryCardSecondaryValue').value = card.secondary_value || '';
                        document.getElementById('summaryCardDescription').value = card.description || '';
                        document.getElementById('summaryCardDisplayOrder').value = card.display_order ?? 0;
                        const selectedIds = (card.category_ids || (card.category_id ? [card.category_id] : [])).map(String);
                        [...summaryCardCategorySelect.options].forEach(option => { option.selected = selectedIds.includes(option.value); });
                        summaryCardNewCategory.value = '';
                        summaryCardNewCategory.classList.add('hidden');
                        document.getElementById('summaryCardDisplayPrecision').value = String(card.display_precision ?? 2);
                        document.getElementById('summaryCardPublished').checked = !!card.is_published;
                        summaryCardsAdminFormPanel.classList.remove('hidden');
                        summaryCardsAdminForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                });

                document.querySelectorAll('.summary-card-toggle').forEach(button => {
                    button.addEventListener('click', async () => {
                        const id = button.dataset.id;
                        const published = button.dataset.published === '1';
                        await fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_cards&id=' + encodeURIComponent(id), {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': summaryCardsCsrfToken },
                            body: JSON.stringify({ is_published: !published })
                        });
                        refreshSummaryCardsAdmin();
                        loadSummaryCards();
                    });
                });

                document.querySelectorAll('.summary-card-delete').forEach(button => {
                    button.addEventListener('click', async () => {
                        const id = button.dataset.id;
                        if (!confirm('Delete this summary card?')) return;
                        await fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_cards&id=' + encodeURIComponent(id), {
                            method: 'DELETE',
                            headers: { Accept: 'application/json', 'X-CSRF-Token': summaryCardsCsrfToken }
                        });
                        refreshSummaryCardsAdmin();
                        loadSummaryCards();
                    });
                });
            }

            summaryCardsAdminForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const payload = {
                    title: document.getElementById('summaryCardTitle').value.trim(),
                    main_value: document.getElementById('summaryCardMainValue').value.trim(),
                    main_label: document.getElementById('summaryCardMainLabel').value.trim(),
                    year_date: document.getElementById('summaryCardYearDate').value.trim(),
                    secondary_label: document.getElementById('summaryCardSecondaryLabel').value.trim(),
                    secondary_value: document.getElementById('summaryCardSecondaryValue').value.trim(),
                    description: document.getElementById('summaryCardDescription').value.trim(),
                    display_order: Number(document.getElementById('summaryCardDisplayOrder').value || 0),
                    display_precision: (() => {
                        const precisionValue = Number(document.getElementById('summaryCardDisplayPrecision').value);
                        return Number.isFinite(precisionValue) ? precisionValue : 2;
                    })(),
                    is_published: document.getElementById('summaryCardPublished').checked
                };
                payload.category_ids = [...summaryCardCategorySelect.selectedOptions].map(option => Number(option.value));
                const newCategoryName = summaryCardNewCategory.value.trim();
                if (newCategoryName) payload.category_names = [newCategoryName];

                const id = document.getElementById('summaryCardId').value;
                const url = '<?= e(base_url('api/iris.php')) ?>?resource=summary_cards' + (id ? '&id=' + encodeURIComponent(id) : '');
                const method = id ? 'PUT' : 'POST';
                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': summaryCardsCsrfToken },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    alert(error.error || 'Unable to save summary card');
                    return;
                }
                summaryCardsAdminForm.reset();
                [...summaryCardCategorySelect.options].forEach(option => { option.selected = false; });
                summaryCardNewCategory.classList.add('hidden');
                summaryCardNewCategory.value = '';
                document.getElementById('summaryCardDisplayOrder').value = '0';
                document.getElementById('summaryCardDisplayPrecision').value = '2';
                document.getElementById('summaryCardPublished').checked = true;
                summaryCardsAdminFormPanel.classList.add('hidden');
                await refreshSummaryCardsAdmin();
                loadSummaryCards();
            });

            toggleSummaryCardFormButton?.addEventListener('click', () => {
                summaryCardsAdminForm.reset();
                summaryCardsAdminFormPanel.classList.toggle('hidden');
                document.getElementById('summaryCardId').value = '';
                [...summaryCardCategorySelect.options].forEach(option => { option.selected = false; });
                summaryCardNewCategory.classList.add('hidden');
                summaryCardNewCategory.value = '';
                document.getElementById('summaryCardDisplayOrder').value = '0';
                document.getElementById('summaryCardDisplayPrecision').value = '2';
                document.getElementById('summaryCardPublished').checked = true;
            });

            cancelSummaryCardFormButton?.addEventListener('click', () => {
                summaryCardsAdminForm.reset();
                [...summaryCardCategorySelect.options].forEach(option => { option.selected = false; });
                summaryCardNewCategory.classList.add('hidden');
                summaryCardNewCategory.value = '';
                summaryCardsAdminFormPanel.classList.add('hidden');
            });

            summaryCardAddCategory.addEventListener('click', () => {
                summaryCardNewCategory.classList.toggle('hidden');
                if (!summaryCardNewCategory.classList.contains('hidden')) summaryCardNewCategory.focus();
            });

            const summaryCardAdminList = document.createElement('div');
            summaryCardAdminList.id = 'summaryCardAdminList';
            summaryCardAdminList.className = 'grid gap-3';
            const formPanel = document.getElementById('summaryCardsAdminFormPanel');
            if (formPanel) {
                formPanel.appendChild(summaryCardAdminList);
            }
            refreshSummaryCardsAdmin().catch(() => {});
        <?php endif; ?>

        const rankingPalette = window.IRISChartConfig?.palettes || { default: ['#0F766E', '#5EEAD4'] };
        let rankingRows = [];
        let rankingScopes = [];
        let rankingTypes = [];
        let rankingChartInstances = [];
        let rankingAllYearsActive = true;
        let activeRankingLevel = 'all';
        let activeRankingScope = 'all';
        let activeRankingType = 'all';
        let rankingChartColorOverrides = {};
        try {
            const storedRankingColors = JSON.parse(localStorage.getItem('iris-ranking-series-colors') || '{}');
            if (storedRankingColors && typeof storedRankingColors === 'object' && !Array.isArray(storedRankingColors)) {
                rankingChartColorOverrides = storedRankingColors;
            }
        } catch (error) {}

        function rankingColor(shortName) {
            return rankingPalette[shortName] || rankingPalette.default;
        }

        function rankingSeriesColor(name, body) {
            const override = rankingChartColorOverrides[name];
            const legacyColors = document.documentElement.classList.contains('dark')
                ? ['#34D399', '#E0A70D', '#60A5FA', '#F87171', '#C084FC', '#22D3EE']
                : rankingColor(body);
            return window.IRISChartColors.resolveFieldColors([name], {
                chartColors: [override],
                fieldColors: window.IRISFieldColors || {},
                legacyColors: [legacyColors[0]],
                defaultColors: window.IRISChartColors.DEFAULT_CHART_COLORS
            })[0];
        }

        function bindRankingFamilyColor(input, familyName) {
            input.addEventListener('change', () => {
                rankingChartColorOverrides[familyName] = input.value.toUpperCase();
                try { localStorage.setItem('iris-ranking-series-colors', JSON.stringify(rankingChartColorOverrides)); } catch (error) {}
                renderRankingHistoryFromControls();
            });
        }

        function getRankingRowsForLevelAndScope() {
            return rankingRows.filter(row => {
                const matchesLevel = activeRankingLevel === 'all'
                    || (activeRankingLevel === 'unassigned' ? !row.level : row.level === activeRankingLevel);
                const matchesScope = activeRankingScope === 'all'
                    || (activeRankingScope === 'unassigned' ? !row.scope_id : String(row.scope_id) === activeRankingScope);
                return matchesLevel && matchesScope;
            });
        }

        function getFilteredRankingRows() {
            return getRankingRowsForLevelAndScope().filter(row => activeRankingType === 'all'
                || String(row.ranking_type || row.body_short_name || row.body_name) === activeRankingType);
        }

        function renderRankingTypeOptions() {
            const select = document.getElementById('rankingTypeFilter');
            if (!select) return;
            const selected = activeRankingType;
            const availableTypes = [...new Set(rankingTypes.map(type => String(type)))].sort((left, right) => left.localeCompare(right));
            select.innerHTML = '<option value="all">All types</option>' + availableTypes.map(type => `<option value="${escapeHtmlDashboard(type)}">${escapeHtmlDashboard(type)}</option>`).join('');
            activeRankingType = [...select.options].some(option => option.value === selected) ? selected : 'all';
            select.value = activeRankingType;
        }

        function renderRankingScopeOptions() {
            const select = document.getElementById('rankingScopeFilter');
            if (!select) return;
            const selected = activeRankingScope;
            select.innerHTML = '<option value="all">All scopes</option>'
                + '<option value="unassigned">Unassigned</option>'
                + rankingScopes.map(scope => `<option value="${escapeHtmlDashboard(scope.id)}">${escapeHtmlDashboard(scope.name)}</option>`).join('');
            activeRankingScope = [...select.options].some(option => option.value === selected) ? selected : 'all';
            select.value = activeRankingScope;
        }

        function getRankingFamily(row) {
            return (row.ranking_type || row.body_short_name || row.body_name) + ' ' + (row.scope_name || 'Unassigned');
        }

        function rankingChange(row, selectedYear) {
            if (!Number.isFinite(Number(row.rank_value))) return null;
            const previous = rankingRows
                .filter(item => (item.ranking_type || item.body_short_name) === (row.ranking_type || row.body_short_name)
                    && item.scope_id === row.scope_id && item.category === row.category
                    && Number(item.year) < Number(selectedYear) && Number.isFinite(Number(item.rank_value)))
                .sort((a, b) => Number(b.year) - Number(a.year))[0];
            if (!previous) return null;
            
            const diff = Number(row.rank_value) - Number(previous.rank_value);
            const isDown = diff > 0; // larger number = worse rank = DOWN
            const isUp = diff < 0; // smaller number = better rank = UP
            const symbol = isUp ? '▲' : isDown ? '▼' : '–';
            const className = isUp ? 'rank-change-up' : isDown ? 'rank-change-down' : 'rank-change-same';
            
            const isBand = (r) => (r.rank_low && r.rank_high && r.rank_low !== r.rank_high) || String(r.global_rank).includes('+') || String(r.global_rank).includes('-');
            
            if (isBand(row) || isBand(previous)) {
                return { difference: diff, symbol, className, previous, text: diff === 0 ? '–' : `${symbol} from ${previous.global_rank || previous.rank_value}` };
            }
            return { difference: diff, symbol, className, previous, text: diff === 0 ? '–' : `${symbol} ${Math.abs(diff)}` };
        }

        function renderRankingHistoryChart(startYear = 'all', endYear = 'all') {
            const chartElement = document.getElementById('rankingChart');
            if (!chartElement) return;
            const allYears = String(startYear) === 'all' || String(endYear) === 'all';
            const validRows = getFilteredRankingRows().filter(row => row.rank_value !== null && row.rank_value !== ''
                && Number.isFinite(Number(row.rank_value))
                && (allYears || (Number(row.year) >= Number(startYear) && Number(row.year) <= Number(endYear))));
            
            // Clean up old instances
            rankingChartInstances.forEach(c => c && c.dispose());
            rankingChartInstances = [];
            chartElement.innerHTML = '';
            
            if (!validRows.length) return;
            
            // SDG Special View
            if (activeRankingType === 'THE Impact SDG') {
                renderSDGChart(validRows, chartElement, startYear, endYear);
                return;
            }

            const years = [...new Set(validRows.map(row => String(row.year)))].sort((a, b) => Number(a) - Number(b));
            const families = new Map();
            validRows.forEach(row => {
                const type = String(row.ranking_type || row.body_short_name || row.body_name || 'Ranking');
                if (type === 'THE Impact SDG') return; // Exclude from default view
                const body = String(row.body_short_name || row.body_name || type);
                const scope = String(row.scope_name || 'Unassigned');
                const family = `${type} ${scope}`;
                if (!families.has(family)) families.set(family, { type, scope, body, dataByYear: new Map() });
                
                // Get latest edition per year (key must be String to match years array)
                const yData = families.get(family).dataByYear;
                const yearKey = String(row.year);
                if (!yData.has(yearKey)) {
                    yData.set(yearKey, []);
                }
                yData.get(yearKey).push(row);
            });

            const dark = document.documentElement.classList.contains('dark');
            const textColor = dark ? '#E5E7EB' : '#475569';
            const splitLineColor = dark ? 'rgba(203,213,225,.22)' : 'rgba(30,96,49,.10)';

            // Setup responsive grid on parent
            chartElement.className = 'grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-6';

            for (const [family, group] of families.entries()) {
                const div = document.createElement('div');
                div.style.minHeight = '300px';
                div.style.width = '100%';
                chartElement.appendChild(div);
                
                const instance = echarts.init(div);
                rankingChartInstances.push(instance);
                
                const seriesColor = rankingSeriesColor(family, group.body);
                const data = years.map(y => {
                    const yStr = String(y);
                    if (!group.dataByYear.has(yStr)) return null;
                    const editions = group.dataByYear.get(yStr);
                    editions.sort((a, b) => String(a.edition || '').localeCompare(String(b.edition || ''))); // sort by edition
                    const latest = editions[editions.length - 1]; // pick latest
                    return {
                        value: Number(latest.rank_value),
                        allEditions: editions,
                        latestRank: latest.global_rank || latest.rank_value
                    };
                });

                // Calculate bounds so single data point or flat line displays properly
                const validValues = data.filter(d => d !== null && d.value !== null && Number.isFinite(d.value)).map(d => d.value);
                let yMin = undefined;
                let yMax = undefined;
                if (validValues.length > 0) {
                    const minVal = Math.min(...validValues);
                    const maxVal = Math.max(...validValues);
                    if (minVal === maxVal) {
                        yMin = Math.max(1, minVal - 5);
                        yMax = maxVal + 5;
                    }
                }

                instance.setOption({
                    title: { text: family, left: 'center', textStyle: { color: textColor, fontSize: 14 } },
                    color: [seriesColor],
                    tooltip: {
                        trigger: 'item',
                        backgroundColor: dark ? '#172033' : '#fff',
                        borderColor: '#dfe7df',
                        textStyle: { color: dark ? '#f8fafc' : '#1f2937', fontSize: 13 },
                        formatter: params => {
                            const d = params.data;
                            if (!d) return '';
                            let html = `<b>${escapeHtmlDashboard(family)} - ${params.name}</b><br>`;
                            d.allEditions.forEach(ed => {
                                html += `${escapeHtmlDashboard(ed.edition || 'Annual')}: <b>${escapeHtmlDashboard(ed.global_rank || ed.rank_value)}</b><br>`;
                            });
                            return html;
                        }
                    },
                    grid: { left: '10%', right: '5%', top: '20%', bottom: '15%', containLabel: true },
                    xAxis: {
                        type: 'category',
                        data: years,
                        axisLabel: { interval: 0, color: textColor, hideOverlap: true },
                        axisLine: { lineStyle: { color: splitLineColor } },
                        splitLine: { show: false }
                    },
                    yAxis: {
                        type: 'value',
                        inverse: true,
                        min: yMin,
                        max: yMax,
                        minInterval: 1,
                        axisLabel: {
                            color: textColor,
                            formatter: value => '#' + Math.round(value)
                        },
                        splitLine: { lineStyle: { color: splitLineColor } },
                        scale: true
                    },
                    series: [{
                        type: 'line',
                        data: data,
                        connectNulls: true,
                        symbol: 'circle',
                        symbolSize: 10,
                        showSymbol: true,
                        lineStyle: { width: 3 },
                        itemStyle: { color: seriesColor }
                    }]
                });
            }
        }

        function renderSDGChart(validRows, chartElement, startYear, endYear) {
            const sdgRows = validRows.filter(r => r.ranking_type === 'THE Impact SDG');
            if (!sdgRows.length) return;
            // Get selected year or latest
            const years = [...new Set(sdgRows.map(r => r.year))].sort((a, b) => b - a);
            const targetYear = startYear !== 'all' ? startYear : years[0];
            const rows = sdgRows.filter(r => r.year == targetYear).sort((a, b) => Number(a.rank_value) - Number(b.rank_value));
            
            chartElement.className = 'grid grid-cols-1 gap-6'; // single column for SDG
            
            const div = document.createElement('div');
            div.style.minHeight = '400px';
            div.style.width = '100%';
            chartElement.appendChild(div);
            
            const instance = echarts.init(div);
            rankingChartInstances.push(instance);
            
            const dark = document.documentElement.classList.contains('dark');
            const textColor = dark ? '#E5E7EB' : '#475569';
            
            instance.setOption({
                title: { text: `THE Impact SDG - ${targetYear}`, left: 'center', textStyle: { color: textColor } },
                tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
                grid: { left: '20%', right: '10%', top: '15%', bottom: '10%', containLabel: true },
                xAxis: { type: 'value', inverse: true, show: false },
                yAxis: { type: 'category', data: rows.map(r => r.category || 'SDG').reverse(), axisLabel: { color: textColor } },
                series: [{
                    type: 'bar',
                    data: rows.map(r => ({ value: Number(r.rank_value), labelText: r.global_rank })).reverse(),
                    itemStyle: { color: '#0F766E' },
                    label: { show: true, position: 'right', formatter: p => p.data.labelText, color: textColor }
                }]
            });
        }

        function renderRankingHistory(startYear = 'all', endYear = 'all') {
            const table = document.getElementById('rankingTable');
            const status = document.getElementById('rankingTableStatus');
            const body = table?.querySelector('tbody');
            const allYears = String(startYear) === 'all' || String(endYear) === 'all';
            let rows = getFilteredRankingRows().filter(row => (allYears || (Number(row.year) >= Number(startYear) && Number(row.year) <= Number(endYear)))
                && row.rank_value !== null && row.rank_value !== '');
                
            if (!table || !status || !body) return;
            body.innerHTML = '';
            
            // Clean up any previously created extra tbodys
            const allTbodys = table.querySelectorAll('tbody');
            for (let i = 1; i < allTbodys.length; i++) {
                allTbodys[i].remove();
            }
            
            renderRankingHistoryChart(startYear, endYear);
            
            if (!rows.length) {
                table.classList.add('hidden');
                status.innerHTML = `<div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 p-6 text-center"><i class="fa-solid fa-folder-open mb-2 text-2xl text-gray-400"></i><p>No numeric rankings found for the selected filters.</p></div>`;
                status.classList.remove('hidden');
                return;
            }
            status.classList.add('hidden');
            table.classList.remove('hidden');
            
            // Default sort: newest year first, then Level, then ranking
            rows.sort((a, b) => {
                if (a.year !== b.year) return b.year - a.year;
                if (a.level !== b.level) return String(a.level).localeCompare(String(b.level));
                return String(a.ranking_type).localeCompare(String(b.ranking_type));
            });

            // Group THE Impact SDG
            const groupedRows = [];
            let sdgGroup = null;
            
            rows.forEach(row => {
                if (row.ranking_type === 'THE Impact SDG') {
                    if (!sdgGroup) {
                        sdgGroup = { isGroup: true, rows: [] };
                        groupedRows.push(sdgGroup);
                    }
                    sdgGroup.rows.push(row);
                } else {
                    groupedRows.push(row);
                }
            });

            groupedRows.forEach(item => {
                if (item.isGroup) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 cursor-pointer';
                    tr.innerHTML = `<td colspan="6" class="py-3 px-4 font-bold text-gray-700 dark:text-gray-200"><i class="fa-solid fa-chevron-down mr-2 text-xs"></i> THE Impact SDG (${item.rows.length} records)</td>`;
                    
                    const tbody = document.createElement('tbody');
                    tbody.style.display = 'none';
                    item.rows.forEach(row => {
                        const change = rankingChange(row, row.year);
                        const changeText = change ? change.text : 'New';
                        const changeTooltip = change && change.previous ? ` title="Compared to ${change.previous.year} ${escapeHtmlDashboard(change.previous.edition||'')}"` : '';
                        const trInner = document.createElement('tr');
                        trInner.className = 'border-t border-gray-50 dark:border-gray-800 text-sm';
                        const scopeAndCategory = [row.scope_name, row.category].filter(Boolean).join(' · ') || 'Unassigned';
                        trInner.innerHTML = `<td class="py-2 pr-4 pl-8">${escapeHtmlDashboard(row.year)}</td><td class="py-2 pr-4 text-gray-500">${escapeHtmlDashboard(row.ranking_type)}</td><td class="py-2 pr-4">${escapeHtmlDashboard(row.level||'')}</td><td class="py-2 pr-4 max-w-[200px] truncate" title="${escapeHtmlDashboard(scopeAndCategory)}">${escapeHtmlDashboard(scopeAndCategory)}</td><td class="py-2 pr-4 font-bold">${escapeHtmlDashboard(row.global_rank || row.rank_value)}</td><td class="py-2"><span class="${change?.className || 'rank-change-same'} font-bold whitespace-nowrap" ${changeTooltip}>${escapeHtmlDashboard(changeText)}</span></td>`;
                        tbody.appendChild(trInner);
                    });
                    
                    tr.addEventListener('click', () => {
                        tbody.style.display = tbody.style.display === 'none' ? 'table-row-group' : 'none';
                        tr.querySelector('i').className = tbody.style.display === 'none' ? 'fa-solid fa-chevron-right mr-2 text-xs' : 'fa-solid fa-chevron-down mr-2 text-xs';
                    });
                    
                    body.appendChild(tr);
                    table.appendChild(tbody);
                } else {
                    const row = item;
                    const change = rankingChange(row, row.year);
                    const changeText = change ? change.text : 'New';
                    const changeTooltip = change && change.previous ? ` title="Compared to ${change.previous.year} ${escapeHtmlDashboard(change.previous.edition||'')}"` : '';
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-gray-100 dark:border-gray-700';
                    const edition = row.edition && row.edition !== 'Annual' ? ` · ${row.edition}` : '';
                    const scopeAndCategory = [row.scope_name, row.category].filter(Boolean).join(' · ') || 'Unassigned';
                    tr.innerHTML = `<td class="py-3 pr-4">${escapeHtmlDashboard(String(row.year) + edition)}</td><td class="py-3 pr-4 font-semibold" style="color: ${rankingSeriesColor(getRankingFamily(row), row.body_short_name||row.body_name)}">${escapeHtmlDashboard(row.ranking_type || row.body_short_name || row.body_name)}</td><td class="py-3 pr-4">${escapeHtmlDashboard(row.level || 'Unclassified')}</td><td class="py-3 pr-4 max-w-[200px] truncate" title="${escapeHtmlDashboard(scopeAndCategory)}">${escapeHtmlDashboard(scopeAndCategory)}</td><td class="py-3 pr-4 font-bold">${escapeHtmlDashboard(row.global_rank || row.rank_value)}</td><td class="py-3"><span class="${change?.className || 'rank-change-same'} font-bold whitespace-nowrap" ${changeTooltip}>${escapeHtmlDashboard(changeText)}</span></td>`;
                    body.appendChild(tr);
                }
            });
        }
        function loadRankingHistory() {
            const fromSelect = document.getElementById('rankingYearFrom');
            const toSelect = document.getElementById('rankingYearTo');
            const scopeSelect = document.getElementById('rankingScopeFilter');
            const levelSelect = document.getElementById('rankingLevelFilter');
            const typeSelect = document.getElementById('rankingTypeFilter');
            fetch('<?= e(base_url('api/rankings.php')) ?>', { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(response => { if (!response.ok) throw new Error('Ranking request failed'); return response.json(); })
                .then(payload => {
                    rankingRows = Array.isArray(payload.rankings) ? payload.rankings : [];
                    rankingScopes = Array.isArray(payload.scopes) ? payload.scopes : [];
                    rankingTypes = Array.isArray(payload.ranking_types) ? payload.ranking_types : [];
                    const years = Array.isArray(payload.years) ? payload.years : [];
                    if (!fromSelect || !toSelect || !scopeSelect || !levelSelect || !typeSelect || !years.length) throw new Error('No ranking years available');
                    const ascendingYears = years.map(Number).sort((left, right) => left - right);
                    const yearOptions = ascendingYears.map(year => `<option value="${year}">${year}</option>`).join('');
                    fromSelect.innerHTML = yearOptions;
                    toSelect.innerHTML = yearOptions;
                    fromSelect.disabled = false;
                    toSelect.disabled = false;
                    levelSelect.innerHTML = '<option value="all">All levels</option><option>Local</option><option>ASEAN</option><option>Asia</option><option>World</option><option value="unassigned">Unassigned</option>';
                    levelSelect.disabled = false;
                    activeRankingLevel = 'all';
                    activeRankingScope = 'all';
                    activeRankingType = 'all';
                    renderRankingScopeOptions();
                    renderRankingTypeOptions();
                    fromSelect.value = String(ascendingYears[0]);
                    toSelect.value = String(ascendingYears[ascendingYears.length - 1]);
                    rankingAllYearsActive = true;
                    updateRankingAllYearsButton();
                    renderRankingHistory('all', 'all');
                })
                .catch(() => {
                    if (fromSelect) { fromSelect.innerHTML = '<option>Unavailable</option>'; fromSelect.disabled = true; }
                    if (toSelect) { toSelect.innerHTML = '<option>Unavailable</option>'; toSelect.disabled = true; }
                    if (scopeSelect) { scopeSelect.innerHTML = '<option>Unavailable</option>'; scopeSelect.disabled = true; }
                    if (levelSelect) { levelSelect.innerHTML = '<option>Unavailable</option>'; levelSelect.disabled = true; }
                    if (typeSelect) { typeSelect.innerHTML = '<option>Unavailable</option>'; typeSelect.disabled = true; }
                    const status = document.getElementById('rankingTableStatus');
                    if (status) status.textContent = 'Ranking data could not be loaded.';
                });
        }

        function updateRankingAllYearsButton() {
            const button = document.getElementById('rankingAllYears');
            if (!button) return;
            button.setAttribute('aria-pressed', String(rankingAllYearsActive));
            button.className = `rounded-lg border px-3 py-2 ${rankingAllYearsActive ? 'border-green-800 bg-green-800 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950' : 'border-green-800/30 bg-white text-green-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200'}`;
        }

        function renderRankingHistoryFromControls() {
            if (rankingAllYearsActive) {
                renderRankingHistory('all', 'all');
                return;
            }
            const startYear = Number(document.getElementById('rankingYearFrom')?.value);
            const endYear = Number(document.getElementById('rankingYearTo')?.value);
            if (Number.isFinite(startYear) && Number.isFinite(endYear)) renderRankingHistory(startYear, endYear);
        }

        document.getElementById('rankingAllYears')?.addEventListener('click', () => {
            rankingAllYearsActive = true;
            const fromSelect = document.getElementById('rankingYearFrom');
            const toSelect = document.getElementById('rankingYearTo');
            if (fromSelect?.options.length) fromSelect.selectedIndex = 0;
            if (toSelect?.options.length) toSelect.selectedIndex = toSelect.options.length - 1;
            updateRankingAllYearsButton();
            renderRankingHistoryFromControls();
        });
        document.getElementById('rankingYearFrom')?.addEventListener('change', event => {
            rankingAllYearsActive = false;
            const toSelect = document.getElementById('rankingYearTo');
            if (Number(event.target.value) > Number(toSelect.value)) toSelect.value = event.target.value;
            updateRankingAllYearsButton();
            renderRankingHistoryFromControls();
        });
        document.getElementById('rankingYearTo')?.addEventListener('change', event => {
            rankingAllYearsActive = false;
            const fromSelect = document.getElementById('rankingYearFrom');
            if (Number(event.target.value) < Number(fromSelect.value)) fromSelect.value = event.target.value;
            updateRankingAllYearsButton();
            renderRankingHistoryFromControls();
        });
        document.getElementById('rankingScopeFilter')?.addEventListener('change', event => {
            activeRankingScope = event.target.value || 'all';
            activeRankingType = 'all';
            renderRankingTypeOptions();
            renderRankingHistoryFromControls();
        });
        document.getElementById('rankingLevelFilter')?.addEventListener('change', event => {
            activeRankingLevel = event.target.value || 'all';
            activeRankingScope = 'all';
            activeRankingType = 'all';
            renderRankingScopeOptions();
            renderRankingTypeOptions();
            renderRankingHistoryFromControls();
        });
        document.getElementById('rankingTypeFilter')?.addEventListener('change', event => {
            activeRankingType = event.target.value || 'all';
            renderRankingHistoryFromControls();
        });

        // --- Apache ECharts Data & Initialization ---
        const trendYears = [];
        const trendRanks = [];
        const trendDisplay = [];
        const collegePieData = [];
        const breakdownSections = [];

        let chartInstances = [];

        function renderAllCharts() {
            chartInstances.forEach(c => c && c.dispose());
            chartInstances = [];

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#E5E7EB' : '#1F2937';
            const splitLineColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.06)';
            const tooltipBg = isDark ? '#1f2937' : '#ffffff';
            const tooltipBorder = isDark ? '#374151' : '#e5e7eb';
            const tooltipText = isDark ? '#f9fafb' : '#111827';

            // 1. QS Rank Line Chart
            const trendElem = document.getElementById('trendChart');
            if (trendElem) {
                const trendColor = window.IRISChartColors.resolveFieldColors(['Rank'], { fieldColors: window.IRISFieldColors, legacyColors: ['#10b981'] })[0];
                const trendChart = echarts.init(trendElem);
                chartInstances.push(trendChart);
                trendChart.setOption({
                    backgroundColor: 'transparent',
                    tooltip: {
                        trigger: 'axis',
                        backgroundColor: tooltipBg,
                        borderColor: tooltipBorder,
                        textStyle: { color: tooltipText },
                        formatter: function(params) {
                            const idx = params[0].dataIndex;
                            return `Year: <b>${trendYears[idx]}</b><br/>Standing: <b>${trendDisplay[idx] || '—'}</b>`;
                        }
                    },
                    grid: { left: '3%', right: '4%', bottom: '3%', top: '10%', containLabel: true },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: trendYears,
                        axisLine: { lineStyle: { color: splitLineColor } },
                        axisLabel: { color: textColor }
                    },
                    yAxis: {
                        type: 'value',
                        inverse: true, // Lower number = higher rank
                        splitLine: { lineStyle: { color: splitLineColor } },
                        axisLabel: { color: textColor }
                    },
                    series: [{
                        name: 'Rank',
                        type: 'line',
                        smooth: true,
                        data: trendRanks,
                        lineStyle: { width: 3, color: trendColor },
                        itemStyle: { color: trendColor },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: 'rgba(16, 185, 129, 0.35)' },
                                { offset: 1, color: 'rgba(16, 185, 129, 0.0)' }
                            ])
                        }
                    }]
                });
            }

            // 2. College Contribution Doughnut Chart
            const collegeElem = document.getElementById('collegeChart');
            if (collegeElem) {
                const collegeColors = window.IRISChartColors.resolveFieldColors(collegePieData.map(item => item.name), {
                    fieldColors: window.IRISFieldColors,
                    legacyColors: ['#1E6031', '#B7791F', '#0F766E', '#2563EB', '#C2410C', '#7C3AED']
                });
                const collegeChart = echarts.init(collegeElem);
                chartInstances.push(collegeChart);
                collegeChart.setOption({
                    backgroundColor: 'transparent',
                    tooltip: {
                        trigger: 'item',
                        backgroundColor: tooltipBg,
                        borderColor: tooltipBorder,
                        textStyle: { color: tooltipText },
                        formatter: params => `${params.name}<br/><strong>${Number(params.value).toLocaleString(undefined, { maximumFractionDigits: 2 })}</strong> (${params.percent}%)`
                    },
                    legend: {
                        orient: 'vertical',
                        right: '1%',
                        top: 'middle',
                        width: '42%',
                        type: 'scroll',
                        textStyle: { color: textColor, fontSize: 11 },
                        formatter: name => String(name).length > 24 ? `${String(name).slice(0, 24)}...` : name
                    },
                    series: [{
                        type: 'pie',
                        radius: ['45%', '70%'],
                        center: ['32%', '50%'],
                        label: {
                            show: true,
                            color: textColor,
                            fontSize: 10,
                            formatter: params => {
                                const name = String(params.name || 'College');
                                const displayName = name.length > 18 ? `${name.slice(0, 18)}...` : name;
                                return `${displayName}: ${params.percent}%`;
                            }
                        },
                        labelLine: { show: true, length: 10, length2: 8 },
                        itemStyle: {
                            borderRadius: 6,
                            borderColor: isDark ? '#1f2937' : '#ffffff',
                            borderWidth: 2
                        },
                        data: collegePieData.map((item, index) => ({ ...item, itemStyle: { ...(item.itemStyle || {}), color: collegeColors[index] } }))
                    }],
                    color: collegeColors
                });
            }

            // 3. Category Breakdown Mini Bar Charts
            breakdownSections.forEach((section, idx) => {
                const elem = document.getElementById('breakdownChart' + idx);
                if (!elem) return;
                const sectionColors = window.IRISChartColors.resolveFieldColors(section.labels, {
                    fieldColors: window.IRISFieldColors,
                    legacyColors: section.labels.map(() => '#10b981')
                });
                const chart = echarts.init(elem);
                chartInstances.push(chart);
                chart.setOption({
                    backgroundColor: 'transparent',
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: { type: 'shadow' },
                        backgroundColor: tooltipBg,
                        borderColor: tooltipBorder,
                        textStyle: { color: tooltipText },
                        formatter: function(params) {
                            const dataIndex = params[0].dataIndex;
                            return `${section.labels[dataIndex]}<br/>Rank/Score: <b>${section.displays[dataIndex]}</b>`;
                        }
                    },
                    grid: { left: '3%', right: '5%', bottom: '3%', top: '5%', containLabel: true },
                    xAxis: {
                        type: 'value',
                        inverse: true,
                        splitLine: { lineStyle: { color: splitLineColor } },
                        axisLabel: { color: textColor, fontSize: 10 }
                    },
                    yAxis: {
                        type: 'category',
                        data: section.labels,
                        axisLine: { lineStyle: { color: splitLineColor } },
                        axisLabel: {
                            color: textColor,
                            fontSize: 10,
                            formatter: function(val) {
                                return val.length > 15 ? val.slice(0, 15) + '...' : val;
                            }
                        }
                    },
                    color: sectionColors,
                    series: [{
                        type: 'bar',
                        data: section.values.map((value, index) => ({ value, itemStyle: { color: sectionColors[index] } })),
                        itemStyle: { color: '#10b981', borderRadius: [4, 0, 0, 4] }
                    }]
                });
            });
        }

        // Live Filters
        const canManagePublishedGraphs = <?= json_encode(($_SESSION['role'] ?? null) === 'super_admin') ?>;

        function renderPublishedScannerGraphs(graphs) {
            const grid = document.getElementById('scannerPublishedGraphsGrid');
            const empty = document.getElementById('scannerPublishedGraphsEmpty');
            const count = document.getElementById('publishedGraphCount');
            if (!grid || !count) return;

            const replacedCharts = new Set();
            grid.querySelectorAll('.scanner-published-card').forEach(el => {
                el._chartResizeObserver?.disconnect?.();
                el._publishedChart?.dispose?.();
                if (el._publishedChart) replacedCharts.add(el._publishedChart);
                el.remove();
            });
            chartInstances = chartInstances.filter(chart => !replacedCharts.has(chart));
            if (!Array.isArray(graphs) || graphs.length === 0) {
                if (empty) empty.style.display = '';
                count.innerHTML = '<i class="fa-solid fa-circle-info mr-1"></i> 0 published graphs';
                return;
            }
            if (empty) empty.style.display = 'none';
            count.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${graphs.length} published graph${graphs.length === 1 ? '' : 's'}`;

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#F8FAFC' : '#4b5563';
            const splitLineColor = isDark ? '#374151' : '#f3f4f6';
            const tooltipBg = isDark ? '#1f2937' : '#ffffff';
            const tooltipBorder = isDark ? '#374151' : '#e5e7eb';
            const tooltipText = isDark ? '#f9fafb' : '#111827';

            graphs.forEach((graph, index) => {
                const card = document.createElement('div');
                card.className = 'scanner-published-card bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm';
                const chartId = `scannerPublishedChart_${index}`;
                const base = window.GraphExport && typeof window.GraphExport.buildSavedChartOption === 'function'
                    ? window.GraphExport.buildSavedChartOption(graph)
                    : null;
                const labels = Array.isArray(graph.labels) ? graph.labels.map(v => String(v ?? '')) : (base?.xAxis?.data || []);
                const storedType = String(graph.chart_type || 'bar').toLowerCase();
                const normalizedType = ['bar', 'line', 'pie', 'doughnut', 'polararea', 'rankedbar', 'ranked-bar'].includes(storedType)
                    ? storedType.replace(/-+/g, '')
                    : (base && base.series && base.series[0] && base.series[0].type ? base.series[0].type : 'bar');
                const type = normalizedType === 'rankedbar' ? 'rankedBar' : normalizedType;
                const values = Array.isArray(graph.values_data) ? graph.values_data.map(v => {
                    if (v === null || v === undefined || String(v).trim() === '') return null;
                    const n = Number(v);
                    return Number.isFinite(n) ? n : null;
                }) : (base && Array.isArray(base.series?.[0]?.data) ? base.series[0].data.map(item => typeof item === 'object' ? Number(item.value ?? 0) : Number(item ?? 0)) : []);
                card.innerHTML = `
                    <div class="flex items-start justify-between gap-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                                <i class="fa-solid fa-chart-line text-emerald-500 mr-2"></i>${escapeHtmlDashboard(graph.title || 'Published Observatory Chart')}
                            </h3>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">PUBLISHED</span>
                            ${canManagePublishedGraphs ? `<button type="button" class="published-graph-delete inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 dark:text-amber-300 dark:bg-amber-900/30 dark:border-amber-800" data-graph-id="${escapeHtmlDashboard(graph.id)}" title="Hide this published chart from the Observatory"><i class="fa-solid fa-eye-slash mr-1" aria-hidden="true"></i> Unpublish</button>` : ''}
                        </div>
                    </div>
                    <div id="${chartId}" class="w-full h-72 pt-4"></div>`;
                grid.appendChild(card);

                card.querySelector('.published-graph-delete')?.addEventListener('click', async event => {
                    const button = event.currentTarget;
                    if (!confirm(`Remove "${graph.title || 'this published chart'}" from the Observatory? It will remain saved and can be published again later.`)) return;
                    button.disabled = true;
                    try {
                        const response = await fetch('<?= e(base_url('api/iris.php')) ?>?resource=graphs&id=' + encodeURIComponent(graph.id) + '&action=unpublish', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ published: false })
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(payload.error || 'Unable to unpublish chart.');
                        loadPublishedScannerGraphs();
                    } catch (error) {
                        button.disabled = false;
                        alert(error.message);
                    }
                });

                const elem = document.getElementById(chartId);
                if (!elem) return;
                const chart = echarts.init(elem);
                card._publishedChart = chart;
                if (typeof ResizeObserver !== 'undefined') {
                    card._chartResizeObserver = new ResizeObserver(() => chart.resize());
                    card._chartResizeObserver.observe(elem);
                }
                chartInstances.push(chart);

                const option = base || (type === 'rankedBar' ? {
                    tooltip: { trigger: 'axis', backgroundColor: tooltipBg, borderColor: tooltipBorder, textStyle: { color: tooltipText }, formatter: params => {
                        const point = Array.isArray(params) ? params[0] : params;
                        const label = labels[point.dataIndex] || point.name || 'Item';
                        const value = values[point.dataIndex] ?? 0;
                        return `${label}<br/>Rank: <b>${value}</b>`;
                    } },
                    grid: { left: '6%', right: '6%', bottom: '6%', top: '6%', containLabel: true },
                    xAxis: { type: 'value', min: 0, axisLabel: { color: textColor } },
                    yAxis: { type: 'category', data: labels.slice().reverse(), axisLabel: { color: textColor, fontSize: 10 } },
                    series: [{ type: 'bar', data: values.slice().reverse().map((value, index) => ({ value, name: labels.slice().reverse()[index] || `Item ${index + 1}` })), itemStyle: { color: '#10b981', borderRadius: [0, 4, 4, 0] } }]
                } : {
                    tooltip: { trigger: 'axis', backgroundColor: tooltipBg, borderColor: tooltipBorder, textStyle: { color: tooltipText } },
                    grid: { left: '4%', right: '4%', bottom: labels.length > 7 ? '15%' : '6%', top: '8%', containLabel: true },
                    xAxis: { type: 'category', data: labels, axisLabel: { color: textColor, rotate: labels.length > 6 ? 35 : 0 } },
                    yAxis: { type: 'value', axisLabel: { color: textColor } },
                    series: [{ type: type === 'line' ? 'line' : 'bar', data: values, itemStyle: { color: '#10b981' }, lineStyle: type === 'line' ? { width: 3, color: '#10b981' } : undefined }]
                });
                chart.setOption(option);
            });
        }

        function escapeHtmlDashboard(value) {
            return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
        }

        function loadPublishedScannerGraphs() {
            fetch('<?= e(base_url('api/dashboard_graphs.php')) ?>', { cache: 'no-store', headers: { 'Accept': 'application/json' } })
                .then(res => {
                    if (!res.ok) throw new Error('Published graph request failed');
                    return res.json();
                })
                .then(data => {
                    window.IRISFieldColors = data.field_colors || window.IRISFieldColors || {};
                    renderPublishedScannerGraphs(data.graphs || []);
                })
                .catch(() => {
                    const count = document.getElementById('publishedGraphCount');
                    const empty = document.getElementById('scannerPublishedGraphsEmpty');
                    if (count) count.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1"></i> Unable to load published graphs';
                    if (empty) empty.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg mr-1"></i> Published scanner graphs could not be loaded.';
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadSummaryCards();
            loadRankingHistory();
            loadPublishedScannerGraphs();
            window.addEventListener('resize', () => {
                chartInstances.forEach(chart => chart?.resize?.());
                rankingChartInstance?.resize?.();
            });
        });
    </script>
</body>
</html>

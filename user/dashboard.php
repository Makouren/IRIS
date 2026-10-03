<?php
require_once __DIR__.'/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user']);
$fieldColors = [];
$fieldColorUpdatedAt = [];
try {
    $storedFieldColors = db()->query('SELECT field_name AS field_key, color, updated_at FROM field_colors')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($storedFieldColors as $fieldColor) {
        $fieldKey = $fieldColor['field_key'];
        $color = $fieldColor['color'];
        $key = strtolower(preg_replace('/\s+/', ' ', trim((string)$fieldKey)) ?? '');
        if ($key !== '' && is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1) {
            $fieldColors[$key] = strtoupper($color);
            $fieldColorUpdatedAt[$key] = $fieldColor['updated_at'];
        }
    }
} catch (Throwable $e) {
    $fieldColors = [];
    $fieldColorUpdatedAt = [];
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
    <script>window.IRISFieldColors = <?= json_encode($fieldColors, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.IRISFieldColorUpdatedAt = <?= json_encode($fieldColorUpdatedAt, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= e(base_url('scanner/js/graphExport.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../scanner/js/graphExport.js') ?>"></script>
    <script type="module">
        import * as IRISChartBuilder from '../scanner/js/modules/chartEngine.js?v=echarts-six-chart-types-1';
        window.IRISChartBuilder = IRISChartBuilder;
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
    <script src="<?= e(base_url('scanner/js/dotBackground.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/js/dotBackground.js') ?>" defer></script>
    <style>
        .summary-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(270px, 100%), 1fr));
            gap: 1rem;
        }
        .iris-hover-card {
            position: relative;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .iris-hover-card::after {
            position: absolute;
            z-index: 2;
            inset: 0;
            padding: 1.5px;
            border-radius: inherit;
            background: linear-gradient(125deg, var(--iris-green), var(--iris-gold) 52%, var(--iris-green));
            content: "";
            opacity: .28;
            pointer-events: none;
            transition: opacity .2s ease;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }
        .scanner-published-scope {
            max-height: min(75vh, 620px);
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
        }
        .scanner-published-scope::-webkit-scrollbar { width: 8px; }
        .scanner-published-scope::-webkit-scrollbar-thumb {
            border: 2px solid transparent;
            border-radius: 999px;
            background: linear-gradient(var(--iris-green), var(--iris-gold)) padding-box;
            background-clip: padding-box;
        }
        .scanner-published-scope { scrollbar-color: var(--iris-green) transparent; scrollbar-width: thin; }
        .scanner-published-scope > header {
            position: sticky;
            top: -1rem;
            z-index: 4;
            background: var(--iris-surface);
        }
        @media (hover: hover) {
            .iris-hover-card:hover,
            .iris-hover-card:focus-within {
                z-index: 3;
                transform: translateY(-3px);
                box-shadow: 0 14px 30px rgba(30, 96, 49, .13), 0 3px 12px rgba(224, 167, 13, .12);
            }
            .iris-hover-card:hover::after,
            .iris-hover-card:focus-within::after { opacity: .95; }
        }
        @media (prefers-reduced-motion: reduce) {
            .iris-hover-card,
            .iris-hover-card::after { transition: none; }
        }
        @media (max-width: 767px) {
            .admin-nav-inner > .logo-refresh-trigger > div:last-child { display: none !important; }
            #user-menu-button > span { display: none !important; }
        }
        @media (max-width: 640px) {
            .admin-nav-inner { gap: .5rem; }
            .admin-nav-inner > .logo-refresh-trigger > div:first-child { width: clamp(8rem, 32vw, 13rem); }
            .admin-nav-actions { gap: .25rem; }
            .admin-nav-link { padding: .5rem; }
            .admin-nav-link span { display: none; }
            .admin-theme-btn { padding: .5rem !important; }
            .admin-profile-btn { gap: .25rem !important; padding: .25rem !important; }
        }
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
        .admin-dropdown a, .admin-dropdown li > button, .admin-dropdown .signout { display: flex !important; align-items: center !important; justify-content: flex-start !important; gap: .6rem !important; width: 100% !important; text-align: left !important; line-height: 1.2 !important; white-space: nowrap !important; }
        .admin-dropdown a, .admin-dropdown li > button { color: #dbeafe !important; padding: .7rem 1rem !important; transition: background .16s ease, color .16s ease, transform .16s ease; }
        .admin-dropdown a:hover, .admin-dropdown li > button:hover { background: rgba(16,185,129,.12) !important; color: #ecfdf5 !important; }
        @media (hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference) { .admin-dropdown a:hover, .admin-dropdown li > button:not(:disabled):hover { transform: translateY(-1px); } }
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
        html:not(.dark) .admin-dropdown .dropdown-name, html:not(.dark) .admin-dropdown a, html:not(.dark) .admin-dropdown li > button { color: #1F2A24 !important; }
        html:not(.dark) .admin-dropdown a:hover, html:not(.dark) .admin-dropdown li > button:hover { background: #EEF6F0 !important; color: #1E6031 !important; }
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
<body data-role="<?= e($_SESSION['role'] ?? 'user') ?>" class="dot-grid-dashboard text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <div id="dashboard-dot-background" aria-hidden="true"></div>
    <div id="page-loader" aria-live="polite" aria-label="Loading page">
        <div class="iris-loader" aria-hidden="true"></div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="admin-nav sticky top-0 z-50 backdrop-blur-md bg-opacity-95">
        <div class="dashboard-container">
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
                    <?php elseif (($_SESSION['role'] ?? null) === 'admin'): ?>
                        <a href="<?= e(base_url('admin/office_upload.php')) ?>" class="admin-nav-link public-link" aria-label="Back to IAO Uploads" title="Back to Uploads">
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i><span>Back to Uploads</span>
                        </a>
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
        .ranking-table th { color: var(--iris-text-faint); font-size: var(--iris-font-xs); letter-spacing: .08em; text-transform: uppercase; }
        .ranking-table td { color: var(--iris-text); font-size: var(--iris-font-sm); }

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

    <main class="dashboard-container py-8 flex-1 space-y-8" id="overview">
        <?php if (flash('error')): ?>
            <div class="flex items-center p-4 text-red-800 rounded-xl bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-800" role="alert"><i class="fa-solid fa-circle-exclamation text-lg mr-3"></i><div class="text-sm font-medium"><?= htmlspecialchars(flash('error')) ?></div></div>
        <?php endif; ?>

        <section id="summary-card-section" class="space-y-4 rounded-2xl border border-gray-200 bg-white/70 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-chart-simple text-amber-500 mr-2"></i> Latest Performance Snapshot
                    </h2>
                </div>
            </div>

            <div id="summaryCardCategoryFilter" class="hidden -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto" aria-label="Filter summary cards by category">
                <div id="summaryCardCategoryChips" class="flex w-max min-w-full items-center gap-2 pb-1" role="group" aria-label="Summary card categories"></div>
            </div>
            <div id="summaryCardsGrid" class="summary-card-grid"></div>
        </section>

        <section id="ranking-history" class="space-y-4 rounded-2xl border border-gray-200 bg-white/70 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 sm:p-5">
            <div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-ranking-star text-amber-500 mr-2"></i> Ranking History
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Compare institutional rankings across available years.</p>
                </div>
            </div>
            <div class="relative flex flex-wrap items-center justify-start gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200" aria-label="Ranking History controls">
                <label class="flex items-center gap-1.5">View
                    <select id="rankingDisplayMode" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="charts">Charts</option>
                        <option value="matrix">Ranking Trend Matrix</option>
                    </select>
                </label>
                <label id="rankingGraphLayoutControl" class="flex items-center gap-1.5">Graph layout
                    <select id="rankingGraphLayout" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="one-per-row">One per row</option>
                        <option value="side-by-side">Side by side</option>
                    </select>
                </label>
                <button id="rankingResetDefaults" type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-green-700 hover:bg-green-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Reset to default</button>
                <details id="rankingFiltersControl" class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                        Filters <i class="fa-solid fa-chevron-down text-xs transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <div id="rankingFiltersPopover" class="fixed z-[100] grid max-h-[min(75vh,560px)] w-[min(92vw,760px)] grid-cols-1 gap-3 overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-xl dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2 lg:grid-cols-3" aria-label="Filter Ranking History">
                    <label class="flex min-w-0 flex-col items-start gap-1">Organization
                        <select id="rankingOrganizationFilter" class="w-full min-w-0 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="all">All organizations</option></select>
                    </label>
                    <label id="rankingListControl" class="hidden min-w-0 flex-col items-start gap-1">List
                        <select id="rankingListFilter" class="w-full min-w-0 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"><option value="all">All lists</option></select>
                    </label>
                    <button id="rankingAllYears" type="button" class="w-auto justify-self-start self-center rounded-lg border border-green-800 bg-green-800 px-3 py-2 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-gray-950" aria-pressed="true">All years</button>
                    <details id="rankingYearRangeControl" class="group min-w-0 sm:col-span-2 lg:col-span-3">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                            Year range <i class="fa-solid fa-chevron-down text-xs transition-transform group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <div class="mt-2 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
                            <label class="flex min-w-0 flex-col items-start gap-1">From
                                <select id="rankingYearFrom" class="w-full min-w-0 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900" disabled><option>Loading...</option></select>
                            </label>
                            <label class="flex min-w-0 flex-col items-start gap-1">To
                                <select id="rankingYearTo" class="w-full min-w-0 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900" disabled><option>Loading...</option></select>
                            </label>
                        </div>
                    </details>
                    </div>
                </details>
            </div>
            <div id="rankingChart" class="grid min-h-64 w-full grid-cols-1 gap-4 xl:grid-cols-2"></div>
        </section>

        <section id="star-rating-cards-section" class="hidden space-y-4 rounded-2xl border border-gray-200 bg-white/70 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-star text-amber-500 mr-2" aria-hidden="true"></i> University Star Ratings
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Published institutional star ratings</p>
                </div>
            </div>
            <div id="star-rating-category-filter" class="hidden -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Filter star rating cards by category">
                <div id="star-rating-category-chips" class="flex w-max min-w-full items-center gap-2 pb-1" role="group" aria-label="Star rating categories"></div>
            </div>
            <div id="star-rating-cards-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
        </section>

        <section id="scanner-published-graphs" class="space-y-4 rounded-2xl border border-gray-200 bg-white/70 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-chart-column text-emerald-500 mr-2"></i> Data &amp; Report Visualization
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Published institutional data and reports</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label for="publishedGraphLayout" class="text-xs font-semibold text-gray-600 dark:text-gray-300">Graph layout</label>
                    <select id="publishedGraphLayout" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="side-by-side">Side by side</option>
                        <option value="one-per-row">One per row</option>
                    </select>
                </div>
            </div>
            <div id="scannerPublishedGraphsGrid" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div id="scannerPublishedGraphsEmpty" class="lg:col-span-2 p-6 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 text-center text-sm text-gray-500 dark:text-gray-400">
                    <i class="fa-solid fa-chart-simple text-lg mr-1"></i> No published data and report visualizations are available yet.
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="admin-footer bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 mt-12">
        <div class="dashboard-container flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 dark:text-gray-400 gap-4">
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
            window.IRISRankingHistory?.themeChanged?.();
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
                return `<article class="iris-hover-card rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"><header class="flex min-h-20 flex-col items-center justify-center text-center">${logo}<h3 class="text-base font-bold text-gray-900 dark:text-white">${title}</h3></header>${year}<div class="mt-2">${rows}</div></article>`;
            }).join('');
        }

        let summaryCardsData = [];
        let summaryCardCategories = [];
        let activeSummaryCategorySlug = '';
        let summaryCardDefaultCategorySlug = '';

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
            const initialSlug = requested || summaryCardDefaultCategorySlug;
            const category = summaryCardCategories.find(item => item.slug === initialSlug);
            activeSummaryCategorySlug = category ? category.slug : '';
            if (requested && !category && replaceUnknown) updateSummaryCategoryUrl('', true);
            else if (!requested && activeSummaryCategorySlug && replaceUnknown) updateSummaryCategoryUrl(activeSummaryCategorySlug, true);
            renderSummaryCategoryChips();
            renderSummaryCards(summaryCardsData);
        }

        window.addEventListener('popstate', () => restoreSummaryCategoryFromUrl());

        let pinnedSummaryInfoControl = null;
        document.addEventListener('click', event => {
            if (!pinnedSummaryInfoControl || pinnedSummaryInfoControl.contains(event.target)) return;
            const trigger = pinnedSummaryInfoControl.querySelector('[data-info-trigger]');
            const panel = pinnedSummaryInfoControl.querySelector('[data-info-panel]');
            if (panel) panel.hidden = true;
            trigger?.setAttribute('aria-expanded', 'false');
            pinnedSummaryInfoControl = null;
        });

        function renderSummaryCards(cards) {
            const grid = document.getElementById('summaryCardsGrid');
            if (!grid) return;
            if (pinnedSummaryInfoControl) {
                const trigger = pinnedSummaryInfoControl.querySelector('[data-info-trigger]');
                const panel = pinnedSummaryInfoControl.querySelector('[data-info-panel]');
                if (panel) panel.hidden = true;
                trigger?.setAttribute('aria-expanded', 'false');
                pinnedSummaryInfoControl = null;
            }
            const publishedCards = Array.isArray(cards) ? cards.filter(card => card && (card.is_published === true || card.is_published === 1 || card.is_published === '1')) : [];
            const visibleCards = publishedCards.filter(card => !activeSummaryCategorySlug || (Array.isArray(card.category_slugs) && card.category_slugs.includes(activeSummaryCategorySlug)));
            if (!visibleCards.length) {
                const message = activeSummaryCategorySlug
                    ? 'There are no published summary cards in this category yet.'
                    : 'No performance snapshot cards are currently published.';
                grid.innerHTML = `<div class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 text-center text-sm text-gray-500 dark:text-gray-400 py-10">${message}</div>`;
                return;
            }

            grid.innerHTML = visibleCards
                .slice()
                .sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0))
                .map(card => {
                    const mainValue = formatSummaryCardValue(card.main_value, card.display_precision ?? 2);
                    const secondaryValue = card.secondary_value ? formatSummaryCardValue(card.secondary_value, card.display_precision ?? 2) : '';
                    const secondaryLabel = card.secondary_label ? escapeHtmlDashboard(card.secondary_label) : '';
                    const description = card.description ? escapeHtmlDashboard(card.description) : '';
                    const secondaryDescription = card.secondary_description ? escapeHtmlDashboard(card.secondary_description) : '';
                    const infoText = card.info_text ? escapeHtmlDashboard(card.info_text) : '';
                    const customFields = Object.values(card.custom_fields || {}).filter(field => field && field.value).map(field =>
                        `<p class="mt-2 text-sm text-gray-700 dark:text-gray-200"><span class="font-semibold">${escapeHtmlDashboard(field.label)}:</span> ${escapeHtmlDashboard(field.value)}</p>`
                    ).join('');
                    const mainLabel = escapeHtmlDashboard(card.main_label || 'Current snapshot');
                    const yearDate = escapeHtmlDashboard(card.year_date || '');
                    const infoLabel = escapeHtmlDashboard(`Information about ${card.title || 'this card'}`);
                    const historyControl = Number(card.history_count || 0) > 1 ? `
                        <div class="mt-3">
                            <button type="button" class="summary-card-history-toggle text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300" data-id="${escapeHtmlDashboard(card.id)}" aria-expanded="false">View Historical Data</button>
                            <div class="summary-card-history-panel mt-2 hidden rounded-lg border border-gray-200 p-3 dark:border-gray-700" data-id="${escapeHtmlDashboard(card.id)}" role="status" aria-live="polite"></div>
                        </div>` : '';
                    return `
                        <article class="iris-hover-card summary-card-shell relative overflow-visible rounded-2xl p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="summary-card-title text-[10px] font-bold uppercase tracking-[0.14em]">${escapeHtmlDashboard(card.title || 'Performance Snapshot')}</div>
                                    <div class="mt-2 whitespace-nowrap text-3xl font-extrabold text-gray-900 dark:text-white">${escapeHtmlDashboard(mainValue)}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    ${yearDate ? `<span class="summary-card-year-badge rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide">${yearDate}</span>` : ''}
                                    ${infoText ? `<div class="summary-card-info-control relative z-40"><button type="button" class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-gray-500/40 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" data-info-trigger aria-label="${infoLabel}" aria-controls="summary-card-info-${escapeHtmlDashboard(card.id)}" aria-expanded="false" title="More information"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></button><div id="summary-card-info-${escapeHtmlDashboard(card.id)}" data-info-panel role="tooltip" hidden class="absolute right-0 top-full z-40 mt-2 w-64 max-w-[75vw] rounded-lg border border-gray-200 bg-white p-3 text-left text-xs font-normal normal-case text-gray-700 shadow-xl dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">${infoText}</div></div>` : ''}
                                </div>
                            </div>
                            <div class="summary-card-label mt-2 text-xs font-semibold uppercase tracking-wide">${mainLabel}</div>
                            ${secondaryLabel || secondaryValue ? `<div class="summary-card-second-row mt-4 flex items-baseline justify-between gap-3 border-t pt-3 text-xs">
                                <span class="summary-card-label">${secondaryLabel}</span>
                                <span class="font-bold text-gray-900 dark:text-white">${escapeHtmlDashboard(secondaryValue)}</span>
                            </div>` : ''}
                            ${secondaryDescription ? `<p class="mt-2 text-sm italic text-gray-500 dark:text-gray-400">${secondaryDescription}</p>` : ''}
                            ${description ? `<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">${description}</p>` : ''}
                            ${customFields}
                            ${historyControl}
                        </article>`;
                }).join('');

            grid.querySelectorAll('.summary-card-info-control').forEach(wrapper => {
                const trigger = wrapper.querySelector('[data-info-trigger]');
                const panel = wrapper.querySelector('[data-info-panel]');
                if (!trigger || !panel) return;
                const open = () => { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); };
                const close = () => { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
                trigger.addEventListener('mouseenter', () => { if (!pinnedSummaryInfoControl) open(); });
                trigger.addEventListener('focus', () => { if (!pinnedSummaryInfoControl) open(); });
                wrapper.addEventListener('mouseleave', event => { if (pinnedSummaryInfoControl !== wrapper && !panel.contains(event.relatedTarget)) close(); });
                trigger.addEventListener('blur', () => { if (pinnedSummaryInfoControl !== wrapper) close(); });
                trigger.addEventListener('click', () => {
                    if (pinnedSummaryInfoControl === wrapper) {
                        pinnedSummaryInfoControl = null;
                        close();
                        return;
                    }
                    if (pinnedSummaryInfoControl) {
                        const previousTrigger = pinnedSummaryInfoControl.querySelector('[data-info-trigger]');
                        const previousPanel = pinnedSummaryInfoControl.querySelector('[data-info-panel]');
                        if (previousPanel) previousPanel.hidden = true;
                        previousTrigger?.setAttribute('aria-expanded', 'false');
                    }
                    pinnedSummaryInfoControl = wrapper;
                    open();
                });
                trigger.addEventListener('keydown', event => {
                    if (event.key !== 'Escape') return;
                    if (pinnedSummaryInfoControl === wrapper) pinnedSummaryInfoControl = null;
                    close();
                    trigger.blur();
                });
            });

            document.querySelectorAll('.summary-card-history-toggle').forEach(button => {
                button.addEventListener('click', async () => {
                    if (button.disabled) return;
                    const panel = [...document.querySelectorAll('.summary-card-history-panel')].find(item => item.dataset.id === button.dataset.id);
                    if (!panel) return;
                    if (!panel.classList.contains('hidden')) {
                        panel.classList.add('hidden');
                        button.setAttribute('aria-expanded', 'false');
                        return;
                    }
                    panel.classList.remove('hidden');
                    button.setAttribute('aria-expanded', 'true');
                    if (panel.dataset.loaded === 'true') return;
                    panel.textContent = 'Loading published history...';
                    button.disabled = true;
                    try {
                        const response = await fetch('<?= e(base_url('api/iris.php')) ?>?resource=summary_card_history&id=' + encodeURIComponent(button.dataset.id), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.error || 'Unable to load published history.');
                        const history = (result.periods || []).filter(period => !period.is_current_public);
                        panel.replaceChildren();
                        if (!history.length) {
                            panel.textContent = 'No published historical periods.';
                            panel.dataset.loaded = 'true';
                            return;
                        }
                        const label = document.createElement('label');
                        label.className = 'block text-xs font-semibold';
                        label.textContent = 'Period';
                        const select = document.createElement('select');
                        select.className = 'mt-1 block w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800';
                        history.forEach(period => select.add(new Option(period.period_label || period.period_key, period.period_key)));
                        const detail = document.createElement('div');
                        detail.className = 'mt-3 space-y-1 text-xs';
                        const renderPeriod = () => {
                            const period = history.find(item => item.period_key === select.value);
                            detail.replaceChildren();
                            for (const [name, value] of Object.entries({
                                Value: period?.main_value, Label: period?.main_label, 'Secondary label': period?.secondary_label,
                                'Secondary value': period?.secondary_value, 'Secondary description': period?.secondary_description,
                                Description: period?.description, Information: period?.info_text
                            })) {
                                if (value == null || value === '') continue;
                                const line = document.createElement('p');
                                line.textContent = `${name}: ${value}`;
                                detail.append(line);
                            }
                            for (const field of Object.values(period?.custom_fields || {})) {
                                if (!field?.value) continue;
                                const line = document.createElement('p');
                                line.textContent = `${field.label}: ${field.value}`;
                                detail.append(line);
                            }
                        };
                        select.addEventListener('change', renderPeriod);
                        label.append(select);
                        panel.append(label, detail);
                        renderPeriod();
                        panel.dataset.loaded = 'true';
                    } catch (error) {
                        panel.textContent = error.message;
                    } finally {
                        button.disabled = false;
                    }
                });
            });

        }

        async function loadSummaryCards() {
            try {
                const response = await fetch('<?= e(base_url('api/dashboard_graphs.php')) ?>', { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Unable to load summary cards');
                const payload = await response.json();
                summaryCardsData = payload.cards || [];
                summaryCardCategories = payload.categories || [];
                summaryCardDefaultCategorySlug = payload.summary_cards_default_category || '';
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

        // --- Published Observatory Graphs ---
        let chartInstances = [];
        let publishedGraphLayout = 'side-by-side';
        const publishedGraphLayoutSelect = document.getElementById('publishedGraphLayout');

        publishedGraphLayoutSelect?.addEventListener('change', () => {
            publishedGraphLayout = publishedGraphLayoutSelect.value === 'one-per-row' ? 'one-per-row' : 'side-by-side';
            document.querySelectorAll('[data-scope-graphs]').forEach(scopeGrid => {
                scopeGrid.classList.toggle('lg:grid-cols-2', publishedGraphLayout === 'side-by-side');
                scopeGrid.classList.toggle('grid-cols-1', true);
            });
            requestAnimationFrame(() => chartInstances.forEach(chart => chart?.resize?.()));
        });

        // Live Filters
        const canManagePublishedGraphs = <?= json_encode(($_SESSION['role'] ?? null) === 'super_admin') ?>;

        function renderPublishedScannerGraphs(graphs) {
            const grid = document.getElementById('scannerPublishedGraphsGrid');
            const empty = document.getElementById('scannerPublishedGraphsEmpty');
            if (!grid) return;

            const replacedCharts = new Set();
            grid.querySelectorAll('.scanner-published-card').forEach(el => {
                el._chartResizeObserver?.disconnect?.();
                el._publishedChart?.dispose?.();
                if (el._publishedChart) replacedCharts.add(el._publishedChart);
                el.remove();
            });
            grid.querySelectorAll('.scanner-published-scope').forEach(el => el.remove());
            chartInstances = chartInstances.filter(chart => !replacedCharts.has(chart));
            if (!Array.isArray(graphs) || graphs.length === 0) {
                if (empty) empty.style.display = '';
                return;
            }
            if (empty) empty.style.display = 'none';

            const scopes = new Map();
            graphs.forEach(graph => {
                const scope = String(graph.scope || '').trim() || 'General';
                if (!scopes.has(scope)) scopes.set(scope, []);
                scopes.get(scope).push(graph);
            });
            const orderedScopes = [...scopes.entries()].sort(([left], [right]) => {
                if (left === 'General') return 1;
                if (right === 'General') return -1;
                return left.localeCompare(right);
            });
            let chartIndex = 0;
            orderedScopes.forEach(([scope, scopedGraphs]) => {
                const scopeCard = document.createElement('section');
                scopeCard.className = 'scanner-published-scope col-span-full rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800';
                scopeCard.innerHTML = `<header class="mb-4 flex items-center justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-700"><h3 class="text-base font-bold text-gray-900 dark:text-white">${escapeHtmlDashboard(scope)}</h3><span class="text-xs font-semibold text-gray-500 dark:text-gray-300">${scopedGraphs.length} graph${scopedGraphs.length === 1 ? '' : 's'}</span></header><div class="grid grid-cols-1 gap-6 ${publishedGraphLayout === 'side-by-side' ? 'lg:grid-cols-2' : ''}" data-scope-graphs></div>`;
                grid.appendChild(scopeCard);
                const scopeGrid = scopeCard.querySelector('[data-scope-graphs]');
                scopedGraphs.forEach(graph => {
                const card = document.createElement('div');
                card.className = 'iris-hover-card scanner-published-card bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm';
                const chartId = `scannerPublishedChart_${chartIndex++}`;
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
                scopeGrid.appendChild(card);

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
                card._publishedGraph = graph;
                if (typeof ResizeObserver !== 'undefined') {
                    card._chartResizeObserver = new ResizeObserver(() => chart.resize());
                    card._chartResizeObserver.observe(elem);
                }
                chartInstances.push(chart);
                chart.setOption(window.IRISChartBuilder.buildSavedGraphOption(graph, {
                    width: elem.clientWidth,
                    theme: { dark: document.documentElement.classList.contains('dark') }
                }));
                });
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
                    window.IRISFieldColorUpdatedAt = data.field_color_updated_at || window.IRISFieldColorUpdatedAt || {};
                    renderPublishedScannerGraphs(data.graphs || []);
                })
                .catch(() => {
                    const empty = document.getElementById('scannerPublishedGraphsEmpty');
                    if (empty) empty.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg mr-1"></i> Published scanner graphs could not be loaded.';
                });
        }

        new MutationObserver(() => {
            const theme = window.IRISChartBuilder.getChartTheme({ dark: document.documentElement.classList.contains('dark') });
            window.IRISRankingHistory?.themeChanged();
            document.querySelectorAll('.scanner-published-card').forEach(card => {
                const element = card.querySelector('[id^="scannerPublishedChart_"]');
                if (element && card._publishedGraph && card._publishedChart) {
                    card._publishedChart.setOption(window.IRISChartBuilder.buildSavedGraphOption(card._publishedGraph, {
                        width: element.clientWidth,
                        theme: { dark: document.documentElement.classList.contains('dark') }
                    }), true);
                }
            });
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        document.addEventListener('DOMContentLoaded', () => {
            loadSummaryCards();
            loadPublishedScannerGraphs();
            window.addEventListener('resize', () => {
                chartInstances.forEach(chart => chart?.resize?.());
                window.IRISRankingHistory?.resize();
            });
        });
    </script>
<script src="<?= e(base_url('user/js/rankingHistory.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/rankingHistory.js') ?>" data-api="<?= e(base_url('api/rankings.php')) ?>" defer></script>
<?php $irisChangeRefreshView = 'public'; require __DIR__ . '/../includes/change_refresh_script.php'; ?>
</body>
</html>

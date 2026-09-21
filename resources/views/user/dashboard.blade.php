
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CLSU Performance Observatory - IRIS</title>
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 backdrop-blur-md bg-opacity-90 dark:bg-opacity-90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-emerald-500/20 ring-2 ring-emerald-400/30">
                        <img src="{{ asset('images/iris-logo.png') }}" alt="IRIS Logo" class="w-10 h-10 object-contain">
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-emerald-600 to-teal-500 dark:from-emerald-400 dark:to-teal-300 bg-clip-text text-transparent">IRIS</span>
                            <span class="text-xs px-2 py-0.5 font-medium rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700/50">CLSU</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Performance Observatory</p>
                    </div>
                </div>

                <!-- Nav Center Links -->
                <div class="hidden md:flex items-center space-x-1">
                    <a href="#overview" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-gray-700/60 rounded-lg">
                        <i class="fa-solid fa-chart-pie mr-2"></i> Observatory
                    </a>
                    <a href="#headline-rankings" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <i class="fa-solid fa-trophy mr-2"></i> Headline Rankings
                    </a>
                    <a href="#program-rankings" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <i class="fa-solid fa-graduation-cap mr-2"></i> Programs
                    </a>
                </div>

                <!-- Right Actions: Dark Mode & User Profile -->
                <div class="flex items-center space-x-3">
                    <!-- Theme Toggle -->
                    <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-2.5">
                        <i id="theme-toggle-dark-icon" class="hidden fa-solid fa-moon text-base"></i>
                        <i id="theme-toggle-light-icon" class="hidden fa-solid fa-sun text-base text-amber-400"></i>
                    </button>

                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button type="button" class="flex items-center space-x-2 text-sm bg-gray-100 dark:bg-gray-700 p-1.5 rounded-full focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-emerald-500 to-amber-400 flex items-center justify-center text-white font-bold text-xs shadow">
                                <?= strtoupper(substr(session('username', 'U'), 0, 2)) ?>
                            </div>
                            <span class="hidden sm:inline-block font-medium text-xs px-1 text-gray-700 dark:text-gray-200"><?= htmlspecialchars(session('username', 'U')) ?></span>
                        </button>
                        <!-- Dropdown Menu -->
                        <div class="z-50 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-xl shadow-lg dark:bg-gray-700 dark:divide-gray-600" id="user-dropdown">
                            <div class="px-4 py-3">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars(session('username', 'U')) ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 mt-1">
                                    <?= session('role') === 'admin' ? 'ADMINISTRATOR' : 'VIEWER' ?>
                                </span>
                            </div>
                            <ul class="py-2" aria-labelledby="user-menu-button">
                                <?php if (session('role') === 'admin'): ?>
                                    <li>
                                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-emerald-600 hover:bg-emerald-50 dark:hover:bg-gray-600 dark:text-emerald-400 font-medium">
                                            <i class="fa-solid fa-shield-halved mr-2"></i> Admin Portal
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="py-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-red-400">
                                        <i class="fa-solid fa-right-from-bracket mr-2"></i> Sign Out
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
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8" id="overview">
        <?php if (session('success')): ?>
            <div class="flex items-center p-4 text-emerald-800 rounded-xl bg-emerald-50 dark:bg-gray-800 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800" role="alert"><i class="fa-solid fa-circle-check text-lg mr-3"></i><div class="text-sm font-medium"><?= htmlspecialchars(session('success')) ?></div></div>
        <?php endif; ?>
        <?php if (session('error')): ?>
            <div class="flex items-center p-4 text-red-800 rounded-xl bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-800" role="alert"><i class="fa-solid fa-circle-exclamation text-lg mr-3"></i><div class="text-sm font-medium"><?= htmlspecialchars(session('error')) ?></div></div>
        <?php endif; ?>
        
        <!-- Welcome Hero Banner -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-emerald-800 to-teal-900 dark:from-emerald-950 dark:to-gray-800 text-white rounded-2xl p-6 shadow-xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
            <div class="relative z-10">
                <div class="flex items-center space-x-2 text-emerald-300 text-xs font-semibold uppercase tracking-wider mb-1">
                    <i class="fa-solid fa-building-columns"></i>
                    <span>Central Luzon State University &bull; Institutional Performance</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Observatory & Analytics Engine</h1>
                <p class="text-sm text-emerald-100/80 mt-1 max-w-xl">
                    Live telemetry across global registries, research citations, and AACCUP / AUN-QA quality frameworks.
                </p>
            </div>
            <div class="relative z-10 flex flex-wrap items-center gap-3">
                <?php if (session('role') === 'admin'): ?>
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-md transition-all">
                        <i class="fa-solid fa-sliders mr-2"></i> Manage Datasets
                    </a>
                <?php endif; ?>
                <div class="flex items-center bg-black/30 backdrop-blur px-3 py-2 rounded-xl text-xs border border-white/10 text-emerald-200">
                    <i class="fa-solid fa-circle text-emerald-400 text-[10px] mr-2 animate-pulse"></i> Telemetry Active
                </div>
            </div>
        </div>

        <!-- 4-Card Executive KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Best Global Rank -->
            <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-500 to-teal-400"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Best Global Rank</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white"><?= htmlspecialchars($best['global_rank'] ?? '—') ?></div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-medium">
                    <?= htmlspecialchars($best['body_name'] ?? 'Registries') ?> &bull; <?= htmlspecialchars($best['year'] ?? date('Y')) ?>
                </p>
            </div>

            <!-- National Rank -->
            <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-amber-500 to-yellow-400"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">National Rank (PH)</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white"><?= htmlspecialchars($phRank['ph_rank'] ?? '—') ?></div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-medium">
                    Philippines Ranking Benchmark <?= !empty($phRank['year']) ? '&bull; ' . htmlspecialchars($phRank['year']) : '' ?>
                </p>
            </div>

            <!-- Bodies Monitored -->
            <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-cyan-500 to-blue-400"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bodies Monitored</span>
                    <div class="w-8 h-8 rounded-lg bg-cyan-50 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white"><?= (int)$bodyCount ?></div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-medium">QS, THE, WURI, UI GreenMetric &amp; Bodies</p>
            </div>

            <!-- Accredited Programs -->
            <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-600 to-green-500"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Accredited Programs</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-certificate"></i>
                    </div>
                </div>
                <div class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white"><?= $accreditedCount ?></div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-medium">AUN-QA &amp; International Standards</p>
            </div>
        </div>

        <!-- Headline Rankings Cards -->
        <div id="headline-rankings" class="space-y-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fa-solid fa-ranking-star text-amber-500 mr-2"></i> Published Headline Rankings
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Latest standings published by major evaluation boards</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <?php foreach ($bodyCards as $row): ?>
                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-emerald-500/50 transition-colors">
                        <div class="flex justify-between items-start mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                <?= htmlspecialchars($row['short_name']) ?><?= $row['category'] ? ' &bull; ' . htmlspecialchars($row['category']) : '' ?>
                            </span>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['year']) ?></span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mt-2"><?= htmlspecialchars($row['name']) ?></h3>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 my-2">
                            <?= htmlspecialchars($row['global_rank'] ?? '—') ?>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Official Global Standing</p>
                        <?php if ($row['note']): ?>
                            <div class="mt-3 p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-600 dark:text-gray-300 border border-gray-100 dark:border-gray-700">
                                <i class="fa-solid fa-circle-info text-cyan-500 mr-1"></i> <?= htmlspecialchars($row['note']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Apache ECharts Visual Analytics Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- QS Global Rank Trajectory (ECharts) -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                            <i class="fa-solid fa-chart-line text-emerald-500 mr-2"></i> QS Global Rank Trajectory
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Multi-year progression (Lower number = Higher Rank)</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                        QS WORLD
                    </span>
                </div>
                <div id="trendChart" class="w-full h-72 pt-4"></div>
            </div>

            <!-- College Contribution Doughnut (ECharts) -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                            <i class="fa-solid fa-chart-pie text-amber-500 mr-2"></i> College Contribution to Score
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Proportionate academic &amp; research weight (<?= (int)$latestCollegeYear ?>)</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                        COLLEGES
                    </span>
                </div>
                <div id="collegeChart" class="w-full h-72 pt-4"></div>
            </div>
        </div>

        <!-- AI Observatory Executive Summary -->
        <div class="p-6 bg-gradient-to-r from-emerald-900/30 to-teal-900/30 border border-emerald-500/30 rounded-2xl shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-emerald-500/20 gap-3">
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500 text-white shadow-sm">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i> IRIS Intelligence Summary
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Automated synthesis of live performance data</span>
                </div>
                <button onclick="loadSummary()" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-300 bg-white dark:bg-gray-800 border border-emerald-300 dark:border-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors" id="regenBtn">
                    <i class="fa-solid fa-arrows-rotate mr-1.5"></i> Regenerate Brief
                </button>
            </div>
            <div id="ai-summary" class="mt-4 text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-emerald-200/50 dark:bg-gray-700 rounded w-3/4"></div>
                    <div class="h-4 bg-emerald-200/50 dark:bg-gray-700 rounded w-5/6"></div>
                    <div class="h-4 bg-emerald-200/50 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <!-- Program Rankings Table with Live Search -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4" id="program-rankings">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700 gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-graduation-cap text-emerald-500 mr-2"></i> National Program Rankings
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Academic degree rankings &amp; year-over-year movement</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fa-solid fa-magnifying-glass text-gray-400 text-xs"></i>
                        </div>
                        <input type="text" id="programSearch" class="block p-2 pl-9 text-xs text-gray-900 border border-gray-300 rounded-lg w-52 bg-gray-50 focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Search program or college..." onkeyup="filterPrograms()">
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap" id="programCountLabel">Showing <?= count($programRows) ?> programs</span>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" id="programTable">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nat. Rank</th>
                            <th scope="col" class="px-4 py-3">Program Name</th>
                            <th scope="col" class="px-4 py-3">College</th>
                            <th scope="col" class="px-4 py-3">Score</th>
                            <th scope="col" class="px-4 py-3">Movement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($programRows)): ?>
                            <tr><td colspan="5" class="text-center py-6 text-gray-500 dark:text-gray-400">No program ranking data recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($programRows as $row):
                                $m = (int)($row['movement'] ?? 0);
                                $moveBadge = $m > 0 
                                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300"><i class="fa-solid fa-arrow-up mr-1"></i> +' . $m . '</span>'
                                    : ($m < 0 
                                        ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300"><i class="fa-solid fa-arrow-down mr-1"></i> ' . $m . '</span>'
                                        : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">&bull; 0</span>');
                            ?>
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                    <td class="px-4 py-3 font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">
                                        #<?= (int)$row['national_rank'] ?>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                            <?= htmlspecialchars($row['short_code']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white"><?= number_format((float)$row['score'], 1) ?></td>
                                    <td class="px-4 py-3"><?= $moveBadge ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ranking Breakdown Sections (ECharts Horizontal Bars) -->
        <?php if (count($breakdownSections)): ?>
            <div class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fa-solid fa-bullseye text-cyan-500 mr-2"></i> Category Breakdowns &amp; SDG Performance
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">In-depth sub-category evaluations &amp; indicators</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($breakdownSections as $idx => $section): ?>
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col">
                            <div class="flex justify-between items-start mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                    <?= htmlspecialchars($section['body']['short_name']) ?> &bull; <?= htmlspecialchars($section['body']['year']) ?>
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2"><?= htmlspecialchars($section['items'][0]['group_label'] ?? 'Breakdown') ?></h3>
                            
                            <!-- ECharts mini bar chart container -->
                            <div id="breakdownChart<?= $idx ?>" class="w-full h-48"></div>

                            <!-- List breakdown table -->
                            <div class="overflow-y-auto max-h-48 mt-3 rounded-lg border border-gray-100 dark:border-gray-700">
                                <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-[10px] uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2">Indicator</th>
                                            <th class="px-3 py-2 text-right">Score/Rank</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <?php foreach ($section['items'] as $item): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200"><?= htmlspecialchars($item['item_label']) ?></td>
                                                <td class="px-3 py-2 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                                    <?= htmlspecialchars($item['rank_display'] ?? '—') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Program Accreditation (AUN-QA) -->
        <?php if ($accreditedCount > 0): ?>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700 gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                            <i class="fa-solid fa-stamp text-emerald-500 mr-2"></i> Program Quality Accreditations (AUN-QA &amp; International)
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Independent external peer reviews</p>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fa-solid fa-magnifying-glass text-gray-400 text-xs"></i>
                        </div>
                        <input type="text" id="accredSearch" class="block p-2 pl-9 text-xs text-gray-900 border border-gray-300 rounded-lg w-52 bg-gray-50 focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Filter accreditations..." onkeyup="filterAccred()">
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" id="accredTable">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                            <tr>
                                <th scope="col" class="px-4 py-3">Program</th>
                                <th scope="col" class="px-4 py-3">Accrediting Body</th>
                                <th scope="col" class="px-4 py-3">Cycle Year</th>
                                <th scope="col" class="px-4 py-3">Assessment Date</th>
                                <th scope="col" class="px-4 py-3">Avg. Score</th>
                                <th scope="col" class="px-4 py-3">Overall Verdict</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($accreditationRows as $row):
                                $v = $row['verdict'] ?? '—';
                                $isApproved = stripos($v, 'adequate') !== false || stripos($v, 'certified') !== false || stripos($v, 'pass') !== false;
                            ?>
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($row['program_name']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-cyan-100 text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-300">
                                            <?= htmlspecialchars($row['accrediting_body']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono"><?= htmlspecialchars($row['year']) ?></td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['assessment_date'] ?? '—') ?></td>
                                    <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white">
                                        <?= $row['avg_score'] !== null ? number_format((float)$row['avg_score'], 2) : '—' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $isApproved ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' ?>">
                                            <i class="fa-solid <?= $isApproved ? 'fa-circle-check' : 'fa-clock' ?> mr-1"></i>
                                            <?= htmlspecialchars($v) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 dark:text-gray-400 gap-4">
            <div class="flex items-center space-x-2">
                <span class="font-bold text-gray-800 dark:text-gray-200">IRIS</span>
                <span>&bull; CLSU Institutional Research and Information System</span>
            </div>
            <div>
                Crafted for Central Luzon State University &bull; Powered by Flowbite &amp; Apache ECharts
            </div>
        </div>
    </footer>

    <!-- Theme Toggle & ECharts Script Initialization -->
    <script>
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

            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
            renderAllCharts();
        });

        // --- Apache ECharts Data & Initialization ---
        const trendYears = <?= json_encode($trendYears) ?>;
        const trendRanks = <?= json_encode($trendRanks) ?>;
        const trendDisplay = <?= json_encode($trendDisplay) ?>;
        const collegePieData = <?= json_encode($collegePieData) ?>;
        const breakdownSections = <?= json_encode(array_map(function ($s) {
            return [
                'labels' => array_map(fn($i) => $i['item_label'], $s['items']),
                'values' => array_map(fn($i) => $i['rank_value'] !== null ? (int)$i['rank_value'] : 0, $s['items']),
                'displays' => array_map(fn($i) => $i['rank_display'] ?? '', $s['items']),
            ];
        }, $breakdownSections)) ?>;

        let chartInstances = [];

        function renderAllCharts() {
            chartInstances.forEach(c => c && c.dispose());
            chartInstances = [];

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#9ca3af' : '#4b5563';
            const splitLineColor = isDark ? '#374151' : '#f3f4f6';
            const tooltipBg = isDark ? '#1f2937' : '#ffffff';
            const tooltipBorder = isDark ? '#374151' : '#e5e7eb';
            const tooltipText = isDark ? '#f9fafb' : '#111827';

            // 1. QS Rank Line Chart
            const trendElem = document.getElementById('trendChart');
            if (trendElem) {
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
                        lineStyle: { width: 3, color: '#10b981' },
                        itemStyle: { color: '#10b981' },
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
                const collegeChart = echarts.init(collegeElem);
                chartInstances.push(collegeChart);
                collegeChart.setOption({
                    backgroundColor: 'transparent',
                    tooltip: {
                        trigger: 'item',
                        backgroundColor: tooltipBg,
                        borderColor: tooltipBorder,
                        textStyle: { color: tooltipText },
                        formatter: '{b}: {c}%'
                    },
                    legend: {
                        orient: 'vertical',
                        right: '0',
                        top: 'middle',
                        textStyle: { color: textColor, fontSize: 11 }
                    },
                    series: [{
                        type: 'pie',
                        radius: ['45%', '70%'],
                        center: ['40%', '50%'],
                        itemStyle: {
                            borderRadius: 6,
                            borderColor: isDark ? '#1f2937' : '#ffffff',
                            borderWidth: 2
                        },
                        data: collegePieData
                    }]
                });
            }

            // 3. Category Breakdown Mini Bar Charts
            breakdownSections.forEach((section, idx) => {
                const elem = document.getElementById('breakdownChart' + idx);
                if (!elem) return;
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
                    series: [{
                        type: 'bar',
                        data: section.values,
                        itemStyle: {
                            color: '#10b981',
                            borderRadius: [4, 0, 0, 4]
                        }
                    }]
                });
            });
        }

        // Live Filters
        function filterPrograms() {
            const q = document.getElementById('programSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#programTable tbody tr');
            let visibleCount = 0;
            rows.forEach(r => {
                const text = r.textContent.toLowerCase();
                const match = text.includes(q);
                r.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });
            document.getElementById('programCountLabel').textContent = `Showing ${visibleCount} programs`;
        }

        function filterAccred() {
            const q = document.getElementById('accredSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#accredTable tbody tr');
            rows.forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        // AI Summary Fetch
        function loadSummary() {
            const summaryEl = document.getElementById('ai-summary');
            const regenBtn = document.getElementById('regenBtn');
            
            summaryEl.innerHTML = `
                <div class="animate-pulse space-y-2">
                    <div class="h-4 bg-emerald-200/50 dark:bg-gray-700 rounded w-3/4"></div>
                    <div class="h-4 bg-emerald-200/50 dark:bg-gray-700 rounded w-5/6"></div>
                    <div class="h-4 bg-emerald-200/50 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            `;
            if (regenBtn) regenBtn.disabled = true;

            fetch('{{ route('api.summary') }}')
                .then(res => res.json())
                .then(data => {
                    summaryEl.textContent = data.summary;
                })
                .catch(() => {
                    summaryEl.textContent = "Unable to fetch automated AI summary at this moment.";
                })
                .finally(() => {
                    if (regenBtn) regenBtn.disabled = false;
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderAllCharts();
            loadSummary();
            window.addEventListener('resize', () => {
                chartInstances.forEach(c => c && c.resize());
            });
        });
    </script>
</body>
</html>

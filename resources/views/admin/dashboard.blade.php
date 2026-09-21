
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IAO Admin Control Panel - IRIS</title>
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
                            <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-emerald-600 to-teal-500 dark:from-emerald-400 dark:to-teal-300 bg-clip-text text-transparent">IRIS Admin</span>
                            <span class="text-xs px-2 py-0.5 font-bold rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-700/50">IAO</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">International Affairs Office Control Panel</p>
                    </div>
                </div>

                <!-- Right Actions: Observatory Link, Dark Mode & User Profile -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('user.dashboard') }}" class="hidden sm:inline-flex items-center px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 dark:bg-gray-700 dark:text-emerald-300 hover:bg-emerald-100 rounded-lg transition-colors border border-emerald-200 dark:border-emerald-800">
                        <i class="fa-solid fa-chart-pie mr-1.5"></i> Public Observatory
                    </a>

                    <!-- Theme Toggle -->
                    <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-2.5">
                        <i id="theme-toggle-dark-icon" class="hidden fa-solid fa-moon text-base"></i>
                        <i id="theme-toggle-light-icon" class="hidden fa-solid fa-sun text-base text-amber-400"></i>
                    </button>

                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button type="button" class="flex items-center space-x-2 text-sm bg-gray-100 dark:bg-gray-700 p-1.5 rounded-full focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-emerald-500 to-amber-400 flex items-center justify-center text-white font-bold text-xs shadow">
                                <?= strtoupper(substr(session('username', 'A'), 0, 2)) ?>
                            </div>
                            <span class="hidden sm:inline-block font-medium text-xs px-1 text-gray-700 dark:text-gray-200"><?= htmlspecialchars(session('username', 'A')) ?></span>
                        </button>
                        <!-- Dropdown Menu -->
                        <div class="z-50 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-xl shadow-lg dark:bg-gray-700 dark:divide-gray-600" id="user-dropdown">
                            <div class="px-4 py-3">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars(session('username', 'A')) ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300 mt-1">ADMINISTRATOR</span>
                            </div>
                            <ul class="py-2" aria-labelledby="user-menu-button">
                                <li>
                                    <a href="{{ route('user.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200">
                                        <i class="fa-solid fa-globe mr-2"></i> Observatory View
                                    </a>
                                </li>
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
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">
        
        <?php if ($msg || $errors->first()): ?>
            <div id="alert-3" class="flex items-center p-4 mb-4 text-emerald-800 rounded-xl bg-emerald-50 dark:bg-gray-800 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800" role="alert">
                <i class="fa-solid fa-circle-check text-lg mr-3"></i>
                <div class="text-sm font-medium">
                    <?= htmlspecialchars($msg ?: $errors->first()) ?>
                </div>
                <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-emerald-50 text-emerald-500 rounded-lg focus:ring-2 focus:ring-emerald-400 p-1.5 hover:bg-emerald-200 inline-flex items-center justify-center h-8 w-8 dark:bg-gray-800 dark:text-emerald-400 dark:hover:bg-gray-700" data-dismiss-target="#alert-3" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Quick Ingestion Options Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Card 1: AI Smart Multi-Format Upload (Recommended) -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border-2 border-emerald-500/40 dark:border-emerald-500/30 shadow-md relative flex flex-col justify-between overflow-hidden">
                <div class="absolute top-0 right-0 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-bl-xl shadow">
                    RECOMMENDED
                </div>
                <div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl mb-4">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Smart AI Document Ingestion</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                        Upload raw PDF certificates, Word documents, multi-sheet Excel files, or photo scans. IRIS automatically parses tables, detects indicators, and provides a staged preview to verify before saving.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('admin.smart-upload') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow transition-colors">
                        <i class="fa-solid fa-file-import mr-2"></i> Launch Smart Ingestion (AI Assistant)
                    </a>
                </div>
            </div>

            <!-- Card 2: Direct CSV Fast Track -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl mb-4">
                        <i class="fa-solid fa-file-csv"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Direct CSV Ingestion</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                        Fast intake for structured CSV tables matching pre-defined database schemas (Rankings, College weights, or Program scores).
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="#direct-csv" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-xl transition-colors">
                        <i class="fa-solid fa-arrow-down mr-2"></i> Jump to CSV Upload Form
                    </a>
                </div>
            </div>
        </div>

        <!-- Direct CSV Upload Form Component -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-6" id="direct-csv">
            <div class="pb-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fa-solid fa-upload text-emerald-500 mr-2"></i> Direct CSV Upload
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Directly append or update structured datasets</p>
            </div>

            <form action="{{ route('admin.upload.csv') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="upload_type" class="block mb-2 text-sm font-semibold text-gray-900 dark:text-white">Target Dataset Schema</label>
                        <select name="upload_type" id="upload_type" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="rankings">Rankings (Headline body standings &amp; country rank)</option>
                            <option value="colleges">College Academic &amp; Research Contributions</option>
                            <option value="programs">National Program Rankings &amp; Movement</option>
                        </select>
                    </div>

                    <div>
                        <label for="csv_file" class="block mb-2 text-sm font-semibold text-gray-900 dark:text-white">Select CSV File</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="block w-full text-sm text-gray-900 border border-gray-300 rounded-xl cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                    </div>
                </div>

                <div>
                    <button type="submit" class="inline-flex items-center px-5 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800 rounded-xl shadow-md transition-all">
                        <i class="fa-solid fa-bolt mr-2"></i> Process &amp; Update Observatory
                    </button>
                </div>
            </form>

            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-100 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-300 space-y-1.5">
                <div class="font-bold text-gray-900 dark:text-white">Expected CSV Column Headers:</div>
                <div>&bull; <code class="text-emerald-600 dark:text-emerald-400 font-mono">rankings</code>: <code>ranking_body_short_name, year, global_rank, ph_rank, note</code></div>
                <div>&bull; <code class="text-emerald-600 dark:text-emerald-400 font-mono">colleges</code>: <code>name, short_code, contribution_percent, year</code></div>
                <div>&bull; <code class="text-emerald-600 dark:text-emerald-400 font-mono">programs</code>: <code>name, college_short_code, national_rank, score, movement, year</code></div>
                <div class="pt-2 text-gray-500 dark:text-gray-400 italic">
                    * For SDG breakdowns, WURI categories, or AUN-QA accreditations, use <a href="{{ route('admin.smart-upload') }}" class="text-emerald-600 dark:text-emerald-400 font-semibold underline">Smart Upload</a>.
                </div>
            </div>
        </div>

        <!-- Recent Ingestion Audit Trail Table -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
            <div class="pb-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fa-solid fa-clock-rotate-left text-teal-500 mr-2"></i> Recent Ingestion Audit Trail
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Log of the 10 most recent data upload operations</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                        <tr>
                            <th scope="col" class="px-4 py-3">Uploaded File</th>
                            <th scope="col" class="px-4 py-3">Format</th>
                            <th scope="col" class="px-4 py-3">Dataset Type</th>
                            <th scope="col" class="px-4 py-3">Rows Processed</th>
                            <th scope="col" class="px-4 py-3">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php $hasLogs = count($logs) > 0; ?>
                        <?php foreach ($logs as $row): ?>
                            <?php $fileType = $row->file_type ? strtoupper($row->file_type) : 'CSV'; ?>
                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white flex items-center space-x-2">
                                    <i class="fa-solid fa-file-lines text-gray-400"></i>
                                    <span><?= htmlspecialchars($row->filename) ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($fileType) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                        <?= htmlspecialchars($row->upload_type) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= (int)$row->rows_inserted ?></td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 font-mono"><?= htmlspecialchars($row->uploaded_at) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$hasLogs): ?>
                            <tr><td colspan="5" class="text-center py-6 text-gray-500 dark:text-gray-400">No upload activity logged yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 dark:text-gray-400 gap-4">
            <div class="flex items-center space-x-2">
                <span class="font-bold text-gray-800 dark:text-gray-200">IRIS Admin</span>
                <span>&bull; Central Luzon State University International Affairs Office</span>
            </div>
            <div>
                Powered by Flowbite &amp; Tailwind CSS
            </div>
        </div>
    </footer>

    <!-- Theme Toggle Script -->
    <script>
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
        });
    </script>
</body>
</html>

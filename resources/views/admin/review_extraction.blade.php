
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Extracted Data - IRIS Admin</title>
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
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col pb-24">

    <!-- Navbar -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 backdrop-blur-md bg-opacity-90 dark:bg-opacity-90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-emerald-500/20">
                        <img src="{{ asset('images/iris-logo.png') }}" alt="IRIS Logo" class="w-10 h-10 object-contain">
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-emerald-600 to-teal-500 dark:from-emerald-400 dark:to-teal-300 bg-clip-text text-transparent">Verification Staging</span>
                            <span class="text-xs px-2 py-0.5 font-bold rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300">REVIEW MODE</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Inspect &amp; Adjust Extracted Records Before Ingestion</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <a href="{{ route('admin.smart-upload') }}" class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        <i class="fa-solid fa-arrow-left mr-1.5"></i> Upload Different File
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">
        
        <!-- Document Meta Header Banner -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Parsed Source Document</span>
                    <?php if ($fileType): ?>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300"><?= htmlspecialchars(strtoupper($fileType)) ?> FORMAT</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-xl font-black text-gray-900 dark:text-white mt-1"><?= htmlspecialchars($filename) ?></h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Extracted <strong><?= $totalRows ?></strong> record(s) across 
                    <strong><?= count(array_filter([count($rankings), count($breakdowns), count($colleges), count($programs), count($accreditations)])) ?></strong> categories.
                </p>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                    <i class="fa-solid fa-circle-check mr-1.5"></i> AI Extraction Ready
                </span>
            </div>
        </div>

        <?php if ($totalRows === 0): ?>
            <div class="p-6 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-2xl text-rose-800 dark:text-rose-300 text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> No recognizable ranking, accreditation, or college metric tables were detected in this document. Please try a different file.
            </div>
        <?php else: ?>
            
            <form action="{{ route('admin.review-extraction.confirm') }}" method="POST" id="reviewForm" class="space-y-6">
                @csrf
                <input type="hidden" name="file_type" value="<?= htmlspecialchars($fileType) ?>">

                <!-- Flowbite Tabs Header -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="extractionTabs" data-tabs-toggle="#extractionTabsContent" role="tablist">
                        <?php if (count($rankings)): ?>
                            <li class="mr-2" role="presentation">
                                <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg font-bold" id="rankings-tab" data-tabs-target="#rankings" type="button" role="tab" aria-controls="rankings" aria-selected="<?= $firstActiveTab === 'rankings' ? 'true' : 'false' ?>">
                                    <i class="fa-solid fa-trophy mr-2 text-emerald-500"></i> Headline Rankings
                                    <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-gray-100 dark:bg-gray-700 rounded-full"><?= count($rankings) ?></span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if (count($breakdowns)): ?>
                            <li class="mr-2" role="presentation">
                                <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg font-bold" id="breakdowns-tab" data-tabs-target="#breakdowns" type="button" role="tab" aria-controls="breakdowns" aria-selected="<?= $firstActiveTab === 'breakdowns' ? 'true' : 'false' ?>">
                                    <i class="fa-solid fa-bullseye mr-2 text-amber-500"></i> SDGs &amp; Sub-Categories
                                    <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-gray-100 dark:bg-gray-700 rounded-full"><?= count($breakdowns) ?></span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if (count($colleges)): ?>
                            <li class="mr-2" role="presentation">
                                <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg font-bold" id="colleges-tab" data-tabs-target="#colleges" type="button" role="tab" aria-controls="colleges" aria-selected="<?= $firstActiveTab === 'colleges' ? 'true' : 'false' ?>">
                                    <i class="fa-solid fa-building-columns mr-2 text-cyan-500"></i> Colleges
                                    <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-gray-100 dark:bg-gray-700 rounded-full"><?= count($colleges) ?></span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if (count($programs)): ?>
                            <li class="mr-2" role="presentation">
                                <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg font-bold" id="programs-tab" data-tabs-target="#programs" type="button" role="tab" aria-controls="programs" aria-selected="<?= $firstActiveTab === 'programs' ? 'true' : 'false' ?>">
                                    <i class="fa-solid fa-graduation-cap mr-2 text-indigo-500"></i> Programs
                                    <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-gray-100 dark:bg-gray-700 rounded-full"><?= count($programs) ?></span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if (count($accreditations)): ?>
                            <li class="mr-2" role="presentation">
                                <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg font-bold" id="accreditations-tab" data-tabs-target="#accreditations" type="button" role="tab" aria-controls="accreditations" aria-selected="<?= $firstActiveTab === 'accreditations' ? 'true' : 'false' ?>">
                                    <i class="fa-solid fa-certificate mr-2 text-teal-500"></i> Accreditations
                                    <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-gray-100 dark:bg-gray-700 rounded-full"><?= count($accreditations) ?></span>
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Tab Panels -->
                <div id="extractionTabsContent">
                    
                    <!-- Tab 1: Rankings -->
                    <?php if (count($rankings)): ?>
                        <div class="hidden p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4" id="rankings" role="tabpanel" aria-labelledby="rankings-tab">
                            <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Global/PH rank accepts specific numbers, rank bands (e.g. "801-1000"), or status strings.</p>
                                <button type="button" onclick="toggleTabRows('rankings')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Toggle All</button>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                                <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-[11px] uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2.5 text-center w-12">Inc</th>
                                            <th class="px-3 py-2.5 w-28">Body</th>
                                            <th class="px-3 py-2.5 w-36">Category</th>
                                            <th class="px-3 py-2.5 w-24">Year</th>
                                            <th class="px-3 py-2.5 w-32">Global Rank</th>
                                            <th class="px-3 py-2.5 w-28">PH Rank</th>
                                            <th class="px-3 py-2.5">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <?php foreach ($rankings as $i => $r): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" name="rankings[<?= $i ?>][include]" class="row-chk w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500" checked>
                                                </td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="rankings[<?= $i ?>][ranking_body_short_name]" value="<?= htmlspecialchars($r['ranking_body_short_name'] ?? '') ?>" placeholder="QS"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="rankings[<?= $i ?>][category]" value="<?= htmlspecialchars($r['category'] ?? '') ?>" placeholder="World / Asia"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="rankings[<?= $i ?>][year]" value="<?= htmlspecialchars($r['year'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold" name="rankings[<?= $i ?>][global_rank]" value="<?= htmlspecialchars($r['global_rank'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="rankings[<?= $i ?>][ph_rank]" value="<?= htmlspecialchars($r['ph_rank'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="rankings[<?= $i ?>][note]" value="<?= htmlspecialchars($r['note'] ?? '') ?>"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tab 2: Breakdowns -->
                    <?php if (count($breakdowns)): ?>
                        <div class="hidden p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4" id="breakdowns" role="tabpanel" aria-labelledby="breakdowns-tab">
                            <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Sub-category and indicator scores (e.g. SDG Ranks, WURI Categories).</p>
                                <button type="button" onclick="toggleTabRows('breakdowns')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Toggle All</button>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                                <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-[11px] uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2.5 text-center w-12">Inc</th>
                                            <th class="px-3 py-2.5 w-24">Body</th>
                                            <th class="px-3 py-2.5 w-20">Year</th>
                                            <th class="px-3 py-2.5 w-36">Group Label</th>
                                            <th class="px-3 py-2.5">Indicator / Item</th>
                                            <th class="px-3 py-2.5 w-32">Rank/Score</th>
                                            <th class="px-3 py-2.5">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <?php foreach ($breakdowns as $i => $b): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" name="breakdowns[<?= $i ?>][include]" class="row-chk w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500" checked>
                                                </td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="breakdowns[<?= $i ?>][ranking_body_short_name]" value="<?= htmlspecialchars($b['ranking_body_short_name'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="breakdowns[<?= $i ?>][year]" value="<?= htmlspecialchars($b['year'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="breakdowns[<?= $i ?>][group_label]" value="<?= htmlspecialchars($b['group_label'] ?? '') ?>" placeholder="SDG Ranks"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-semibold" name="breakdowns[<?= $i ?>][item_label]" value="<?= htmlspecialchars($b['item_label'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold" name="breakdowns[<?= $i ?>][rank_display]" value="<?= htmlspecialchars($b['rank_display'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="breakdowns[<?= $i ?>][note]" value="<?= htmlspecialchars($b['note'] ?? '') ?>"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tab 3: Colleges -->
                    <?php if (count($colleges)): ?>
                        <div class="hidden p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4" id="colleges" role="tabpanel" aria-labelledby="colleges-tab">
                            <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">College academic &amp; research weight distributions.</p>
                                <button type="button" onclick="toggleTabRows('colleges')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Toggle All</button>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                                <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-[11px] uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2.5 text-center w-12">Inc</th>
                                            <th class="px-3 py-2.5">College Name</th>
                                            <th class="px-3 py-2.5 w-32">Short Code</th>
                                            <th class="px-3 py-2.5 w-36">Contribution %</th>
                                            <th class="px-3 py-2.5 w-28">Year</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <?php foreach ($colleges as $i => $c): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" name="colleges[<?= $i ?>][include]" class="row-chk w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500" checked>
                                                </td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-semibold" name="colleges[<?= $i ?>][name]" value="<?= htmlspecialchars($c['name'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="colleges[<?= $i ?>][short_code]" value="<?= htmlspecialchars($c['short_code'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" step="0.1" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold" name="colleges[<?= $i ?>][contribution_percent]" value="<?= htmlspecialchars($c['contribution_percent'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="colleges[<?= $i ?>][year]" value="<?= htmlspecialchars($c['year'] ?? '') ?>"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tab 4: Programs -->
                    <?php if (count($programs)): ?>
                        <div class="hidden p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4" id="programs" role="tabpanel" aria-labelledby="programs-tab">
                            <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Academic degree program national standings and trajectories.</p>
                                <button type="button" onclick="toggleTabRows('programs')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Toggle All</button>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                                <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-[11px] uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2.5 text-center w-12">Inc</th>
                                            <th class="px-3 py-2.5">Program Name</th>
                                            <th class="px-3 py-2.5 w-28">College Code</th>
                                            <th class="px-3 py-2.5 w-28">Nat. Rank</th>
                                            <th class="px-3 py-2.5 w-24">Score</th>
                                            <th class="px-3 py-2.5 w-24">Movement</th>
                                            <th class="px-3 py-2.5 w-24">Year</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <?php foreach ($programs as $i => $p): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" name="programs[<?= $i ?>][include]" class="row-chk w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500" checked>
                                                </td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-semibold" name="programs[<?= $i ?>][name]" value="<?= htmlspecialchars($p['name'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="programs[<?= $i ?>][college_short_code]" value="<?= htmlspecialchars($p['college_short_code'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold" name="programs[<?= $i ?>][national_rank]" value="<?= htmlspecialchars($p['national_rank'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" step="0.1" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="programs[<?= $i ?>][score]" value="<?= htmlspecialchars($p['score'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="programs[<?= $i ?>][movement]" value="<?= htmlspecialchars($p['movement'] ?? 0) ?>"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="programs[<?= $i ?>][year]" value="<?= htmlspecialchars($p['year'] ?? '') ?>"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tab 5: Accreditations -->
                    <?php if (count($accreditations)): ?>
                        <div class="hidden p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4" id="accreditations" role="tabpanel" aria-labelledby="accreditations-tab">
                            <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">AUN-QA and external peer review evaluations.</p>
                                <button type="button" onclick="toggleTabRows('accreditations')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Toggle All</button>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                                <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-[11px] uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2.5 text-center w-12">Inc</th>
                                            <th class="px-3 py-2.5">Program Name</th>
                                            <th class="px-3 py-2.5 w-32">Accrediting Body</th>
                                            <th class="px-3 py-2.5 w-24">Year</th>
                                            <th class="px-3 py-2.5 w-32">Assessment Date</th>
                                            <th class="px-3 py-2.5">Criterion</th>
                                            <th class="px-3 py-2.5 w-24">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <?php foreach ($accreditations as $i => $a): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" name="accreditations[<?= $i ?>][include]" class="row-chk w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500" checked>
                                                </td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-semibold" name="accreditations[<?= $i ?>][program_name]" value="<?= htmlspecialchars($a['program_name'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="accreditations[<?= $i ?>][accrediting_body]" value="<?= htmlspecialchars($a['accrediting_body'] ?? 'AUN-QA') ?>"></td>
                                                <td class="px-2 py-1"><input type="number" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="accreditations[<?= $i ?>][year]" value="<?= htmlspecialchars($a['year'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="accreditations[<?= $i ?>][assessment_date]" value="<?= htmlspecialchars($a['assessment_date'] ?? '') ?>" placeholder="YYYY-MM-DD"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" name="accreditations[<?= $i ?>][criterion]" value="<?= htmlspecialchars($a['criterion'] ?? '') ?>"></td>
                                                <td class="px-2 py-1"><input type="text" class="w-full p-1.5 text-xs rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold" name="accreditations[<?= $i ?>][score]" value="<?= htmlspecialchars($a['score'] ?? '') ?>"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Fixed Bottom Action Bar -->
                <div class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-t border-gray-200 dark:border-gray-700 py-3 px-4 shadow-2xl">
                    <div class="max-w-7xl mx-auto flex items-center justify-between">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-bold text-gray-900 dark:text-white">Ready for Ingestion:</span> Review all values before confirming.
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('admin.smart-upload') }}" class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl">Cancel</a>
                            <button type="submit" class="inline-flex items-center px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-lg transition-all">
                                <i class="fa-solid fa-circle-check mr-2"></i> Confirm &amp; Save to Observatory
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        <?php endif; ?>

    </main>

    <script>
        function toggleTabRows(tabId) {
            const pane = document.getElementById(tabId);
            if (!pane) return;
            const checkboxes = pane.querySelectorAll('.row-chk');
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            checkboxes.forEach(c => c.checked = !allChecked);
        }
    </script>
</body>
</html>

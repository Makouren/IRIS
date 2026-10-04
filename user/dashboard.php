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
    <script src="<?= e(base_url('scanner/js/charts/chartConfig.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/charts/chartMapping.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/charts/chartColors.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../scanner/js/charts/chartColors.js') ?>"></script>
    <script>window.IRIS_DASHBOARD_CONFIG = <?= json_encode(['baseUrl' => base_url(''), 'irisApiUrl' => base_url('api/iris.php'), 'dashboardGraphsApiUrl' => base_url('api/dashboard_graphs.php'), 'canManagePublishedGraphs' => ($_SESSION['role'] ?? null) === 'super_admin', 'fieldColors' => $fieldColors, 'fieldColorUpdatedAt' => $fieldColorUpdatedAt], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.IRISFieldColors = window.IRIS_DASHBOARD_CONFIG.fieldColors; window.IRISFieldColorUpdatedAt = window.IRIS_DASHBOARD_CONFIG.fieldColorUpdatedAt;</script>
    <script src="<?= e(base_url('scanner/js/charts/graphExport.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../scanner/js/charts/graphExport.js') ?>"></script>
    <script type="module" src="<?= e(base_url('user/js/dashboard/chartBuilder.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/dashboard/chartBuilder.js') ?>"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/portalNavigation.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/css/portalNavigation.css') ?>">
    <script src="<?= e(base_url('scanner/js/ui/dotBackground.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/js/ui/dotBackground.js') ?>" defer></script>
    <link rel="stylesheet" href="<?= e(base_url('user/css/dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/css/dashboard.css') ?>">
</head>
<body data-role="<?= e($_SESSION['role'] ?? 'user') ?>" class="dot-grid-dashboard text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">
    <div id="dashboard-dot-background" aria-hidden="true"></div>
    <div id="page-loader" aria-live="polite" aria-label="Loading page">
        <div class="iris-loader" aria-hidden="true"></div>
    </div>

    <?php $portalNavMode = 'public'; require __DIR__ . '/../includes/navigation/portal_nav.php'; ?>

    <!-- Main Container -->


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
    <script src="<?= e(base_url('user/js/dashboard/main.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/dashboard/main.js') ?>"></script>
<?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
    <?php require __DIR__ . '/../admin/includes/account_manager_modal.php'; ?>
    <?php require __DIR__ . '/../admin/includes/template_manager_modal.php'; ?>
    <script src="<?= e(base_url('admin/js/accountManager.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../admin/js/accountManager.js') ?>" defer></script>
    <script src="<?= e(base_url('admin/js/templateManager.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../admin/js/templateManager.js') ?>" defer></script>
    <script src="<?= e(base_url('admin/js/profileWorkbookMapper.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../admin/js/profileWorkbookMapper.js') ?>" defer></script>
<?php endif; ?>
<script src="<?= e(base_url('user/js/rankingHistory.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/rankingHistory.js') ?>" data-api="<?= e(base_url('api/rankings.php')) ?>" defer></script>
<?php $irisChangeRefreshView = 'public'; require __DIR__ . '/../includes/scripts/change_refresh_script.php'; ?>
</body>
</html>

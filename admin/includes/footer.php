        </div>
    </main>

    <!-- Footer -->
    <style>
        html:not(.dark) .admin-footer { background: #1E6031 !important; border-top: 3px solid #E0A70D !important; color: #ffffff !important; }
        html:not(.dark) .admin-footer span, html:not(.dark) .admin-footer div { color: rgba(255,255,255,0.90) !important; }
        html:not(.dark) .admin-footer-title { color: #ffffff !important; }
    </style>
    <footer class="admin-footer bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 dark:text-gray-400 gap-4">
            <div class="flex items-center space-x-2">
                <span class="font-bold admin-footer-title text-gray-800 dark:text-gray-200">IRIS Admin</span>
                <span>&bull; IAO'S INTERNATIONAL RAPPORT INSIGHT SYSTEM</span>
            </div>
            <div>
                Powered by Flowbite &amp; Tailwind CSS
            </div>
        </div>
    </footer>

    <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
        <?php require __DIR__ . '/account_manager_modal.php'; ?>
        <?php require __DIR__ . '/template_manager_modal.php'; ?>
        <?php require __DIR__ . '/ranking_body_manager_modal.php'; ?>
        <?php require __DIR__ . '/ranking_review_modal.php'; ?>
        <?php require __DIR__ . '/import_preview_modal.php'; ?>
        <?php require __DIR__ . '/summary_card_history_modal.php'; ?>
    <?php endif; ?>

    <!-- Theme Toggle & Script Setup -->
    <script>
        (function () {
            const loader = document.getElementById('page-loader');
            const hideLoader = () => { if (loader) loader.classList.add('hidden'); };
            setTimeout(hideLoader, 90);
            window.addEventListener('load', hideLoader);

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

            window.addEventListener('beforeunload', (event) => {
                if (window.IRIS_STUDIO_DIRTY) {
                    event.preventDefault();
                    event.returnValue = 'You have unsaved changes in the Review Editor. Are you sure you want to leave?';
                    return event.returnValue;
                }
            });

            window.addEventListener('resize', () => {
                if (window.IRISApp?.state?.studioChartInstance) {
                    try { window.IRISApp.state.studioChartInstance.resize(); } catch (e) {}
                }
            });
        })();

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
        });
    </script>

    <script src="<?= e(base_url('scanner/js/parsers/excelParser.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/ai/graphEngine.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/ai/graphEngine.js') ?>"></script>
    <script src="<?= e(base_url('scanner/js/database/dbManager.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/database/dbManager.js') ?>"></script>
    <script src="<?= e(base_url('scanner/js/samples.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/scanner.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/tableFilter.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/chartData.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/chartData.js') ?>"></script>
    <script src="<?= e(base_url('scanner/js/chartMapping.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/sheetMerge.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/sheetMerge.js') ?>"></script>
    <script src="<?= e(base_url('scanner/js/sourceIngestion.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/documentPagination.js')) ?>"></script>
    <script src="<?= e(base_url('scanner/js/chartColors.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/chartColors.js') ?>"></script>
    <script src="<?= e(base_url('scanner/js/graphExport.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/graphExport.js') ?>"></script>
    <script type="module" src="<?= e(base_url('scanner/js/app.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/app.js') ?>"></script>
    <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
        <script src="<?= e(base_url('admin/js/accountManager.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../js/accountManager.js') ?>" defer></script>
        <script src="<?= e(base_url('admin/js/templateManager.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../js/templateManager.js') ?>" defer></script>
        <script src="<?= e(base_url('admin/js/profileWorkbookMapper.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../js/profileWorkbookMapper.js') ?>" defer></script>
        <script src="<?= e(base_url('admin/js/rankingBodyManager.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../js/rankingBodyManager.js') ?>" defer></script>
        <script src="<?= e(base_url('admin/js/rankingReview.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../js/rankingReview.js') ?>" defer></script>
        <script type="module" src="<?= e(base_url('scanner/js/modules/import/importPreviewModal.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../../scanner/js/modules/import/importPreviewModal.js') ?>"></script>
        <script src="<?= e(base_url('admin/js/summaryCardHistory.js')) ?>?v=<?= (int) filemtime(__DIR__.'/../js/summaryCardHistory.js') ?>" defer></script>
    <?php endif; ?>
    <?php require __DIR__ . '/../../includes/change_refresh_script.php'; ?>
</body>
</html>

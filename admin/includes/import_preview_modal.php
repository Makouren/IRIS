<div id="templateImportModal" data-profile-api="<?= e(base_url('api/templates.php')) ?>" data-ranking-api="<?= e(base_url('api/imports/ranking_history_import.php')) ?>" data-summary-api="<?= e(base_url('api/imports/summary_card_import.php')) ?>" data-recovery-api="<?= e(base_url('api/imports/import_recovery.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" class="fixed inset-0 z-[1160] hidden items-center justify-center bg-slate-950/60 p-4" aria-hidden="true">
    <section class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="templateImportTitle">
        <header class="mb-4 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-slate-700">
            <div><h2 id="templateImportTitle" class="text-lg font-extrabold">Template data import</h2><p data-import-file class="mt-1 text-sm text-gray-500 dark:text-slate-400"></p></div>
            <button type="button" data-import-close class="rounded-lg px-3 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800" aria-label="Close">&times;</button>
        </header>
        <div data-import-notice class="mb-4 rounded-lg p-3 text-sm" role="status" aria-live="polite">Choose an upload to preview changes.</div>
        <div data-import-source-picker class="mb-4 hidden flex-wrap items-end gap-3">
            <label class="min-w-64 flex-1 text-sm font-semibold">Office upload<select data-import-source class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"><option value="">Choose an upload</option></select></label>
            <div class="flex gap-2">
                <button type="button" data-import-load-source class="hidden rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50" disabled><i class="fa-solid fa-eye mr-1" aria-hidden="true"></i>Preview selected</button>
                <button type="button" data-import-delete-upload class="hidden rounded-lg border border-red-300 px-3 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-800 dark:text-red-200 dark:hover:bg-red-950" disabled title="Delete an unapplied Summary Card upload"><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only">Delete selected Summary Card upload</span></button>
            </div>
        </div>
        <div data-import-sheet-picker class="mb-4 hidden flex-wrap items-end gap-3">
            <label class="min-w-64 flex-1 text-sm font-semibold">Worksheet<select data-import-sheet-select class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"><option value="">Choose a worksheet</option></select></label>
            <button type="button" data-import-sheet-choose class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Load worksheet</button>
        </div>
        <div data-import-sheet class="mb-2 text-xs font-semibold text-gray-500 dark:text-slate-400"></div>
        <div data-import-summary-selection class="mb-2 hidden items-center gap-2 text-sm font-semibold">
            <label class="inline-flex items-center gap-2"><input type="checkbox" data-import-select-all> Select all available rows</label>
            <span data-import-selection-count class="text-xs font-normal text-gray-500 dark:text-slate-400"></span>
        </div>
        <div data-import-review-surface class="hidden overflow-x-auto rounded-lg border border-gray-200 dark:border-slate-700">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-gray-100 text-xs font-bold uppercase text-gray-600 dark:bg-slate-800 dark:text-slate-300"><tr><th class="p-2">Source</th><th class="p-2" data-import-identity-heading>Action / identity</th><th class="p-2" data-import-existing-heading>Existing values</th><th class="p-2" data-import-incoming-heading>Incoming values</th><th class="p-2" data-import-apply-heading>Apply row</th></tr></thead>
                <tbody data-import-rows class="divide-y divide-gray-200 dark:divide-slate-800"><tr><td colspan="5" class="p-4 text-center text-gray-500">No preview loaded.</td></tr></tbody>
            </table>
        </div>
        <div data-import-pagination class="mt-3 hidden items-center justify-between gap-2">
            <button type="button" data-import-previous class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-bold disabled:opacity-40 dark:border-slate-700" disabled>Previous</button>
            <span data-import-page class="text-xs text-gray-500 dark:text-slate-400"></span>
            <button type="button" data-import-next class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-bold disabled:opacity-40 dark:border-slate-700" disabled>Next</button>
        </div>
        <footer class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <button type="button" data-import-revert class="hidden rounded-md border border-red-300 px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-200">Revert this import</button>
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" data-import-reviewed> I reviewed this diff</label>
            <div class="ml-auto flex gap-2"><button type="button" data-import-close class="rounded-md border border-gray-300 px-3 py-2 text-sm font-bold dark:border-slate-700">Close</button><button type="button" data-import-apply class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50" disabled>Apply selected rows</button></div>
        </footer>
    </section>
</div>

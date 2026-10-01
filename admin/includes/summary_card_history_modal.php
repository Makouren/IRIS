<div id="summaryCardHistoryModal" data-api="<?= e(base_url('api/iris.php?resource=summary_card_history')) ?>" data-csrf="<?= e(csrf_token()) ?>" class="fixed inset-0 z-[1210] hidden items-center justify-center bg-slate-950/70 p-4" aria-hidden="true">
    <section class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="summaryCardHistoryTitle">
        <header class="mb-4 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-slate-700">
            <div><h2 id="summaryCardHistoryTitle" class="text-lg font-extrabold">Summary Card history</h2><p data-summary-history-current class="mt-1 text-sm text-gray-500 dark:text-slate-400"></p></div>
            <button type="button" data-summary-history-close class="rounded-lg px-3 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800" aria-label="Close">&times;</button>
        </header>
        <div data-summary-history-notice class="mb-4 rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>
        <div data-summary-history-rows class="space-y-3"></div>
        <footer class="mt-5 flex justify-end"><button type="button" data-summary-history-close class="rounded-md border border-gray-300 px-3 py-2 text-sm font-bold dark:border-slate-700">Close</button></footer>
    </section>
</div>

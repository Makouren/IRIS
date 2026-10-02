<div id="rankingBodyManagerModal" data-api="<?= e(base_url('api/templates.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" class="fixed inset-0 z-[1200] hidden items-center justify-center bg-slate-950/70 p-4" aria-hidden="true">
    <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="rankingBodyManagerTitle">
        <header class="mb-5 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-slate-700">
            <div><h2 id="rankingBodyManagerTitle" class="text-lg font-extrabold">Manage ranking bodies</h2><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Names used by template links and Ranking History.</p></div>
            <button type="button" data-ranking-body-manager-close class="rounded-lg px-3 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800" aria-label="Close">&times;</button>
        </header>
        <div id="rankingBodyManagerNotice" class="mb-4 hidden rounded-lg p-3 text-sm" role="status"></div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(250px,0.7fr)]">
            <section>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-slate-400">Ranking bodies</h3>
                <div id="rankingBodyManagerList" class="grid gap-2" aria-live="polite"></div>
            </section>
            <form id="rankingBodyManagerForm" class="h-fit space-y-3 rounded-lg border border-gray-200 p-4 dark:border-slate-700">
                <h3 id="rankingBodyManagerFormTitle" class="text-sm font-bold">Add ranking body</h3>
                <input type="hidden" name="ranking_body_id" value="">
                <label class="block text-sm font-semibold">Name<input name="body_name" type="text" required maxlength="100" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <label class="block text-sm font-semibold">Short name<input name="short_name" type="text" required maxlength="20" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <label class="block text-sm font-semibold">Sort order<input name="sort_order" type="number" required min="0" max="1000000" value="100" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Save ranking body</button>
                    <button type="button" id="rankingBodyManagerNew" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800">Cancel / New</button>
                </div>
            </form>
        </div>
    </section>
</div>

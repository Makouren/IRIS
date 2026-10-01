<div id="rankingReviewModal" data-api="<?= e(base_url('api/template_reviews.php')) ?>" data-template-api="<?= e(base_url('api/templates.php')) ?>" data-source-base="<?= e(base_url('admin/upload_source.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" class="fixed inset-0 z-[1150] hidden items-center justify-center bg-slate-950/60 p-4" aria-hidden="true">
    <section class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="rankingReviewTitle">
        <header class="mb-5 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-slate-700">
            <div><h2 id="rankingReviewTitle" class="text-lg font-extrabold">Ranking upload review</h2><p id="rankingReviewRecordName" class="mt-1 text-sm text-gray-500 dark:text-slate-400"></p></div>
            <button type="button" data-ranking-review-close class="rounded-lg px-3 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800" aria-label="Close">&times;</button>
        </header>
        <div id="rankingReviewNotice" class="mb-4 hidden rounded-lg p-3 text-sm" role="status"></div>
        <div class="mb-5 flex flex-wrap items-end gap-3">
            <label class="min-w-64 flex-1 text-sm font-semibold">Template / ranking body<select id="rankingReviewTemplate" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"><option value="">Choose a linked template</option></select></label>
            <button id="rankingReviewPreview" type="button" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800"><i class="fa-solid fa-code-compare mr-1" aria-hidden="true"></i>Preview diff</button>
        </div>
        <div id="rankingReviewCounts" class="mb-4 grid gap-2 sm:grid-cols-4" aria-live="polite"></div>
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-slate-700">
            <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1.3fr)_100px] gap-2 bg-gray-100 px-3 py-2 text-xs font-bold uppercase text-gray-600 dark:bg-slate-800 dark:text-slate-300"><span>Year / Category</span><span>Current rank</span><span>Uploaded rank</span><span>Decision</span></div>
            <div id="rankingReviewRows" class="divide-y divide-gray-200 dark:divide-slate-800"><p class="px-3 py-5 text-sm text-gray-500 dark:text-slate-400">Choose a linked template and preview the upload.</p></div>
        </div>
        <footer class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-gray-500 dark:text-slate-400">New rows are selected by default; changed rows require explicit acceptance.</p>
            <button id="rankingReviewApprove" type="button" disabled class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Approve selected rows</button>
        </footer>
    </section>
</div>

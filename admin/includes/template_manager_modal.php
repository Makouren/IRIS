<div id="templateManagerModal" data-api="<?= e(base_url('api/templates.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-download-base="<?= e(base_url('admin/template_download.php')) ?>" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-slate-950/60 p-4" aria-hidden="true">
    <section class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="templateManagerTitle">
        <header class="mb-5 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-slate-700">
            <div><h2 id="templateManagerTitle" class="text-lg font-extrabold">Manage Templates</h2><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Office templates available during file submission.</p></div>
            <button type="button" data-template-manager-close class="rounded-lg px-3 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800" aria-label="Close">&times;</button>
        </header>
        <div id="templateManagerNotice" class="mb-4 hidden rounded-lg p-3 text-sm" role="status"></div>
        <section class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20" aria-labelledby="summaryCardProfileHeading">
            <div class="mb-3"><h3 id="summaryCardProfileHeading" class="text-sm font-bold">Summary Card import profile</h3><p data-active-summary-profile class="mt-1 text-xs text-gray-600 dark:text-slate-300">Loading active profile…</p></div>
            <div class="grid gap-3 lg:grid-cols-[minmax(220px,0.7fr)_minmax(0,1fr)]">
                <div class="space-y-3">
                    <label class="block text-sm font-semibold">Available Summary Card profiles<select data-summary-profile-select class="mt-1 block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm dark:border-slate-700 dark:bg-slate-800"><option value="">Loading profiles…</option></select></label>
                    <button type="button" data-summary-profile-activate class="rounded-md border border-emerald-700 px-3 py-2 text-sm font-bold text-emerald-900 hover:bg-emerald-100 dark:text-emerald-200 dark:hover:bg-emerald-950">Set as active profile</button>
                    <label class="block text-sm font-semibold">Profile name<input data-summary-profile-name type="text" maxlength="150" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm dark:border-slate-700 dark:bg-slate-800"></label>
                    <button type="button" data-summary-profile-save class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-800">Save profile settings</button>
                </div>
                <label class="block text-sm font-semibold">Field mappings and identity<textarea data-summary-profile-json rows="12" spellcheck="false" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white p-2.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-800"></textarea></label>
            </div>
        </section>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.8fr)]">
            <section>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-slate-400">Templates</h3>
                <div id="templateManagerList" class="grid gap-2" aria-live="polite"></div>
            </section>
            <form id="templateManagerForm" class="h-fit space-y-3 rounded-lg border border-gray-200 p-4 dark:border-slate-700" enctype="multipart/form-data">
                <div class="flex items-center justify-between gap-2"><h3 class="text-sm font-bold">Upload template</h3><button type="button" data-ranking-body-manager-open class="rounded-md border border-gray-300 px-2 py-1 text-xs font-bold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800">Manage ranking bodies</button></div>
                <label class="block text-sm font-semibold">Template name<input name="name" type="text" required maxlength="150" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <label class="block text-sm font-semibold">Ranking body (optional)<select id="templateRankingBodySelect" name="ranking_body_id" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"><option value="">Not linked</option></select></label>
                <label class="block text-sm font-semibold">File<input name="template_file" type="file" required accept=".xlsx,.xls,.csv,.docx" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-slate-700 dark:bg-slate-800"></label>
                <p class="text-xs text-gray-500 dark:text-slate-400">XLSX, XLS, CSV, or DOCX. Maximum 100 MB.</p>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800"><i class="fa-solid fa-cloud-arrow-up mr-1" aria-hidden="true"></i>Upload template</button>
            </form>
        </div>
    </section>
</div>

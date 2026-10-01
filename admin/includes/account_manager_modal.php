<div id="accountManagerModal" data-api="<?= e(base_url('api/accounts.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-slate-950/60 p-4" aria-hidden="true">
    <section class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="accountManagerTitle">
        <header class="mb-5 flex items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-slate-700">
            <div><h2 id="accountManagerTitle" class="text-lg font-extrabold">Manage accounts</h2><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Create and maintain office and viewer access.</p></div>
            <button type="button" data-account-manager-close class="rounded-lg px-3 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800" aria-label="Close">&times;</button>
        </header>
        <div id="accountManagerNotice" class="mb-4 hidden rounded-lg p-3 text-sm" role="status"></div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.9fr)]">
            <section>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-slate-400">Accounts</h3>
                <div id="accountManagerList" class="grid gap-2" aria-live="polite"></div>
            </section>
            <form id="accountManagerForm" class="h-fit space-y-3 rounded-lg border border-gray-200 p-4 dark:border-slate-700">
                <h3 id="accountManagerFormTitle" class="text-sm font-bold">Create account</h3>
                <input type="hidden" name="id" value="">
                <label class="block text-sm font-semibold">Username<input name="username" required maxlength="50" pattern="[A-Za-z0-9_-]{1,50}" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <label class="block text-sm font-semibold">Email<input name="email" type="email" required maxlength="100" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <label class="block text-sm font-semibold">Role<select name="role" required class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"><option value="admin">Admin</option><option value="user">User</option></select></label>
                <label id="accountManagerOfficeField" class="block text-sm font-semibold">Office name<input name="office_name" maxlength="100" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"></label>
                <label class="block text-sm font-semibold"><span id="accountManagerPasswordLabel">Password</span><input name="password" type="password" minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800"><span class="mt-1 block text-xs font-normal text-gray-500 dark:text-slate-400">At least 8 characters. Leave blank when editing to keep the current password.</span></label>
                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Save account</button>
                    <button type="button" id="accountManagerResetPassword" class="hidden rounded-lg border border-amber-300 px-3 py-2 text-sm font-bold text-amber-900 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-200 dark:hover:bg-amber-950">Reset password</button>
                    <button type="button" id="accountManagerNew" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800">New account</button>
                </div>
            </form>
        </div>
    </section>
</div>
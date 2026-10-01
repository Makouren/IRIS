<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$accountQuery = db()->prepare('SELECT office_name FROM users WHERE id = ?');
$accountQuery->execute([(int)$_SESSION['user_id']]);
$officeName = (string)($accountQuery->fetchColumn() ?: '');
$uploadsQuery = db()->prepare('SELECT records.fileName, records.fileType, records.fileSize, records.scannedAt, records.uploaded_at, records.status, records.adminNotes, records.metadata,
    COALESCE(profiles.destination, IF(templates.ranking_body_id IS NOT NULL, "ranking_history", "analytics")) AS profile_purpose
    FROM records
    LEFT JOIN templates ON templates.id = records.template_id
    LEFT JOIN template_import_profiles profiles ON profiles.template_id = records.template_id
        WHERE records.uploaded_by = ? ORDER BY records.uploaded_at DESC, records.scannedAt DESC');
$uploadsQuery->execute([(int)$_SESSION['user_id']]);
$uploads = $uploadsQuery->fetchAll(PDO::FETCH_ASSOC);
foreach ($uploads as &$upload) {
    $metadata = json_decode((string)($upload['metadata'] ?? ''), true);
    $upload['upload_purpose'] = is_array($metadata) && !empty($metadata['upload_purpose'])
        ? $metadata['upload_purpose']
        : $upload['profile_purpose'];
}
unset($upload);
$templateQuery = db()->prepare('SELECT templates.id, templates.name, templates.original_filename,
    COALESCE(profiles.destination, IF(templates.ranking_body_id IS NOT NULL, "ranking_history", "analytics")) AS upload_purpose,
        bodies.name AS ranking_body_name, bodies.short_name
    FROM templates
    LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.id
        LEFT JOIN ranking_bodies bodies ON bodies.id = templates.ranking_body_id
    WHERE templates.is_active = 1
    ORDER BY templates.name ASC, templates.id DESC');
$templateQuery->execute();
$templates = $templateQuery->fetchAll(PDO::FETCH_ASSOC);
$success = flash('success');
$error = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Upload - IRIS</title>
    <script>(function(){const t=localStorage.getItem('color-theme')||localStorage.getItem('iris-theme');document.documentElement.classList.toggle('dark',t?t==='dark':matchMedia('(prefers-color-scheme: dark)').matches)})();</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-slate-100">
    <header class="border-b border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
            <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">IRIS Office Portal</p><h1 class="text-xl font-extrabold"><?= e($officeName) ?> Uploads</h1></div>
            <nav class="flex items-center gap-2 text-sm font-semibold">
                <a class="rounded-lg px-3 py-2 text-emerald-800 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-800" href="<?= e(base_url('user/dashboard.php')) ?>">Public dashboards</a>
                <a class="rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-slate-800" href="<?= e(base_url('auth/change_password.php')) ?>">Change password</a>
                <form method="POST" action="<?= e(base_url('auth/logout.php')) ?>"><?= csrf_field() ?><button class="rounded-lg bg-gray-100 px-3 py-2 hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700" type="submit">Sign out</button></form>
            </nav>
        </div>
    </header>
    <main class="mx-auto grid max-w-6xl gap-8 px-4 py-8 lg:grid-cols-[minmax(280px,0.8fr)_minmax(0,1.5fr)]">
        <section class="h-fit rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-1 text-lg font-bold">Upload a file</h2>
            <p class="mb-5 text-sm text-gray-500 dark:text-slate-400">Files are sent to the Super Admin for review.</p>
            <?php if ($success): ?><div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" action="<?= e(base_url('admin/upload_process.php')) ?>" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <label for="officeUploadPurpose" class="block text-sm font-semibold">Upload purpose</label>
                <select id="officeUploadPurpose" name="upload_purpose" required class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">Choose a destination</option>
                    <option value="summary_cards">Latest Performance Snapshot</option>
                    <option value="ranking_history">Ranking History</option>
                    <option value="analytics">Analytics / general dataset</option>
                </select>
                <div data-template-control>
                    <label for="officeTemplateSelect" class="block text-sm font-semibold">Template</label>
                    <select id="officeTemplateSelect" name="template_id" required disabled class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800">
                        <option value="">Choose a purpose first</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= (int)$template['id'] ?>" data-purpose="<?= e($template['upload_purpose']) ?>">
                                <?= e($template['name'] . ' — ' . $template['original_filename'] . ($template['ranking_body_name'] ? ' · ' . $template['ranking_body_name'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p data-template-filter-help class="text-xs text-gray-500 dark:text-slate-400">Choose a purpose to see its upload requirements.</p>
                <label for="officeFile" class="block text-sm font-semibold">Select document</label>
                    <input id="officeFile" name="office_file" type="file" required accept=".xlsx,.csv,.tsv" class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-gray-50 text-sm file:mr-4 file:border-0 file:bg-emerald-700 file:px-4 file:py-3 file:font-semibold file:text-white dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs text-gray-500 dark:text-slate-400">XLSX, CSV, or TSV. Maximum 10 MB. Other file types cannot be read automatically yet.</p>
                <button class="w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-900" type="submit"><i class="fa-solid fa-cloud-arrow-up mr-2" aria-hidden="true"></i>Send for review</button>
            </form>
        </section>
        <section>
            <div class="mb-4 flex items-end justify-between gap-3"><div><h2 class="text-lg font-bold">Your uploads</h2><p class="text-sm text-gray-500 dark:text-slate-400">Only files submitted by this office are shown.</p></div><span class="rounded-full bg-gray-200 px-2.5 py-1 text-xs font-bold dark:bg-slate-800"><?= count($uploads) ?> total</span></div>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-100 text-xs uppercase text-gray-600 dark:bg-slate-800 dark:text-slate-300"><tr><th class="px-4 py-3">File</th><th class="px-4 py-3">Purpose</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Verification notes</th></tr></thead><tbody class="divide-y divide-gray-200 dark:divide-slate-800">
                <?php foreach ($uploads as $upload): ?><tr><td class="max-w-56 px-4 py-3 font-semibold"><span class="block truncate" title="<?= e($upload['fileName']) ?>"><?= e($upload['fileName']) ?></span><span class="text-xs font-normal text-gray-500 dark:text-slate-400"><?= e(strtoupper((string)$upload['fileType'])) ?> · <?= number_format((int)$upload['fileSize'] / 1048576, 2) ?> MB</span></td><td class="whitespace-nowrap px-4 py-3"><?= e(match ($upload['upload_purpose']) { 'summary_cards' => 'Latest Snapshot', 'ranking_history' => 'Ranking History', default => 'Analytics' }) ?></td><td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-slate-300"><?= e($upload['uploaded_at'] ?: $upload['scannedAt']) ?></td><td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"><?= e($upload['status'] ?: 'Pending Review') ?></span></td><td class="min-w-48 px-4 py-3 text-gray-600 dark:text-slate-300"><?= e($upload['adminNotes'] ?: 'No notes yet.') ?></td></tr><?php endforeach; ?>
                <?php if (!$uploads): ?><tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-slate-400">No uploads yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </section>
        <section class="lg:col-span-2">
            <div class="mb-3"><h2 class="text-lg font-bold">Active templates</h2><p class="text-sm text-gray-500 dark:text-slate-400">Templates provided by the Super Admin for office use.</p></div>
            <div id="activeTemplatesList" data-api="<?= e(base_url('api/templates.php')) ?>" data-download-base="<?= e(base_url('admin/template_download.php')) ?>" class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                <?php foreach ($templates as $template): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div><p class="font-semibold"><?= e($template['name']) ?></p><p class="text-xs text-gray-500 dark:text-slate-400"><?= e($template['original_filename']) ?></p></div>
                        <a class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800" href="<?= e(base_url('admin/template_download.php?id=' . (int)$template['id'])) ?>"><i class="fa-solid fa-download" aria-hidden="true"></i>Download</a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$templates): ?><p class="px-4 py-6 text-sm text-gray-500 dark:text-slate-400">No active templates are available.</p><?php endif; ?>
            </div>
        </section>
    </main>
    <script src="<?= e(base_url('admin/js/officeTemplates.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/officeTemplates.js') ?>" defer></script>
    <?php require __DIR__ . '/../includes/change_refresh_script.php'; ?>
</body>
</html>
<?php
/**
 * Purpose: Admin page for office upload; uses the shared admin layout and server-side access checks.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config/upload_limits.php';
require_once __DIR__ . '/../includes/assets/asset_bundles.php';
requireRole(['admin']);
$accountQuery = db()->prepare('SELECT offices.office_name
    FROM users LEFT JOIN offices ON offices.office_id = users.office_id
    WHERE users.user_id = ?');
$accountQuery->execute([(int)$_SESSION['user_id']]);
$officeName = (string)($accountQuery->fetchColumn() ?: '');
$uploadsQuery = db()->prepare('SELECT records.file_name AS fileName, records.file_type AS fileType, records.file_size AS fileSize, records.scanned_at AS scannedAt, records.uploaded_at, records.status, records.metadata,
    COALESCE(upload_profiles.destination, profiles.destination, templates.destination, JSON_UNQUOTE(JSON_EXTRACT(records.metadata, "$.upload_purpose")), "analytics") AS profile_purpose
    FROM records
    LEFT JOIN templates ON templates.template_id = records.template_id
    LEFT JOIN template_import_profiles upload_profiles ON upload_profiles.import_profile_id = records.import_profile_id
    LEFT JOIN template_import_profiles profiles ON profiles.template_id = records.template_id
        WHERE records.uploaded_by = ? ORDER BY records.uploaded_at DESC, records.scanned_at DESC');
$uploadsQuery->execute([(int)$_SESSION['user_id']]);
$uploads = $uploadsQuery->fetchAll(PDO::FETCH_ASSOC);
foreach ($uploads as &$upload) {
    $metadata = json_decode((string)($upload['metadata'] ?? ''), true);
    $upload['upload_purpose'] = is_array($metadata) && !empty($metadata['upload_purpose'])
        ? $metadata['upload_purpose']
        : $upload['profile_purpose'];
}
unset($upload);
$templateQuery = db()->prepare('SELECT templates.template_id AS id, templates.name, templates.original_filename,
    COALESCE(profiles.destination, templates.destination, "analytics") AS upload_purpose
    FROM templates
    LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.template_id
    WHERE templates.is_active = 1
    ORDER BY templates.name ASC, templates.template_id DESC');
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
    <script>window.tailwind = { config: { darkMode: 'class' } };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
    <?php render_iris_stylesheet_bundle(); ?>
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/portalNavigation.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/css/portalNavigation.css') ?>">
    <script src="<?= e(base_url('scanner/js/ui/dotBackground.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/js/ui/dotBackground.js') ?>" defer></script>
    <style>
        #page-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.68);backdrop-filter:blur(6px);z-index:10000;transition:opacity .3s ease,visibility .3s ease;}
        #page-loader.hidden{opacity:0;visibility:hidden;pointer-events:none;}
        .iris-loader{position:relative;width:72px;height:72px;border-radius:50%;background:conic-gradient(#10b981,#34d399,#fbbf24,#10b981);animation:spin 1s linear infinite;box-shadow:0 0 30px rgba(16,185,129,.5)}
        .iris-loader::before{content:"";position:absolute;inset:10px;border-radius:50%;background:rgba(15,23,42,.9);border:2px solid rgba(255,255,255,.18)}
        .iris-loader::after{content:"IRIS";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;letter-spacing:.12em;color:#d1fae5}
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body class="dot-grid-dashboard min-h-screen text-gray-900 dark:text-gray-100">
    <div id="dashboard-dot-background" aria-hidden="true"></div>
    <div id="page-loader" class="hidden" aria-live="polite" aria-label="Loading page">
        <div class="iris-loader" aria-hidden="true"></div>
    </div>
    <?php $portalNavMode = 'office'; require __DIR__ . '/../includes/navigation/portal_nav.php'; ?>
    <main class="dashboard-container office-upload-layout">
        <section class="studio-left-card office-upload-form-card h-fit">
            <h2 class="mb-1 text-lg font-bold">Upload a file</h2>
            <p class="mb-5 text-sm text-gray-700 dark:text-slate-300">Files are sent to the Super Admin for review.</p>
            <?php if ($success): ?><div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" action="<?= e(base_url('admin/upload_process.php')) ?>" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <label for="officeUploadPurpose" class="block text-sm font-semibold">Upload purpose</label>
                <select id="officeUploadPurpose" name="upload_purpose" required class="form-input">
                    <option value="">Choose a destination</option>
                    <option value="analytics">Data &amp; Report Visualization</option>
                    <option value="summary_cards">Summary Cards</option>
                    <option value="ranking_history">Ranking History</option>
                </select>
                <div data-template-control>
                    <label for="officeTemplateSelect" class="block text-sm font-semibold">Template profile (optional)</label>
                    <select id="officeTemplateSelect" name="template_id" disabled class="form-input disabled:opacity-50">
                        <option value="">General (uncategorized — template can be assigned later)</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= (int)$template['id'] ?>" data-purpose="<?= e($template['upload_purpose']) ?>">
                                <?= e($template['name'] . ' — ' . $template['original_filename']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p data-template-filter-help class="text-xs text-gray-500 dark:text-slate-400">Choose a purpose to see its upload requirements.</p>
                <label for="officeFile" class="block text-sm font-semibold">Select document</label>
                <input id="officeFile" name="office_file" type="file" required accept=".xlsx,.csv,.tsv" class="form-input office-file-input block cursor-pointer" aria-describedby="officeFileHelp officeFileError">
                <p id="officeFileHelp" class="text-xs text-gray-500 dark:text-slate-400">Allowed: XLSX, CSV, or TSV. Maximum <?= e(iris_upload_limit_label()) ?>.</p>
                <p id="officeFileError" class="hidden rounded-lg border border-red-200 bg-red-50 p-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert"></p>
                <button class="btn-save-modal w-full justify-center" type="submit"><i class="fa-solid fa-cloud-arrow-up mr-2" aria-hidden="true"></i>Send for review</button>
            </form>
        </section>
        <section class="studio-right-card office-uploads-panel">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold">Your uploads</h2><p class="text-sm text-gray-500 dark:text-slate-400">Only files submitted by this office are shown.</p></div><span class="badge badge-low"><?= count($uploads) ?> total</span></div>
            <div class="table-container office-uploads-scroll" tabindex="0" aria-label="Your recent uploads; scroll to see older uploads">
                <table class="data-table office-uploads-table">
                    <thead><tr><th>File</th><th>Purpose</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($uploads as $upload): ?>
                        <?php $uploadStatus = $upload['status'] ?: 'Pending Review'; ?>
                        <tr>
                            <td><span class="office-file-name" title="<?= e($upload['fileName']) ?>"><?= e($upload['fileName']) ?></span><span class="block text-xs font-normal text-gray-500 dark:text-slate-400"><?= e(strtoupper((string)$upload['fileType'])) ?> · <?= number_format((int)$upload['fileSize'] / 1048576, 2) ?> MB</span></td>
                            <td><?= e(match ($upload['upload_purpose']) { 'summary_cards' => 'Summary Cards', 'ranking_history' => 'Ranking History', default => 'Data & Report Visualization' }) ?></td>
                            <td><?= e($upload['uploaded_at'] ?: $upload['scannedAt']) ?></td>
                            <td><span class="badge <?= $uploadStatus === 'Approved' ? 'badge-low' : 'badge-medium' ?> office-status-badge"><?= e($uploadStatus === 'Approved' ? 'Published' : $uploadStatus) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$uploads): ?><tr><td colspan="4" class="text-center">No uploads yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="studio-right-card office-templates-section">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-lg font-bold">Active templates</h2><p class="text-sm text-gray-700 dark:text-slate-300">Templates and destination workbooks provided by the Super Admin for office use.</p></div>
                <label for="activeTemplateDestination" class="grid gap-1 text-sm font-semibold">Template category
                    <select id="activeTemplateDestination" class="form-input min-w-64">
                        <option value="analytics">Data &amp; Report Visualization</option>
                        <option value="summary_cards">Summary Cards</option>
                        <option value="ranking_history">Ranking History</option>
                    </select>
                </label>
            </div>
            <div id="activeTemplatesList" data-api="<?= e(base_url('api/templates.php')) ?>" data-download-base="<?= e(base_url('admin/template_download.php')) ?>" class="office-active-templates">
                <p class="py-4 text-sm text-gray-700 dark:text-slate-300">Loading active templates…</p>
            </div>
        </section>
    </main>
    <script>
        (() => {
            const form = document.querySelector('form[action*="upload_process.php"]');
            const input = document.getElementById('officeFile');
            const message = document.getElementById('officeFileError');
            const maxBytes = <?= IRIS_MAX_UPLOAD_BYTES ?>;
            const maxLabel = <?= json_encode(iris_upload_limit_label()) ?>;
            if (!form || !input || !message) return;
            const validateFileSize = () => {
                const file = input.files?.[0];
                const tooLarge = Boolean(file && file.size > maxBytes);
                message.textContent = tooLarge ? `This file is too large. Choose a file no larger than ${maxLabel}.` : '';
                message.classList.toggle('hidden', !tooLarge);
                input.setCustomValidity(tooLarge ? `Choose a file no larger than ${maxLabel}.` : '');
                return !tooLarge;
            };
            input.addEventListener('change', validateFileSize);
            form.addEventListener('submit', event => {
                if (!validateFileSize()) {
                    event.preventDefault();
                    input.focus();
                }
            });
        })();
    </script>
    <script src="<?= e(base_url('admin/js/officeTemplates.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/officeTemplates.js') ?>" defer></script>
    <?php require __DIR__ . '/../includes/scripts/change_refresh_script.php'; ?>
    <script>
        (function () {
            const loader = document.getElementById('page-loader');
            let templateDownloadPending = false;
            let templateDownloadReset;
            const hideLoader = () => { if (loader) loader.classList.add('hidden'); };
            setTimeout(hideLoader, 90);
            window.addEventListener('load', hideLoader);
            document.addEventListener('click', event => {
                const link = event.target.closest('a[href]');
                if (!link) return;
                const target = new URL(link.href, window.location.href);
                if (!target.pathname.endsWith('/admin/template_download.php')) return;
                templateDownloadPending = true;
                window.clearTimeout(templateDownloadReset);
                templateDownloadReset = window.setTimeout(() => { templateDownloadPending = false; }, 2000);
            }, true);
            window.addEventListener('beforeunload', () => {
                if (templateDownloadPending) {
                    templateDownloadPending = false;
                    window.clearTimeout(templateDownloadReset);
                    return;
                }
                if (loader) loader.classList.remove('hidden');
            });
            window.addEventListener('pagehide', () => { if (loader) loader.classList.add('hidden'); });
        })();
    </script>
</body>
</html>
<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$accountQuery = db()->prepare('SELECT offices.office_name
    FROM users LEFT JOIN offices ON offices.office_id = users.office_id
    WHERE users.user_id = ?');
$accountQuery->execute([(int)$_SESSION['user_id']]);
$officeName = (string)($accountQuery->fetchColumn() ?: '');
$uploadsQuery = db()->prepare('SELECT records.file_name AS fileName, records.file_type AS fileType, records.file_size AS fileSize, records.scanned_at AS scannedAt, records.uploaded_at, records.status, records.metadata,
    COALESCE(upload_profiles.destination, profiles.destination, JSON_UNQUOTE(JSON_EXTRACT(records.metadata, "$.upload_purpose")), "analytics") AS profile_purpose
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
    COALESCE(profiles.destination, "analytics") AS upload_purpose
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/styles.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/css/styles.css') ?>">
    <script src="<?= e(base_url('scanner/js/dotBackground.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/js/dotBackground.js') ?>" defer></script>
</head>
<body class="dot-grid-dashboard min-h-screen text-gray-900 dark:text-gray-100">
    <div id="dashboard-dot-background" aria-hidden="true"></div>
    <header class="app-header office-upload-header">
        <div class="app-header-inner">
            <div class="brand-container min-w-0">
                <div class="brand-logo-seal w-52 h-11 flex items-center justify-center overflow-hidden shrink-0 rounded-lg bg-white px-3 py-1.5">
                    <img src="<?= e(base_url('images/iris-panel-logo.svg')) ?>" alt="IRIS SielMetrics+ Logo" class="h-10 w-full object-contain object-left">
                </div>
                <div class="min-w-0">
                    <h1 class="brand-title">Office Upload</h1>
                    <p class="brand-subline">Central Luzon State University</p>
                </div>
            </div>
            <nav class="header-nav office-header-nav" aria-label="Office portal navigation">
                <a class="nav-btn" href="<?= e(base_url('user/dashboard.php')) ?>"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><span>Public dashboards</span></a>
                <button id="officeThemeToggle" class="nav-btn" type="button" aria-label="Switch to dark theme" title="Switch to dark theme" aria-pressed="false"><i class="fa-solid fa-moon" aria-hidden="true"></i><span>Theme</span></button>
                <a class="nav-btn" href="<?= e(base_url('auth/change_password.php')) ?>"><i class="fa-solid fa-key" aria-hidden="true"></i><span>Change password</span></a>
                <form method="POST" action="<?= e(base_url('auth/logout.php')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button class="nav-btn" type="submit"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Sign out</span></button>
                </form>
            </nav>
        </div>
    </header>
    <main class="dashboard-container office-upload-layout">
        <section class="studio-left-card office-upload-form-card h-fit">
            <h2 class="mb-1 text-lg font-bold">Upload a file</h2>
            <p class="mb-5 text-sm text-gray-500 dark:text-slate-400">Files are sent to the Super Admin for review.</p>
            <?php if ($success): ?><div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" action="<?= e(base_url('admin/upload_process.php')) ?>" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <label for="officeUploadPurpose" class="block text-sm font-semibold">Upload purpose</label>
                <select id="officeUploadPurpose" name="upload_purpose" required class="form-input">
                    <option value="">Choose a destination</option>
                    <option value="analytics">Data and Report Visualization</option>
                    <option value="summary_cards">Summary Cards</option>
                    <option value="ranking_history">Ranking History</option>
                </select>
                <div data-template-control>
                    <label for="officeTemplateSelect" class="block text-sm font-semibold">Template profile (optional)</label>
                    <select id="officeTemplateSelect" name="template_id" disabled class="form-input disabled:opacity-50">
                        <option value="">Use active destination profile</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= (int)$template['id'] ?>" data-purpose="<?= e($template['upload_purpose']) ?>">
                                <?= e($template['name'] . ' — ' . $template['original_filename']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p data-template-filter-help class="text-xs text-gray-500 dark:text-slate-400">Choose a purpose to see its upload requirements.</p>
                <label for="officeFile" class="block text-sm font-semibold">Select document</label>
                    <input id="officeFile" name="office_file" type="file" required accept=".xlsx,.csv,.tsv" class="form-input office-file-input block cursor-pointer">
                    <p class="text-xs text-gray-500 dark:text-slate-400">XLSX, CSV, or TSV. Maximum 10 MB. Other file types cannot be read automatically yet.</p>
                <button class="btn-save-modal w-full justify-center" type="submit"><i class="fa-solid fa-cloud-arrow-up mr-2" aria-hidden="true"></i>Send for review</button>
            </form>
        </section>
        <section class="studio-right-card office-uploads-panel">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold">Your uploads</h2><p class="text-sm text-gray-500 dark:text-slate-400">Only files submitted by this office are shown.</p></div><span class="badge badge-low"><?= count($uploads) ?> total</span></div>
            <div class="table-container">
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
                <div><h2 class="text-lg font-bold">Active templates</h2><p class="text-sm text-gray-500 dark:text-slate-400">Templates provided by the Super Admin for office use.</p></div>
                <label for="activeTemplateDestination" class="grid gap-1 text-sm font-semibold">Template category
                    <select id="activeTemplateDestination" class="form-input min-w-64">
                        <option value="analytics">Data &amp; Report Visualization</option>
                        <option value="summary_cards">Summary Cards</option>
                        <option value="ranking_history">Ranking History</option>
                    </select>
                </label>
            </div>
            <div id="activeTemplatesList" data-api="<?= e(base_url('api/templates.php')) ?>" data-download-base="<?= e(base_url('admin/template_download.php')) ?>" class="office-active-templates">
                <p class="py-4 text-sm text-gray-500 dark:text-slate-400">Loading active templates…</p>
            </div>
        </section>
    </main>
    <script src="<?= e(base_url('admin/js/officeTemplates.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/js/officeTemplates.js') ?>" defer></script>
    <?php require __DIR__ . '/../includes/change_refresh_script.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/../config/upload_limits.php';
$activeNav = $activeNav ?? '';
$portalNavMode = $portalNavMode ?? 'public';
$portalRole = (string)($_SESSION['role'] ?? 'user');
$portalUsername = (string)($_SESSION['username'] ?? 'User');
$portalIsOffice = $portalNavMode === 'office';
$portalIsAdmin = $portalNavMode === 'admin';
$portalTitle = $portalIsOffice ? 'IRIS Office' : ($portalIsAdmin ? 'IRIS Admin' : 'CLSU Observatory');
$portalBadge = $portalIsOffice
    ? 'OFFICE UPLOAD'
    : ($portalIsAdmin
        ? ($activeNav === 'ingestion' ? 'FILE INGESTION' : ($activeNav === 'review' ? 'REVIEW EDITOR' : ($activeNav === 'archives' ? 'FILE ARCHIVES' : 'SAVED GRAPHS')))
        : 'PUBLIC ANALYTICS');
$portalLogoClass = 'portal-nav admin-nav' . ($portalIsOffice ? ' office-upload-header' : '') . ' sticky top-0 z-50 backdrop-blur-md bg-opacity-95';
$portalLinkClass = 'admin-nav-link public-link';
$portalThemeClass = 'admin-theme-btn rounded-lg text-sm p-2.5';
$portalInitials = strtoupper(substr($portalUsername, 0, 2));
$portalAdminLinks = [
    ['admin/review_editor.php', 'fa-pen-to-square', 'Review Editor', 'review'],
    ['admin/file_archives.php', 'fa-box-archive', 'File Archives', 'archives'],
    ['admin/saved_graphs.php', 'fa-chart-line', 'Saved Graphs', 'saved_graphs'],
    ['user/dashboard.php', 'fa-globe', 'Observatory View', 'public'],
];
?>
<nav class="<?= e($portalLogoClass) ?>" aria-label="<?= $portalIsOffice ? 'Office portal' : ($portalIsAdmin ? 'Super Admin' : 'Observatory') ?> navigation">
    <div class="portal-nav-inner admin-nav-inner">
        <a href="<?= e(base_url('user/dashboard.php')) ?>" class="logo-refresh-trigger portal-nav-brand" data-target="<?= e(base_url('user/dashboard.php')) ?>" aria-label="Go to the public Observatory">
            <span class="portal-nav-logo">
                <img src="<?= e(base_url('images/iris-panel-logo.svg')) ?>" alt="IRIS SielMetrics+ Logo" class="h-10 w-full object-contain object-left">
            </span>
            <span class="portal-nav-brand-copy">
                <span class="portal-nav-brand-title"><?= e($portalTitle) ?></span>
                <?php if ($portalBadge !== ''): ?><span class="portal-nav-badge"><?= e($portalBadge) ?></span><?php endif; ?>
                <span class="portal-nav-brand-sub"><?= $portalIsOffice ? 'Central Luzon State University' : ($portalIsAdmin ? 'International Affairs Office Control Panel' : 'International Affairs Office · IRIS') ?></span>
            </span>
        </a>

        <button type="button" class="portal-nav-toggle" id="portalNavToggle" aria-label="Open navigation menu" aria-controls="portalNavMenu" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <div class="portal-nav-menu" id="portalNavMenu">
            <div class="admin-nav-actions portal-nav-actions">
                <?php if ($portalIsOffice): ?>
                    <a class="<?= e($portalLinkClass) ?>" href="<?= e(base_url('user/dashboard.php')) ?>"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><span>Public dashboards</span></a>
                <?php elseif ($portalIsAdmin): ?>
                    <a class="<?= e($portalLinkClass) ?>" href="<?= e(base_url('user/dashboard.php')) ?>"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span>Observatory</span></a>
                <?php else: ?>
                    <?php if ($portalRole === 'super_admin'): ?>
                        <a class="<?= e($portalLinkClass) ?>" href="<?= e(base_url('admin/review_editor.php')) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Edit</span></a>
                    <?php elseif ($portalRole === 'admin'): ?>
                        <a class="<?= e($portalLinkClass) ?>" href="<?= e(base_url('admin/office_upload.php')) ?>"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i><span>Back to Uploads</span></a>
                    <?php endif; ?>
                <?php endif; ?>

                <button id="<?= $portalIsOffice ? 'officeThemeToggle' : 'theme-toggle' ?>" type="button" class="<?= e($portalThemeClass) ?>" aria-label="Toggle theme" title="Toggle theme" aria-pressed="false">
                    <i id="<?= $portalIsOffice ? 'office-theme-dark-icon' : 'theme-toggle-dark-icon' ?>" class="hidden fa-solid fa-moon text-base" aria-hidden="true"></i>
                    <i id="<?= $portalIsOffice ? 'office-theme-light-icon' : 'theme-toggle-light-icon' ?>" class="hidden fa-solid fa-sun text-base text-amber-400" aria-hidden="true"></i>
                </button>
            </div>

            <div class="portal-nav-profile">
                <button type="button" class="admin-profile-btn flex items-center gap-2 p-1.5 rounded-full focus:ring-2 focus:ring-emerald-500" id="user-menu-button" aria-expanded="false" aria-controls="user-dropdown" aria-haspopup="true">
                    <span class="portal-nav-avatar"><?= e($portalInitials) ?></span>
                    <span class="portal-nav-username"><?= e($portalUsername) ?></span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 mr-1" aria-hidden="true"></i>
                </button>
                <div class="admin-dropdown z-50 hidden my-3 w-56 text-base list-none rounded-xl shadow-2xl" id="user-dropdown">
                    <div class="px-4 py-3 border-b border-slate-700">
                        <span class="dropdown-name block text-sm font-bold"><?= e($portalUsername) ?></span>
                        <span class="portal-nav-role"><?= $portalRole === 'super_admin' ? 'SUPER ADMIN' : ($portalRole === 'admin' ? 'OFFICE ADMIN' : 'VIEWER') ?></span>
                    </div>
                    <?php if ($portalRole === 'super_admin' && !$portalIsOffice): ?>
                        <ul class="py-2" aria-labelledby="user-menu-button">
                            <?php foreach ($portalAdminLinks as [$path, $icon, $label, $key]): ?>
                                <li><a href="<?= e(base_url($path)) ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a></li>
                            <?php endforeach; ?>
                            <li><button type="button" data-account-manager-open><i class="fa-solid fa-users-gear" aria-hidden="true"></i><span>Manage accounts</span></button></li>
                            <li><button type="button" data-template-manager-open><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span>Manage Templates</span></button></li>
                        </ul>
                    <?php endif; ?>
                    <ul class="py-2" aria-labelledby="user-menu-button">
                        <li><a href="<?= e(base_url('auth/change_password.php')) ?>"><i class="fa-solid fa-key" aria-hidden="true"></i><span>Change password</span></a></li>
                    </ul>
                    <div class="py-1 border-t border-slate-700">
                        <form method="POST" action="<?= e(base_url('auth/logout.php')) ?>" class="w-full">
                            <?= csrf_field() ?>
                            <button type="submit" class="signout w-full px-4 py-2 text-sm whitespace-nowrap">
                                <i class="fa-solid fa-right-from-bracket flex-shrink-0" aria-hidden="true"></i><span>Sign out</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
<script>window.IRIS_MAX_UPLOAD_BYTES = <?= IRIS_MAX_UPLOAD_BYTES ?>;</script>
<script src="<?= e(base_url('scanner/js/ui/portalNavigation.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../scanner/js/ui/portalNavigation.js') ?>" defer></script>

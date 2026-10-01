<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user']);
$error = flash('error');
$success = flash('success');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $query = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $query->execute([(int)$_SESSION['user_id']]);
    $hash = (string)$query->fetchColumn();
    if (!password_verify($current, $hash)) {
        flash_redirect('auth/change_password.php', 'error', 'Current password is incorrect.');
    }
    if (strlen($new) < 8 || !hash_equals($new, $confirm)) {
        flash_redirect('auth/change_password.php', 'error', 'New passwords must match and contain at least 8 characters.');
    }
    $update = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
    $update->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
    flash_redirect('auth/change_password.php', 'success', 'Password updated.');
}
$home = ($_SESSION['role'] ?? '') === 'super_admin' ? 'admin/review_editor.php' : (($_SESSION['role'] ?? '') === 'admin' ? 'admin/office_upload.php' : 'user/dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - IRIS</title>
    <script>(function(){const t=localStorage.getItem('color-theme')||localStorage.getItem('iris-theme');document.documentElement.classList.toggle('dark',t?t==='dark':matchMedia('(prefers-color-scheme: dark)').matches)})();</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-50 px-4 py-12 text-gray-900 dark:bg-gray-950 dark:text-white">
    <main class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <a class="text-sm font-semibold text-emerald-700 dark:text-emerald-400" href="<?= e(base_url($home)) ?>">&larr; Back</a>
        <h1 class="mb-5 mt-4 text-xl font-extrabold">Change password</h1>
        <?php foreach (['error' => $error, 'success' => $success] as $kind => $message): if ($message): ?>
            <p class="mb-4 rounded-lg p-3 text-sm <?= $kind === 'error' ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' ?>" role="status"><?= e($message) ?></p>
        <?php endif; endforeach; ?>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <label class="block text-sm font-semibold">Current password<input class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800" type="password" name="current_password" autocomplete="current-password" required></label>
            <label class="block text-sm font-semibold">New password<input class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800" type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
            <label class="block text-sm font-semibold">Confirm new password<input class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></label>
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800" type="submit">Update password</button>
        </form>
    </main>
</body>
</html>
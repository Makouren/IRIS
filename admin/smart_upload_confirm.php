<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
if (!ALLOW_SUPER_ADMIN_UPLOAD) flash_redirect('admin/review_editor.php', 'error', 'File uploads are handled by office accounts.');
require_once __DIR__ . '/../includes/data_insert.php';
verify_csrf();
if (empty($_SESSION['pending_extraction'])) flash_redirect('admin/smart_upload.php', 'error', 'The extraction session expired. Please upload the file again.');

$pdo = db();
$inserted = 0;
$skipped = 0;
$used = [];
try {
	$pdo->beginTransaction();
	foreach ($_POST['rankings'] ?? [] as $row) if (isset($row['include'])) { insert_ranking($row) ? $inserted++ : $skipped++; $used['rankings'] = true; }
	foreach ($_POST['breakdowns'] ?? [] as $row) if (isset($row['include'])) { insert_breakdown($row) ? $inserted++ : $skipped++; $used['ranking_breakdowns'] = true; }
	foreach ($_POST['colleges'] ?? [] as $row) if (isset($row['include'])) { insert_college($row) ? $inserted++ : $skipped++; $used['colleges'] = true; }
	foreach ($_POST['programs'] ?? [] as $row) if (isset($row['include'])) { insert_program($row) ? $inserted++ : $skipped++; $used['programs'] = true; }
	foreach ($_POST['accreditations'] ?? [] as $row) if (isset($row['include'])) { insert_accreditation($row) ? $inserted++ : $skipped++; $used['accreditations'] = true; }
	$type = count($used) === 1 ? array_key_first($used) : (count($used) > 1 ? 'mixed' : 'none');
	$pdo->prepare('INSERT INTO uploads_log(uploaded_by, filename, file_type, upload_type, rows_inserted) VALUES(?, ?, ?, ?, ?)')
		->execute([$_SESSION['user_id'], $_SESSION['pending_extraction_filename'] ?? 'unknown', $_POST['file_type'] ?? ($_SESSION['pending_extraction_file_type'] ?? ''), $type, $inserted]);
	$pdo->commit();
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	flash_redirect('admin/review_extraction.php', 'error', 'Could not save the reviewed extraction.');
}

unset($_SESSION['pending_extraction'], $_SESSION['pending_extraction_filename'], $_SESSION['pending_extraction_file_type']);
$message = "Saved {$inserted} row(s)." . ($skipped ? " {$skipped} row(s) skipped." : '');
flash_redirect('admin/dashboard.php', 'success', $message);

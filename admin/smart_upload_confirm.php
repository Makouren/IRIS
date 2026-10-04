<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
if (!ALLOW_SUPER_ADMIN_UPLOAD) flash_redirect('admin/review_editor.php', 'error', 'File uploads are handled by office accounts.');
require_once __DIR__ . '/../includes/data/data_insert.php';
verify_csrf();
if (empty($_SESSION['pending_extraction'])) flash_redirect('admin/smart_upload.php', 'error', 'The extraction session expired. Please upload the file again.');

$isIncludedRow = static fn(array $row): bool => isset($row['include']) && in_array((string)$row['include'], ['on', '1'], true);
$selectedRows = 0;
foreach (['rankings', 'breakdowns', 'colleges', 'programs', 'accreditations'] as $collection) {
	$rows = $_POST[$collection] ?? [];
	if (!is_array($rows)) flash_redirect('admin/review_extraction.php', 'error', 'The reviewed extraction data is invalid. Please review it again.');
	foreach ($rows as $row) {
		if (!is_array($row)) flash_redirect('admin/review_extraction.php', 'error', 'The reviewed extraction data is invalid. Please review it again.');
		if ($isIncludedRow($row)) $selectedRows++;
	}
}
if ($selectedRows === 0 && ($_POST['confirm_empty'] ?? '') !== '1') {
	flash_redirect('admin/review_extraction.php', 'error', 'No rows were selected. Confirm the 0-row upload in the review form before saving.');
}

$pdo = db();
$inserted = 0;
$skipped = 0;
$used = [];
try {
	$pdo->beginTransaction();
	foreach ($_POST['rankings'] ?? [] as $row) if ($isIncludedRow($row)) { insert_ranking($row) ? $inserted++ : $skipped++; $used['rankings'] = true; }
	foreach ($_POST['breakdowns'] ?? [] as $row) if ($isIncludedRow($row)) { insert_breakdown($row) ? $inserted++ : $skipped++; $used['ranking_breakdowns'] = true; }
	foreach ($_POST['colleges'] ?? [] as $row) if ($isIncludedRow($row)) { insert_college($row) ? $inserted++ : $skipped++; $used['colleges'] = true; }
	foreach ($_POST['programs'] ?? [] as $row) if ($isIncludedRow($row)) { insert_program($row) ? $inserted++ : $skipped++; $used['programs'] = true; }
	foreach ($_POST['accreditations'] ?? [] as $row) if ($isIncludedRow($row)) { insert_accreditation($row) ? $inserted++ : $skipped++; $used['accreditations'] = true; }
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

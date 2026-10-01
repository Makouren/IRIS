<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/SpreadsheetReader.php';
$currentRole = (string)($_SESSION['role'] ?? '');
if ($currentRole === 'super_admin' && !ALLOW_SUPER_ADMIN_UPLOAD) {
	requireRole(['admin']);
} else {
	requireRole(['super_admin', 'admin']);
}
verify_csrf();

$file = $_FILES['office_file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
	flash_redirect('admin/office_upload.php', 'error', 'Choose a file to upload.');
}

$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['xlsx', 'csv', 'tsv'];
$maxBytes = 10 * 1024 * 1024;
if (!in_array($extension, $allowedExtensions, true)) {
	error_log('IRIS rejected unsupported office upload: ' . basename((string)$file['name']) . ' (.' . $extension . ')');
	flash_redirect('admin/office_upload.php', 'error', "This file type can't be read automatically yet. Please upload .xlsx, .csv, or .tsv.");
}
if ((int)$file['size'] < 1 || (int)$file['size'] > $maxBytes) {
	flash_redirect('admin/office_upload.php', 'error', 'Spreadsheet files must be smaller than 10 MB.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
$signature = file_get_contents($file['tmp_name'], false, null, 0, 4) ?: '';
$csvHasNullByte = false;
if (in_array($extension, ['csv', 'tsv'], true) && ($csvHandle = fopen($file['tmp_name'], 'rb')) !== false) {
	while (!feof($csvHandle)) {
		if (str_contains((string)fread($csvHandle, 8192), "\0")) {
			$csvHasNullByte = true;
			break;
		}
	}
	fclose($csvHandle);
}
$validMime = match ($extension) {
	'csv' => in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true) && !$csvHasNullByte,
	'tsv' => in_array($mime, ['text/plain', 'text/tab-separated-values', 'application/vnd.ms-excel'], true) && !$csvHasNullByte,
	'xlsx' => in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'], true)
		&& $signature === "PK\x03\x04"
		&& class_exists(ZipArchive::class)
		&& (static function () use ($file): bool {
			$zip = new ZipArchive();
			if ($zip->open($file['tmp_name']) !== true) return false;
			$valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('xl/workbook.xml') !== false;
			$zip->close();
			return $valid;
		})(),
	default => false,
};
if (!$validMime) {
	error_log('IRIS rejected office spreadsheet content: file=' . basename((string)$file['name']) . ', extension=' . $extension . ', MIME=' . $mime . ', signature=' . bin2hex($signature));
	$errorMessage = $extension === 'xlsx'
		? "We couldn't read this Excel file. Make sure it's a valid .xlsx and try again."
		: 'The file contents do not match the selected spreadsheet type.';
	flash_redirect('admin/office_upload.php', 'error', $errorMessage);
}

try {
	$parsed = (new SpreadsheetReader())->parse($file['tmp_name'], $extension, (string)$file['name']);
} catch (Throwable $exception) {
	error_log('IRIS spreadsheet parse failure for ' . basename((string)$file['name']) . ': ' . $exception->getMessage());
	$errorMessage = $extension === 'xlsx'
		? "We couldn't read this Excel file. Make sure it's a valid .xlsx and try again."
		: "We couldn't read this CSV/TSV file. Check its delimiter and contents, then try again.";
	flash_redirect('admin/office_upload.php', 'error', $errorMessage);
}

$storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
if (!is_dir($storagePath) && !mkdir($storagePath, 0750, true) && !is_dir($storagePath)) {
	flash_redirect('admin/office_upload.php', 'error', 'Private upload storage is unavailable.');
}
$realStorage = realpath($storagePath);
$documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
if (!$realStorage || ($documentRoot && strncasecmp($realStorage, $documentRoot, strlen($documentRoot)) === 0)) {
	flash_redirect('admin/office_upload.php', 'error', 'Upload storage must be outside the web root.');
}

$storedName = bin2hex(random_bytes(24)) . '.' . $extension;
$storedPath = $realStorage . DIRECTORY_SEPARATOR . $storedName;
if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
	flash_redirect('admin/office_upload.php', 'error', 'Unable to store the uploaded file.');
}
chmod($storedPath, 0640);

try {
	$pdo = db();
	$account = $pdo->prepare('SELECT office_name FROM users WHERE id = ? AND role = ? AND is_active = 1 LIMIT 1');
	$account->execute([(int)$_SESSION['user_id'], $currentRole]);
	$officeName = $account->fetchColumn();
	if ($currentRole === 'admin' && !$officeName) throw new RuntimeException('The office account is no longer active.');
	if ($currentRole === 'super_admin') $officeName = 'Super Admin';

	$originalName = basename((string)$file['name']);
	$originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'upload.' . $extension;
	$recordId = 'rec_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
	$metadata = json_encode(array_merge($parsed['metadata'], ['stored_file' => $storedName]), JSON_THROW_ON_ERROR);
	$insert = $pdo->prepare("INSERT INTO records
		(id, fileName, fileType, fileSize, scannedAt, status, uploaded_by, office_name, uploaded_at,
		 docType, rawText, extractedData, graphDrafts, adminNotes, metadata, updatedAt)
		VALUES (?, ?, ?, ?, NOW(), 'Pending Review', ?, ?, NOW(), ?, ?, ?, ?, '', ?, NULL)");
	$insert->execute([
		$recordId,
		function_exists('mb_substr') ? mb_substr($originalName, 0, 255, 'UTF-8') : substr($originalName, 0, 255),
		$extension,
		(int)$file['size'],
		(int)$_SESSION['user_id'],
		$officeName,
		'Office Upload',
		(string)$parsed['rawText'],
		json_encode($parsed['sheetsData'], JSON_THROW_ON_ERROR),
		json_encode([], JSON_THROW_ON_ERROR),
		$metadata,
	]);
} catch (Throwable $exception) {
	if (is_file($storedPath)) unlink($storedPath);
	flash_redirect('admin/office_upload.php', 'error', 'Upload could not be added to the review queue.');
}

flash_redirect('admin/office_upload.php', 'success', 'File uploaded and added to the Super Admin review queue.');

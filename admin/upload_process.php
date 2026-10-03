<?php
ini_set('display_errors', '0');
ini_set('html_errors', '0');
try {
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__.'/../includes/upload_limits.php';
require_once __DIR__.'/../includes/SpreadsheetReader.php';
require_once __DIR__ . '/../includes/helpers/SummaryCardImportProfiles.php';
require_once __DIR__ . '/../includes/helpers/ProfileWorkbookService.php';
$currentRole = (string)($_SESSION['role'] ?? '');
if ($currentRole === 'super_admin' && !ALLOW_SUPER_ADMIN_UPLOAD) {
	requireRole(['admin']);
} else {
	requireRole(['super_admin', 'admin']);
}
if (iris_upload_request_exceeded_post_limit()) {
	flash_redirect('admin/office_upload.php', 'error', 'The upload request exceeds the server request limit. Keep the file at or below ' . iris_upload_limit_label() . ' and configure PHP post_max_size to at least 12M.');
}
verify_csrf();
$uploadPurpose = trim((string)($_POST['upload_purpose'] ?? ''));
if (!in_array($uploadPurpose, ['analytics', 'ranking_history', 'summary_cards'], true)) {
	flash_redirect('admin/office_upload.php', 'error', 'Choose an upload purpose.');
}
$templateInput = trim((string)($_POST['template_id'] ?? ''));
$templateId = null;
if ($templateInput !== '') {
	$parsedTemplateId = filter_var($templateInput, FILTER_VALIDATE_INT);
	if (!$parsedTemplateId || $parsedTemplateId < 1) {
		flash_redirect('admin/office_upload.php', 'error', 'Choose a valid active template.');
	}
	$templateId = (int)$parsedTemplateId;
}
if ($templateId === null && $uploadPurpose === 'analytics') {
	flash_redirect('admin/office_upload.php', 'error', 'Choose an active template for the selected upload purpose.');
}
$pdo = db();
$importProfileId = null;
$selectedTemplate = null;
$profileConfig = null;
if ($templateId !== null) {
	$templateQuery = $pdo->prepare('SELECT templates.template_id AS id, profiles.import_profile_id, COALESCE(profiles.destination, templates.destination, \'analytics\') AS destination
		FROM templates
		LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.template_id
		WHERE templates.template_id = ? AND templates.is_active = 1 LIMIT 1');
	$templateQuery->execute([$templateId]);
	$selectedTemplate = $templateQuery->fetch(PDO::FETCH_ASSOC);
	if (!$selectedTemplate) {
		flash_redirect('admin/office_upload.php', 'error', 'The selected template is no longer active. Choose another option.');
	}
	$templatePurpose = $selectedTemplate['destination'] ?: 'analytics';
	if ($templatePurpose !== $uploadPurpose) {
		flash_redirect('admin/office_upload.php', 'error', 'The selected template does not match the upload purpose. Choose a matching template.');
	}
	$importProfileId = $selectedTemplate['import_profile_id'] === null ? null : (int)$selectedTemplate['import_profile_id'];
	if ($importProfileId !== null) $profileConfig = SummaryCardImportProfiles::get($pdo, $importProfileId, true, $uploadPurpose);
}
if ($templateId === null && in_array($uploadPurpose, ['summary_cards', 'ranking_history'], true)) {
	try {
		$activeProfile = SummaryCardImportProfiles::active($pdo, $uploadPurpose);
		$importProfileId = (int)$activeProfile['id'];
		$templateId = $activeProfile['template_id'] === null ? null : (int)$activeProfile['template_id'];
		$profileConfig = $activeProfile;
	} catch (Throwable $exception) {
		flash_redirect('admin/office_upload.php', 'error', 'No active import profile is configured for this destination. Ask the Super Admin to choose one.');
	}
}

$file = $_FILES['office_file'] ?? null;
if (!$file) {
	flash_redirect('admin/office_upload.php', 'error', 'Choose a file to upload.');
}
$uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
	flash_redirect('admin/office_upload.php', 'error', iris_upload_error_message($uploadError));
}
if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
	flash_redirect('admin/office_upload.php', 'error', 'The uploaded file could not be read. Please try again.');
}

$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['xlsx', 'csv', 'tsv'];
if (!in_array($extension, $allowedExtensions, true)) {
	error_log('IRIS rejected unsupported office upload: ' . basename((string)$file['name']) . ' (.' . $extension . ')');
	flash_redirect('admin/office_upload.php', 'error', "This file type can't be read automatically yet. Please upload .xlsx, .csv, or .tsv.");
}
if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > IRIS_MAX_UPLOAD_BYTES) {
	flash_redirect('admin/office_upload.php', 'error', 'The spreadsheet must be between 1 byte and ' . iris_upload_limit_label() . '.');
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
	$headerRow = $profileConfig['workbook_header_row'] ?? null;
	$headerRow = filter_var($headerRow, FILTER_VALIDATE_INT);
	$headerRow = $headerRow === false || $headerRow === null || $headerRow < 1 ? null : (int)$headerRow;
	$selectedSheet = in_array($extension, ['csv', 'tsv'], true) ? null : (trim((string)($profileConfig['sheet_selector'] ?? '')) ?: null);
	$parsed = (new SpreadsheetReader())->parse($file['tmp_name'], $extension, (string)$file['name'], $selectedSheet, $headerRow);
} catch (Throwable $exception) {
	error_log('IRIS spreadsheet parse failure for ' . basename((string)$file['name']) . ': ' . $exception->getMessage());
	$errorMessage = $profileConfig !== null
		? $exception->getMessage()
		: ($extension === 'xlsx'
		? "We couldn't read this Excel file. Make sure it's a valid .xlsx and try again."
		: "We couldn't read this CSV/TSV file. Check its delimiter and contents, then try again.");
	flash_redirect('admin/office_upload.php', 'error', $errorMessage);
}

if ($profileConfig !== null && in_array($uploadPurpose, ['summary_cards', 'ranking_history'], true)) {
	$parsedSheets = array_values($parsed['sheetsData'] ?? []);
	$sheetData = $parsedSheets[0] ?? null;
	if (!$sheetData) flash_redirect('admin/office_upload.php', 'error', 'The selected workbook has no readable worksheet.');
	$expectedHeaders = is_array($profileConfig['workbook_headers'] ?? null) ? $profileConfig['workbook_headers'] : [];
	if (!$expectedHeaders) $expectedHeaders = ProfileWorkbookService::expectedHeaders($profileConfig, $uploadPurpose);
	try {
		ProfileWorkbookService::assertHeadersMatch($sheetData['headers'] ?? [], $expectedHeaders, $uploadPurpose);
	} catch (InvalidArgumentException $exception) {
		flash_redirect('admin/office_upload.php', 'error', $exception->getMessage());
	}
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
	$account = $pdo->prepare('SELECT users.office_id, offices.office_name
		FROM users
		INNER JOIN roles ON roles.role_id = users.role_id
		LEFT JOIN offices ON offices.office_id = users.office_id
		WHERE users.user_id = ? AND roles.role_name = ? AND users.is_active = 1 LIMIT 1');
	$account->execute([(int)$_SESSION['user_id'], $currentRole]);
	$accountRow = $account->fetch(PDO::FETCH_ASSOC);
	if (!$accountRow || ($currentRole === 'admin' && (empty($accountRow['office_id']) || empty($accountRow['office_name'])))) {
		throw new RuntimeException('The office account is no longer active.');
	}
	$officeId = $accountRow['office_id'] ?? null;
	if ($currentRole === 'super_admin') $officeId = null;

	$originalName = basename((string)$file['name']);
	$originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'upload.' . $extension;
	$metadata = json_encode(array_merge($parsed['metadata'], ['stored_file' => $storedName, 'upload_purpose' => $uploadPurpose]), JSON_THROW_ON_ERROR);
	$insert = $pdo->prepare("INSERT INTO records
		(file_name, file_type, file_size, scanned_at, status, uploaded_by, office_id, uploaded_at,
		 template_id, import_profile_id, doc_type, raw_text, extracted_data, graph_drafts, admin_notes, metadata, updated_at)
		VALUES (?, ?, ?, NOW(), 'Pending Review', ?, ?, NOW(), ?, ?, ?, ?, ?, ?, '', ?, NULL)");
	$insert->execute([
		function_exists('mb_substr') ? mb_substr($originalName, 0, 255, 'UTF-8') : substr($originalName, 0, 255),
		$extension,
		(int)$file['size'],
		(int)$_SESSION['user_id'],
		$officeId,
		$templateId,
		$importProfileId,
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
} catch (Throwable $exception) {
	error_log('IRIS office upload failure: ' . $exception);
	if (!headers_sent()) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
	}
	exit('Upload processing failed. Please try again or contact the administrator.');
}

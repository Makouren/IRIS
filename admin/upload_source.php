<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin']);

$recordId = trim((string)($_GET['id'] ?? ''));
if ($recordId === '') {
    http_response_code(400);
    exit('Record id is required.');
}

$query = db()->prepare('SELECT file_name AS fileName, file_type AS fileType, scanned_at AS scannedAt, metadata FROM records WHERE record_id = ? LIMIT 1');
$query->execute([$recordId]);
$record = $query->fetch(PDO::FETCH_ASSOC);
if (!$record) {
    http_response_code(404);
    exit('Record not found.');
}

$metadata = json_decode((string)($record['metadata'] ?? ''), true);
$storedName = is_array($metadata) ? (string)($metadata['stored_file'] ?? '') : '';
if (!preg_match('/^[a-f0-9]{48}\.(xlsx|xls|csv|pdf|docx|png|jpg|jpeg|webp)$/', $storedName)) {
    http_response_code(404);
    exit('Source file not found.');
}

$storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
$realStorage = realpath($storagePath);
$filePath = $realStorage ? realpath($realStorage . DIRECTORY_SEPARATOR . $storedName) : false;
if (!$realStorage || !$filePath || dirname($filePath) !== $realStorage || !is_file($filePath)) {
    http_response_code(404);
    exit('Source file not found.');
}

$pdo = db();
$pdo->prepare('UPDATE records SET opened_at = COALESCE(opened_at, NOW()) WHERE record_id = ?')->execute([(int)$recordId]);

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = match ($extension) {
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'csv' => 'text/csv; charset=UTF-8',
    'tsv' => 'text/tab-separated-values; charset=UTF-8',
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};

$inline = in_array($record['fileType'], ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true);
$disposition = $inline ? 'inline' : 'attachment';
$originalName = basename(str_replace('\\', '/', (string)$record['fileName']));
$originalName = preg_replace('/[\r\n\x00-\x1F\x7F"<>:|?*]/', '_', $originalName);
$baseName = pathinfo($originalName, PATHINFO_FILENAME);
$baseName = trim($baseName) !== '' ? $baseName : 'record-' . $recordId;
$recordTimestamp = strtotime((string)($record['scannedAt'] ?? ''));
$downloadStamp = $recordTimestamp === false ? date('mdy') : date('mdy', $recordTimestamp);
$filename = $baseName . '-restored-' . $downloadStamp . '.' . $extension;
$fileHandle = fopen($filePath, 'rb');
if ($fileHandle === false) {
    http_response_code(500);
    exit('Unable to read the archived source file.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($filename, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
header('Expires: 0');
fpassthru($fileHandle);
fclose($fileHandle);
exit;
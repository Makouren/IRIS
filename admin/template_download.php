<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin'], true);

$templateId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$templateId || $templateId < 1) {
    http_response_code(400);
    exit('Template id is required.');
}

$pdo = db();
if (($_SESSION['role'] ?? '') === 'super_admin') {
    $query = $pdo->prepare('SELECT file_path, original_filename FROM templates WHERE id = ? LIMIT 1');
} else {
    $query = $pdo->prepare('SELECT file_path, original_filename FROM templates WHERE id = ? AND is_active = 1 LIMIT 1');
}
$query->execute([$templateId]);
$template = $query->fetch(PDO::FETCH_ASSOC);
if (!$template) {
    http_response_code(404);
    exit('Template not found.');
}

$fileName = (string)$template['file_path'];
if (!preg_match('/^[a-f0-9]{48}\.(xlsx|xls|csv|docx)$/', $fileName)) {
    http_response_code(404);
    exit('Template file not found.');
}
$storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
$realStorage = realpath($storagePath);
$filePath = $realStorage ? realpath($realStorage . DIRECTORY_SEPARATOR . $fileName) : false;
if (!$realStorage || !$filePath || dirname($filePath) !== $realStorage || !is_file($filePath)) {
    http_response_code(404);
    exit('Template file not found.');
}

$downloadName = str_replace(["\r", "\n", '"', '\\', '/'], '_', (string)$template['original_filename']);
if ($downloadName === '') $downloadName = 'template.' . pathinfo($fileName, PATHINFO_EXTENSION);
$headerFileName = preg_replace('/[^\x20-\x7E]/', '_', $downloadName) ?: 'template.' . pathinfo($fileName, PATHINFO_EXTENSION);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . $headerFileName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Cache-Control: private, no-store');
readfile($filePath);

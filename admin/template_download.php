<?php
ini_set('display_errors', '0');
ini_set('html_errors', '0');
try {
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
require_once __DIR__ . '/../includes/helpers/ProfileWorkbookService.php';

if (($_GET['ranking_history'] ?? '') === '1') $_GET['destination'] = 'ranking_history';
$destination = (string)($_GET['destination'] ?? '');
if (in_array($destination, ['summary_cards', 'ranking_history'], true)) {
    try {
        [$profile, $workbook] = ProfileWorkbookService::profileWorkbook(db(), $destination);
        $storedName = (string)($workbook['workbook_file_path'] ?? '');
        $storedPath = $storedName !== '' ? ProfileWorkbookService::workbookPath($storedName) : null;
        if ($storedPath) {
            $path = $storedPath;
            $downloadName = (string)($workbook['workbook_original_filename'] ?? '');
            $storedExtension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
            if ($storedExtension !== '' && strtolower(pathinfo($downloadName, PATHINFO_EXTENSION)) !== $storedExtension) {
                $downloadName = pathinfo($downloadName, PATHINFO_FILENAME) . '.' . $storedExtension;
            }
        } else {
            $headers = ProfileWorkbookService::expectedHeaders($profile, $destination);
            if (!$headers) throw new RuntimeException('Map the profile columns before downloading a template.');
            $headerRow = filter_var($workbook['workbook_header_row'] ?? null, FILTER_VALIDATE_INT);
            $path = ProfileWorkbookService::createWorkbook($headers, (string)($profile['sheet_selector'] ?: ($destination === 'ranking_history' ? 'Ranking History' : 'Summary Cards')), $headerRow && $headerRow > 0 ? (int)$headerRow : 1);
            $downloadName = $destination === 'ranking_history' ? 'IRIS_Ranking_History_Template.xlsx' : 'IRIS_Summary_Cards_Template.xlsx';
        }
        $downloadName = str_replace(["\r", "\n", '"', '\\', '/'], '_', $downloadName ?: 'IRIS_Template.xlsx');
        $headerName = preg_replace('/[^\x20-\x7E]/', '_', $downloadName) ?: 'IRIS_Template.xlsx';
        header('Content-Type: ' . ((strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream')));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $headerName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        if (!$storedPath) @unlink($path);
        exit;
    } catch (Throwable $exception) {
        error_log('IRIS template workbook download failure: ' . $exception);
        http_response_code(500);
        exit('Unable to prepare the template workbook. Check the server log.');
    }
}

$templateId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$templateId || $templateId < 1) {
    http_response_code(400);
    exit('Template id is required.');
}

$pdo = db();
if (($_SESSION['role'] ?? '') === 'super_admin') {
    $query = $pdo->prepare('SELECT file_path, original_filename FROM templates WHERE template_id = ? LIMIT 1');
} else {
    $query = $pdo->prepare('SELECT file_path, original_filename FROM templates WHERE template_id = ? AND is_active = 1 LIMIT 1');
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
} catch (Throwable $exception) {
    error_log('IRIS template download failure: ' . $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('Template download failed. Please try again or contact the administrator.');
}

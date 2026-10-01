<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/extractors.php';

require_admin();
if (!ALLOW_SUPER_ADMIN_UPLOAD) flash_redirect('admin/review_editor.php', 'error', 'File uploads are handled by office accounts.');
verify_csrf();

$f = $_FILES['upload_file'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
    flash_redirect('admin/smart_upload.php', 'error', 'Please choose a file.');
}

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['csv', 'xlsx', 'xls', 'docx', 'pdf', 'jpg', 'jpeg', 'png'];
if (!in_array($ext, $allowed, true)) {
    flash_redirect('admin/smart_upload.php', 'error', 'Unsupported file type.');
}

try {
    if ($ext === 'csv') {
        $result = smart_map_csv($f['tmp_name']);
        if ($result === null) {
            flash_redirect('admin/smart_upload.php', 'error', 'CSV header requirements not met. Expected headers for Rankings (ranking_body_short_name, year, global_rank, ph_rank), Colleges (name, short_code, contribution_percent, year), or Programs (name, college_short_code, national_rank, score).');
        }
        $_SESSION['pending_extraction'] = $result;
        $_SESSION['pending_extraction_filename'] = basename($f['name']);
        $_SESSION['pending_extraction_file_type'] = $ext;
        redirect_to('admin/review_extraction.php');
    } else {
        flash_redirect('admin/smart_upload.php', 'error', 'Direct document ingestion supports CSV files using rule-based header mapping. For PDF, DOCX, Excel, and image files, please use the IRIS Scanner.');
    }
} catch (Throwable $e) {
    flash_redirect('admin/smart_upload.php', 'error', $e->getMessage());
}


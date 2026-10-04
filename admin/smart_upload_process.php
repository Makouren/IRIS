<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config/upload_limits.php';
require_once __DIR__ . '/../includes/upload/extractors.php';

require_admin();
if (!ALLOW_SUPER_ADMIN_UPLOAD) flash_redirect('admin/review_editor.php', 'error', 'File uploads are handled by office accounts.');
if (iris_upload_request_exceeded_post_limit()) {
    flash_redirect('admin/smart_upload.php', 'error', 'The upload request exceeds the server request limit. Keep the file at or below ' . iris_upload_limit_label() . ' and configure PHP post_max_size to at least 12M.');
}
verify_csrf();

$f = $_FILES['upload_file'] ?? null;
if (!$f) {
    flash_redirect('admin/smart_upload.php', 'error', 'Please choose a file.');
}
$uploadError = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    flash_redirect('admin/smart_upload.php', 'error', iris_upload_error_message($uploadError));
}
if ((int)($f['size'] ?? 0) < 1 || (int)$f['size'] > IRIS_MAX_UPLOAD_BYTES) {
    flash_redirect('admin/smart_upload.php', 'error', 'The file must be between 1 byte and ' . iris_upload_limit_label() . '.');
}
if (!isset($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
    flash_redirect('admin/smart_upload.php', 'error', 'The uploaded file could not be read. Please try again.');
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

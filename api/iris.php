<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/LatestYearResolver.php';
require_once __DIR__ . '/../includes/helpers/SummaryCardHistory.php';
require_once __DIR__ . '/../includes/helpers/RecordSheetMerge.php';
require_once __DIR__ . '/../includes/helpers/RecordFileHistory.php';
require_once __DIR__ . '/../includes/api/common.php';
// Authenticate before dispatch so a handler can never bypass the shared role boundary.
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$resource = $_GET['resource'] ?? '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
$recordId = $_GET['record_id'] ?? null;
if (in_array($action, ['preview-record-merge', 'merge-records'], true) && ($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Only a Super Admin can merge records.']);
    exit;
}
// Mutating requests must pass the admin gate and CSRF check before any resource handler runs.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    requireRole(['super_admin'], true);
    ensure_json_csrf();
}
if (($_SESSION['role'] ?? '') !== 'super_admin'
    && !in_array($resource, ['summary_cards', 'summary_card_history', 'summary_card_categories', 'field_colors'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    if ($resource === 'record_file_history') {
        require __DIR__ . '/../includes/api/handlers/record_file_history.php';
    }

    if ($resource === 'field_colors') {
        require __DIR__ . '/../includes/api/handlers/field_colors.php';
    }

    if ($resource === 'summary_card_categories') {
        require __DIR__ . '/../includes/api/handlers/summary_card_categories.php';
    }

    if ($resource === 'summary_card_history') {
        require __DIR__ . '/../includes/api/handlers/summary_card_history.php';
    }

    if ($resource === 'summary_cards') {
        require __DIR__ . '/../includes/api/handlers/summary_cards.php';
    }

    if ($resource === 'records') {
        require __DIR__ . '/../includes/api/handlers/records.php';
    }

    if ($resource === 'graphs') {
        require __DIR__ . '/../includes/api/handlers/graphs.php';
    }
    bad('Unknown API resource',404);
} catch(Throwable $e) { error_log($e->getMessage()); bad('Server error: '.$e->getMessage(),500); }

<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers/LatestYearResolver.php';
require_once __DIR__ . '/../../includes/helpers/TemplateImportSupport.php';
require_once __DIR__ . '/../../includes/helpers/SummaryCardImportService.php';
require_once __DIR__ . '/../../includes/helpers/ImportBatchAudit.php';
requireRole(['super_admin'], true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function summary_import_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') summary_import_fail('Method not allowed.', 405);
$expected = (string)($_SESSION['_csrf'] ?? '');
$provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) summary_import_fail('Invalid CSRF token.', 419);
$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) summary_import_fail('Invalid request body.');

TemplateImportSupport::response(static function () use ($data): array {
    $pdo = db();
    $recordId = trim((string)($data['record_id'] ?? ''));
    if ($recordId === '') throw new InvalidArgumentException('Record id is required.');
    $selectedSheet = isset($data['sheet_name']) ? (string)$data['sheet_name'] : null;
    $parsed = TemplateImportSupport::parse($pdo, $recordId, 'summary_cards', $selectedSheet);
    $action = (string)($data['action'] ?? 'preview');
    if ($action === 'preview') {
        return [
            'record' => ['id' => $recordId, 'file_name' => $parsed['record']['fileName']],
            'sheet_name' => $parsed['sheet']['name'],
            'rows' => SummaryCardImportService::preview($pdo, $parsed)
        ];
    }
    if ($action !== 'apply') throw new InvalidArgumentException('Unknown summary-card import action.');
    $selectedRows = $data['selected_rows'] ?? null;
    if (!is_array($selectedRows) || !array_is_list($selectedRows) || !$selectedRows) {
        throw new InvalidArgumentException('Select at least one Summary Card row to apply.');
    }
    $selectedCoordinates = [];
    foreach ($selectedRows as $selectedRow) {
        if (!is_array($selectedRow)) throw new InvalidArgumentException('Selected Summary Card rows are invalid.');
        $selectedSheet = trim((string)($selectedRow['sheet_name'] ?? ''));
        $selectedRowNumber = filter_var($selectedRow['row_number'] ?? null, FILTER_VALIDATE_INT);
        if ($selectedSheet === '' || $selectedRowNumber === false || $selectedRowNumber < 1) {
            throw new InvalidArgumentException('Select valid Summary Card rows to apply.');
        }
        $coordinate = json_encode([$selectedSheet, $selectedRowNumber], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (isset($selectedCoordinates[$coordinate])) throw new InvalidArgumentException('A Summary Card row was selected more than once.');
        $selectedCoordinates[$coordinate] = true;
    }
    $parsed['rows'] = array_values(array_filter($parsed['rows'], static function (array $row) use ($selectedCoordinates): bool {
        $coordinate = json_encode([(string)$row['sheet_name'], (int)$row['row_number']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return isset($selectedCoordinates[$coordinate]);
    }));
    if (count($parsed['rows']) !== count($selectedCoordinates)) {
        throw new InvalidArgumentException('One or more selected rows are not in the current worksheet. Refresh the preview and select rows again.');
    }
    return SummaryCardImportService::apply(
        $pdo,
        $parsed,
        is_array($data['row_versions'] ?? null) ? $data['row_versions'] : [],
        !empty($data['reviewed_diff']),
        (int)$_SESSION['user_id']
    );
});

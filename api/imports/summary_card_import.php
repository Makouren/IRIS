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

function summary_import_values(array $input, array $card, int $rowNumber): array
{
    $importKey = trim((string)($input['import_key'] ?? ''));
    $period = trim((string)($input['period_key'] ?? ''));
    if ($importKey === '' || strlen($importKey) > 100) throw new RuntimeException("Row {$rowNumber}: import_key is required and must not exceed 100 characters.");
    if ($period === '') throw new RuntimeException("Row {$rowNumber}: period_key is required for snapshot history.");
    try {
        $periodEnd = LatestYearResolver::periodEnd($period);
    } catch (RuntimeException $exception) {
        throw new RuntimeException("Row {$rowNumber}: {$exception->getMessage()}");
    }
    $allowed = ['main_value', 'secondary_value', 'year_date', 'main_label', 'secondary_label', 'description', 'secondary_description', 'info_text'];
    $values = [];
    foreach ($allowed as $field) {
        $incoming = trim((string)($input[$field] ?? ''));
        if ($incoming !== '') $values[$field] = $incoming;
    }
    foreach ($values as $field => $value) {
        $limit = in_array($field, ['description', 'secondary_description', 'info_text'], true) ? 65535 : 255;
        if (strlen($value) > $limit) throw new RuntimeException("Row {$rowNumber}: {$field} exceeds its storage limit.");
    }
    if (!isset($values['main_value']) && trim((string)($card['main_value'] ?? '')) === '') {
        throw new RuntimeException("Row {$rowNumber}: main_value is required for the selected summary card.");
    }
    return ['import_key' => $importKey, 'period_key' => $period, 'period_end' => $periodEnd, 'values' => $values];
}

function summary_import_card(PDO $pdo, string $importKey, bool $lock): ?array
{
    $sql = 'SELECT * FROM summary_cards WHERE import_key = ?' . ($lock ? ' FOR UPDATE' : '');
    $query = $pdo->prepare($sql);
    $query->execute([$importKey]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function summary_import_legacy_card(PDO $pdo, string $logicalKey, array $contentHashes, bool $lock): ?array
{
    foreach ($contentHashes as $contentHash) {
        $legacyKey = 'snapshot-' . substr(hash('sha256', (string)$contentHash . "\0" . $logicalKey), 0, 90);
        $card = summary_import_card($pdo, $legacyKey, $lock);
        if ($card) return $card;
    }
    return null;
}

function summary_import_snapshot(PDO $pdo, string $cardId, string $period, bool $lock): ?array
{
    $query = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE card_id = ? AND period_key = ?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$cardId, $period]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function summary_import_version(?array $card, ?array $snapshot): string
{
    return hash('sha256', json_encode([$card, $snapshot], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function summary_import_preview(PDO $pdo, array $parsed, bool $lock = false): array
{
    $result = [];
    $seen = [];
    $latestIndexes = LatestYearResolver::latestIndexes($parsed['rows']);
    $legacyContentHashes = null;
    $legacyCards = [];
    foreach ($parsed['rows'] as $inputIndex => $input) {
        $logicalKey = trim((string)($input['values']['import_key'] ?? ''));
        $mappedInput = $input['values'];
        $mappedInput['import_key'] = $logicalKey;
        $existingCard = summary_import_card($pdo, $mappedInput['import_key'], $lock);
        if (!$existingCard) {
            if ($legacyContentHashes === null) {
                $batches = $pdo->query("SELECT content_sha256 FROM import_batches WHERE destination = 'summary_cards' AND status = 'applied' ORDER BY id DESC");
                $legacyContentHashes = $batches->fetchAll(PDO::FETCH_COLUMN);
            }
            if (!array_key_exists($logicalKey, $legacyCards)) {
                $legacyCards[$logicalKey] = summary_import_legacy_card($pdo, $logicalKey, $legacyContentHashes, $lock);
            }
            $existingCard = $legacyCards[$logicalKey];
        }
        $card = $existingCard ?: ['main_value' => ''];
        $mapped = summary_import_values($mappedInput, $card, $input['row_number']);
        $key = hash('sha256', $mapped['import_key'] . "\0" . $mapped['period_key']);
        if (isset($seen[$key])) throw new RuntimeException("Sheet {$input['sheet_name']}, row {$input['row_number']}: duplicate card and period in the uploaded file.");
        $seen[$key] = true;
        $snapshot = $existingCard ? summary_import_snapshot($pdo, (string)$existingCard['id'], $mapped['period_key'], $lock) : null;
        $snapshotValues = array_replace([
            'main_value' => '',
            'secondary_value' => '',
            'year_date' => $mapped['period_key'],
            'description' => '',
            'secondary_description' => '',
            'info_text' => ''
        ], array_intersect_key($mapped['values'], array_flip(['main_value', 'secondary_value', 'year_date', 'description', 'secondary_description', 'info_text'])));
        $snapshotSame = $snapshot !== null;
        if ($snapshot) {
            foreach ($snapshotValues as $field => $value) {
                if ((string)($snapshot[$field] ?? '') !== (string)$value) $snapshotSame = false;
            }
        }
        $kind = !$snapshot ? 'insert' : ($snapshotSame ? 'unchanged' : 'replace');
        $currentFields = array_intersect_key($mapped['values'], array_flip(['main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text']));
        $currentChanged = !$existingCard;
        if ($existingCard) {
            foreach ($currentFields as $field => $value) {
                if ((string)($existingCard[$field] ?? '') !== (string)$value) $currentChanged = true;
            }
        }
        $result[] = [
            'key' => $key,
            'kind' => $kind,
            'import_key' => $logicalKey,
            'card_import_key' => $mapped['import_key'],
            'period_key' => $mapped['period_key'],
            'period_end' => $mapped['period_end'],
            'card_id' => $existingCard ? (string)$existingCard['id'] : null,
            'card_title' => $existingCard['title'] ?? $input['sheet_name'],
            'new_card' => !$existingCard,
            'current' => $existingCard,
            'current_changed' => $currentChanged,
            'incoming' => $mapped['values'],
            'snapshot_before' => $snapshot,
            'snapshot_after' => $snapshotValues,
            'current_eligible' => true,
            'suggested' => ($latestIndexes[$logicalKey] ?? null) === $inputIndex,
            'row_version' => summary_import_version($existingCard, $snapshot),
            'sheet_name' => $input['sheet_name'],
            'row_number' => $input['row_number']
        ];
    }
    usort($result, static fn(array $left, array $right): int => $right['period_end'] <=> $left['period_end']);
    return $result;
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

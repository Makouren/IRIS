<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers/LatestYearResolver.php';
require_once __DIR__ . '/../../includes/helpers/SummaryCardHistory.php';
require_once __DIR__ . '/../../includes/helpers/SummaryCardCategoryStorage.php';
requireRole(['super_admin'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function import_recovery_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function import_recovery_matches(array $current, array $expected, ?array $currentCategoryNames = null): bool
{
    foreach ($expected as $field => $value) {
        if ($field === 'updated_at') continue;
        if ($field === 'category_names') {
            if (!is_array($value) || !is_array($currentCategoryNames)) return false;
            $expectedNames = array_values($value);
            $actualNames = array_values($currentCategoryNames);
            sort($expectedNames, SORT_STRING);
            sort($actualNames, SORT_STRING);
            if ($expectedNames !== $actualNames) return false;
            continue;
        }
        if (!array_key_exists($field, $current) || (string)$current[$field] !== (string)$value) return false;
    }
    return true;
}

$expected = (string)($_SESSION['_csrf'] ?? '');
$provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') import_recovery_fail('Method not allowed.', 405);
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) import_recovery_fail('Invalid CSRF token.', 419);
$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) import_recovery_fail('Invalid request body.');
$batchId = filter_var($data['batch_id'] ?? null, FILTER_VALIDATE_INT);
if (!$batchId || $batchId < 1) import_recovery_fail('Import batch id is required.');

$pdo = db();
$pdo->beginTransaction();
try {
    $batchQuery = $pdo->prepare('SELECT * FROM import_batches WHERE batch_id = ? FOR UPDATE');
    $batchQuery->execute([$batchId]);
    $batch = $batchQuery->fetch(PDO::FETCH_ASSOC);
    if (!$batch) {
        $pdo->rollBack();
        import_recovery_fail('Import batch not found.', 404);
    }
    if ($batch['status'] !== 'applied') {
        $pdo->rollBack();
        import_recovery_fail('This import batch has already been reverted.');
    }

    $rowsQuery = $pdo->prepare('SELECT * FROM import_batch_rows WHERE batch_id = ? ORDER BY batch_row_id DESC FOR UPDATE');
    $rowsQuery->execute([$batchId]);
    $rows = $rowsQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $audit) {
        $sourceData = json_decode((string)($audit['source_data'] ?? ''), true);
        $before = is_array($sourceData) && is_array($sourceData['before'] ?? null) ? $sourceData['before'] : null;
        $after = is_array($sourceData) && is_array($sourceData['after'] ?? null) ? $sourceData['after'] : null;
        if (!is_array($after) || !in_array($audit['entity_type'], ['ranking', 'summary_card_snapshot', 'summary_card'], true)) {
            throw new RuntimeException('Import audit data is invalid; rollback was stopped.');
        }
        if ($audit['entity_type'] === 'ranking') {
            $id = (int)$audit['entity_id'];
            $fields = ['ranking_body_id', 'ranking_type_id', 'category_id', 'year', 'global_rank', 'global_rank_display', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_display', 'ph_rank_value', 'source'];
            if (CustomImportFields::columnExists($pdo, 'rankings')) $fields[] = 'custom_fields';
            $fields[] = 'seed_managed';
            $query = $pdo->prepare('SELECT ranking_id AS id, ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $fields)) . ' FROM rankings WHERE ranking_id = ? FOR UPDATE');
            $query->execute([$id]);
            $current = $query->fetch(PDO::FETCH_ASSOC);
            $expectedAfter = array_intersect_key($after, array_flip($fields));
            if (!$current || !import_recovery_matches($current, $expectedAfter)) {
                throw new RuntimeException('A ranking row changed after this batch. Rollback was stopped.');
            }
            if ($before === null) {
                $pdo->prepare('DELETE FROM rankings WHERE ranking_id = ?')->execute([$id]);
            } else {
                $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
                $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
                $pdo->prepare('UPDATE rankings SET ' . $sets . ' WHERE ranking_id = ?')->execute([...$values, $id]);
            }
            continue;
        }

        if ($audit['entity_type'] === 'summary_card_snapshot') {
            $snapshotId = (int)$audit['entity_id'];
            $query = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ? FOR UPDATE');
            $query->execute([$snapshotId]);
            $current = $query->fetch(PDO::FETCH_ASSOC);
            if (!$current || !import_recovery_matches($current, $after)) {
                throw new RuntimeException('A summary snapshot changed after this batch. Rollback was stopped.');
            }
            if ($before === null) {
                $pdo->prepare('DELETE FROM summary_card_snapshots WHERE snapshot_id = ?')->execute([$snapshotId]);
                $pdo->prepare('DELETE FROM summary_card_periods WHERE card_id = ? AND period_key = ?')->execute([$current['card_id'], $current['period_key']]);
            } else {
                $fields = ['title', 'period_label', 'period_sort', 'period_precision', 'is_published', 'main_value', 'main_label', 'secondary_label', 'secondary_value', 'year_date', 'description', 'secondary_description', 'info_text', 'source_info'];
                if (CustomImportFields::columnExists($pdo, 'summary_card_snapshots')) $fields[] = 'custom_fields';
                $fields = array_merge($fields, ['source_record_id', 'batch_id', 'last_source_record_id', 'last_batch_id']);
                $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
                $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
                $pdo->prepare('UPDATE summary_card_snapshots SET ' . $sets . ' WHERE snapshot_id = ?')->execute([...$values, $snapshotId]);
                $periodColumns = ['card_id', 'period_key', 'period_label', 'period_sort', 'period_precision', 'main_value', 'main_label', 'secondary_label', 'secondary_value', 'year_date', 'description', 'secondary_description', 'info_text', 'source_info'];
                $periodValues = [
                    $before['card_id'], $before['period_key'], $before['period_label'], $before['period_sort'], $before['period_precision'], $before['main_value'],
                    $before['main_label'], $before['secondary_label'], $before['secondary_value'], $before['year_date'], $before['description'],
                    $before['secondary_description'], $before['info_text'], $before['source_info']
                ];
                if (CustomImportFields::columnExists($pdo, 'summary_card_periods')) {
                    $periodColumns[] = 'custom_fields';
                    $periodValues[] = $before['custom_fields'] ?? null;
                }
                $periodColumns[] = 'is_published';
                $periodValues[] = $before['is_published'];
                $periodUpdates = array_values(array_filter($periodColumns, static fn(string $field): bool => !in_array($field, ['card_id', 'period_key'], true)));
                $periodSql = 'INSERT INTO summary_card_periods (' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $periodColumns)) . ')
                    VALUES (' . implode(', ', array_fill(0, count($periodColumns), '?')) . ')
                    ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = VALUES(`' . $field . '`)', $periodUpdates));
                $pdo->prepare($periodSql)->execute($periodValues);
            }
            SummaryCardHistory::syncLive($pdo, (string)$current['card_id']);
            continue;
        }

        $cardId = (int)$audit['entity_id'];
        $query = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
        $query->execute([$cardId]);
        $current = $query->fetch(PDO::FETCH_ASSOC);
        $currentCategoryNames = $current && array_key_exists('category_names', $after)
            ? SummaryCardCategoryStorage::names($pdo, $cardId)
            : null;
        if (!$current || !import_recovery_matches($current, $after, $currentCategoryNames)) {
            throw new RuntimeException('A summary card changed after this batch. Rollback was stopped.');
        }
        if ($before === null) {
            $pdo->prepare('DELETE FROM summary_cards WHERE card_id = ?')->execute([$cardId]);
            continue;
        }
        $fields = ['import_key', 'title', 'display_order', 'display_precision', 'is_published'];
        $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
        $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
        $pdo->prepare('UPDATE summary_cards SET ' . $sets . ' WHERE card_id = ?')->execute([...$values, $cardId]);
        if (array_key_exists('category_names', $before)) SummaryCardCategoryStorage::replace($pdo, $cardId, $before['category_names']);
    }
    $pdo->prepare("UPDATE import_batches SET status = 'reverted' WHERE batch_id = ?")->execute([$batchId]);
    $pdo->commit();
    echo json_encode(['success' => true, 'batch_id' => $batchId, 'restored_rows' => count($rows)]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($exception instanceof RuntimeException) import_recovery_fail($exception->getMessage(), 409);
    error_log('IRIS import recovery failed: ' . $exception->getMessage());
    import_recovery_fail('Unable to revert this import batch.', 500);
}

<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function import_recovery_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function import_recovery_matches(array $current, array $expected): bool
{
    foreach ($expected as $field => $value) {
        if ($field === 'updated_at') continue;
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
    $batchQuery = $pdo->prepare('SELECT * FROM import_batches WHERE id = ? FOR UPDATE');
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

    $rowsQuery = $pdo->prepare('SELECT * FROM import_batch_rows WHERE batch_id = ? ORDER BY id DESC FOR UPDATE');
    $rowsQuery->execute([$batchId]);
    $rows = $rowsQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $audit) {
        $before = $audit['before_state'] !== null ? json_decode((string)$audit['before_state'], true) : null;
        $after = json_decode((string)$audit['after_state'], true);
        if (!is_array($after) || ($audit['entity_type'] !== 'ranking' && $audit['entity_type'] !== 'summary_card')) {
            throw new RuntimeException('Import audit data is invalid; rollback was stopped.');
        }
        if ($audit['entity_type'] === 'ranking') {
            $id = (int)$audit['entity_id'];
            $query = $pdo->prepare('SELECT * FROM rankings WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $current = $query->fetch(PDO::FETCH_ASSOC);
            if (!$current || !import_recovery_matches($current, $after)) {
                throw new RuntimeException('A ranking row changed after this batch. Rollback was stopped.');
            }
            if ($before === null) {
                $pdo->prepare('DELETE FROM rankings WHERE id = ?')->execute([$id]);
            } else {
                $fields = ['ranking_body_id', 'scope_id', 'ranking_type', 'level', 'year', 'edition', 'category', 'global_rank', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_value', 'note', 'source', 'verification_status', 'seed_managed'];
                $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
                $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
                $pdo->prepare('UPDATE rankings SET ' . $sets . ' WHERE id = ?')->execute([...$values, $id]);
            }
            continue;
        }

        if (str_starts_with((string)$audit['entity_id'], 'snapshot:')) {
            [, $cardId, $period] = array_pad(explode(':', (string)$audit['entity_id'], 3), 3, '');
            if ($cardId === '' || $period === '') throw new RuntimeException('Snapshot audit key is invalid.');
            $query = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE card_id = ? AND period_key = ? FOR UPDATE');
            $query->execute([$cardId, $period]);
            $current = $query->fetch(PDO::FETCH_ASSOC);
            if (!$current || !import_recovery_matches($current, $after)) {
                throw new RuntimeException('A summary snapshot changed after this batch. Rollback was stopped.');
            }
            if ($before === null) {
                $pdo->prepare('DELETE FROM summary_card_snapshots WHERE id = ?')->execute([(int)$current['id']]);
            } else {
                $pdo->prepare('UPDATE summary_card_snapshots SET main_value = ?, secondary_value = ?, year_date = ?, description = ?, secondary_description = ?, info_text = ?, source_record_id = ?, batch_id = ? WHERE id = ?')->execute([
                    $before['main_value'], $before['secondary_value'], $before['year_date'], $before['description'] ?? null, $before['secondary_description'] ?? null, $before['info_text'] ?? null,
                    $before['source_record_id'], $before['batch_id'], (int)$current['id']
                ]);
            }
            continue;
        }

        $cardId = (string)$audit['entity_id'];
        $query = $pdo->prepare('SELECT * FROM summary_cards WHERE id = ? FOR UPDATE');
        $query->execute([$cardId]);
        $current = $query->fetch(PDO::FETCH_ASSOC);
        if (!$current || !import_recovery_matches($current, $after)) {
            throw new RuntimeException('A summary card changed after this batch. Rollback was stopped.');
        }
        if ($before === null) {
            $pdo->prepare('DELETE FROM summary_cards WHERE id = ?')->execute([$cardId]);
            continue;
        }
        $fields = ['import_key', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text'];
        $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
        $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
        $pdo->prepare('UPDATE summary_cards SET ' . $sets . ' WHERE id = ?')->execute([...$values, $cardId]);
    }
    $pdo->prepare("UPDATE import_batches SET status = 'reverted', reverted_at = NOW() WHERE id = ?")->execute([$batchId]);
    $pdo->commit();
    echo json_encode(['success' => true, 'batch_id' => $batchId, 'restored_rows' => count($rows)]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($exception instanceof RuntimeException) import_recovery_fail($exception->getMessage(), 409);
    error_log('IRIS import recovery failed: ' . $exception->getMessage());
    import_recovery_fail('Unable to revert this import batch.', 500);
}

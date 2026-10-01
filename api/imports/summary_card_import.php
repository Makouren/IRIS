<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers/LatestYearResolver.php';
require_once __DIR__ . '/../../includes/helpers/TemplateImportSupport.php';
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
    $parsed = TemplateImportSupport::parse($pdo, $recordId, 'summary_cards');
    if (($data['action'] ?? 'preview') === 'preview') {
        $pdo->beginTransaction();
        try {
            $rows = summary_import_preview($pdo, $parsed, true);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        return ['record' => ['id' => $recordId, 'file_name' => $parsed['record']['fileName']], 'sheet_name' => $parsed['sheet']['name'], 'rows' => $rows];
    }
    if (($data['action'] ?? '') !== 'apply') throw new InvalidArgumentException('Unknown summary-card import action.');
    $versions = $data['row_versions'] ?? [];
    $currentKeys = $data['current_keys'] ?? [];
    if (empty($data['reviewed_diff']) || !is_array($versions) || !is_array($currentKeys)) {
        throw new InvalidArgumentException('Review the diff and provide valid import selections.');
    }

    $pdo->beginTransaction();
    try {
        $rows = summary_import_preview($pdo, $parsed, true);
        foreach ($rows as $row) {
            if (!isset($versions[$row['key']]) || !hash_equals((string)$versions[$row['key']], $row['row_version'])) {
                throw new RuntimeException('The preview is stale because summary-card data changed. Preview the upload again.', 409);
            }
        }
        $rowByKey = array_column($rows, null, 'key');
        $labels = [];
        foreach ($rows as $row) $labels[$row['import_key']] = true;
        $currentRows = [];
        foreach ($currentKeys as $logicalKey => $rowKey) {
            $currentRow = is_string($rowKey) ? ($rowByKey[$rowKey] ?? null) : null;
            if (!$currentRow || $currentRow['import_key'] !== (string)$logicalKey || $currentRow['kind'] === 'blocked') {
                throw new InvalidArgumentException('A selected row does not match its Global Label. Refresh the preview.');
            }
            $currentRows[(string)$logicalKey] = $currentRow;
        }
        if (array_diff_key($labels, $currentRows) || array_diff_key($currentRows, $labels)) {
            throw new InvalidArgumentException('Select exactly one row per Global Label.');
        }
        $acceptedSet = [];
        foreach ($rows as $row) {
            if (in_array($row['kind'], ['insert', 'replace'], true)) $acceptedSet[$row['key']] = true;
        }
        $workByKey = [];
        foreach ($rows as $row) {
            if (isset($acceptedSet[$row['key']]) && in_array($row['kind'], ['insert', 'replace'], true)) $workByKey[$row['key']] = $row;
        }
        foreach ($currentRows as $currentRow) {
            if ($currentRow['new_card'] || $currentRow['current_changed'] || in_array($currentRow['kind'], ['insert', 'replace'], true)) {
                $workByKey[$currentRow['key']] = $currentRow;
            }
        }
        $work = array_values($workByKey);
        if (!$work) {
            $pdo->commit();
            return ['success' => true, 'inserted' => 0, 'updated' => 0, 'message' => 'No changes detected.'];
        }
        $inserted = count(array_filter($work, static fn(array $row): bool => $row['kind'] === 'insert'));
        $updated = count($work) - $inserted;
        $batchId = ImportBatchAudit::create($pdo, $parsed['record'], 'summary_cards', (int)$_SESSION['user_id'], $inserted, $updated);
        $newCardRows = array_filter($currentRows, static fn(array $row): bool => $row['new_card']);
        $createdCards = [];
        foreach ($newCardRows as $logicalKey => $row) {
            $values = $row['incoming'];
            $cardId = 'summary_snapshot_' . bin2hex(random_bytes(12));
            $insertCard = $pdo->prepare('INSERT INTO summary_cards (id, import_key, title, main_value, main_label, year_date, secondary_label, secondary_value, description, secondary_description, info_text, display_order, display_precision, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $insertCard->execute([
                $cardId,
                $row['card_import_key'],
                $row['sheet_name'],
                $values['main_value'] ?? $row['snapshot_after']['main_value'],
                $values['main_label'] ?? '',
                $values['year_date'] ?? $row['period_key'],
                $values['secondary_label'] ?? '',
                $values['secondary_value'] ?? '',
                $values['description'] ?? '',
                $values['secondary_description'] ?? '',
                $values['info_text'] ?? '',
                0,
                0
            ]);
            $createdCards[$logicalKey] = $cardId;
            $cardQuery = $pdo->prepare('SELECT * FROM summary_cards WHERE id = ?');
            $cardQuery->execute([$cardId]);
            $savedCard = $cardQuery->fetch(PDO::FETCH_ASSOC);
            ImportBatchAudit::row($pdo, $batchId, 'summary_card', $cardId, $row['sheet_name'], $row['row_number'], null, $savedCard);
        }

        foreach ($currentRows as $logicalKey => $row) {
            if (!$row['card_id'] || (string)($row['current']['import_key'] ?? '') === $logicalKey) continue;
            $cardQuery = $pdo->prepare('SELECT * FROM summary_cards WHERE id = ? FOR UPDATE');
            $cardQuery->execute([$row['card_id']]);
            $before = $cardQuery->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new RuntimeException('The selected summary card no longer exists. Refresh the preview.');
            $pdo->prepare('UPDATE summary_cards SET import_key = ? WHERE id = ?')->execute([$logicalKey, $row['card_id']]);
            $cardQuery->execute([$row['card_id']]);
            ImportBatchAudit::row($pdo, $batchId, 'summary_card', (string)$row['card_id'], $row['sheet_name'], $row['row_number'], $before, $cardQuery->fetch(PDO::FETCH_ASSOC));
        }

        foreach ($currentRows as $logicalKey => $row) {
            if (!$row['current_changed'] || $row['new_card']) continue;
            $cardId = (string)$row['card_id'];
            $currentCard = $row['current'];
            $previousPeriod = trim((string)($currentCard['year_date'] ?? '')) ?: 'previous-' . $batchId;
            if (strlen($previousPeriod) > 20) throw new RuntimeException('The existing card period is too long to archive.');
            if (!summary_import_snapshot($pdo, $cardId, $previousPeriod, true)) {
                $pdo->prepare('INSERT INTO summary_card_snapshots (card_id, period_key, main_value, secondary_value, year_date, description, secondary_description, info_text, source_record_id, batch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                    $cardId, $previousPeriod, $currentCard['main_value'], $currentCard['secondary_value'] ?? '', $currentCard['year_date'] ?? '', $currentCard['description'] ?? '', $currentCard['secondary_description'] ?? '', $currentCard['info_text'] ?? '', $recordId, $batchId
                ]);
                $snapshotId = (int)$pdo->lastInsertId();
                $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE id = ?');
                $snapshotQuery->execute([$snapshotId]);
                ImportBatchAudit::row($pdo, $batchId, 'summary_card', 'snapshot:' . $cardId . ':' . $previousPeriod, $row['sheet_name'], $row['row_number'], null, $snapshotQuery->fetch(PDO::FETCH_ASSOC));
            }
        }
        usort($work, static fn(array $left, array $right): int => $left['period_end'] <=> $right['period_end']);
        foreach ($work as $row) {
            $logicalKey = $row['import_key'];
            $cardId = $createdCards[$logicalKey] ?? $row['card_id'];
            if (!$cardId) throw new RuntimeException('A new snapshot card was not created. Preview the upload again.');
            $snapshotValues = $row['snapshot_after'];
            $isCurrentRow = ($currentRows[$row['import_key']]['key'] ?? null) === $row['key'];
            $storeSnapshot = (isset($acceptedSet[$row['key']]) && in_array($row['kind'], ['insert', 'replace'], true))
                || ($isCurrentRow && in_array($row['kind'], ['insert', 'replace'], true));
            if ($storeSnapshot) {
                $snapshotBefore = summary_import_snapshot($pdo, $cardId, $row['period_key'], true);
                if ($snapshotBefore) {
                    $same = true;
                    foreach ($snapshotValues as $field => $value) {
                        if ((string)($snapshotBefore[$field] ?? '') !== (string)$value) $same = false;
                    }
                    if (!$same) {
                        $pdo->prepare('UPDATE summary_card_snapshots SET main_value = ?, secondary_value = ?, year_date = ?, description = ?, secondary_description = ?, info_text = ?, source_record_id = ?, batch_id = ? WHERE id = ?')->execute([
                            $snapshotValues['main_value'], $snapshotValues['secondary_value'], $snapshotValues['year_date'], $snapshotValues['description'], $snapshotValues['secondary_description'], $snapshotValues['info_text'], $recordId, $batchId, (int)$snapshotBefore['id']
                        ]);
                        $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE id = ?');
                        $snapshotQuery->execute([(int)$snapshotBefore['id']]);
                        ImportBatchAudit::row($pdo, $batchId, 'summary_card', 'snapshot:' . $cardId . ':' . $row['period_key'], $row['sheet_name'], $row['row_number'], $snapshotBefore, $snapshotQuery->fetch(PDO::FETCH_ASSOC));
                    }
                } else {
                $pdo->prepare('INSERT INTO summary_card_snapshots (card_id, period_key, main_value, secondary_value, year_date, description, secondary_description, info_text, source_record_id, batch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                    $cardId, $row['period_key'], $snapshotValues['main_value'], $snapshotValues['secondary_value'], $snapshotValues['year_date'], $snapshotValues['description'], $snapshotValues['secondary_description'], $snapshotValues['info_text'], $recordId, $batchId
                ]);
                $snapshotId = (int)$pdo->lastInsertId();
                $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE id = ?');
                $snapshotQuery->execute([$snapshotId]);
                ImportBatchAudit::row($pdo, $batchId, 'summary_card', 'snapshot:' . $cardId . ':' . $row['period_key'], $row['sheet_name'], $row['row_number'], null, $snapshotQuery->fetch(PDO::FETCH_ASSOC));
                }
            }

            if ($isCurrentRow && !isset($createdCards[$row['import_key']]) && $row['current_changed']) {
                $currentValues = array_intersect_key($row['incoming'], array_flip(['main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text']));
                $sets = [];
                $params = [];
                foreach ($currentValues as $field => $value) { $sets[] = '`' . $field . '` = ?'; $params[] = $value; }
                if ($sets) {
                    $cardQuery = $pdo->prepare('SELECT * FROM summary_cards WHERE id = ? FOR UPDATE');
                    $cardQuery->execute([$cardId]);
                    $before = $cardQuery->fetch(PDO::FETCH_ASSOC);
                    $pdo->prepare('UPDATE summary_cards SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute([...$params, $cardId]);
                    $cardQuery->execute([$cardId]);
                    ImportBatchAudit::row($pdo, $batchId, 'summary_card', $cardId, $row['sheet_name'], $row['row_number'], $before, $cardQuery->fetch(PDO::FETCH_ASSOC));
                }
            }
        }
        $pdo->commit();
        return ['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'batch_id' => $batchId, 'message' => 'Selected summary-card rows were applied.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
});

<?php
declare(strict_types=1);

require_once __DIR__ . '/CustomImportFields.php';
require_once __DIR__ . '/LatestYearResolver.php';
require_once __DIR__ . '/SummaryCardCategoryStorage.php';
require_once __DIR__ . '/SummaryCardHistory.php';

final class ImportedRecordDataCleanup
{
    private static function matches(array $current, array $expected, ?array $currentCategoryNames = null): bool
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

    private static function restoreRanking(PDO $pdo, array $audit, ?array $before, array $after, bool $override = false): void
    {
        $id = (int)$audit['entity_id'];
        $fields = ['ranking_body_id', 'ranking_type_id', 'category_id', 'year', 'global_rank', 'global_rank_display', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_display', 'ph_rank_value', 'source', 'info_text'];
        if (CustomImportFields::columnExists($pdo, 'rankings')) $fields[] = 'custom_fields';
        $fields[] = 'seed_managed';
        $quotedFields = implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $fields));
        $query = $pdo->prepare('SELECT ranking_id AS id, ' . $quotedFields . ' FROM rankings WHERE ranking_id = ? FOR UPDATE');
        $query->execute([$id]);
        $current = $query->fetch(PDO::FETCH_ASSOC);
        if (!$override && (!$current || !self::matches($current, array_intersect_key($after, array_flip($fields))))) {
            throw new RuntimeException('A ranking row changed after its source upload. Delete was stopped to protect newer data.', 409);
        }
        if (!$current) return;
        if ($before === null) {
            $pdo->prepare('DELETE FROM rankings WHERE ranking_id = ?')->execute([$id]);
            return;
        }
        $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
        $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
        $pdo->prepare('UPDATE rankings SET ' . $sets . ' WHERE ranking_id = ?')->execute([...$values, $id]);
    }

    private static function restoreSnapshot(PDO $pdo, array $audit, ?array $before, array $after, bool $override = false): void
    {
        $snapshotId = (int)$audit['entity_id'];
        $query = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ? FOR UPDATE');
        $query->execute([$snapshotId]);
        $current = $query->fetch(PDO::FETCH_ASSOC);
        if (!$override && (!$current || !self::matches($current, $after))) {
            throw new RuntimeException('A Summary Card period changed after its source upload. Delete was stopped to protect newer data.', 409);
        }
        if (!$current) return;
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
    }

    private static function restoreCard(PDO $pdo, array $audit, ?array $before, array $after, bool $override = false): void
    {
        $cardId = (int)$audit['entity_id'];
        $query = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
        $query->execute([$cardId]);
        $current = $query->fetch(PDO::FETCH_ASSOC);
        $currentCategoryNames = $current && array_key_exists('category_names', $after)
            ? SummaryCardCategoryStorage::names($pdo, (string)$cardId)
            : null;
        if (!$override && (!$current || !self::matches($current, $after, $currentCategoryNames))) {
            throw new RuntimeException('A Summary Card changed after its source upload. Delete was stopped to protect newer data.', 409);
        }
        if (!$current) return;
        if ($before === null) {
            $pdo->prepare('DELETE FROM summary_cards WHERE card_id = ?')->execute([$cardId]);
            return;
        }
        $fields = ['import_key', 'title', 'display_order', 'display_precision', 'is_published'];
        $sets = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields));
        $values = array_map(static fn(string $field) => $before[$field] ?? null, $fields);
        $pdo->prepare('UPDATE summary_cards SET ' . $sets . ' WHERE card_id = ?')->execute([...$values, $cardId]);
        if (array_key_exists('category_names', $before)) SummaryCardCategoryStorage::replace($pdo, (string)$cardId, $before['category_names']);
    }

    public static function remove(PDO $pdo, int $recordId, bool $override = false): void
    {
        self::removeMany($pdo, [$recordId], $override);
    }

    public static function removeMany(PDO $pdo, array $recordIds, bool $override = false): void
    {
        if (!$pdo->inTransaction()) throw new LogicException('Imported data cleanup must run in the parent record transaction.');
        $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds), static fn(int $id): bool => $id > 0)));
        if (!$recordIds) return;
        $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
        $batchesQuery = $pdo->prepare("SELECT batch_id FROM import_batches WHERE source_record_id IN ($placeholders) AND status = 'applied' ORDER BY created_at DESC, batch_id DESC FOR UPDATE");
        $batchesQuery->execute($recordIds);
        $batchIds = array_map('intval', $batchesQuery->fetchAll(PDO::FETCH_COLUMN));
        $rowsQuery = $pdo->prepare('SELECT batch_row_id, entity_type, entity_id, source_data FROM import_batch_rows WHERE batch_id = ? ORDER BY batch_row_id DESC FOR UPDATE');
        $markReverted = $pdo->prepare("UPDATE import_batches SET status = 'reverted', reverted_at = NOW() WHERE batch_id = ?");
        foreach ($batchIds as $batchId) {
            $rowsQuery->execute([$batchId]);
            $rows = $rowsQuery->fetchAll(PDO::FETCH_ASSOC);
            $orderedRows = array_merge(
                array_values(array_filter($rows, static fn(array $row): bool => $row['entity_type'] === 'summary_card_snapshot')),
                array_values(array_filter($rows, static fn(array $row): bool => $row['entity_type'] !== 'summary_card_snapshot' && $row['entity_type'] !== 'summary_card')),
                array_values(array_filter($rows, static fn(array $row): bool => $row['entity_type'] === 'summary_card'))
            );
            foreach ($orderedRows as $audit) {
                $sourceData = json_decode((string)($audit['source_data'] ?? ''), true);
                $before = is_array($sourceData) && is_array($sourceData['before'] ?? null) ? $sourceData['before'] : null;
                $after = is_array($sourceData) && is_array($sourceData['after'] ?? null) ? $sourceData['after'] : null;
                if ($after === null) throw new RuntimeException('Import audit data is invalid; the parent upload was not deleted.', 409);
                if ($audit['entity_type'] === 'ranking') {
                    self::restoreRanking($pdo, $audit, $before, $after, $override);
                } elseif ($audit['entity_type'] === 'summary_card_snapshot') {
                    self::restoreSnapshot($pdo, $audit, $before, $after, $override);
                } elseif ($audit['entity_type'] === 'summary_card') {
                    self::restoreCard($pdo, $audit, $before, $after, $override);
                } else {
                    throw new RuntimeException('Import audit data contains an unsupported entity; the parent upload was not deleted.', 409);
                }
            }
            $markReverted->execute([$batchId]);
        }
    }
}

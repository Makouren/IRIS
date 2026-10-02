<?php
declare(strict_types=1);

final class ImportBatchAudit
{
    public static function create(PDO $pdo, array $record, string $destination, int $userId, int $inserted, int $updated): int
    {
        $query = $pdo->prepare('INSERT INTO import_batches (source_record_id, import_type, status, rows_processed, rows_inserted, rows_updated, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $recordId = filter_var($record['id'] ?? $record['record_id'] ?? null, FILTER_VALIDATE_INT);
        $query->execute([
            $recordId === false || $recordId === null || $recordId < 1 ? null : (int)$recordId,
            $destination,
            'applied',
            $inserted + $updated,
            $inserted,
            $updated,
            $userId
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function row(PDO $pdo, int $batchId, string $entityType, string $entityId, string $sheet, int $rowNumber, ?array $before, array $after): void
    {
        $query = $pdo->prepare('INSERT INTO import_batch_rows (batch_id, row_number, entity_type, entity_id, status, message, source_data) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $query->execute([
            $batchId,
            $rowNumber,
            $entityType,
            $entityId,
            'applied',
            'Sheet: ' . $sheet,
            json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

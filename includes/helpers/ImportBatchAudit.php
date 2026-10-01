<?php
declare(strict_types=1);

final class ImportBatchAudit
{
    public static function create(PDO $pdo, array $record, string $destination, int $userId, int $inserted, int $updated): int
    {
        $query = $pdo->prepare('INSERT INTO import_batches (created_by, template_id, source_record_id, destination, file_name, content_sha256, inserted_count, updated_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $templateId = filter_var($record['template_id'] ?? null, FILTER_VALIDATE_INT);
        $query->execute([
            $userId,
            $templateId === false || $templateId === null || $templateId < 1 ? null : (int)$templateId,
            (string)$record['id'],
            $destination,
            (string)$record['fileName'],
            (string)$record['sha256'],
            $inserted,
            $updated
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function row(PDO $pdo, int $batchId, string $entityType, string $entityId, string $sheet, int $rowNumber, ?array $before, array $after): void
    {
        $query = $pdo->prepare('INSERT INTO import_batch_rows (batch_id, entity_type, entity_id, sheet_name, source_row_number, before_state, after_state) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $query->execute([
            $batchId,
            $entityType,
            $entityId,
            $sheet,
            $rowNumber,
            $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
    }
}

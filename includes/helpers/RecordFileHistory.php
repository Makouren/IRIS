<?php

declare(strict_types=1);

final class RecordFileHistory
{
    public static function storeFile(string $sourcePath, string $storageRoot, string $originalName, string $fileType): array
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException('A referenced source file is unavailable for File History.');
        }
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv', 'tsv'], true)) {
            throw new RuntimeException('The source file type cannot be archived.');
        }
        $directory = self::historyDirectory($storageRoot, true);
        if ($directory === null) throw new RuntimeException('Private File History storage is unavailable.');

        $key = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $key;
        if (!copy($sourcePath, $destination)) throw new RuntimeException('Unable to archive a source file for File History.');
        chmod($destination, 0640);
        $size = filesize($destination);
        $hash = hash_file('sha256', $destination);
        if ($size === false || $hash === false) {
            @unlink($destination);
            throw new RuntimeException('Unable to verify an archived source file.');
        }
        $truncate = static fn(string $value, int $limit): string => function_exists('mb_substr')
            ? mb_substr($value, 0, $limit)
            : substr($value, 0, $limit);

        return [
            'key' => $key,
            'original_file_name' => $truncate($originalName, 255),
            'file_type' => $truncate($fileType, 100),
            'file_size' => $size,
            'file_sha256' => $hash,
        ];
    }

    public static function resolveFile(string $key, string $storageRoot): ?string
    {
        if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $key)) return null;
        $directory = self::historyDirectory($storageRoot, false);
        if ($directory === null) return null;
        $path = realpath($directory . DIRECTORY_SEPARATOR . $key);
        return $path && dirname($path) === $directory && is_file($path) ? $path : null;
    }

    public static function removeFile(?string $key, string $storageRoot): void
    {
        if (!is_string($key) || $key === '') return;
        $path = self::resolveFile($key, $storageRoot);
        if ($path && !@unlink($path)) error_log('IRIS could not remove an unreferenced File History snapshot: ' . $key);
    }

    public static function insertVersion(PDO $pdo, array $entry): int
    {
        $snapshot = json_encode($entry['snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $maxPacket = (int)$pdo->query('SELECT @@max_allowed_packet')->fetchColumn();
        if ($maxPacket > 0 && strlen($snapshot) > max(1024, $maxPacket - 16384)) {
            throw new RuntimeException("The record snapshot exceeds this database connection's packet limit; the merge was not applied.");
        }
        $query = $pdo->prepare('INSERT INTO record_file_history
            (merge_group_id, record_id, source_record_id, target_record_id, entry_type, merge_method,
             acting_super_admin_id, snapshot_format, snapshot_json, file_snapshot_key, original_file_name,
             file_type, file_size, file_sha256)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)');
        $query->execute([
            $entry['merge_group_id'],
            $entry['record_id'],
            $entry['source_record_id'],
            $entry['target_record_id'],
            $entry['entry_type'],
            $entry['merge_method'],
            $entry['acting_super_admin_id'],
            $snapshot,
            $entry['file']['key'] ?? null,
            $entry['file']['original_file_name'] ?? null,
            $entry['file']['file_type'] ?? null,
            $entry['file']['file_size'] ?? null,
            $entry['file']['file_sha256'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function listForRecord(PDO $pdo, int $recordId): array
    {
        $query = $pdo->prepare('SELECT version_id, merge_group_id, record_id, source_record_id, target_record_id,
                entry_type, merge_method, acting_super_admin_id, created_at, original_file_name, file_type,
                file_size, file_sha256
            FROM record_file_history
            WHERE record_id = ? OR source_record_id = ? OR target_record_id = ?
            ORDER BY created_at DESC, version_id DESC');
        $query->execute([$recordId, $recordId, $recordId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function loadVersion(PDO $pdo, int $versionId): ?array
    {
        $query = $pdo->prepare('SELECT * FROM record_file_history WHERE version_id = ?');
        $query->execute([$versionId]);
        $version = $query->fetch(PDO::FETCH_ASSOC);
        return $version ?: null;
    }

    private static function historyDirectory(string $storageRoot, bool $create): ?string
    {
        $root = realpath($storageRoot);
        if (!$root || !is_dir($root)) return null;
        $path = $root . DIRECTORY_SEPARATOR . 'history';
        if (!is_dir($path) && $create && !mkdir($path, 0750, true) && !is_dir($path)) return null;
        $directory = realpath($path);
        return $directory && dirname($directory) === $root && is_dir($directory) ? $directory : null;
    }
}

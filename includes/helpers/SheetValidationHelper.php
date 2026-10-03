<?php
declare(strict_types=1);

require_once __DIR__ . '/../upload_limits.php';

final class SheetValidationHelper
{
    public static function assertFile(string $path, string $extension): void
    {
        if (!in_array(strtolower($extension), ['xlsx', 'csv', 'tsv'], true)) {
            throw new RuntimeException('Server-side imports support CSV, TSV, and XLSX files.');
        }
        if (!is_file($path) || !is_readable($path)) throw new RuntimeException('The uploaded spreadsheet file is unavailable.');
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > IRIS_MAX_UPLOAD_BYTES) {
            throw new RuntimeException('The uploaded file is empty or exceeds the ' . iris_upload_limit_label() . ' import limit.');
        }
    }

    public static function assertHeaders(array $headers, callable $normalize): void
    {
        if (!$headers) throw new RuntimeException('The selected worksheet has no header row.');
        $seen = [];
        foreach ($headers as $header) {
            $key = $normalize($header);
            if ($key === '') throw new RuntimeException('The selected worksheet contains a blank column header.');
            if (isset($seen[$key])) throw new RuntimeException('The selected worksheet contains duplicate column headers.');
            $seen[$key] = true;
        }
    }
}
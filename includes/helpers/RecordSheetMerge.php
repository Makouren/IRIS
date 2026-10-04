<?php
/**
 * Purpose: Shared PHP helper for record sheet merge operations used by application workflows.
 */


declare(strict_types=1);

function record_merge_normalize_text(mixed $value): string
{
    $text = strtolower(trim((string)($value ?? '')));
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) $text = strtolower($transliterated);
    }
    $normalized = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
    return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
}

function record_merge_is_blank(mixed $value): bool
{
    return $value === null || (is_string($value) && trim($value) === '');
}

function record_merge_normalized_date(mixed $value): ?string
{
    if (!is_string($value) || !preg_match('/^(\d{4}-\d{2}-\d{2})(?:$|T)/', trim($value), $matches)) return null;
    $date = $matches[1];
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year) ? $date : null;
}

function record_merge_normalized_comparable(mixed $value): string
{
    $date = record_merge_normalized_date($value);
    if ($date !== null) return $date;
    if ((is_int($value) || is_float($value) || (is_string($value) && trim($value) !== '' && is_numeric(str_replace(',', '', trim($value)))))) {
        $number = (float)str_replace(',', '', (string)$value);
        if (is_finite($number)) return rtrim(rtrim(sprintf('%.15F', $number), '0'), '.') ?: '0';
    }
    return record_merge_normalize_text($value);
}

function record_merge_same_value(mixed $left, mixed $right): bool
{
    if (record_merge_is_blank($left) && record_merge_is_blank($right)) return true;
    $leftDate = record_merge_normalized_date($left);
    $rightDate = record_merge_normalized_date($right);
    if ($leftDate !== null || $rightDate !== null) return $leftDate === $rightDate;
    $leftNumeric = is_numeric(str_replace(',', '', trim((string)$left))) && !record_merge_is_blank($left);
    $rightNumeric = is_numeric(str_replace(',', '', trim((string)$right))) && !record_merge_is_blank($right);
    if ($leftNumeric && $rightNumeric) return (float)str_replace(',', '', (string)$left) === (float)str_replace(',', '', (string)$right);
    return trim((string)$left) === trim((string)$right);
}

function record_merge_numeric_stats(array $headers, array $rows): array
{
    $stats = [];
    foreach ($headers as $column => $header) {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if (is_int($value) || is_float($value)) {
                if (!is_nan((float)$value)) $values[] = (float)$value;
            }
        }
        if ($values && count($values) >= count($rows) * 0.4) {
            $sum = array_sum($values);
            $stats[(string)$header] = [
                'count' => count($values),
                'sum' => round($sum, 2),
                'min' => round(min($values), 2),
                'max' => round(max($values), 2),
                'avg' => round($sum / count($values), 2),
            ];
        }
    }
    return $stats;
}

/**
 * Merge source worksheet rows into the target using normalized composite keys.
 *
 * @param array $target Existing target sheet with headers and rows.
 * @param array $source Incoming source sheet with headers and rows.
 * @param array $keyColumns Zero-based target header indexes defining row identity.
 * @param bool $allowPartialHeaderOverlap Whether the established overlap threshold may be relaxed.
 * @return array Merged sheet, conflict details, and row/column statistics or validation error.
 * @side-effects None; returns a new sheet structure without mutating either input.
 */
function record_merge_sheet(array $target, array $source, array $keyColumns, bool $allowPartialHeaderOverlap = false): array
{
    $targetHeaders = $target['headers'] ?? null;
    $sourceHeaders = $source['headers'] ?? null;
    $targetRows = $target['rows'] ?? null;
    $sourceRows = $source['rows'] ?? null;
    if (!is_array($targetHeaders) || !is_array($sourceHeaders) || !is_array($targetRows) || !is_array($sourceRows)
        || !$targetHeaders || !$sourceHeaders || !$targetRows || !$sourceRows) {
        return ['error' => 'Both selected worksheets must have headers and data rows.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
    }
    foreach ([[$target, $targetRows], [$source, $sourceRows]] as [$sheet, $rows]) {
        if (!isset($sheet['rowCount']) || !is_int($sheet['rowCount'])
            || !in_array($sheet['rowCount'], [count($rows), count($rows) + 1], true)) {
            return ['error' => 'A worksheet row count does not match its data.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        }
    }

    $targetHeaderIndex = [];
    foreach ($targetHeaders as $index => $header) {
        if (!is_string($header) || trim($header) === '') return ['error' => 'The target worksheet contains an invalid header.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        $normalized = record_merge_normalize_text($header);
        if ($normalized === '' || array_key_exists($normalized, $targetHeaderIndex)) return ['error' => 'The target worksheet contains duplicate headers.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        $targetHeaderIndex[$normalized] = $index;
    }
    $sourceHeaderIndex = [];
    foreach ($sourceHeaders as $index => $header) {
        if (!is_string($header) || trim($header) === '') return ['error' => 'The source worksheet contains an invalid header.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        $normalized = record_merge_normalize_text($header);
        if ($normalized === '' || array_key_exists($normalized, $sourceHeaderIndex)) return ['error' => 'The source worksheet contains duplicate headers.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        $sourceHeaderIndex[$normalized] = $index;
    }
    $overlap = count(array_intersect_key($targetHeaderIndex, $sourceHeaderIndex));
    if ($allowPartialHeaderOverlap) {
        if ($overlap === 0) return ['error' => 'General worksheets need at least one shared header to match rows.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        $keyColumns = array_values(array_unique(array_filter(
            $keyColumns,
            static fn($column): bool => is_int($column)
                && isset($targetHeaders[$column])
                && isset($sourceHeaderIndex[record_merge_normalize_text($targetHeaders[$column])])
        )));
        if (!$keyColumns) {
            foreach ($targetHeaderIndex as $normalized => $column) {
                if (isset($sourceHeaderIndex[$normalized])) {
                    $keyColumns = [$column];
                    break;
                }
            }
        }
    } elseif ($overlap / count($targetHeaderIndex) < 0.5) {
        return ['error' => 'Fewer than half of the target headers match the source worksheet.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
    }
    if (!$keyColumns || count($keyColumns) !== count(array_unique($keyColumns))) return ['error' => 'Select at least one unique key column.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
    foreach ($keyColumns as $column) {
        if (!is_int($column) || $column < 0 || !isset($targetHeaders[$column])) return ['error' => 'A selected key column is invalid.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
        if (!isset($sourceHeaderIndex[record_merge_normalize_text($targetHeaders[$column])])) return ['error' => 'The source worksheet is missing a selected key column.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
    }
    foreach ([[$targetRows, count($targetHeaders)], [$sourceRows, count($sourceHeaders)]] as [$rows, $maxColumns]) {
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) > $maxColumns) return ['error' => 'A worksheet row has an invalid shape.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
            foreach ($row as $value) {
                if (!is_scalar($value) && $value !== null) return ['error' => 'A worksheet row contains an invalid cell value.', 'sheet' => null, 'conflicts' => [], 'unresolved' => 0];
            }
        }
    }

    $sourceKeyColumns = array_map(static fn(int $column): int => $sourceHeaderIndex[record_merge_normalize_text($targetHeaders[$column])], $keyColumns);
    // Normalize the composite key on both sheets so equivalent dates/numbers match despite formatting.
    $keyForRow = static function (array $row, array $columns): ?string {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if (record_merge_is_blank($value)) return null;
            $values[] = record_merge_normalized_comparable($value);
        }
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    };
    $targetMatches = [];
    foreach ($targetRows as $rowIndex => $row) {
        $key = $keyForRow($row, $keyColumns);
        if ($key !== null) $targetMatches[$key][] = $rowIndex;
    }
    $lastIncoming = [];
    foreach ($sourceRows as $rowIndex => $row) {
        $key = $keyForRow($row, $sourceKeyColumns);
        // Keep the last duplicate source row, matching the preview's deterministic conflict choice.
        if ($key !== null) $lastIncoming[$key] = $rowIndex;
    }

    $mergedRows = array_map(static function (array $row) use ($targetHeaders): array {
        return array_map(static fn(int $column): mixed => $row[$column] ?? '', array_keys($targetHeaders));
    }, $targetRows);
    $conflicts = [];
    $stats = [
        'inserted' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
        'duplicateIncoming' => count($sourceRows) - count($lastIncoming),
        'duplicateExisting' => 0,
        'ignoredColumns' => array_values(array_filter($sourceHeaders, static fn(string $header): bool => !isset($targetHeaderIndex[record_merge_normalize_text($header)]))),
        'updatedByColumn' => [],
        'rowStatus' => array_fill(0, count($sourceRows), 'skipped'),
    ];

    foreach ($sourceRows as $rowIndex => $row) {
        $key = $keyForRow($row, $sourceKeyColumns);
        if ($key === null || ($lastIncoming[$key] ?? null) !== $rowIndex) {
            $stats['skipped']++;
            continue;
        }
        $matches = $targetMatches[$key] ?? [];
        if (!$matches) {
            // A new key appends one row; existing keys update in place below.
            $added = [];
            foreach ($targetHeaders as $header) {
                $sourceColumn = $sourceHeaderIndex[record_merge_normalize_text($header)] ?? null;
                $added[] = $sourceColumn === null ? '' : ($row[$sourceColumn] ?? '');
            }
            $mergedRows[] = $added;
            $stats['inserted']++;
            $stats['rowStatus'][$rowIndex] = 'inserted';
            continue;
        }

        $stats['duplicateExisting'] += max(0, count($matches) - 1);
        $targetRowIndex = $matches[0];
        $changed = false;
        foreach ($targetHeaders as $column => $header) {
            if (in_array($column, $keyColumns, true)) continue;
            $sourceColumn = $sourceHeaderIndex[record_merge_normalize_text($header)] ?? null;
            if ($sourceColumn === null) continue;
            $sourceValue = $row[$sourceColumn] ?? null;
            $targetValue = $targetRows[$targetRowIndex][$column] ?? null;
            // Blank source cells are omissions, not instructions to erase target values.
            if (record_merge_is_blank($sourceValue) || record_merge_same_value($targetValue, $sourceValue)) continue;
            // For differing non-key values, merge semantics intentionally prefer the selected source.
            $mergedRows[$targetRowIndex][$column] = $sourceValue;
            $stats['updatedByColumn'][$header] = ($stats['updatedByColumn'][$header] ?? 0) + 1;
            $changed = true;
        }
        if ($changed) {
            $stats['updated']++;
            $stats['rowStatus'][$rowIndex] = 'updated';
        } else {
            $stats['unchanged']++;
            $stats['rowStatus'][$rowIndex] = 'unchanged';
        }
    }

    $headerless = isset($target['rowCount']) && $target['rowCount'] === count($targetRows);
    $sheet = array_merge($target, [
        'headers' => array_values($targetHeaders),
        'rows' => $mergedRows,
        'rowCount' => count($mergedRows) + ($headerless ? 0 : 1),
        'numericStats' => record_merge_numeric_stats($targetHeaders, $mergedRows),
    ]);
    return ['sheet' => $sheet, 'conflicts' => [], 'unresolved' => 0, 'stats' => $stats];
}

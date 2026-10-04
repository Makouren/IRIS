<?php
/**
 * Purpose: Shared PHP helper for import sheet reader operations used by application workflows.
 */

declare(strict_types=1);

require_once __DIR__ . '/SheetValidationHelper.php';

final class ImportSheetSelectionRequired extends RuntimeException
{
    public readonly array $candidates;
    public readonly ?string $selectedSheet;

    public function __construct(array $candidates, string $message = 'Choose the worksheet to preview.', ?string $selectedSheet = null)
    {
        $this->candidates = $candidates;
        $this->selectedSheet = $selectedSheet;
        parent::__construct($message);
    }
}

final class ImportSheetReader
{
    public static function read(string $path, string $extension, ?string $sheetSelector = null, ?int $headerRow = null): array
    {
        $extension = strtolower($extension);
        if ($headerRow !== null && ($headerRow < 1 || $headerRow > 10000)) throw new InvalidArgumentException('Header row must be between 1 and 10,000.');
        SheetValidationHelper::assertFile($path, $extension);
        if (in_array($extension, ['csv', 'tsv'], true)) {
            return [self::readDelimited($path, $extension === 'tsv' ? "\t" : ',', $headerRow)];
        }
        if ($extension === 'xlsx') {
            return self::readXlsx($path, $sheetSelector, $headerRow);
        }
        // ponytail: Native PHP handles CSV/TSV/XLSX here; binary XLS needs an installed spreadsheet library.
        throw new RuntimeException('Server-side imports support CSV, TSV, and XLSX files. Convert legacy XLS files to one of these formats.');
    }

    public static function selectSheet(array $sheets, ?string $selector, array $requiredAliases): array
    {
        if ($selector !== null && $selector !== '') {
            foreach ($sheets as $sheet) {
                if ($sheet['name'] === $selector) return $sheet;
            }
            throw new RuntimeException('The configured worksheet was not found in the uploaded file.');
        }
        if (count($sheets) === 1) return $sheets[0];
        $matches = [];
        foreach ($sheets as $sheet) {
            $headers = array_map([self::class, 'normalizeHeader'], $sheet['headers']);
            $groups = array_map(static fn($aliases): array => array_map([self::class, 'normalizeHeader'], is_array($aliases) ? $aliases : [$aliases]), $requiredAliases);
            $hasRequiredHeaders = true;
            foreach ($groups as $aliases) {
                if ($aliases === [] || !array_intersect($aliases, $headers)) {
                    $hasRequiredHeaders = false;
                    break;
                }
            }
            if ($hasRequiredHeaders) $matches[] = $sheet;
        }
        if (count($matches) === 1) return $matches[0];
        if (count($matches) === 0) {
            throw new InvalidArgumentException('No worksheet matches the configured template import mappings. Add the required header mappings for the canonical import fields.');
        }
        throw new ImportSheetSelectionRequired(array_values(array_map(static fn(array $sheet): string => (string)$sheet['name'], $matches)));
    }

    public static function normalizeHeader(mixed $value): string
    {
        $text = strtolower(trim((string)$value));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '');
    }

    private static function readDelimited(string $path, string $delimiter, ?int $headerRow = null): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('Unable to read the uploaded spreadsheet.');
        $rows = [];
        $sourceRow = 0;
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[++$sourceRow] = $values;
            if (count($rows) > 10001) {
                fclose($handle);
                throw new RuntimeException('The worksheet exceeds the 10,000-row import limit.');
            }
        }
        fclose($handle);
        if (!$rows) throw new RuntimeException('The uploaded worksheet is empty.');
        $selectedHeaderRow = $headerRow ?? array_key_first($rows);
        if (!isset($rows[$selectedHeaderRow])) throw new InvalidArgumentException('The selected header row is not present in the worksheet.');
        $headers = array_map(static fn($value): string => trim((string)$value), $rows[$selectedHeaderRow]);
        if (isset($headers[0])) $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        $dataRows = [];
        foreach ($rows as $number => $values) if ($number > $selectedHeaderRow) $dataRows[] = ['values' => array_values($values), 'row_number' => $number];
        return self::sheet(basename($path), $headers, $dataRows, $selectedHeaderRow);
    }

    private static function readXlsx(string $path, ?string $sheetSelector = null, ?int $headerRow = null): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('The PHP ZIP extension is required to read XLSX files.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('The XLSX file could not be opened.');
        try {
            $workbook = self::xml($zip, 'xl/workbook.xml');
            $relationships = self::xml($zip, 'xl/_rels/workbook.xml.rels');
            $targets = [];
            foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                $attributes = $relationship->attributes();
                $targets[(string)$attributes['Id']] = (string)$attributes['Target'];
            }
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $sharedXml = self::xml($zip, 'xl/sharedStrings.xml');
                foreach ($sharedXml->xpath('//*[local-name()="si"]') ?: [] as $item) {
                    $shared[] = self::textNodes($item);
                }
            }
            $sheets = [];
            foreach ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [] as $sheetNode) {
                $attributes = $sheetNode->attributes();
                if ($sheetSelector !== null && $sheetSelector !== '' && (string)$attributes['name'] !== $sheetSelector) continue;
                $relation = $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $target = $targets[(string)$relation['id']] ?? '';
                if ($target === '') continue;
                $entry = self::resolveZipPath('xl', $target);
                $xml = self::xml($zip, $entry);
                $grid = [];
                foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $rowNode) {
                    $rowAttributes = $rowNode->attributes();
                    $sourceRow = max(1, (int)($rowAttributes['r'] ?? 1));
                    $values = [];
                    foreach ($rowNode->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                        $cellAttributes = $cell->attributes();
                        $column = self::columnIndex((string)($cellAttributes['r'] ?? 'A1'));
                        $type = (string)($cellAttributes['t'] ?? '');
                        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
                        $value = isset($valueNodes[0]) ? (string)$valueNodes[0] : '';
                        if ($type === 's') $value = (string)($shared[(int)$value] ?? '');
                        elseif ($type === 'inlineStr') {
                            $inline = $cell->xpath('./*[local-name()="is"]') ?: [];
                            $value = self::textNodes($inline[0] ?? null);
                        }
                        elseif ($type === 'b') $value = $value === '1' ? 'TRUE' : 'FALSE';
                        $values[$column] = $value;
                    }
                    if ($values) {
                        $max = max(array_keys($values));
                        $grid[$sourceRow] = array_replace(array_fill(0, $max + 1, ''), $values);
                    }
                    if (count($grid) > 10001) throw new RuntimeException('The worksheet exceeds the 10,000-row import limit.');
                }
                if (!$grid) continue;
                ksort($grid);
                $first = $headerRow ?? array_key_first($grid);
                if (!isset($grid[$first])) continue;
                $headers = array_map(static fn($value): string => trim((string)$value), $grid[$first]);
                try {
                    SheetValidationHelper::assertHeaders($headers, [self::class, 'normalizeHeader']);
                } catch (InvalidArgumentException $exception) {
                    if ($headerRow !== null) continue;
                    throw $exception;
                }
                $rows = [];
                foreach ($grid as $number => $values) if ($number > $first) $rows[] = ['values' => $values, 'row_number' => $number];
                $sheets[] = ['name' => (string)$attributes['name'], 'headers' => $headers, 'rows' => $rows, 'header_row' => (int)$first];
            }
            if (!$sheets) throw new RuntimeException('No readable worksheet was found in the XLSX file.');
            return $sheets;
        } finally {
            $zip->close();
        }
    }

    private static function xml(ZipArchive $zip, string $entry): SimpleXMLElement
    {
        $content = $zip->getFromName($entry);
        if ($content === false) throw new RuntimeException('The XLSX workbook is missing a required XML part.');
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$xml) throw new RuntimeException('The XLSX workbook contains invalid XML.');
        return $xml;
    }

    private static function textNodes(?SimpleXMLElement $element): string
    {
        if (!$element) return '';
        $parts = [];
        foreach ($element->xpath('.//*[local-name()="t"]') ?: [] as $text) $parts[] = (string)$text;
        return implode('', $parts);
    }

    private static function resolveZipPath(string $base, string $target): string
    {
        $segments = str_starts_with($target, '/') ? [] : explode('/', $base);
        foreach (explode('/', ltrim($target, '/')) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') array_pop($segments);
            else $segments[] = $segment;
        }
        return implode('/', $segments);
    }

    private static function columnIndex(string $cellReference): int
    {
        preg_match('/^[A-Z]+/i', $cellReference, $match);
        $index = 0;
        foreach (str_split(strtoupper($match[0] ?? 'A')) as $letter) $index = $index * 26 + ord($letter) - 64;
        return $index - 1;
    }

    private static function sheet(string $name, array $headers, array $rows, int $headerRow = 1): array
    {
        SheetValidationHelper::assertHeaders($headers, [self::class, 'normalizeHeader']);
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (is_array($row) && array_key_exists('values', $row)) $normalized[] = ['values' => array_values($row['values']), 'row_number' => (int)($row['row_number'] ?? ($index + $headerRow + 1))];
            else $normalized[] = ['values' => array_values($row), 'row_number' => $index + $headerRow + 1];
        }
        return ['name' => $name, 'headers' => $headers, 'rows' => $normalized, 'header_row' => $headerRow];
    }
}

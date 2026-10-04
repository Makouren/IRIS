<?php
/**
 * Purpose: Shared PHP include for spreadsheet reader application behavior.
 * Included by: admin/upload_process.php and workbook import helpers.
 * Inputs/outputs: Parses a local file path and options; returns normalized workbook/sheet arrays.
 * Dependencies: ZipArchive, SimpleXML, and the PHP XML extensions.
 * Load order: Require the class before constructing SpreadsheetReader.
 */
final class SpreadsheetReader {
    private const MAX_FILE_BYTES = 10485760;
    private const MAX_UNCOMPRESSED_BYTES = 52428800;
    private const MAX_ZIP_ENTRIES = 4000;
    private const MAX_ROWS = 50000;
    private const MAX_COLUMNS = 512;
    private const MAX_OUTPUT_CELLS = 1000000;
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Dispatch a validated local workbook to its format-specific parser.
     *
     * @param string $path Readable temporary file path.
     * @param string $extension File extension without a leading dot.
     * @param string $fileName Original display name used in the result.
     * @param string|null $selectedSheet Optional worksheet name to parse.
     * @param int|null $headerRow Optional one-based row containing headers.
     * @return array Normalized workbook metadata and parsed sheets.
     */
    public function parse(string $path, string $extension, string $fileName, ?string $selectedSheet = null, ?int $headerRow = null): array {
        $extension = strtolower($extension);
        if ($headerRow !== null && ($headerRow < 1 || $headerRow > 10000)) throw new InvalidArgumentException('Header row must be between 1 and 10,000.');
        if (!is_file($path) || !is_readable($path)) throw new RuntimeException('The uploaded file is not readable.');
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_FILE_BYTES) throw new RuntimeException('The spreadsheet must be between 1 byte and 10 MB.');

        if ($extension === 'xlsx') {
            $signature = file_get_contents($path, false, null, 0, 4);
            if ($signature !== "PK\x03\x04") throw new RuntimeException('The file is not a valid XLSX workbook.');
            return $this->parseXlsx($path, $fileName, $selectedSheet, $headerRow);
        }
        if ($extension === 'csv' || $extension === 'tsv') return $this->parseDelimited($path, $extension, $fileName, $selectedSheet, $headerRow);
        if ($extension === 'xls') throw new RuntimeException('Legacy XLS files are not supported for automatic reading yet. Save this workbook as XLSX and upload it again.');
        throw new RuntimeException('This file type cannot be read automatically yet. Please upload .xlsx, .csv, or .tsv.');
    }

    private function parseXlsx(string $path, string $fileName, ?string $selectedSheet = null, ?int $headerRow = null): array {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('The XLSX ZIP package is corrupt or unreadable.');
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ZIP_ENTRIES) throw new RuntimeException('The XLSX package has an invalid number of ZIP entries.');
            $uncompressedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) throw new RuntimeException('The XLSX ZIP package contains an unreadable entry.');
                $uncompressedBytes += (int)($stat['size'] ?? 0);
                if ($uncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) throw new RuntimeException('The XLSX package expands beyond the 50 MB safety limit.');
            }

            $workbook = $this->loadXml($zip, 'xl/workbook.xml');
            $relationships = $this->loadXml($zip, 'xl/_rels/workbook.xml.rels');
            $sharedStrings = $this->readSharedStrings($zip);
            $dateStyles = $this->readDateStyles($zip);
            $date1904 = false;
            $workbookPropertyNodes = $this->xpathNodes($workbook, '/x:workbook/x:workbookPr');
            $workbookProperties = $workbookPropertyNodes[0] ?? null;
            if ($workbookProperties) {
                $date1904Value = strtolower((string)$workbookProperties['date1904']);
                $date1904 = in_array($date1904Value, ['1', 'true'], true);
            }

            $relationshipTargets = [];
            $relationshipNodes = $this->xpathNodes($relationships, '/p:Relationships/p:Relationship');
            foreach ($relationshipNodes as $relationship) {
                $id = (string)$relationship['Id'];
                $target = (string)$relationship['Target'];
                if ($id !== '' && $target !== '') $relationshipTargets[$id] = $this->resolveWorkbookTarget($target);
            }

            $sheets = [];
            $sheetNodes = $this->xpathNodes($workbook, '/x:workbook/x:sheets/x:sheet');
            if (!$sheetNodes) throw new RuntimeException('The XLSX workbook contains no sheets.');
            foreach ($sheetNodes as $sheetNode) {
                $name = trim((string)$sheetNode['name']);
                if ($selectedSheet !== null && $selectedSheet !== '' && $name !== $selectedSheet) continue;
                $relationshipId = (string)$sheetNode->attributes(self::REL_NS)['id'];
                $target = $relationshipTargets[$relationshipId] ?? null;
                if ($name === '' || !$target || $zip->locateName($target) === false) throw new RuntimeException('An XLSX sheet points to a missing worksheet file.');
                $worksheet = $this->loadXml($zip, $target);
                $matrix = $this->readWorksheet($worksheet, $sharedStrings, $dateStyles, $date1904);
                $hidden = in_array(strtolower((string)$sheetNode['state']), ['hidden', 'veryhidden'], true);
                $sheets[$name] = $this->makeSheet($name, $matrix, $hidden, $headerRow);
            }
            if (!$sheets) throw new RuntimeException('The XLSX workbook contains no readable sheets.');
            return $this->makeWorkbookResult($sheets, $fileName);
        } finally {
            $zip->close();
        }
    }

    private function loadXml(ZipArchive $zip, string $entry): SimpleXMLElement {
        $xml = $zip->getFromName($entry);
        if ($xml === false) throw new RuntimeException('The XLSX package is missing ' . $entry . '.');
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT | LIBXML_NOCDATA);
            if (!$parsed) throw new RuntimeException('The XLSX XML is malformed in ' . $entry . '.');
            $parsed->registerXPathNamespace('x', self::MAIN_NS);
            $parsed->registerXPathNamespace('r', self::REL_NS);
            $parsed->registerXPathNamespace('p', self::PACKAGE_REL_NS);
            return $parsed;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xpathNodes(SimpleXMLElement $node, string $expression): array {
        $node->registerXPathNamespace('x', self::MAIN_NS);
        $node->registerXPathNamespace('r', self::REL_NS);
        $node->registerXPathNamespace('p', self::PACKAGE_REL_NS);
        return $node->xpath($expression) ?: [];
    }

    /** Resolve a workbook relationship without allowing it to escape the XLSX ZIP root. */
    private function resolveWorkbookTarget(string $target): string {
        $target = rawurldecode(str_replace('\\', '/', $target));
        $parts = str_starts_with($target, '/') ? [] : ['xl'];
        foreach (explode('/', ltrim($target, '/')) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                if (!$parts) throw new RuntimeException('An XLSX relationship points outside the workbook package.');
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    private function readSharedStrings(ZipArchive $zip): array {
        if ($zip->locateName('xl/sharedStrings.xml') === false) return [];
        $root = $this->loadXml($zip, 'xl/sharedStrings.xml');
        $strings = [];
        foreach ($this->xpathNodes($root, '/x:sst/x:si') as $item) $strings[] = $this->richText($item);
        return $strings;
    }

    private function readDateStyles(ZipArchive $zip): array {
        if ($zip->locateName('xl/styles.xml') === false) return [];
        $root = $this->loadXml($zip, 'xl/styles.xml');
        $customFormats = [];
        foreach ($this->xpathNodes($root, '/x:styleSheet/x:numFmts/x:numFmt') as $format) $customFormats[(int)$format['numFmtId']] = (string)$format['formatCode'];
        $builtInDates = array_fill_keys(array_merge(range(14, 22), range(27, 36), range(45, 47), range(50, 58)), true);
        $dateStyles = [];
        $cellFormats = $this->xpathNodes($root, '/x:styleSheet/x:cellXfs/x:xf');
        foreach ($cellFormats as $styleIndex => $format) {
            $formatId = (int)$format['numFmtId'];
            if (isset($builtInDates[$formatId]) || $this->isCustomDateFormat($customFormats[$formatId] ?? '')) $dateStyles[(int)$styleIndex] = true;
        }
        return $dateStyles;
    }

    private function isCustomDateFormat(string $format): bool {
        if ($format === '') return false;
        $format = preg_replace('/"[^"]*"|\\\\.|\[[^\]]*\]/', '', $format) ?? '';
        if (preg_match('/[yd]/i', $format)) return true;
        return preg_match('/m{3,}/i', $format) === 1;
    }

    /** Read sparse XLSX rows while retaining their real column indexes and year values. */
    private function readWorksheet(SimpleXMLElement $worksheet, array $sharedStrings, array $dateStyles, bool $date1904): array {
        $sheetDataNodes = $this->xpathNodes($worksheet, '/x:worksheet/x:sheetData');
        if (!$sheetDataNodes) return [];
        $rowNodes = $this->xpathNodes($worksheet, '/x:worksheet/x:sheetData/x:row');
        $yearColumns = [];
        foreach ($rowNodes as $candidateRow) {
            $candidateHeaders = [];
            foreach ($this->xpathNodes($candidateRow, './x:c') as $cell) {
                $reference = strtoupper((string)$cell['r']);
                if (!preg_match('/^([A-Z]+)[0-9]+$/', $reference, $match)) continue;
                $column = $this->columnLettersToIndex($match[1]);
                $headerValue = $this->readCell($cell, $sharedStrings, [], $date1904);
                if (!$this->isEmptyCell($headerValue)) $candidateHeaders[$column] = $headerValue;
            }
            if (!$candidateHeaders) continue;
            foreach ($candidateHeaders as $column => $header) {
                if (is_string($header) && in_array(strtolower(trim($header)), ['year', 'yr', 'ranking year'], true)) $yearColumns[$column] = true;
            }
            break;
        }
        $matrix = [];
        $totalRows = 0;
        foreach ($rowNodes as $rowNode) {
            $row = [];
            foreach ($this->xpathNodes($rowNode, './x:c') as $cell) {
                $reference = strtoupper((string)$cell['r']);
                if (!preg_match('/^([A-Z]+)[0-9]+$/', $reference, $match)) continue;
                $column = $this->columnLettersToIndex($match[1]);
                if ($column >= 16384) throw new RuntimeException('The XLSX worksheet exceeds the supported column limit.');
                $value = $this->readCell($cell, $sharedStrings, $dateStyles, $date1904, isset($yearColumns[$column]));
                if (!$this->isEmptyCell($value)) $row[$column] = $value;
            }
            if (!$row) continue;
            ksort($row, SORT_NUMERIC);
            $matrix[max(1, (int)$rowNode['r'])] = $row;
            $totalRows++;
            if ($totalRows > self::MAX_ROWS) throw new RuntimeException('The spreadsheet exceeds the 50,000 row safety limit.');
        }
        return $matrix;
    }

    /** Convert a cell according to its XLSX type, shared-string index, and date style. */
    private function readCell(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles, bool $date1904, bool $preserveYear = false) {
        $type = (string)$cell['t'];
        if ($type === 'e') return '';
        if ($type === 'inlineStr') {
            $inlineNodes = $this->xpathNodes($cell, './x:is');
            return $this->richText($inlineNodes[0] ?? null);
        }
        $valueNodes = $this->xpathNodes($cell, './x:v');
        $valueNode = $valueNodes[0] ?? null;
        if (!$valueNode) return '';
        $value = (string)$valueNode;
        if ($type === 's') {
            $index = filter_var($value, FILTER_VALIDATE_INT);
            return $index !== false && isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
        }
        if ($type === 'str') return $value;
        if ($type === 'b') return $value === '1';
        if ($type === 'n' || $type === '') {
            $numeric = $this->toNumber($value);
            if ($numeric === null) return '';
            $styleIndex = (int)$cell['s'];
            if (isset($dateStyles[$styleIndex])) {
                if (!$preserveYear || !preg_match('/^\d{4}(?:\.0+)?$/', $value)) return $this->excelSerialToDate((float)$numeric, $date1904);
            }
            return $numeric;
        }
        return $value;
    }

    private function richText(?SimpleXMLElement $node): string {
        if (!$node) return '';
        $text = '';
        foreach ($this->xpathNodes($node, './x:t') as $run) $text .= (string)$run;
        foreach ($this->xpathNodes($node, './x:r/x:t') as $run) $text .= (string)$run;
        return $text;
    }

    private function excelSerialToDate(float $serial, bool $date1904): string {
        $days = (int)floor($serial);
        $origin = new DateTimeImmutable($date1904 ? '1904-01-01' : '1899-12-30');
        return $origin->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    private function columnLettersToIndex(string $letters): int {
        $index = 0;
        foreach (str_split($letters) as $letter) $index = $index * 26 + ord($letter) - 64;
        return $index - 1;
    }

    private function columnIndexToLetters(int $index): string {
        $letters = '';
        for ($value = $index + 1; $value > 0; $value = intdiv($value - 1, 26)) $letters = chr(65 + (($value - 1) % 26)) . $letters;
        return $letters;
    }

    private function toNumber(string $value) {
        if ($value === '' || !is_numeric($value)) return null;
        if (preg_match('/^[+-]?\d+$/', $value)) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer !== false) return $integer;
        }
        $number = (float)$value;
        return is_finite($number) ? $number : null;
    }

    private function isEmptyCell($value): bool {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** Parse CSV/TSV data into the same sheet shape returned by the XLSX parser. */
    private function parseDelimited(string $path, string $extension, string $fileName, ?string $selectedSheet = null, ?int $headerRow = null): array {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('The delimited file could not be opened.');
        try {
            $prefix = fread($handle, 3);
            if ($prefix !== "\xEF\xBB\xBF") rewind($handle);
            $sample = fgets($handle);
            if ($sample === false) throw new RuntimeException('The delimited file is empty.');
            $delimiter = $extension === 'tsv' ? "\t" : $this->detectDelimiter($sample);
            rewind($handle);
            if ($prefix === "\xEF\xBB\xBF") fseek($handle, 3);

            $matrix = [];
            $sourceRow = 0;
            while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $sourceRow++;
                $values = array_map(static fn($value) => trim((string)($value ?? '')), $values);
                if (!$values || count(array_filter($values, static fn($value) => $value !== '')) === 0) continue;
                $row = [];
                foreach ($values as $column => $value) {
                    if ($value !== '') $row[$column] = $this->toNumber($value) ?? $value;
                }
                if ($row) $matrix[$sourceRow] = $row;
                if (count($matrix) > self::MAX_ROWS + 1) throw new RuntimeException('The spreadsheet exceeds the 50,000 row safety limit.');
            }
        } finally {
            fclose($handle);
        }
        $sheetName = pathinfo($fileName, PATHINFO_FILENAME) ?: 'Sheet1';
        if ($selectedSheet !== null && $selectedSheet !== '' && $selectedSheet !== $sheetName) throw new RuntimeException('The configured worksheet was not found in the uploaded file.');
        return $this->makeWorkbookResult([$sheetName => $this->makeSheet($sheetName, $matrix, false, $headerRow)], $fileName);
    }

    private function detectDelimiter(string $sample): string {
        $bestDelimiter = ',';
        $bestCount = 1;
        foreach ([',', ';', "\t"] as $delimiter) {
            $count = count(str_getcsv($sample, $delimiter, '"', ''));
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }
        return $bestDelimiter;
    }

    /** Normalize a row matrix into headers, data rows, counts, and numeric summaries. */
    private function makeSheet(string $name, array $matrix, bool $hidden, ?int $headerRow = null): array {
        if (!$matrix) return ['name' => $name, 'hidden' => $hidden, 'rowCount' => 0, 'colCount' => 0, 'headers' => [], 'rows' => [], 'numericStats' => []];
        $rawRows = $matrix;
        $headerKey = $headerRow ?? array_key_first($matrix);
        if ($headerKey === null || !array_key_exists($headerKey, $matrix)) throw new RuntimeException('The selected header row is not present in the worksheet.');
        $firstRow = $matrix[$headerKey];
        $firstNonEmpty = array_filter($firstRow, fn($value) => !$this->isEmptyCell($value));
        $headerless = $headerRow === null && count($firstNonEmpty) > 0 && count(array_filter($firstNonEmpty, static fn($value) => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) === count($firstNonEmpty);
        $columnCount = 0;
        foreach ($matrix as $row) if ($row) $columnCount = max($columnCount, max(array_keys($row)) + 1);
        if ($columnCount > self::MAX_COLUMNS) throw new RuntimeException('The worksheet exceeds the 512 column safety limit.');
        $dataRowCount = max(0, count($matrix) - ($headerless ? 0 : 1));
        if (($dataRowCount + ($headerless ? 0 : 1)) * $columnCount > self::MAX_OUTPUT_CELLS) throw new RuntimeException('The worksheet exceeds the expanded cell safety limit.');
        $headers = [];
        $dataRows = [];
        if ($headerless) {
            $dataRows = array_values($matrix);
            for ($column = 0; $column < $columnCount; $column++) $headers[] = 'Column ' . $this->columnIndexToLetters($column);
        } else {
            foreach ($matrix as $number => $values) if ((int)$number > (int)$headerKey) $dataRows[] = $values;
            for ($column = 0; $column < $columnCount; $column++) {
                $header = $firstRow[$column] ?? '';
                $headers[] = $this->isEmptyCell($header) ? 'Column ' . $this->columnIndexToLetters($column) : (string)$header;
            }
        }

        $rows = [];
        foreach ($dataRows as $sourceRow) {
            $row = [];
            for ($column = 0; $column < $columnCount; $column++) $row[] = $sourceRow[$column] ?? '';
            if (count(array_filter($row, fn($value) => !$this->isEmptyCell($value))) === 0) continue;
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) throw new RuntimeException('The spreadsheet exceeds the 50,000 row safety limit.');
        }

        return [
            'name' => $name,
            'hidden' => $hidden,
            'rowCount' => count($rows) + ($headerless ? 0 : 1),
            'colCount' => $columnCount,
            'headers' => $headers,
            'rows' => $rows,
            'header_row' => $headerless ? null : (int)$headerKey,
            'rawRows' => array_map(static function (int|string $rowNumber, array $row) use ($columnCount): array {
                $values = [];
                for ($column = 0; $column < $columnCount; $column++) $values[] = $row[$column] ?? '';
                return ['row_number' => (int)$rowNumber, 'values' => $values];
            }, array_keys($rawRows), array_values($rawRows)),
            'numericStats' => $this->numericStats($headers, $rows),
        ];
    }

    private function numericStats(array $headers, array $rows): array {
        $stats = [];
        foreach ($headers as $column => $header) {
            $values = [];
            foreach ($rows as $row) if (is_int($row[$column] ?? null) || is_float($row[$column] ?? null)) $values[] = $row[$column];
            if ($values && count($values) >= count($rows) * 0.4) {
                $stats[$header] = [
                    'count' => count($values),
                    'sum' => array_sum($values),
                    'min' => min($values),
                    'max' => max($values),
                    'avg' => array_sum($values) / count($values),
                ];
            }
        }
        return $stats;
    }

    private function makeWorkbookResult(array $sheets, string $fileName): array {
        $hasContent = false;
        foreach ($sheets as $sheet) {
            if (!empty($sheet['headers']) || !empty($sheet['rows'])) { $hasContent = true; break; }
        }
        if (!$sheets || !$hasContent) throw new RuntimeException('The spreadsheet contains no readable worksheet data.');
        $sheetNames = array_keys($sheets);
        $totalRows = array_sum(array_map(static fn(array $sheet): int => (int)$sheet['rowCount'], $sheets));
        if ($totalRows > self::MAX_ROWS) throw new RuntimeException('The workbook exceeds the 50,000 total row safety limit.');
        return [
            'sheetsData' => $sheets,
            'rawText' => '',
            'metadata' => [
                'sheetCount' => count($sheets),
                'sheets' => $sheetNames,
                'totalRows' => $totalRows,
                'sourceFileName' => basename($fileName),
            ],
        ];
    }

    /**
     * Convert a parsed worksheet with year/category/rank columns into ranking rows.
     *
     * @param array $sheet Parsed sheet structure from SpreadsheetReader.
     * @return array|null Normalized ranking rows, or null when required values are absent.
     */
    function ranking_rows_from_sheet(array $sheet): ?array {
        $normalized = [];
        foreach (($sheet['headers'] ?? []) as $index => $header) {
            $key = strtolower(trim((string)$header));
            if (in_array($key, ['year', 'yr', 'ranking year'], true)) $normalized['year'] = $index;
            if (in_array($key, ['category', 'indicator', 'metric', 'item'], true)) $normalized['category'] = $index;
            if (in_array($key, ['rank', 'global rank', 'score', 'value'], true)) $normalized['rank_value'] = $index;
        }
        if (!isset($normalized['year'], $normalized['category'], $normalized['rank_value'])) return null;
        $result = [];
        foreach (($sheet['rows'] ?? []) as $row) {
            $result[] = [
                'year' => $row[$normalized['year']] ?? null,
                'category' => $row[$normalized['category']] ?? null,
                'rank_value' => $row[$normalized['rank_value']] ?? null,
            ];
        }
        return $result;
    }
}

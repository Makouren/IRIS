<?php
declare(strict_types=1);

require_once __DIR__ . '/../upload/SpreadsheetReader.php';
require_once __DIR__ . '/../config/upload_limits.php';
require_once __DIR__ . '/SummaryCardImportProfiles.php';
require_once __DIR__ . '/TemplateImportSupport.php';

final class ProfileWorkbookService
{
    private const DESTINATIONS = ['summary_cards', 'ranking_history'];

    public static function metadataReady(PDO $pdo): bool
    {
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?, ?, ?)');
        $query->execute(['template_import_profiles', 'workbook_file_path', 'workbook_original_filename', 'workbook_headers', 'workbook_header_row']);
        return (int)$query->fetchColumn() === 4;
    }

    public static function status(PDO $pdo, string $destination, ?int $profileId): array
    {
        if (!in_array($destination, self::DESTINATIONS, true)) throw new InvalidArgumentException('Choose a valid import destination.');
        $schemaReady = self::metadataReady($pdo);
        if (!$schemaReady) return [
            'profile_exists' => false,
            'built_in_profile_exists' => false,
            'schema_ready' => false,
            'custom_fields_ready' => CustomImportFields::storageReady($pdo),
            'ready' => false,
            'message' => 'Run the pending migrations.'
        ];
        $query = $pdo->prepare('SELECT import_profile_id FROM template_import_profiles WHERE template_id IS NULL AND destination = ? LIMIT 1');
        $query->execute([$destination]);
        $builtInId = $query->fetchColumn();
        $exists = false;
        if ($profileId !== null && $profileId > 0) {
            $profileQuery = $pdo->prepare('SELECT import_profile_id FROM template_import_profiles WHERE import_profile_id = ? AND destination = ?');
            $profileQuery->execute([$profileId, $destination]);
            $exists = (bool)$profileQuery->fetchColumn();
        }
        return [
            'profile_exists' => $builtInId !== false && $exists,
            'built_in_profile_exists' => $builtInId !== false,
            'schema_ready' => $schemaReady,
            'custom_fields_ready' => CustomImportFields::storageReady($pdo),
            'ready' => $builtInId !== false && $exists && $schemaReady,
            'message' => !$schemaReady ? 'Run the pending migrations.' : ($builtInId === false ? 'No built-in profile is configured.' : ($exists ? '' : 'Choose an available profile.'))
        ];
    }

    private static function storageRoot(): string
    {
        $path = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) throw new RuntimeException('Private workbook storage is unavailable.');
        $root = realpath($path);
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if (!$root || ($documentRoot && (strcasecmp($root, $documentRoot) === 0 || strncasecmp($root, $documentRoot . DIRECTORY_SEPARATOR, strlen($documentRoot) + 1) === 0))) {
            throw new RuntimeException('Workbook storage must be outside the web root.');
        }
        return $root;
    }

    private static function validateUpload(array $file): array
    {
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(iris_upload_error_message($uploadError));
        }
        if (!isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new InvalidArgumentException('The uploaded workbook could not be read. Please try again.');
        }
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) throw new InvalidArgumentException('Choose an XLSX, XLS, or CSV file.');
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > IRIS_MAX_UPLOAD_BYTES) throw new InvalidArgumentException('The workbook must be between 1 byte and ' . iris_upload_limit_label() . '.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
        $signature = file_get_contents((string)$file['tmp_name'], false, null, 0, 8) ?: '';
        $valid = match ($extension) {
            'csv' => in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)
                && !self::hasNullByte((string)$file['tmp_name']),
            'xls' => in_array($mime, ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/x-cfb', 'application/octet-stream'], true)
                && str_starts_with($signature, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'xlsx' => in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'], true)
                && str_starts_with($signature, "PK\x03\x04")
                && class_exists(ZipArchive::class)
                && self::validXlsx((string)$file['tmp_name']),
            default => false
        };
        if (!$valid) throw new InvalidArgumentException('The file contents do not match the selected spreadsheet type.');
        $name = basename((string)$file['name']);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'profile.' . $extension;
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 255, 'UTF-8') : substr($name, 0, 255);
        return [$extension, $name];
    }

    private static function hasNullByte(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) return true;
        while (!feof($handle)) {
            if (str_contains((string)fread($handle, 8192), "\0")) { fclose($handle); return true; }
        }
        fclose($handle);
        return false;
    }

    private static function validXlsx(string $path): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return false;
        $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();
        return $valid;
    }

    public static function upload(PDO $pdo, array $file, string $destination, int $profileId): array
    {
        if (!in_array($destination, self::DESTINATIONS, true)) throw new InvalidArgumentException('Choose a valid import destination.');
        if (!self::metadataReady($pdo)) throw new RuntimeException('Run the pending migrations.');
        SummaryCardImportProfiles::get($pdo, $profileId, false, $destination);
        [$extension, $originalName] = self::validateUpload($file);
        if (isset($_POST['original_filename']) && is_string($_POST['original_filename'])) {
            $submittedName = basename(str_replace('\\', '/', $_POST['original_filename']));
            $submittedName = preg_replace('/[\x00-\x1F\x7F]/u', '', $submittedName) ?: $originalName;
            $originalName = function_exists('mb_substr') ? mb_substr($submittedName, 0, 255, 'UTF-8') : substr($submittedName, 0, 255);
        }
        try {
            $parsed = (new SpreadsheetReader())->parse((string)$file['tmp_name'], $extension, $originalName);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException($exception->getMessage());
        }
        $token = bin2hex(random_bytes(24));
        $storedName = $token . '.' . $extension;
        $root = self::storageRoot();
        $path = $root . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file((string)$file['tmp_name'], $path)) throw new RuntimeException('Unable to store the uploaded workbook.');
        chmod($path, 0640);
        if (!isset($_SESSION['_profile_mapper_uploads']) || !is_array($_SESSION['_profile_mapper_uploads'])) $_SESSION['_profile_mapper_uploads'] = [];
        $_SESSION['_profile_mapper_uploads'][$token] = [
            'file' => $storedName,
            'destination' => $destination,
            'profile_id' => $profileId,
            'original_filename' => $originalName,
            'created_at' => time()
        ];
        self::cleanupExpiredUploads($root);
        return [
            'token' => $token,
            'original_filename' => $originalName,
            'sheets' => array_map(static function (array $sheet): array {
                $rawRows = $sheet['rawRows'] ?? [];
                $rowNumbers = array_map(static fn(array $row): int => (int)$row['row_number'], $rawRows);
                return ['name' => (string)$sheet['name'], 'max_row' => min(10000, max($rowNumbers ?: [1]))];
            }, array_values($parsed['sheetsData'] ?? []))
        ];
    }

    private static function cleanupExpiredUploads(string $root): void
    {
        if (!isset($_SESSION['_profile_mapper_uploads']) || !is_array($_SESSION['_profile_mapper_uploads'])) return;
        foreach ($_SESSION['_profile_mapper_uploads'] as $token => $item) {
            if (time() - (int)($item['created_at'] ?? 0) < 7200) continue;
            $file = (string)($item['file'] ?? '');
            if (preg_match('/^[a-f0-9]{48}\.(xlsx|xls|csv)$/', $file)) {
                $path = realpath($root . DIRECTORY_SEPARATOR . $file);
                if ($path && dirname($path) === $root && is_file($path)) @unlink($path);
            }
            unset($_SESSION['_profile_mapper_uploads'][$token]);
        }
    }

    private static function staged(PDO $pdo, string $token, string $destination, int $profileId): array
    {
        $item = $_SESSION['_profile_mapper_uploads'][$token] ?? null;
        if (!is_array($item) || ($item['destination'] ?? '') !== $destination || (int)($item['profile_id'] ?? 0) !== $profileId) {
            throw new RuntimeException('The mapper workbook expired. Upload it again.');
        }
        $root = self::storageRoot();
        $file = (string)($item['file'] ?? '');
        if (!preg_match('/^[a-f0-9]{48}\.(xlsx|xls|csv)$/', $file)) throw new RuntimeException('The mapper workbook is invalid.');
        $path = realpath($root . DIRECTORY_SEPARATOR . $file);
        if (!$path || dirname($path) !== $root || !is_file($path)) throw new RuntimeException('The mapper workbook is unavailable. Upload it again.');
        SummaryCardImportProfiles::get($pdo, $profileId, false, $destination);
        return [$item, $path, strtolower(pathinfo($file, PATHINFO_EXTENSION))];
    }

    public static function preview(PDO $pdo, string $token, string $destination, int $profileId, string $sheetName, int $headerRow): array
    {
        [$item, $path, $extension] = self::staged($pdo, $token, $destination, $profileId);
        if ($headerRow < 1 || $headerRow > 10000) throw new InvalidArgumentException('Choose a header row from 1 to 10,000.');
        $parsed = (new SpreadsheetReader())->parse($path, $extension, (string)$item['original_filename']);
        $sheets = array_values($parsed['sheetsData'] ?? []);
        $sheet = null;
        foreach ($sheets as $candidate) if ((string)$candidate['name'] === $sheetName) { $sheet = $candidate; break; }
        if (!$sheet) throw new InvalidArgumentException('Choose a worksheet from the uploaded workbook.');
        $rawRows = $sheet['rawRows'] ?? [];
        $headerValues = null;
        foreach ($rawRows as $row) if ((int)$row['row_number'] === $headerRow) { $headerValues = $row['values']; break; }
        if ($headerValues === null) throw new InvalidArgumentException('The selected header row is not present in the worksheet.');
        $headers = array_map(static fn($header): string => trim((string)$header), $headerValues);
        SheetValidationHelper::assertHeaders($headers, [ImportSheetReader::class, 'normalizeHeader']);
        $profile = SummaryCardImportProfiles::get($pdo, $profileId, false, $destination);
        if ($destination === 'ranking_history') $profile = TemplateImportSupport::normalizeRankingProfile($profile);
        $targets = array_values(array_unique(array_merge(array_keys($profile['mapping_rules']), array_keys($profile['header_aliases']))));
        foreach (array_keys($profile['custom_fields'] ?? []) as $key) $targets[] = 'custom_fields.' . $key;
        $targets = array_values(array_unique($targets));
        if ($destination === 'ranking_history') {
            $allowed = ['organization', 'ranking_type', 'year', 'global_rank', 'ph_rank', 'info_text'];
            $targets = array_values(array_filter($targets, static fn(string $field): bool => in_array($field, $allowed, true) || preg_match('/^custom_fields\\.[a-z][a-z0-9_]{0,47}$/', $field) === 1));
        } else {
            $allowed = array_values(array_unique(array_merge(['import_key', 'card_title', 'period_key', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text', 'source_info', 'category_names', 'display_precision'], $profile['identity_fields'] ?? [])));
            $targets = array_values(array_filter($targets, static fn(string $field): bool => in_array($field, $allowed, true) || preg_match('/^custom_fields\\.[a-z][a-z0-9_]{0,47}$/', $field) === 1));
        }
        $required = $destination === 'ranking_history'
            ? ['organization', 'ranking_type', 'year', 'global_rank']
            : array_values(array_unique(array_merge(['import_key', 'period_key', 'main_value', 'main_label'], $profile['identity_fields'] ?? [])));
        $suggestions = TemplateImportSupport::suggestFieldMappings($headers, $profile, $targets, $required);
        $samples = [];
        foreach ($rawRows as $row) {
            if ((int)$row['row_number'] <= $headerRow) continue;
            $samples[] = array_values($row['values']);
            if (count($samples) >= 3) break;
        }
        $_SESSION['_profile_mapper_uploads'][$token]['sheet'] = $sheetName;
        $_SESSION['_profile_mapper_uploads'][$token]['header_row'] = $headerRow;
        return [
            'sheet' => $sheetName,
            'header_row' => $headerRow,
            'headers' => $headers,
            'targets' => $targets,
            'required' => $required,
            'suggestions' => $suggestions,
            'samples' => $samples
        ];
    }

    public static function commit(PDO $pdo, string $token, string $destination, int $profileId, string $sheetName, int $headerRow): array
    {
        [$item, $path] = self::staged($pdo, $token, $destination, $profileId);
        if (($item['sheet'] ?? null) !== $sheetName || (int)($item['header_row'] ?? 0) !== $headerRow) {
            throw new InvalidArgumentException('Preview the selected worksheet and header row before saving.');
        }
        $headers = self::preview($pdo, $token, $destination, $profileId, $sheetName, $headerRow)['headers'];
        $root = self::storageRoot();
        $newFile = bin2hex(random_bytes(24)) . '.' . pathinfo((string)$item['file'], PATHINFO_EXTENSION);
        $newPath = $root . DIRECTORY_SEPARATOR . $newFile;
        if (!rename($path, $newPath)) throw new RuntimeException('Unable to attach the workbook to this profile.');
        return [
            'workbook_file_path' => $newFile,
            'workbook_original_filename' => (string)$item['original_filename'],
            'workbook_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'workbook_header_row' => $headerRow,
            'headers' => $headers,
            'old_file' => self::oldProfileFile($pdo, $profileId),
            'new_path' => $newPath,
            'staged_path' => $path,
            'token' => $token
        ];
    }

    private static function oldProfileFile(PDO $pdo, int $profileId): ?string
    {
        $query = $pdo->prepare('SELECT workbook_file_path FROM template_import_profiles WHERE import_profile_id = ?');
        $query->execute([$profileId]);
        $value = $query->fetchColumn();
        return $value === false || $value === null ? null : (string)$value;
    }

    public static function finishCommit(array $workbook): void
    {
        unset($_SESSION['_profile_mapper_uploads'][$workbook['token']]);
        if (!empty($workbook['old_file'])) self::deleteStored((string)$workbook['old_file']);
    }

    public static function abortCommit(array $workbook): void
    {
        if (!empty($workbook['new_path']) && is_file((string)$workbook['new_path'])) {
            if (!empty($workbook['staged_path'])) @rename((string)$workbook['new_path'], (string)$workbook['staged_path']);
            if (is_file((string)$workbook['new_path'])) @unlink((string)$workbook['new_path']);
        }
    }

    public static function deleteStored(string $fileName): void
    {
        if (!preg_match('/^[a-f0-9]{48}\.(xlsx|xls|csv)$/', $fileName)) return;
        $root = self::storageRoot();
        $path = realpath($root . DIRECTORY_SEPARATOR . $fileName);
        if ($path && dirname($path) === $root && is_file($path)) @unlink($path);
    }

    public static function profileWorkbook(PDO $pdo, string $destination): array
    {
        if (!self::metadataReady($pdo)) throw new RuntimeException('Run the pending migrations.');
        $profile = SummaryCardImportProfiles::active($pdo, $destination);
        $query = $pdo->prepare('SELECT workbook_file_path, workbook_original_filename, workbook_headers, workbook_header_row FROM template_import_profiles WHERE import_profile_id = ?');
        $query->execute([(int)$profile['id']]);
        $workbook = $query->fetch(PDO::FETCH_ASSOC) ?: [];
        return [$profile, $workbook];
    }

    public static function workbookPath(string $fileName): ?string
    {
        if (!preg_match('/^[a-f0-9]{48}\.(xlsx|xls|csv)$/', $fileName)) return null;
        $root = self::storageRoot();
        $path = realpath($root . DIRECTORY_SEPARATOR . $fileName);
        return $path && dirname($path) === $root && is_file($path) ? $path : null;
    }

    public static function savedWorkbookPreview(PDO $pdo, int $profileId, string $destination): ?array
    {
        if (!in_array($destination, self::DESTINATIONS, true)) throw new InvalidArgumentException('Choose a valid import destination.');
        if (!self::metadataReady($pdo)) return null;
        $profile = SummaryCardImportProfiles::get($pdo, $profileId, false, $destination);
        $fileName = (string)($profile['workbook_file_path'] ?? '');
        if ($fileName === '') return null;
        $path = self::workbookPath($fileName);
        if ($path === null) throw new RuntimeException('The saved profile workbook is unavailable.');
        $originalName = (string)($profile['workbook_original_filename'] ?? 'Workbook');
        $sheetName = trim((string)($profile['sheet_selector'] ?? ''));
        $headerRow = filter_var($profile['workbook_header_row'] ?? null, FILTER_VALIDATE_INT);
        if ($headerRow === false || $headerRow === null || $headerRow < 1) throw new RuntimeException('The saved workbook header row is invalid.');
        $parsed = (new SpreadsheetReader())->parse(
            $path,
            strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
            $originalName,
            $sheetName !== '' ? $sheetName : null,
            $headerRow
        );
        $sheet = $parsed['sheetsData'][$sheetName !== '' ? $sheetName : (array_key_first($parsed['sheetsData']) ?? '')] ?? null;
        if ($sheet === null) throw new RuntimeException('The saved workbook worksheet is unavailable.');
        $headers = array_slice($sheet['headers'], 0, 30);
        $rows = [];
        foreach ($sheet['rawRows'] as $row) {
            if ((int)$row['row_number'] <= $headerRow) continue;
            $rows[] = [
                'row_number' => (int)$row['row_number'],
                'values' => array_slice($row['values'], 0, 30)
            ];
            if (count($rows) >= 15) break;
        }
        return [
            'filename' => $originalName,
            'sheet' => (string)$sheet['name'],
            'header_row' => $headerRow,
            'headers' => $headers,
            'rows' => $rows,
            'total_columns' => (int)$sheet['colCount'],
            'truncated_columns' => (int)$sheet['colCount'] > count($headers)
        ];
    }

    public static function expectedHeaders(array $profile, string $destination = 'summary_cards'): array
    {
        if ($destination === 'ranking_history') $profile = TemplateImportSupport::normalizeRankingProfile($profile);
        $headers = [];
        $removed = ['scope', 'scope_id', 'level', 'level_id', 'edition', 'category', 'rank_low', 'rank_high', 'note', 'verification_status'];
        foreach ($profile['mapping_rules'] as $field => $header) {
            if ($destination === 'ranking_history' && in_array($field, $removed, true)) continue;
            if ($destination === 'summary_cards' && $field === 'display_order') continue;
            if (is_string($header) && trim($header) !== '') $headers[] = trim($header);
        }
        return array_values(array_unique($headers));
    }

    public static function assertHeadersMatch(array $actual, array $expected, string $destination): void
    {
        $normalize = [ImportSheetReader::class, 'normalizeHeader'];
        $normalizedActual = array_map($normalize, $actual);
        if (count(array_unique($normalizedActual)) !== count($normalizedActual)) {
            throw new InvalidArgumentException('The uploaded workbook contains duplicate column headers.');
        }
        $actualMap = [];
        foreach ($actual as $header) $actualMap[$normalize($header)] = (string)$header;
        $expectedMap = [];
        foreach ($expected as $header) $expectedMap[$normalize($header)] = (string)$header;
        $missing = array_values(array_map(static fn(string $key): string => $expectedMap[$key], array_diff(array_keys($expectedMap), array_keys($actualMap))));
        if ($missing) {
            $message = $destination === 'ranking_history' ? 'Ranking History' : 'Summary Cards';
            throw new InvalidArgumentException($message . ' workbook is missing active profile columns: ' . implode(', ', $missing) . '. Unmapped columns are ignored.');
        }
    }

    public static function createWorkbook(array $headers, string $sheetName, int $headerRow = 1): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('XLSX support is unavailable.');
        if ($headerRow < 1 || $headerRow > 10000) $headerRow = 1;
        $columnName = static function (int $column): string {
            $name = '';
            while ($column > 0) { $column--; $name = chr(65 + ($column % 26)) . $name; $column = intdiv($column, 26); }
            return $name;
        };
        $xmlCells = '';
        foreach ($headers as $index => $header) {
            $ref = $columnName($index + 1) . $headerRow;
            $xmlCells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars((string)$header, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
        }
        $sheetName = substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetName) ?: 'Template', 0, 31);
        $path = tempnam(sys_get_temp_dir(), 'iris-profile-template-');
        if ($path === false) throw new RuntimeException('Unable to create the template workbook.');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { @unlink($path); throw new RuntimeException('Unable to create the template workbook.'); }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $escapedSheetName = htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $escapedSheetName . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $rows = '';
        for ($number = 1; $number <= $headerRow; $number++) $rows .= '<row r="' . $number . '">' . ($number === $headerRow ? $xmlCells : '') . '</row>';
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $rows . '</sheetData></worksheet>');
        $zip->close();
        return $path;
    }
}
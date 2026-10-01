<?php
declare(strict_types=1);

require_once __DIR__ . '/ImportSheetReader.php';
require_once __DIR__ . '/SummaryCardImportProfiles.php';

final class TemplateImportSupport
{
    public static function record(PDO $pdo, string $recordId): array
    {
        $query = $pdo->prepare('SELECT id, fileName, fileType, template_id, import_profile_id, metadata FROM records WHERE id = ? LIMIT 1');
        $query->execute([$recordId]);
        $record = $query->fetch(PDO::FETCH_ASSOC);
        if (!$record) throw new RuntimeException('Upload record not found.');
        $metadata = json_decode((string)($record['metadata'] ?? ''), true);
        $stored = is_array($metadata) ? (string)($metadata['stored_file'] ?? '') : '';
        if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $stored)) {
            throw new RuntimeException('This record does not have a supported private spreadsheet file.');
        }
        $storage = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
        $root = realpath($storage);
        $path = $root ? realpath($root . DIRECTORY_SEPARATOR . $stored) : false;
        if (!$root || !$path || dirname($path) !== $root || !is_file($path)) {
            throw new RuntimeException('The uploaded spreadsheet file is unavailable.');
        }
        if (filesize($path) > 100 * 1024 * 1024) throw new RuntimeException('The uploaded file exceeds the 100 MB import limit.');
        $record['metadata'] = is_array($metadata) ? $metadata : [];
        $record['path'] = $path;
        $record['sha256'] = hash_file('sha256', $path);
        return $record;
    }

    public static function profile(PDO $pdo, int $templateId, string $destination): array
    {
        $query = $pdo->prepare('SELECT * FROM template_import_profiles WHERE template_id = ? AND destination = ? LIMIT 1');
        $query->execute([$templateId, $destination]);
        $profile = $query->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new RuntimeException('No import profile is configured for this template and destination.');
        foreach (['header_aliases', 'required_columns', 'mapping_rules', 'defaults_json'] as $field) {
            $value = json_decode((string)$profile[$field], true);
            if (!is_array($value)) throw new RuntimeException('The template import profile contains invalid configuration.');
            $profile[$field] = $value;
        }
        $identityFields = json_decode((string)($profile['identity_fields'] ?? ''), true);
        if ($identityFields === null) $identityFields = $destination === 'summary_cards' ? ['import_key'] : [];
        if (!is_array($identityFields) || !array_is_list($identityFields) || array_filter($identityFields, static fn($field): bool => !is_string($field))) {
            throw new RuntimeException('The template import profile contains invalid identity fields.');
        }
        $profile['identity_fields'] = $identityFields;
        return $profile;
    }

    private static function builtInSummaryProfile(): array
    {
        return [
            'sheet_selector' => null,
            'header_aliases' => [
                'import_key' => ['Main Descriptive Text', 'Global Label', 'Card Key', 'Import Key', 'Identifier'],
                'card_title' => ['Card Title', 'Metric Title', 'Summary Card Title'],
                'period_key' => ['Year', 'Period', 'Reporting Year', 'Year / Date'],
                'main_value' => ['Rank / Rank Bracket', 'Rank', 'Value', 'Main Value', 'Global Rank', 'Overall Rank'],
                'main_label' => ['Main Descriptive Text', 'Main Label', 'Headline', 'Label'],
                'year_date' => ['Year', 'Year / Date', 'Date', 'Reporting Year'],
                'secondary_label' => ['Secondary Label', 'Secondary Title'],
                'secondary_value' => ['Secondary Value', 'Secondary Metric'],
                'description' => ['Description', 'Main Description', 'Summary Description'],
                'secondary_description' => ['Second Description', 'Secondary Description', 'Italic Description', 'Supplementary Description'],
                'info_text' => ['Information Text', 'Information Text (ⓘ)', 'Information Text (i)', 'Info', 'Information', 'Info Text'],
                'source_info' => ['Source Information', 'Source', 'Source Info', 'Source Details']
            ],
            'required_columns' => ['import_key', 'period_key', 'main_value', 'main_label'],
            'identity_fields' => ['import_key'],
            'mapping_rules' => [
                'import_key' => 'Main Descriptive Text',
                'card_title' => 'Card Title',
                'period_key' => 'Year',
                'main_value' => 'Rank / Rank Bracket',
                'main_label' => 'Main Descriptive Text',
                'year_date' => 'Year',
                'secondary_label' => 'Secondary Label',
                'secondary_value' => 'Secondary Value',
                'description' => 'Description',
                'secondary_description' => 'Second Description',
                'info_text' => 'Information Text',
                'source_info' => 'Source Information'
            ],
            'defaults_json' => []
        ];
    }

    private static function builtInSummaryLegacyKey(string $mainLabel): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $mainLabel);
        $normalized = strtolower(trim($ascii === false ? $mainLabel : $ascii));
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '', '-');
        return 'snapshot-' . substr($slug !== '' ? $slug : hash('sha256', $mainLabel), 0, 88);
    }

    public static function resolveFieldIndexes(array $sheetHeaders, array $profile, array $requiredFields): array
    {
        $headerIndexes = [];
        foreach ($sheetHeaders as $index => $header) {
            $headerIndexes[ImportSheetReader::normalizeHeader((string)$header)] = $index;
        }
        $resolved = [];
        foreach ($requiredFields as $field) {
            $candidates = [];
            if (isset($profile['mapping_rules'][$field]) && is_string($profile['mapping_rules'][$field])) {
                $candidates[] = $profile['mapping_rules'][$field];
            }
            $aliases = $profile['header_aliases'][$field] ?? [];
            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    if (is_string($alias) && trim($alias) !== '') $candidates[] = $alias;
                }
            }
            $candidates[] = $field;
            $candidates = array_values(array_unique(array_filter(array_map(static fn($value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null, $candidates), static fn($value): bool => $value !== null)));
            $matchedIndex = null;
            foreach ($candidates as $candidate) {
                $normalized = ImportSheetReader::normalizeHeader($candidate);
                if (isset($headerIndexes[$normalized])) {
                    $matchedIndex = $headerIndexes[$normalized];
                    break;
                }
            }
            if ($matchedIndex === null) {
                throw new InvalidArgumentException("Required template field '{$field}' is not mapped to a worksheet column. Add a mapping in the import profile, for example 'Global Label -> import_key'.");
            }
            $resolved[$field] = $matchedIndex;
        }
        return $resolved;
    }

    private static function resolveProfileFieldIndexes(array $headers, array $profile, array $requiredFields, bool $includeOptional): array
    {
        $resolved = self::resolveFieldIndexes($headers, $profile, $requiredFields);
        if (!$includeOptional) return $resolved;
        $optional = array_values(array_unique(array_merge(array_keys($profile['mapping_rules'] ?? []), array_keys($profile['header_aliases'] ?? []))));
        foreach ($optional as $field) {
            if (isset($resolved[$field])) continue;
            try {
                $resolved += self::resolveFieldIndexes($headers, $profile, [$field]);
            } catch (InvalidArgumentException $exception) {
                continue;
            }
        }
        return $resolved;
    }

    public static function canonicalImportKey(array $values, array $identityFields, string $sheet, int $rowNumber): string
    {
        if (!$identityFields || !in_array('import_key', $identityFields, true)) {
            throw new InvalidArgumentException('Summary Card identity fields must include import_key.');
        }
        $identity = [];
        foreach ($identityFields as $field) {
            $value = trim((string)($values[$field] ?? ''));
            if ($value === '') throw new RuntimeException("Sheet {$sheet}, row {$rowNumber}: identity field '{$field}' is required.");
            $identity[$field] = $value;
        }
        if (count($identity) === 1 && isset($identity['import_key'])) return $identity['import_key'];
        ksort($identity, SORT_STRING);
        return 'identity-' . hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function identityPeriodKey(array $values, array $identityFields, string $periodKey, string $sheet, int $rowNumber): string
    {
        return hash('sha256', self::canonicalImportKey($values, $identityFields, $sheet, $rowNumber) . "\0" . $periodKey);
    }

    public static function validateUniqueIdentityPeriod(array &$seen, array $values, array $identityFields, string $periodKey, string $sheet, int $rowNumber): string
    {
        $key = self::identityPeriodKey($values, $identityFields, $periodKey, $sheet, $rowNumber);
        if (isset($seen[$key])) throw new RuntimeException("Sheet {$sheet}, row {$rowNumber}: duplicate configured Summary Card identity and period in the uploaded file.");
        $seen[$key] = true;
        return $key;
    }

    public static function normalizeCategoryNames(mixed $value): ?array
    {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('Categories must be a delimited list of names.');
        $text = trim((string)($value ?? ''));
        if ($text === '') return null;
        $names = [];
        foreach (explode(',', $text) as $name) {
            $name = trim($name);
            if ($name !== '') $names[] = $name;
        }
        return $names ?: null;
    }

    public static function normalizeDisplayPrecision(mixed $value): ?int
    {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('Display Precision must be a number from 0 to 2.');
        $text = trim((string)($value ?? ''));
        if ($text === '') return null;
        if (!preg_match('/^[0-2]$/', $text)) throw new InvalidArgumentException('Display Precision must be 0, 1, or 2.');
        return (int)$text;
    }

    public static function parse(PDO $pdo, string $recordId, string $destination, ?string $selectedSheet = null): array
    {
        $record = self::record($pdo, $recordId);
        $recordedPurpose = (string)($record['metadata']['upload_purpose'] ?? '');
        $builtInSummary = false;
        if (!empty($record['import_profile_id'])) {
            $profile = SummaryCardImportProfiles::get($pdo, (int)$record['import_profile_id']);
            if ($profile['destination'] !== $destination) throw new RuntimeException('This upload was submitted for a different destination than its import profile.');
        } elseif (!empty($record['template_id'])) {
            $profile = self::profile($pdo, (int)$record['template_id'], $destination);
        } elseif ($destination === 'summary_cards' && $recordedPurpose === 'summary_cards') {
            $profile = SummaryCardImportProfiles::active($pdo);
        } else {
            throw new RuntimeException('This destination requires an assigned import template.');
        }
        if ($recordedPurpose !== '' && $recordedPurpose !== $destination) {
            throw new RuntimeException('This upload was submitted for a different destination than the template is currently configured for.');
        }
        $sheets = ImportSheetReader::read($record['path'], (string)$record['fileType']);
        if (in_array(strtolower((string)$record['fileType']), ['csv', 'tsv'], true)) $sheets[0]['name'] = (string)$record['fileName'];
        $identityFields = $profile['identity_fields'] ?? ['import_key'];
        $required = array_values(array_unique(array_merge($profile['required_columns'], $identityFields)));
        $requiredAliases = [];
        foreach ($required as $field) {
            $aliases = $profile['header_aliases'][$field] ?? [];
            $rule = $profile['mapping_rules'][$field] ?? null;
            $requiredAliases[] = array_values(array_unique(array_merge(is_string($rule) ? [$rule] : [], is_array($aliases) ? $aliases : [$field])));
        }
        $profileSheet = trim((string)($profile['sheet_selector'] ?? ''));
        $sheetSelector = $profileSheet !== '' ? $profileSheet : (trim((string)$selectedSheet) ?: null);
        $candidateSheets = [];
        foreach ($sheets as $candidate) {
            try {
                $candidateIndexMap = self::resolveProfileFieldIndexes($candidate['headers'], $profile, $required, $destination === 'summary_cards');
                $candidateSheets[] = ['sheet' => $candidate, 'fieldIndexes' => $candidateIndexMap];
            } catch (InvalidArgumentException $exception) {
                continue;
            }
        }
        if ($sheetSelector !== null && $sheetSelector !== '') {
            $sheet = null;
            foreach ($sheets as $candidate) {
                if ($candidate['name'] === $sheetSelector) {
                    $sheet = $candidate;
                    break;
                }
            }
            if ($sheet === null) throw new RuntimeException('The configured worksheet was not found in the uploaded file.');
            try {
                $fieldIndexes = self::resolveProfileFieldIndexes($sheet['headers'], $profile, $required, $destination === 'summary_cards');
            } catch (InvalidArgumentException $exception) {
                throw $exception;
            }
        } else {
            if (count($candidateSheets) === 1) {
                $sheet = $candidateSheets[0]['sheet'];
                $fieldIndexes = $candidateSheets[0]['fieldIndexes'];
            } elseif (count($candidateSheets) > 1) {
                throw new ImportSheetSelectionRequired(array_values(array_map(static fn(array $item): string => (string)$item['sheet']['name'], $candidateSheets)));
            } else {
                throw new InvalidArgumentException('No worksheet matches the configured import mappings. Add the required canonical field mappings in the import profile.');
            }
        }
        $rows = [];
        foreach ($sheet['rows'] as $row) {
            $mapped = [];
            $legacyImportKey = null;
            foreach ($fieldIndexes as $field => $index) {
                $rawValue = (string)($row['values'][$index] ?? '');
                if ($field === 'category_names') {
                    $categoryNames = self::normalizeCategoryNames($rawValue);
                    if ($categoryNames !== null) $mapped[$field] = $categoryNames;
                    continue;
                }
                if ($field === 'display_precision') {
                    $displayPrecision = self::normalizeDisplayPrecision($rawValue);
                    if ($displayPrecision !== null) $mapped[$field] = $displayPrecision;
                    continue;
                }
                $mapped[$field] = $field === 'import_key' ? $rawValue : trim($rawValue);
            }
            if ($builtInSummary && !empty($mapped['import_key']) && !empty($mapped['main_label'])) {
                $legacyImportKey = self::builtInSummaryLegacyKey($mapped['main_label']);
            }
            if (!array_filter($mapped, static fn($value): bool => $value !== '')) continue;
            $rows[] = ['values' => array_replace($profile['defaults_json'], $mapped), 'legacy_import_key' => $legacyImportKey, 'sheet_name' => $sheet['name'], 'row_number' => (int)$row['row_number']];
        }
        if (!$rows) throw new RuntimeException('No non-empty import rows were found in the selected worksheet.');
        return ['record' => $record, 'profile' => $profile, 'sheet' => $sheet, 'field_indexes' => $fieldIndexes, 'rows' => $rows, 'built_in' => $builtInSummary];
    }

    public static function response(callable $callback): never
    {
        try {
            $result = $callback();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (ImportSheetSelectionRequired $exception) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['requires_sheet_selection' => true, 'candidate_sheets' => $exception->candidates, 'selected_sheet' => $exception->selectedSheet, 'error' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            http_response_code(in_array($exception->getCode(), [409, 422], true) ? $exception->getCode() : 422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            error_log('IRIS template import failed: ' . $exception->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unable to process the template import.']);
        }
        exit;
    }
}

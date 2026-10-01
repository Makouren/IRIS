<?php
declare(strict_types=1);

require_once __DIR__ . '/ImportSheetReader.php';

final class TemplateImportSupport
{
    public static function record(PDO $pdo, string $recordId): array
    {
        $query = $pdo->prepare('SELECT id, fileName, fileType, template_id, metadata FROM records WHERE id = ? LIMIT 1');
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
        return $profile;
    }

    private static function builtInSummaryProfile(): array
    {
        return [
            'sheet_selector' => null,
            'header_aliases' => [
                'period_key' => ['Period', 'Year', 'Year / Date'],
                'main_value' => ['Rank / Rank Bracket', 'Rank', 'Value', 'Main Value', 'Global Rank'],
                'main_label' => ['Main Descriptive Text', 'Main Label'],
                'year_date' => ['Year', 'Year / Date', 'Date'],
                'secondary_label' => ['Secondary Label'],
                'secondary_value' => ['Secondary Value'],
                'description' => ['Description', 'Main Description'],
                'secondary_description' => ['Second Description', 'Italic Description', 'Secondary Description'],
                'info_text' => ['Information Text (ⓘ)', 'Information Text (i)', 'Info', 'Information', 'Info Text']
            ],
            'required_columns' => ['period_key', 'main_value', 'main_label'],
            'mapping_rules' => [
                'period_key' => 'Year',
                'main_value' => 'Rank / Rank Bracket',
                'main_label' => 'Main Descriptive Text',
                'year_date' => 'Year',
                'secondary_label' => 'Secondary Label',
                'secondary_value' => 'Secondary Value',
                'description' => 'Description',
                'secondary_description' => 'Second Description',
                'info_text' => 'Information Text (ⓘ)'
            ],
            'defaults_json' => []
        ];
    }

    private static function builtInSummaryKey(string $mainLabel): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $mainLabel);
        $normalized = strtolower(trim($ascii === false ? $mainLabel : $ascii));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $slug = trim($slug, '-');
        return 'snapshot-' . substr($slug !== '' ? $slug : hash('sha256', $mainLabel), 0, 88);
    }

    public static function parse(PDO $pdo, string $recordId, string $destination): array
    {
        $record = self::record($pdo, $recordId);
        $recordedPurpose = (string)($record['metadata']['upload_purpose'] ?? '');
        $builtInSummary = false;
        if (empty($record['template_id'])) {
            if ($destination !== 'summary_cards' || $recordedPurpose !== 'summary_cards') {
                throw new RuntimeException('This destination requires an assigned import template.');
            }
            $profile = self::builtInSummaryProfile();
            $builtInSummary = true;
        } else {
            $profile = self::profile($pdo, (int)$record['template_id'], $destination);
        }
        if ($recordedPurpose !== '' && $recordedPurpose !== $destination) {
            throw new RuntimeException('This upload was submitted for a different destination than the template is currently configured for.');
        }
        $sheets = ImportSheetReader::read($record['path'], (string)$record['fileType']);
        if (in_array(strtolower((string)$record['fileType']), ['csv', 'tsv'], true)) $sheets[0]['name'] = (string)$record['fileName'];
        $required = array_values(array_unique($profile['required_columns']));
        $requiredAliases = [];
        foreach ($required as $field) {
            $aliases = $profile['header_aliases'][$field] ?? [];
            $rule = $profile['mapping_rules'][$field] ?? null;
            $requiredAliases[] = array_values(array_unique(array_merge(is_string($rule) ? [$rule] : [], is_array($aliases) ? $aliases : [$field])));
        }
        $sheet = ImportSheetReader::selectSheet($sheets, $profile['sheet_selector'] ?: null, $requiredAliases);
        $headerIndexes = [];
        foreach ($sheet['headers'] as $index => $header) {
            $headerIndexes[ImportSheetReader::normalizeHeader($header)] = $index;
        }
        $fieldIndexes = [];
        foreach ($profile['mapping_rules'] as $field => $rule) {
            $candidateHeaders = is_string($rule) ? [$rule] : [];
            $candidateHeaders = array_merge($candidateHeaders, is_array($profile['header_aliases'][$field] ?? null) ? $profile['header_aliases'][$field] : [$field]);
            foreach ($candidateHeaders as $candidate) {
                $key = ImportSheetReader::normalizeHeader($candidate);
                if (isset($headerIndexes[$key])) {
                    $fieldIndexes[$field] = $headerIndexes[$key];
                    break;
                }
            }
        }
        foreach ($profile['required_columns'] as $field) {
            if (!array_key_exists($field, $fieldIndexes)) throw new RuntimeException('Required template column is missing: ' . $field);
        }
        $rows = [];
        foreach ($sheet['rows'] as $row) {
            $mapped = [];
            foreach ($fieldIndexes as $field => $index) $mapped[$field] = trim((string)($row['values'][$index] ?? ''));
            if ($builtInSummary && empty($mapped['import_key']) && !empty($mapped['main_label'])) {
                $mapped['import_key'] = self::builtInSummaryKey($mapped['main_label']);
            }
            if (!array_filter($mapped, static fn($value): bool => $value !== '')) continue;
            $rows[] = ['values' => array_replace($profile['defaults_json'], $mapped), 'sheet_name' => $sheet['name'], 'row_number' => (int)$row['row_number']];
        }
        if (!$rows) throw new RuntimeException('No non-empty import rows were found in the selected worksheet.');
        return ['record' => $record, 'profile' => $profile, 'sheet' => $sheet, 'rows' => $rows, 'built_in' => $builtInSummary];
    }

    public static function response(callable $callback): never
    {
        try {
            $result = $callback();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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

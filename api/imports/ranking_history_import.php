<?php
/**
 * Purpose: API endpoint for ranking history import import operations; validates the request before changing imported data.
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers/RankBoundsParser.php';
require_once __DIR__ . '/../../includes/helpers/TemplateImportSupport.php';
require_once __DIR__ . '/../../includes/helpers/ImportBatchAudit.php';
require_once __DIR__ . '/../../includes/helpers/CustomImportFields.php';
requireRole(['super_admin'], true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function ranking_import_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function ranking_import_csrf(): void
{
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) ranking_import_fail('Invalid CSRF token.', 419);
}

function ranking_import_row(PDO $pdo, array $data, array $defaults, int $rowNumber): array
{
    $organization = trim((string)($data['organization'] ?? ''));
    if ($organization === '') throw new RuntimeException("Row {$rowNumber}: Organization is blank.");
    $organizationLength = function_exists('mb_strlen') ? mb_strlen($organization, 'UTF-8') : strlen($organization);
    if ($organizationLength > 512) throw new RuntimeException("Row {$rowNumber}: Organization exceeds 512 characters.");
    $bodyQuery = $pdo->prepare('SELECT ranking_body_id AS id, name FROM ranking_bodies WHERE LOWER(name) = LOWER(?) OR LOWER(short_name) = LOWER(?) LIMIT 1');
    $bodyQuery->execute([$organization, $organization]);
    $body = $bodyQuery->fetch(PDO::FETCH_ASSOC) ?: null;

    $type = trim((string)($data['ranking_type'] ?? ''));
    if ($type === '' || strlen($type) > 320) throw new RuntimeException("Row {$rowNumber}: Ranking Type is required and must not exceed 320 characters.");
    $typeId = null;
    if ($body) {
        $typeQuery = $pdo->prepare('SELECT ranking_type_id FROM ranking_types WHERE ranking_body_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
        $typeQuery->execute([(int)$body['id'], $type]);
        $typeId = $typeQuery->fetchColumn();
    }
    $year = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > 2200) throw new RuntimeException("Row {$rowNumber}: Year must be between 1900 and 2200.");
    $rank = trim((string)($data['global_rank'] ?? ''));
    if ($rank === '' || strlen($rank) > 50) throw new RuntimeException("Row {$rowNumber}: Rank is required and must not exceed 50 characters.");
    [$low, $high, $value] = RankBoundsParser::parse($rank);

    $optional = static function (string $field, int $limit) use ($data, $defaults, $rowNumber): array {
        $raw = trim((string)($data[$field] ?? $defaults[$field] ?? ''));
        if (strlen($raw) > $limit) throw new RuntimeException("Row {$rowNumber}: {$field} exceeds its storage limit.");
        if ($raw === '') return ['mode' => 'preserve', 'value' => null];
        if (strtoupper($raw) === '__CLEAR__') return ['mode' => 'clear', 'value' => null];
        return ['mode' => 'set', 'value' => $raw];
    };
    return [
        'organization' => $organization,
        'ranking_body_id' => $body ? (int)$body['id'] : null,
        'ranking_type_id' => $typeId ? (int)$typeId : null,
        'organization_name' => $body['name'] ?? $organization,
        'ranking_type' => $type,
        'year' => (int)$year,
        'global_rank' => $low,
        'global_rank_display' => $rank,
        'rank_low' => $low,
        'rank_high' => $high,
        'rank_value' => $value,
        'ph_rank_input' => $optional('ph_rank', 50),
        'info_text_input' => $optional('info_text', 16777215),
        'custom_fields' => CustomImportFields::merge([], $data['custom_fields'] ?? [])
    ];
}

function ranking_import_key(array $row): string
{
    return hash('sha256', json_encode([
        $row['ranking_body_id'] ?? strtolower((string)$row['organization']),
        strtolower((string)$row['ranking_type']),
        (int)$row['year']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ranking_import_find(PDO $pdo, array $identity, bool $lock): array
{
    $customFieldsColumn = CustomImportFields::columnExists($pdo, 'rankings') ? 'rankings.custom_fields' : 'NULL AS custom_fields';
    $sql = 'SELECT rankings.ranking_id AS id, rankings.ranking_body_id, rankings.ranking_type_id,
            ranking_types.name AS ranking_type, rankings.year, rankings.global_rank, rankings.global_rank_display, rankings.rank_low,
            rankings.rank_high, rankings.rank_value, rankings.ph_rank, rankings.ph_rank_display, rankings.ph_rank_value,
            rankings.source, rankings.info_text, ' . $customFieldsColumn . ', rankings.seed_managed
        FROM rankings
        INNER JOIN ranking_types ON ranking_types.ranking_type_id = rankings.ranking_type_id
        WHERE rankings.ranking_body_id = ? AND LOWER(ranking_types.name) = LOWER(?) AND rankings.year = ?
        ORDER BY rankings.ranking_id ASC';
    if ($lock) $sql .= ' FOR UPDATE';
    $query = $pdo->prepare($sql);
    $query->execute([$identity['ranking_body_id'], $identity['ranking_type'], $identity['year']]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function ranking_import_version(array $matches): string
{
    return hash('sha256', json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ranking_import_values_match(array $existing, array $incoming): bool
{
    foreach (['global_rank', 'global_rank_display', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_display', 'ph_rank_value', 'source', 'info_text'] as $field) {
        $current = $existing[$field] ?? null;
        $next = $incoming[$field] ?? null;
        if ($current === null || $next === null || $current === '' || $next === '') {
            if ((string)($current ?? '') !== (string)($next ?? '')) return false;
        } elseif (in_array($field, ['rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_value'], true) && is_numeric($current) && is_numeric($next)) {
            if ((float)$current !== (float)$next) return false;
        } elseif ((string)$current !== (string)$next) {
            return false;
        }
    }
    return CustomImportFields::decode($existing['custom_fields'] ?? []) === CustomImportFields::decode($incoming['custom_fields'] ?? []);
}

function ranking_import_preview(PDO $pdo, array $parsed, bool $lock = false, bool $includeRankBounds = false): array
{
    $rows = [];
    $seen = [];
    $typeExists = $pdo->prepare('SELECT 1 FROM ranking_types WHERE ranking_body_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
    $organizationIndex = $parsed['field_indexes']['organization'] ?? null;
    $organizationHeader = is_int($organizationIndex)
        ? trim((string)($parsed['sheet']['headers'][$organizationIndex] ?? ''))
        : '';
    $organizationColumn = null;
    if (is_int($organizationIndex) && $organizationIndex >= 0) {
        $columnNumber = $organizationIndex + 1;
        $columnLetters = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $columnLetters = chr(65 + ($columnNumber % 26)) . $columnLetters;
            $columnNumber = intdiv($columnNumber, 26);
        }
        $organizationColumn = [
            'field' => 'Organization',
            'header' => $organizationHeader !== '' ? $organizationHeader : '(blank header)',
            'letter' => $columnLetters
        ];
    }
    foreach ($parsed['rows'] as $input) {
        try {
            $incoming = ranking_import_row($pdo, $input['values'], $parsed['profile']['defaults_json'], $input['row_number']);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            if (str_contains($error, 'Organization is blank.')) {
                $error .= $organizationColumn
                    ? " Blocked field: Organization. Source column: \"{$organizationColumn['header']}\" (Column {$organizationColumn['letter']}); its cell is blank. Check the mapping, worksheet, and header row."
                    : ' Blocked field: Organization. No source worksheet column is mapped to this field.';
            } elseif (str_contains($error, 'Organization exceeds 512 characters.')) {
                $error .= $organizationColumn
                    ? " Blocked field: Organization. Source column: \"{$organizationColumn['header']}\" (Column {$organizationColumn['letter']})."
                    : ' Blocked field: Organization.';
            }
            $key = hash('sha256', json_encode([$input['sheet_name'], $input['row_number']], JSON_UNESCAPED_UNICODE));
            $rows[] = [
                'key' => $key,
                'kind' => 'blocked',
                'error' => $error,
                'blocked_field' => str_contains($error, 'Blocked field: Organization.') ? 'Organization' : null,
                'blocked_column' => $organizationColumn,
                'identity' => $input['values'],
                'sheet_name' => $input['sheet_name'],
                'row_number' => $input['row_number'],
                'row_version' => ranking_import_version([])
            ];
            continue;
        }
        $key = ranking_import_key($incoming);
        if (isset($seen[$key])) {
            $previousIndex = $seen[$key];
            $rows[$previousIndex]['kind'] = 'blocked';
            $rows[$previousIndex]['error'] = 'Duplicate Organization, Ranking Type, and Year identity in the uploaded file.';
            $rows[] = ['key' => $key . '-' . $input['row_number'], 'kind' => 'blocked', 'error' => 'Duplicate Organization, Ranking Type, and Year identity in the uploaded file.', 'identity' => $incoming, 'sheet_name' => $input['sheet_name'], 'row_number' => $input['row_number'], 'row_version' => ranking_import_version([])];
            continue;
        }
        $seen[$key] = count($rows);
        if ($incoming['ranking_body_id'] === null) {
            $rows[] = ['key' => $key, 'kind' => 'blocked', 'error' => "Unknown Organization '{$incoming['organization']}'. Match an existing Organization name or short name.", 'identity' => $incoming, 'sheet_name' => $input['sheet_name'], 'row_number' => $input['row_number'], 'row_version' => ranking_import_version([])];
            continue;
        }
        $matches = ranking_import_find($pdo, $incoming, $lock);
        if (count($matches) > 1) {
            $rows[] = ['key' => $key, 'kind' => 'blocked', 'error' => 'More than one existing row matches this Organization, Ranking Type, and Year identity.', 'identity' => $incoming, 'row_version' => ranking_import_version($matches), 'sheet_name' => $input['sheet_name'], 'row_number' => $input['row_number']];
            continue;
        }
        $existing = $matches[0] ?? null;
        $incoming['custom_fields'] = CustomImportFields::merge($existing['custom_fields'] ?? [], $incoming['custom_fields'] ?? []);
        foreach (['ph_rank', 'info_text'] as $field) {
            $inputKey = $field . '_input';
            $existingValue = $field === 'ph_rank'
                ? ($existing['ph_rank_display'] ?? (isset($existing['ph_rank']) ? (string)$existing['ph_rank'] : null))
                : ($existing[$field] ?? null);
            $effective = $incoming[$inputKey]['mode'] === 'preserve'
                ? $existingValue
                : ($incoming[$inputKey]['mode'] === 'clear' ? null : $incoming[$inputKey]['value']);
            $incoming[$field] = $effective;
        }
        $incoming['ph_rank_display'] = $incoming['ph_rank'];
        $incoming['ph_rank'] = $incoming['ph_rank'] === null ? null : parse_rank_to_value((string)$incoming['ph_rank']);
        $incoming['ph_rank_value'] = $incoming['ph_rank'];
        $identity = [
            'ranking_body_id' => $incoming['ranking_body_id'],
            'organization' => $incoming['organization_name'],
            'ranking_type' => $incoming['ranking_type'],
            'year' => $incoming['year'],
            'global_rank' => $incoming['global_rank'],
            'global_rank_display' => $incoming['global_rank_display'],
            'rank_low' => $incoming['rank_low'],
            'rank_high' => $incoming['rank_high'],
            'rank_value' => $incoming['rank_value'],
            'ph_rank' => $incoming['ph_rank'],
            'ph_rank_display' => $incoming['ph_rank_display'],
            'ph_rank_value' => $incoming['ph_rank_value'],
            'source' => $existing['source'] ?? null,
            'info_text' => $incoming['info_text'],
            'custom_fields' => $incoming['custom_fields'],
            'seed_managed' => 0
        ];
        $same = $existing !== null && ranking_import_values_match($existing, $identity);
        $typeExists->execute([$incoming['ranking_body_id'], $incoming['ranking_type']]);
        $displayIdentity = $identity;
        $displayExisting = $existing;
        if (!$includeRankBounds) {
            unset($displayIdentity['rank_low'], $displayIdentity['rank_high']);
            if ($displayExisting) unset($displayExisting['rank_low'], $displayExisting['rank_high']);
        }
        $rows[] = [
            'key' => $key,
            'kind' => !$existing ? 'insert' : ($same ? 'unchanged' : 'update'),
            'new_type' => !$typeExists->fetchColumn(),
            'identity' => $displayIdentity,
            'existing_id' => $existing ? (int)$existing['id'] : null,
            'existing' => $displayExisting,
            'row_version' => $existing ? ranking_import_version([$existing]) : ranking_import_version([]),
            'sheet_name' => $input['sheet_name'],
            'row_number' => $input['row_number']
        ];
    }
    return $rows;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') ranking_import_fail('Method not allowed.', 405);
ranking_import_csrf();
$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) ranking_import_fail('Invalid request body.');

TemplateImportSupport::response(static function () use ($data): array {
    $pdo = db();
    $recordId = trim((string)($data['record_id'] ?? ''));
    if ($recordId === '') throw new InvalidArgumentException('Record id is required.');
    $selectedSheet = isset($data['sheet_name']) ? (string)$data['sheet_name'] : null;
    $parsed = TemplateImportSupport::parse($pdo, $recordId, 'ranking_history', $selectedSheet);

    if (($data['action'] ?? 'preview') === 'preview') {
        $pdo->beginTransaction();
        try {
            $rows = ranking_import_preview($pdo, $parsed, true);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        return ['record' => ['id' => $recordId, 'file_name' => $parsed['record']['fileName']], 'sheet_name' => $parsed['sheet']['name'], 'rows' => $rows];
    }
    if (($data['action'] ?? '') !== 'apply') throw new InvalidArgumentException('Unknown ranking import action.');
    if (empty($data['reviewed_diff'])) throw new InvalidArgumentException('Review the diff before approving this import.');
    $accepted = $data['accepted_keys'] ?? [];
    $versions = $data['row_versions'] ?? [];
    if (!is_array($accepted) || !array_is_list($accepted) || !is_array($versions)) throw new InvalidArgumentException('Invalid import selections.');

    $pdo->beginTransaction();
    try {
        $rows = ranking_import_preview($pdo, $parsed, true, true);
        foreach ($rows as $row) {
            if (!isset($versions[$row['key']]) || !hash_equals((string)$versions[$row['key']], $row['row_version'])) {
                throw new RuntimeException('The preview is stale because ranking data changed. Preview the upload again.', 409);
            }
        }
        $acceptedSet = array_fill_keys(array_filter($accepted, 'is_string'), true);
        $work = array_values(array_filter($rows, static fn(array $row): bool => isset($acceptedSet[$row['key']]) && in_array($row['kind'], ['insert', 'update'], true)));
        if (!$work) {
            $pdo->commit();
            return ['success' => true, 'inserted' => 0, 'updated' => 0, 'message' => 'No changes detected.'];
        }
        $inserted = count(array_filter($work, static fn(array $row): bool => $row['kind'] === 'insert'));
        $updated = count($work) - $inserted;
        $batchId = ImportBatchAudit::create($pdo, $parsed['record'], 'ranking_history', (int)$_SESSION['user_id'], $inserted, $updated);
        $columns = ['ranking_body_id', 'ranking_type_id', 'year', 'global_rank', 'global_rank_display', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_display', 'ph_rank_value', 'source', 'info_text'];
        if (CustomImportFields::columnExists($pdo, 'rankings')) $columns[] = 'custom_fields';
        $insert = $pdo->prepare('INSERT INTO rankings (' . implode(', ', $columns) . ', seed_managed) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ', 0)');
        $set = implode(', ', array_map(static fn(string $column): string => '`' . $column . '` = ?', $columns));
        $update = $pdo->prepare('UPDATE rankings SET ' . $set . ', seed_managed = 0 WHERE ranking_id = ?');
        foreach ($work as $row) {
            $identity = $row['identity'];
            $typeId = $identity['ranking_type_id'] ?? null;
            if (!$typeId) {
                $typeInsert = $pdo->prepare('INSERT INTO ranking_types (ranking_body_id, name) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE ranking_type_id = LAST_INSERT_ID(ranking_type_id)');
                $typeInsert->execute([$identity['ranking_body_id'], $identity['ranking_type']]);
                $typeId = (int)$pdo->lastInsertId();
            }
            $identity['ranking_type_id'] = (int)$typeId;
            $identity['custom_fields'] = CustomImportFields::encode($identity['custom_fields'] ?? []);
            $values = array_map(static fn(string $column) => $identity[$column] ?? null, $columns);
            $before = $row['existing'];
            if ($row['kind'] === 'insert') {
                $insert->execute($values);
                $id = (int)$pdo->lastInsertId();
            } else {
                $id = (int)$row['existing_id'];
                $update->execute([...$values, $id]);
            }
            $customFieldsColumn = in_array('custom_fields', $columns, true) ? 'custom_fields' : 'NULL AS custom_fields';
            $savedQuery = $pdo->prepare('SELECT ranking_id AS id, ranking_body_id, ranking_type_id, year, global_rank, global_rank_display, rank_low, rank_high, rank_value, ph_rank, ph_rank_display, ph_rank_value, source, info_text, ' . $customFieldsColumn . ', seed_managed FROM rankings WHERE ranking_id = ?');
            $savedQuery->execute([$id]);
            $after = $savedQuery->fetch(PDO::FETCH_ASSOC);
            ImportBatchAudit::row($pdo, $batchId, 'ranking', (string)$id, $row['sheet_name'], $row['row_number'], $before, $after);
        }
        $pdo->commit();
        return ['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'batch_id' => $batchId, 'message' => 'Ranking History import applied.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
});

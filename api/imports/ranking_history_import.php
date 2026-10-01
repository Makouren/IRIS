<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers/RankBoundsParser.php';
require_once __DIR__ . '/../../includes/helpers/TemplateImportSupport.php';
require_once __DIR__ . '/../../includes/helpers/ImportBatchAudit.php';
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

function ranking_import_identity(PDO $pdo, array $data, int $bodyId, array $defaults, int $rowNumber): array
{
    $scopeId = null;
    $scopeInput = trim((string)($data['scope_id'] ?? '')) ?: trim((string)($defaults['scope_id'] ?? ''));
    if ($scopeInput !== '') {
        if (!ctype_digit($scopeInput)) throw new RuntimeException("Row {$rowNumber}: scope_id must be a whole number.");
        $scopeId = (int)$scopeInput;
        $scopeCheck = $pdo->prepare('SELECT id FROM ranking_scopes WHERE id = ?');
        $scopeCheck->execute([$scopeId]);
        if (!$scopeCheck->fetchColumn()) throw new RuntimeException("Row {$rowNumber}: scope does not exist.");
    } else {
        $scopeName = trim((string)($data['scope'] ?? '')) ?: trim((string)($defaults['scope'] ?? ''));
        if ($scopeName !== '' && strtolower($scopeName) !== 'unassigned') {
            $scopeCheck = $pdo->prepare('SELECT id FROM ranking_scopes WHERE LOWER(name) = LOWER(?)');
            $scopeCheck->execute([$scopeName]);
            $scopeId = $scopeCheck->fetchColumn();
            if ($scopeId === false) throw new RuntimeException("Row {$rowNumber}: unknown scope '{$scopeName}'. Add it to Ranking Scopes before importing.");
            $scopeId = (int)$scopeId;
        }
    }

    $level = null;
    $levelId = trim((string)($data['level_id'] ?? '')) ?: trim((string)($defaults['level_id'] ?? ''));
    $levelName = trim((string)($data['level'] ?? '')) ?: trim((string)($defaults['level'] ?? ''));
    if ($levelId !== '') {
        if (!ctype_digit($levelId)) throw new RuntimeException("Row {$rowNumber}: level_id must be a whole number.");
        $levelQuery = $pdo->prepare('SELECT name FROM ranking_levels WHERE id = ?');
        $levelQuery->execute([(int)$levelId]);
        $level = $levelQuery->fetchColumn();
        if ($level === false) throw new RuntimeException("Row {$rowNumber}: ranking level does not exist.");
    } elseif ($levelName !== '' && strtolower($levelName) !== 'unassigned') {
        $levelQuery = $pdo->prepare('SELECT name FROM ranking_levels WHERE LOWER(name) = LOWER(?)');
        $levelQuery->execute([$levelName]);
        $level = $levelQuery->fetchColumn();
        if ($level === false) throw new RuntimeException("Row {$rowNumber}: unknown level '{$levelName}'. Add it to Ranking Levels before importing.");
    }

    $year = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > 2200) throw new RuntimeException("Row {$rowNumber}: year must be between 1900 and 2200.");
    $type = trim((string)($data['ranking_type'] ?? $defaults['ranking_type'] ?? ''));
    if ($type === '') $type = trim((string)($defaults['ranking_type'] ?? ''));
    if ($type === '') throw new RuntimeException("Row {$rowNumber}: ranking_type is required or must be configured as a template default.");
    $edition = trim((string)($data['edition'] ?? '')) ?: (trim((string)($defaults['edition'] ?? '')) ?: 'Annual');
    $category = trim((string)($data['category'] ?? '')) ?: (trim((string)($defaults['category'] ?? '')) ?: 'Overall');
    $rank = trim((string)($data['global_rank'] ?? ''));
    if ($rank === '') throw new RuntimeException("Row {$rowNumber}: global_rank is required.");
    $limits = ['ranking_type' => 100, 'edition' => 80, 'category' => 100, 'global_rank' => 50];
    foreach (['ranking_type' => $type, 'edition' => $edition, 'category' => $category, 'global_rank' => $rank] as $field => $fieldValue) {
        if (strlen($fieldValue) > $limits[$field]) throw new RuntimeException("Row {$rowNumber}: {$field} exceeds its storage limit.");
    }
    if ($level !== null && strlen($level) > 20) throw new RuntimeException("Row {$rowNumber}: level exceeds the rankings table limit of 20 characters.");
    try {
        [$low, $high, $value] = RankBoundsParser::parse($rank);
    } catch (InvalidArgumentException $exception) {
        throw new RuntimeException("Row {$rowNumber}: {$exception->getMessage()}");
    }
    $status = trim((string)($data['verification_status'] ?? '')) ?: (trim((string)($defaults['verification_status'] ?? '')) ?: 'verified');
    if (!in_array($status, ['verified', 'inferred', 'conflicting', 'assumed', 'unverified'], true)) {
        throw new RuntimeException("Row {$rowNumber}: invalid verification_status.");
    }
    $optional = static fn(string $field): string => trim((string)($data[$field] ?? '')) ?: trim((string)($defaults[$field] ?? ''));
    foreach (['ph_rank' => 50, 'note' => 255, 'source' => 500] as $field => $limit) {
        if (strlen($optional($field)) > $limit) throw new RuntimeException("Row {$rowNumber}: {$field} exceeds its storage limit.");
    }
    return [
        'ranking_body_id' => $bodyId,
        'scope_id' => $scopeId,
        'ranking_type' => $type,
        'level' => $level,
        'year' => (int)$year,
        'edition' => $edition,
        'category' => $category,
        'global_rank' => $rank,
        'rank_low' => $low,
        'rank_high' => $high,
        'rank_value' => $value,
        'ph_rank' => $optional('ph_rank') ?: null,
        'ph_rank_value' => parse_rank_to_value($optional('ph_rank')),
        'note' => $optional('note') ?: null,
        'source' => $optional('source') ?: null,
        'verification_status' => $status
    ];
}

function ranking_import_key(array $row): string
{
    return hash('sha256', json_encode([
        $row['ranking_body_id'], $row['scope_id'], strtolower((string)$row['ranking_type']),
        strtolower((string)$row['level']), $row['year'], strtolower((string)$row['edition']), strtolower((string)$row['category'])
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ranking_import_find(PDO $pdo, array $identity, bool $lock): array
{
    $sql = 'SELECT * FROM rankings WHERE ranking_body_id = ? AND (scope_id <=> ?)
        AND LOWER(COALESCE(ranking_type, "")) = LOWER(?) AND LOWER(COALESCE(level, "")) = LOWER(?)
        AND year = ? AND LOWER(edition) = LOWER(?) AND (category <=> ?) ORDER BY id ASC';
    if ($lock) $sql .= ' FOR UPDATE';
    $query = $pdo->prepare($sql);
    $query->execute([$identity['ranking_body_id'], $identity['scope_id'], $identity['ranking_type'], $identity['level'] ?? '', $identity['year'], $identity['edition'], $identity['category']]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function ranking_import_legacy(PDO $pdo, array $identity, bool $lock): array
{
    $sql = 'SELECT * FROM rankings WHERE ranking_body_id = ? AND year = ? AND (category <=> ?)
        AND (scope_id IS NULL OR scope_id = ?) AND (ranking_type IS NULL OR LOWER(ranking_type) = LOWER(?))
        AND (level IS NULL OR LOWER(level) = LOWER(?)) AND LOWER(edition) = LOWER(?)
        AND (scope_id IS NULL OR ranking_type IS NULL OR level IS NULL)';
    if ($lock) $sql .= ' FOR UPDATE';
    $query = $pdo->prepare($sql);
    $query->execute([$identity['ranking_body_id'], $identity['year'], $identity['category'], $identity['scope_id'], $identity['ranking_type'], $identity['level'] ?? '', $identity['edition']]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function ranking_import_version(array $matches): string
{
    return hash('sha256', json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ranking_import_preview(PDO $pdo, array $parsed, int $bodyId, bool $lock = false): array
{
    $rows = [];
    $seen = [];
    foreach ($parsed['rows'] as $input) {
        try {
            $identity = ranking_import_identity($pdo, $input['values'], $bodyId, $parsed['profile']['defaults_json'], $input['row_number']);
        } catch (Throwable $exception) {
            throw new RuntimeException("Sheet {$input['sheet_name']}, row {$input['row_number']}: {$exception->getMessage()}");
        }
        $key = ranking_import_key($identity);
        if (isset($seen[$key])) throw new RuntimeException("Sheet {$input['sheet_name']}, row {$input['row_number']}: duplicate ranking identity in the uploaded file.");
        $seen[$key] = true;
        $matches = ranking_import_find($pdo, $identity, $lock);
        $legacy = [];
        if (!$matches) $legacy = ranking_import_legacy($pdo, $identity, $lock);
        if (count($matches) > 1 || count($legacy) > 1) {
            $rows[] = ['key' => $key, 'kind' => 'blocked', 'error' => 'More than one existing row matches this identity.', 'identity' => $identity, 'sheet_name' => $input['sheet_name'], 'row_number' => $input['row_number'], 'row_version' => ranking_import_version(count($matches) ? $matches : $legacy)];
            continue;
        }
        $existing = $matches[0] ?? ($legacy[0] ?? null);
        $kind = !$existing ? 'insert' : (isset($legacy[0]) ? 'legacy' : 'update');
        if ($existing && !isset($legacy[0])) {
            $same = true;
            foreach (['global_rank', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'note', 'source', 'verification_status'] as $field) {
                if ((string)($existing[$field] ?? '') !== (string)($identity[$field] ?? '')) $same = false;
            }
            if ($same) $kind = 'unchanged';
        }
        $rows[] = [
            'key' => $key,
            'kind' => $kind,
            'identity' => $identity,
            'existing_id' => $existing ? (int)$existing['id'] : null,
            'existing' => $existing,
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
    $parsed = TemplateImportSupport::parse($pdo, $recordId, 'ranking_history');
    $bodyQuery = $pdo->prepare('SELECT ranking_body_id FROM templates WHERE id = ?');
    $bodyQuery->execute([(int)$parsed['record']['template_id']]);
    $bodyId = (int)$bodyQuery->fetchColumn();
    if ($bodyId < 1) throw new RuntimeException('The template is not linked to a ranking body.');

    if (($data['action'] ?? 'preview') === 'preview') {
        $pdo->beginTransaction();
        try {
            $rows = ranking_import_preview($pdo, $parsed, $bodyId, true);
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
        $rows = ranking_import_preview($pdo, $parsed, $bodyId, true);
        foreach ($rows as $row) {
            if (!isset($versions[$row['key']]) || !hash_equals((string)$versions[$row['key']], $row['row_version'])) {
                throw new RuntimeException('The preview is stale because ranking data changed. Preview the upload again.', 409);
            }
        }
        $acceptedSet = array_fill_keys(array_filter($accepted, 'is_string'), true);
        $work = array_values(array_filter($rows, static fn(array $row): bool => isset($acceptedSet[$row['key']]) && in_array($row['kind'], ['insert', 'update', 'legacy'], true)));
        if (!$work) {
            $pdo->commit();
            return ['success' => true, 'inserted' => 0, 'updated' => 0, 'message' => 'No changes detected.'];
        }
        $inserted = count(array_filter($work, static fn(array $row): bool => $row['kind'] === 'insert'));
        $updated = count($work) - $inserted;
        $batchId = ImportBatchAudit::create($pdo, $parsed['record'], 'ranking_history', (int)$_SESSION['user_id'], $inserted, $updated);
        $columns = ['ranking_body_id', 'scope_id', 'ranking_type', 'level', 'year', 'edition', 'category', 'global_rank', 'rank_low', 'rank_high', 'rank_value', 'ph_rank', 'ph_rank_value', 'note', 'source', 'verification_status'];
        $insert = $pdo->prepare('INSERT INTO rankings (' . implode(', ', $columns) . ', seed_managed) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ', 0)');
        $set = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', $columns));
        $update = $pdo->prepare('UPDATE rankings SET ' . $set . ', seed_managed = 0 WHERE id = ?');
        foreach ($work as $row) {
            $identity = $row['identity'];
            if ($row['existing']) {
                foreach (['ph_rank', 'ph_rank_value', 'note', 'source'] as $optionalField) {
                    if (trim((string)($identity[$optionalField] ?? '')) === '') $identity[$optionalField] = $row['existing'][$optionalField] ?? null;
                }
            }
            $values = array_map(static fn(string $column) => $identity[$column] ?? null, $columns);
            $before = $row['existing'];
            if ($row['kind'] === 'insert') {
                $insert->execute($values);
                $id = (int)$pdo->lastInsertId();
            } else {
                $id = (int)$row['existing_id'];
                $update->execute([...$values, $id]);
            }
            $savedQuery = $pdo->prepare('SELECT * FROM rankings WHERE id = ?');
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

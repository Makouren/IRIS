<?php
/**
 * Purpose: API endpoint for template reviews operations; serves the corresponding application data or action.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/RankBoundsParser.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
requireRole(['super_admin'], true);

function template_review_fail(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function template_review_input(): array {
    $value = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($value) ? $value : [];
}

function template_review_csrf(): void {
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) template_review_fail('Invalid CSRF token.', 419);
}

function template_review_record(PDO $pdo, string $recordId, bool $lock = false): array {
    $sql = 'SELECT records.record_id AS id, records.file_name AS fileName, records.file_type AS fileType,
                   records.file_size AS fileSize, records.status, offices.office_name,
                   records.uploaded_by, records.template_id,
                   templates.name AS template_name, templates.ranking_body_id,
                   bodies.name AS ranking_body_name, bodies.short_name AS ranking_body_short_name
            FROM records
            LEFT JOIN offices ON offices.office_id = records.office_id
            LEFT JOIN templates ON templates.template_id = records.template_id
            LEFT JOIN ranking_bodies bodies ON bodies.ranking_body_id = templates.ranking_body_id
            WHERE records.record_id = ?' . ($lock ? ' FOR UPDATE' : '');
    $query = $pdo->prepare($sql);
    $query->execute([$recordId]);
    $record = $query->fetch(PDO::FETCH_ASSOC);
    if (!$record) template_review_fail('Record not found.', 404);
    return $record;
}

function template_review_rows(array $inputRows): array {
    if (!array_is_list($inputRows) || count($inputRows) > 5000) template_review_fail('Invalid diff rows.');
    $rows = [];
    $seen = [];
    $skipped = 0;
    foreach ($inputRows as $input) {
        if (!is_array($input)) template_review_fail('Each diff row must be an object.');
        $rawYear = trim((string)($input['year'] ?? ''));
        $rankDisplay = trim((string)($input['global_rank'] ?? ''));
        if ($rawYear === '' && $rankDisplay === '') {
            $skipped++;
            continue;
        }
        $year = filter_var($rawYear, FILTER_VALIDATE_INT);
        if ($year === false || $year < 1900 || $year > 2200) template_review_fail('A row has an invalid year. Use a whole year from 1900 to 2200.');
        if ($rankDisplay === '' || strlen($rankDisplay) > 50) template_review_fail('A row has an empty or overlong rank value.');
        try {
            [$rankLow, $rankHigh, $rankValue] = RankBoundsParser::parse($rankDisplay);
        } catch (InvalidArgumentException) {
            template_review_fail('A row has an invalid rank value.');
        }
        $category = trim((string)($input['category'] ?? ''));
        if (strlen($category) > 100) template_review_fail('Category must not exceed 100 characters.');
        $category = $category !== '' ? $category : null;
        $key = (string)$year . ':' . base64_encode(strtolower((string)$category));
        if (isset($seen[$key])) template_review_fail('The uploaded file has multiple rows for the same year and category.');
        $seen[$key] = true;
        $rows[] = [
            'key' => $key,
            'year' => (int)$year,
            'category' => $category,
            'global_rank' => $rankDisplay,
            'rank_low' => $rankLow,
            'rank_high' => $rankHigh,
            'rank_value' => $rankValue,
        ];
    }
    return [$rows, $skipped];
}

function template_review_diff(PDO $pdo, int $rankingBodyId, array $rows, bool $lock = false): array {
    $result = [];
    $suffix = $lock ? ' FOR UPDATE' : '';
    $query = $pdo->prepare("SELECT rankings.ranking_id AS id,
            COALESCE(rankings.global_rank_display, CASE WHEN rankings.rank_low IS NOT NULL AND rankings.rank_high IS NOT NULL AND rankings.rank_low <> rankings.rank_high
                THEN CONCAT(rankings.rank_low, '-', rankings.rank_high) ELSE CAST(rankings.global_rank AS CHAR) END) AS global_rank,
            rankings.rank_value
        FROM rankings
        LEFT JOIN ranking_categories categories ON categories.category_id = rankings.category_id AND categories.ranking_body_id = ?
        WHERE rankings.ranking_body_id = ? AND rankings.ranking_type_id IS NULL AND rankings.year = ?
            AND categories.name <=> ?
        ORDER BY rankings.ranking_id ASC LIMIT 1" . $suffix);
    foreach ($rows as $row) {
        $query->execute([$rankingBodyId, $rankingBodyId, $row['year'], $row['category']]);
        $existing = $query->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $row['kind'] = 'new';
            $row['existing_id'] = null;
            $row['existing_global_rank'] = null;
            $row['existing_rank_value'] = null;
        } else {
            $row['existing_id'] = (int)$existing['id'];
            $row['existing_global_rank'] = $existing['global_rank'];
            $row['existing_rank_value'] = $existing['rank_value'] !== null ? (float)$existing['rank_value'] : null;
            $row['kind'] = ((string)$existing['global_rank'] === $row['global_rank']
                && $existing['rank_value'] !== null
                && (float)$existing['rank_value'] === (float)$row['rank_value']) ? 'unchanged' : 'changed';
        }
        $result[] = $row;
    }
    return $result;
}

function template_review_category_id(PDO $pdo, int $rankingBodyId, string $name, bool $create): ?int {
    $query = $pdo->prepare('SELECT category_id FROM ranking_categories WHERE ranking_body_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
    $query->execute([$rankingBodyId, $name]);
    $categoryId = $query->fetchColumn();
    if ($categoryId) return (int)$categoryId;
    if (!$create) return null;
    try {
        $insert = $pdo->prepare('INSERT INTO ranking_categories (ranking_body_id, name) VALUES (?, ?)');
        $insert->execute([$rankingBodyId, $name]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $exception) {
        $query->execute([$rankingBodyId, $name]);
        $categoryId = $query->fetchColumn();
        if ($categoryId) return (int)$categoryId;
        throw $exception;
    }
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $recordId = trim((string)($_GET['record_id'] ?? ''));
        if ($recordId === '') template_review_fail('Record id is required.');
        $record = template_review_record($pdo, $recordId);
        $templates = $pdo->prepare('SELECT templates.template_id AS id, templates.name, templates.ranking_body_id,
                bodies.name AS ranking_body_name, bodies.short_name AS ranking_body_short_name
            FROM templates INNER JOIN ranking_bodies bodies ON bodies.ranking_body_id = templates.ranking_body_id
            ORDER BY templates.is_active DESC, templates.name ASC, templates.template_id DESC');
        $templates->execute();
        echo json_encode(['record' => $record, 'templates' => $templates->fetchAll(PDO::FETCH_ASSOC)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($method !== 'POST') template_review_fail('Method not allowed.', 405);
    template_review_csrf();
    $data = template_review_input();
    $action = (string)($data['action'] ?? '');
    $recordId = trim((string)($data['record_id'] ?? ''));
    if ($recordId === '') template_review_fail('Record id is required.');

    if ($action === 'assign-template') {
        $templateId = filter_var($data['template_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$templateId || $templateId < 1) template_review_fail('Choose a template linked to a ranking body.');
        $templateQuery = $pdo->prepare('SELECT template_id FROM templates WHERE template_id = ? AND ranking_body_id IS NOT NULL');
        $templateQuery->execute([$templateId]);
        if (!$templateQuery->fetchColumn()) template_review_fail('Choose a template linked to a ranking body.');
        template_review_record($pdo, $recordId);
        $pdo->prepare('UPDATE records SET template_id = ?, updated_at = NOW() WHERE record_id = ?')->execute([$templateId, $recordId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (!in_array($action, ['preview', 'approve'], true)) template_review_fail('Unknown review action.');
    $record = template_review_record($pdo, $recordId);
    if (empty($record['template_id']) || empty($record['ranking_body_id'])) template_review_fail('Assign a template linked to a ranking body first.', 409);
    [$rows, $skipped] = template_review_rows($data['rows'] ?? []);

    if ($action === 'preview') {
        $diff = template_review_diff($pdo, (int)$record['ranking_body_id'], $rows);
        $counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0];
        foreach ($diff as $row) $counts[$row['kind']]++;
        $visibleRows = array_values(array_filter($diff, static fn(array $row): bool => $row['kind'] !== 'unchanged'));
        echo json_encode(['record' => $record, 'counts' => $counts, 'skipped_rows' => $skipped, 'rows' => $visibleRows], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    $acceptedKeys = $data['accepted_keys'] ?? [];
    if (!is_array($acceptedKeys) || !array_is_list($acceptedKeys)) template_review_fail('Invalid accepted row selection.');
    $acceptedKeys = array_fill_keys(array_filter($acceptedKeys, 'is_string'), true);
    $pdo->beginTransaction();
    try {
        $record = template_review_record($pdo, $recordId, true);
        if (empty($record['template_id']) || empty($record['ranking_body_id'])) template_review_fail('Assign a template linked to a ranking body first.', 409);
        $diff = template_review_diff($pdo, (int)$record['ranking_body_id'], $rows, true);
        $source = trim((string)($record['office_name'] ?? '')) ?: 'Office Upload';
        $insert = $pdo->prepare('INSERT INTO rankings (ranking_body_id, category_id, year, global_rank, global_rank_display, rank_low, rank_high, rank_value, source, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $update = $pdo->prepare('UPDATE rankings SET global_rank = ?, global_rank_display = ?, rank_low = ?, rank_high = ?, rank_value = ?, source = ?, verification_status = ? WHERE ranking_id = ?');
        $inserted = 0;
        $updated = 0;
        foreach ($diff as $row) {
            if (!isset($acceptedKeys[$row['key']])) continue;
            $categoryId = $row['category'] !== null
                ? template_review_category_id($pdo, (int)$record['ranking_body_id'], $row['category'], true)
                : null;
            if ($row['kind'] === 'new') {
                $insert->execute([(int)$record['ranking_body_id'], $categoryId, $row['year'], $row['rank_low'], $row['global_rank'], $row['rank_low'], $row['rank_high'], $row['rank_value'], $source, 'verified']);
                $inserted++;
            } elseif ($row['kind'] === 'changed') {
                $update->execute([$row['rank_low'], $row['global_rank'], $row['rank_low'], $row['rank_high'], $row['rank_value'], $source, 'verified', $row['existing_id']]);
                $updated++;
            }
        }
        $pdo->prepare("UPDATE records SET status = 'Approved', updated_at = NOW() WHERE record_id = ?")->execute([$recordId]);
        if ($record['uploaded_by'] !== null) {
            $log = $pdo->prepare('INSERT INTO uploads_log (uploaded_by, filename, file_type, upload_type, rows_inserted) VALUES (?, ?, ?, ?, ?)');
            $log->execute([(int)$record['uploaded_by'], (string)$record['fileName'], (string)$record['fileType'], 'ranking_history_review', $inserted + $updated]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'approved' => true]);
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
} catch (Throwable $exception) {
    template_review_fail('Unable to process the ranking review.', 500);
}

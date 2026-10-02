<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/RankBoundsParser.php';
requireRole(['super_admin'], true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function admin_rankings_bad(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function admin_rankings_verify_csrf(): void
{
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) admin_rankings_bad('Invalid CSRF token.', 419);
}

function admin_rankings_body(PDO $pdo, array $data): array
{
    $organization = trim((string)($data['organization'] ?? ''));
    if ($organization === '' || strlen($organization) > 100) admin_rankings_bad('Organization is required and must not exceed 100 characters.');
    $query = $pdo->prepare('SELECT ranking_body_id AS id, name, short_name FROM ranking_bodies WHERE LOWER(name) = LOWER(?) OR LOWER(short_name) = LOWER(?) LIMIT 1');
    $query->execute([$organization, $organization]);
    $body = $query->fetch(PDO::FETCH_ASSOC);
    if ($body) return $body;
    $baseName = function_exists('mb_substr') ? mb_substr($organization, 0, 20, 'UTF-8') : substr($organization, 0, 20);
    $shortName = $baseName;
    $suffix = 1;
    $shortCheck = $pdo->prepare('SELECT ranking_body_id FROM ranking_bodies WHERE LOWER(short_name) = LOWER(?) LIMIT 1');
    do {
        $shortCheck->execute([$shortName]);
        if (!$shortCheck->fetchColumn()) break;
        $suffixText = '-' . $suffix++;
        $prefix = function_exists('mb_substr') ? mb_substr($baseName, 0, 20 - strlen($suffixText), 'UTF-8') : substr($baseName, 0, 20 - strlen($suffixText));
        $shortName = $prefix . $suffixText;
    } while (true);
    $insert = $pdo->prepare('INSERT INTO ranking_bodies (name, short_name) VALUES (?, ?)');
    $insert->execute([$organization, $shortName]);
    return ['id' => (int)$pdo->lastInsertId(), 'name' => $organization, 'short_name' => $shortName];
}

function admin_rankings_type(PDO $pdo, int $bodyId, string $name): int
{
    $query = $pdo->prepare('SELECT ranking_type_id FROM ranking_types WHERE ranking_body_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
    $query->execute([$bodyId, $name]);
    $typeId = $query->fetchColumn();
    if ($typeId) return (int)$typeId;
    try {
        $insert = $pdo->prepare('INSERT INTO ranking_types (ranking_body_id, name) VALUES (?, ?)');
        $insert->execute([$bodyId, $name]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $exception) {
        $query->execute([$bodyId, $name]);
        $typeId = $query->fetchColumn();
        if ($typeId) return (int)$typeId;
        throw $exception;
    }
}

function admin_rankings_payload(PDO $pdo, array $data, int $currentId = 0): array
{
    $type = trim((string)($data['ranking_type'] ?? ''));
    if ($type === '' || strlen($type) > 320) admin_rankings_bad('Ranking type is required and must not exceed 320 characters.');
    $year = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > 2200) admin_rankings_bad('Year must be between 1900 and 2200.');
    $rank = trim((string)($data['global_rank'] ?? ''));
    $infoText = trim((string)($data['info_text'] ?? ''));
    if ($rank === '' || strlen($rank) > 50) admin_rankings_bad('Rank is required and must not exceed 50 characters.');
    if (strlen($infoText) > 65535) admin_rankings_bad('Information must not exceed 65,535 bytes.');
    try {
        [$rankLow, $rankHigh, $rankValue] = RankBoundsParser::parse($rank);
    } catch (InvalidArgumentException $exception) {
        admin_rankings_bad($exception->getMessage());
    }

    $body = admin_rankings_body($pdo, $data);
    $typeId = admin_rankings_type($pdo, (int)$body['id'], $type);
    $duplicate = $pdo->prepare('SELECT ranking_id FROM rankings WHERE ranking_body_id = ? AND ranking_type_id = ? AND year = ? AND ranking_id <> ? LIMIT 1');
    $duplicate->execute([(int)$body['id'], $typeId, (int)$year, $currentId]);
    $duplicateId = $duplicate->fetchColumn();
    if ($duplicateId) {
        http_response_code(409);
        echo json_encode(['error' => 'This Organization, Ranking Type, and Year already exists.', 'duplicate_id' => (int)$duplicateId, 'message' => 'This ranking already exists. Edit it instead?']);
        exit;
    }

    return [
        'ranking_body_id' => (int)$body['id'],
        'ranking_type_id' => $typeId,
        'year' => (int)$year,
        'global_rank' => $rankLow,
        'global_rank_display' => $rank,
        'rank_low' => $rankLow,
        'rank_high' => $rankHigh,
        'rank_value' => $rankValue,
        'info_text' => $infoText !== '' ? $infoText : null
    ];
}

function admin_rankings_row(PDO $pdo, int $id): array
{
        $query = $pdo->prepare("SELECT r.ranking_id AS id, bodies.name AS organization, bodies.short_name AS organization_short_name,
            bodies.sort_order AS organization_sort_order,
            types.name AS ranking_type, r.year,
            COALESCE(r.global_rank_display, CASE WHEN r.rank_low IS NOT NULL AND r.rank_high IS NOT NULL AND r.rank_low <> r.rank_high
                THEN CONCAT(r.rank_low, '-', r.rank_high) ELSE CAST(r.global_rank AS CHAR) END) AS global_rank,
            r.rank_value, r.info_text
        FROM rankings r
        INNER JOIN ranking_bodies bodies ON bodies.ranking_body_id = r.ranking_body_id
        LEFT JOIN ranking_types types ON types.ranking_type_id = r.ranking_type_id
        WHERE r.ranking_id = ?");
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) admin_rankings_bad('Ranking row not found.', 404);
    return $row;
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $bodies = $pdo->query('SELECT ranking_body_id AS id, name, short_name, sort_order FROM ranking_bodies ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $rankings = $pdo->query("SELECT r.ranking_id AS id, bodies.ranking_body_id, bodies.name AS organization,
            bodies.short_name AS organization_short_name, bodies.sort_order AS organization_sort_order, types.name AS ranking_type, r.year,
                COALESCE(r.global_rank_display, CASE WHEN r.rank_low IS NOT NULL AND r.rank_high IS NOT NULL AND r.rank_low <> r.rank_high
                    THEN CONCAT(r.rank_low, '-', r.rank_high) ELSE CAST(r.global_rank AS CHAR) END) AS global_rank,
                r.rank_value, r.info_text
            FROM rankings r
            INNER JOIN ranking_bodies bodies ON bodies.ranking_body_id = r.ranking_body_id
            LEFT JOIN ranking_types types ON types.ranking_type_id = r.ranking_type_id
            ORDER BY bodies.sort_order ASC, CASE
                WHEN UPPER(bodies.short_name) = 'WURI' AND LOWER(types.name) LIKE '%overall%' THEN 0
                WHEN UPPER(bodies.short_name) = 'QS' AND LOWER(types.name) LIKE '%asia%' AND LOWER(types.name) NOT LIKE '%south eastern%' THEN 1
                WHEN UPPER(bodies.short_name) = 'QS' AND LOWER(types.name) LIKE '%south eastern%' THEN 2
                WHEN LOWER(bodies.name) LIKE '%webometrics%' THEN 3
                ELSE 4
            END,
            bodies.name ASC, types.name ASC, r.year ASC,
            r.rank_value IS NULL ASC, r.rank_value ASC")->fetchAll(PDO::FETCH_ASSOC);
        $state = json_decode((string)$pdo->query('SELECT state_data FROM app_change_state WHERE id = 1')->fetchColumn(), true);
        $defaults = is_array($state['ranking_history'] ?? null)
            ? $state['ranking_history']
            : ['default_organization' => null, 'default_list' => null];
        echo json_encode(['bodies' => $bodies, 'rankings' => $rankings, 'chart_defaults' => $defaults], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    admin_rankings_verify_csrf();
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($data)) admin_rankings_bad('Invalid request body.');

    if ($method === 'POST' && ($data['action'] ?? '') === 'save-chart-defaults') {
        $organization = trim((string)($data['default_organization'] ?? ''));
        $list = trim((string)($data['default_list'] ?? ''));
        if (strlen($organization) > 100 || strlen($list) > 320) admin_rankings_bad('Choose a valid default organization and ranking list.');
        if ($organization === '' && $list !== '') admin_rankings_bad('Choose an organization before setting a default ranking list.');
        if ($organization !== '') {
            $organizationQuery = $pdo->prepare('SELECT 1 FROM rankings r
                INNER JOIN ranking_bodies bodies ON bodies.ranking_body_id = r.ranking_body_id
                WHERE bodies.name = ? AND bodies.short_name <> \'DEMO\' AND r.rank_value IS NOT NULL LIMIT 1');
            $organizationQuery->execute([$organization]);
            if (!$organizationQuery->fetchColumn()) admin_rankings_bad('The default organization has no numeric ranking rows.', 404);
        }
        if ($list !== '') {
            $listQuery = $pdo->prepare('SELECT 1 FROM rankings r
                INNER JOIN ranking_bodies bodies ON bodies.ranking_body_id = r.ranking_body_id
                INNER JOIN ranking_types types ON types.ranking_type_id = r.ranking_type_id
                WHERE bodies.name = ? AND bodies.short_name <> \'DEMO\' AND types.name = ? AND r.rank_value IS NOT NULL LIMIT 1');
            $listQuery->execute([$organization, $list]);
            if (!$listQuery->fetchColumn()) admin_rankings_bad('The selected ranking list does not belong to this organization.', 404);
        }
        $save = $pdo->prepare("INSERT INTO app_change_state (id, state_data) VALUES (1,
            JSON_OBJECT('ranking_history', JSON_OBJECT('default_organization', ?, 'default_list', ?)))
            ON DUPLICATE KEY UPDATE state_data = JSON_SET(COALESCE(state_data, JSON_OBJECT()),
                '$.ranking_history', JSON_OBJECT('default_organization', ?, 'default_list', ?))");
        $organizationDefault = $organization !== '' ? $organization : null;
        $listDefault = $list !== '' ? $list : null;
        $save->execute([$organizationDefault, $listDefault, $organizationDefault, $listDefault]);
        echo json_encode(['success' => true, 'chart_defaults' => ['default_organization' => $organization ?: null, 'default_list' => $list ?: null]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    if ($method === 'POST') {
        $values = admin_rankings_payload($pdo, $data);
        $columns = array_keys($values);
        $quoted = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
        $insert = $pdo->prepare('INSERT INTO rankings (' . implode(', ', $quoted) . ', seed_managed) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ', 0)');
        $insert->execute(array_values($values));
        echo json_encode(admin_rankings_row($pdo, (int)$pdo->lastInsertId()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) admin_rankings_bad('Ranking row id is required.');
    if ($method === 'PUT' || $method === 'PATCH') {
        $values = admin_rankings_payload($pdo, $data, (int)$id);
        $sets = implode(', ', array_map(static fn(string $column): string => '`' . $column . '` = ?', array_keys($values)));
        $pdo->prepare('UPDATE rankings SET ' . $sets . ', seed_managed = 0 WHERE ranking_id = ?')->execute([...array_values($values), (int)$id]);
        echo json_encode(admin_rankings_row($pdo, (int)$id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($method === 'DELETE') {
        $delete = $pdo->prepare('DELETE FROM rankings WHERE ranking_id = ?');
        $delete->execute([(int)$id]);
        echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
        exit;
    }
    admin_rankings_bad('Method not allowed.', 405);
} catch (Throwable $exception) {
    error_log('IRIS ranking administration failed: ' . $exception->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['error' => 'Unable to manage ranking data.']);
}
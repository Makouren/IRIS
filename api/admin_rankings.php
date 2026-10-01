<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin'], true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function admin_rankings_bad(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function admin_rankings_require_admin(): void {
    requireRole(['super_admin'], true);
}

function admin_rankings_verify_csrf(): void {
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        admin_rankings_bad('Invalid CSRF token.', 419);
    }
}

function admin_rankings_bounds(string $display, $lowValue, $highValue): array {
    $low = $lowValue !== null && $lowValue !== '' ? filter_var($lowValue, FILTER_VALIDATE_INT) : null;
    $high = $highValue !== null && $highValue !== '' ? filter_var($highValue, FILTER_VALIDATE_INT) : null;
    if (($lowValue !== null && $lowValue !== '' && $low === false) || ($highValue !== null && $highValue !== '' && $high === false)) {
        admin_rankings_bad('Rank bounds must be whole numbers.');
    }
    if ($low === null && $high === null && $display !== '') {
        $clean = str_replace(',', '', trim($display));
        if (preg_match('/^=?\s*(\d+)\s*[-–—]\s*(\d+)$/', $clean, $match)) {
            $low = (int)$match[1];
            $high = (int)$match[2];
        } elseif (preg_match('/^=?\s*(\d+)\+$/', $clean, $match)) {
            $low = (int)$match[1];
        } elseif (preg_match('/^top\s*(\d+)$/i', $clean, $match)) {
            $low = 1;
            $high = (int)$match[1];
        } elseif (preg_match('/^=?\s*(\d+)$/', $clean, $match)) {
            $low = (int)$match[1];
            $high = (int)$match[1];
        }
    }
    if ($low !== null && $low < 0) admin_rankings_bad('Rank lower bound cannot be negative.');
    if ($high !== null && $high < 0) admin_rankings_bad('Rank upper bound cannot be negative.');
    if ($low !== null && $high !== null && $high < $low) admin_rankings_bad('Rank upper bound must be greater than or equal to the lower bound.');
    $value = $low === null ? null : ($high === null ? (float)$low : ($low + $high) / 2);
    return [$low, $high, $value];
}

function admin_rankings_payload(array $data, PDO $pdo, int $currentId = 0): array {
    // Organization (free-entry combobox)
    $orgName = trim((string)($data['organization'] ?? $data['ranking_body_name'] ?? $data['ranking_body_id'] ?? ''));
    if (is_numeric($orgName) && (int)$orgName > 0) {
        $bodyCheck = $pdo->prepare('SELECT name FROM ranking_bodies WHERE id = ?');
        $bodyCheck->execute([(int)$orgName]);
        $found = $bodyCheck->fetchColumn();
        if ($found) $orgName = $found;
    }
    if ($orgName === '') admin_rankings_bad('Organization name is required.');
    if (strlen($orgName) > 100) admin_rankings_bad('Organization name must not exceed 100 characters.');

    $bodyStmt = $pdo->prepare('SELECT id, name FROM ranking_bodies WHERE LOWER(name) = LOWER(?) OR LOWER(short_name) = LOWER(?) LIMIT 1');
    $bodyStmt->execute([$orgName, $orgName]);
    $existingBody = $bodyStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingBody) {
        $bodyId = (int)$existingBody['id'];
        $orgName = $existingBody['name'];
    } else {
        $insertBody = $pdo->prepare('INSERT INTO ranking_bodies (name, short_name) VALUES (?, ?)');
        $insertBody->execute([$orgName, $orgName]);
        $bodyId = (int)$pdo->lastInsertId();
    }

    // Scope (free-entry combobox)
    $scopeInput = trim((string)($data['scope'] ?? $data['scope_name'] ?? $data['scope_id'] ?? ''));
    $scopeId = null;
    if ($scopeInput !== '' && strtolower($scopeInput) !== 'unassigned') {
        if (strlen($scopeInput) > 80) admin_rankings_bad('Scope name must not exceed 80 characters.');
        $scopeStmt = $pdo->prepare('SELECT id, name FROM ranking_scopes WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $scopeStmt->execute([$scopeInput]);
        $existingScope = $scopeStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingScope) {
            $scopeId = (int)$existingScope['id'];
        } else {
            $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM ranking_scopes')->fetchColumn();
            $insertScope = $pdo->prepare('INSERT INTO ranking_scopes (name, sort_order) VALUES (?, ?)');
            $insertScope->execute([$scopeInput, $nextOrder]);
            $scopeId = (int)$pdo->lastInsertId();
        }
    }

    // Level (free-entry combobox)
    $levelInput = trim((string)($data['level'] ?? ''));
    $level = null;
    if ($levelInput !== '' && strtolower($levelInput) !== 'unassigned') {
        if (strlen($levelInput) > 80) admin_rankings_bad('Level must not exceed 80 characters.');
        $lvlStmt = $pdo->prepare('SELECT name FROM ranking_levels WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $lvlStmt->execute([$levelInput]);
        $foundLvl = $lvlStmt->fetchColumn();
        if ($foundLvl) {
            $level = $foundLvl;
        } else {
            $rnkLvlStmt = $pdo->prepare('SELECT DISTINCT level FROM rankings WHERE LOWER(level) = LOWER(?) AND level IS NOT NULL AND level <> "" LIMIT 1');
            $rnkLvlStmt->execute([$levelInput]);
            $foundRnkLvl = $rnkLvlStmt->fetchColumn();
            $level = $foundRnkLvl ?: $levelInput;
            try {
                $nextLvlOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM ranking_levels')->fetchColumn();
                $pdo->prepare('INSERT IGNORE INTO ranking_levels (name, sort_order) VALUES (?, ?)')->execute([$level, $nextLvlOrder]);
            } catch (Throwable $e) {}
        }
    }

    // Ranking Type (free-entry combobox)
    $typeInput = trim((string)($data['ranking_type'] ?? ''));
    if ($typeInput === '') $typeInput = $orgName;
    if (strlen($typeInput) > 100) admin_rankings_bad('Ranking type must not exceed 100 characters.');

    $typeStmt = $pdo->prepare('SELECT ranking_type FROM rankings WHERE LOWER(ranking_type) = LOWER(?) AND ranking_type IS NOT NULL AND ranking_type <> "" LIMIT 1');
    $typeStmt->execute([$typeInput]);
    $foundType = $typeStmt->fetchColumn();
    $rankingType = $foundType ?: $typeInput;

    // Category & Edition
    $categoryInput = trim((string)($data['category'] ?? 'Overall'));
    if ($categoryInput === '') $categoryInput = 'Overall';
    if (strlen($categoryInput) > 100) admin_rankings_bad('Category must not exceed 100 characters.');
    $catStmt = $pdo->prepare('SELECT category FROM rankings WHERE LOWER(category) = LOWER(?) AND category IS NOT NULL AND category <> "" LIMIT 1');
    $catStmt->execute([$categoryInput]);
    $foundCat = $catStmt->fetchColumn();
    $category = $foundCat ?: $categoryInput;

    $editionInput = trim((string)($data['edition'] ?? 'Annual'));
    if ($editionInput === '') $editionInput = 'Annual';
    if (strlen($editionInput) > 80) admin_rankings_bad('Edition must not exceed 80 characters.');
    $edStmt = $pdo->prepare('SELECT edition FROM rankings WHERE LOWER(edition) = LOWER(?) AND edition IS NOT NULL AND edition <> "" LIMIT 1');
    $edStmt->execute([$editionInput]);
    $foundEd = $edStmt->fetchColumn();
    $edition = $foundEd ?: $editionInput;

    // Year
    $yearRaw = $data['year'] ?? date('Y');
    $year = filter_var($yearRaw, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > 2200) admin_rankings_bad('Year must be between 1900 and 2200.');

    // Duplicate Check: organization + ranking_type + scope + level + year + edition + category
    $dupCheck = $pdo->prepare('
        SELECT r.id FROM rankings r
        WHERE r.ranking_body_id = ?
          AND LOWER(r.ranking_type) = LOWER(?)
          AND (r.scope_id = ? OR (r.scope_id IS NULL AND ? IS NULL))
          AND (LOWER(r.level) = LOWER(?) OR (r.level IS NULL AND ? IS NULL))
          AND r.year = ?
          AND LOWER(r.edition) = LOWER(?)
          AND LOWER(r.category) = LOWER(?)
          AND r.id <> ?
        LIMIT 1
    ');
    $dupCheck->execute([
        $bodyId,
        $rankingType,
        $scopeId, $scopeId,
        $level, $level,
        (int)$year,
        $edition,
        $category,
        $currentId
    ]);
    $dupId = $dupCheck->fetchColumn();
    if ($dupId) {
        http_response_code(409);
        echo json_encode([
            'error' => 'This ranking already exists.',
            'duplicate_id' => (int)$dupId,
            'message' => 'This ranking already exists. Edit it instead?'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Ranks & Bounds
    $globalRank = trim((string)($data['global_rank'] ?? ''));
    $phRank = trim((string)($data['ph_rank'] ?? ''));
    $note = trim((string)($data['note'] ?? ''));
    $source = trim((string)($data['source'] ?? ''));
    $verificationStatus = trim((string)($data['verification_status'] ?? 'verified'));
    if (strlen($globalRank) > 50 || strlen($phRank) > 50) admin_rankings_bad('Rank values must not exceed 50 characters.');
    if (strlen($note) > 255) admin_rankings_bad('Note must not exceed 255 characters.');
    if (strlen($source) > 500) admin_rankings_bad('Source must not exceed 500 characters.');
    if (!in_array($verificationStatus, ['verified', 'inferred', 'conflicting', 'assumed', 'unverified'], true)) admin_rankings_bad('Select a valid verification status.');

    [$rankLow, $rankHigh, $rankValue] = admin_rankings_bounds($globalRank, $data['rank_low'] ?? null, $data['rank_high'] ?? null);

    return [
        (int)$bodyId,
        (int)$year,
        $category,
        $scopeId,
        $rankingType,
        $level,
        $edition,
        $globalRank !== '' ? $globalRank : null,
        $rankLow,
        $rankHigh,
        $rankValue,
        $phRank !== '' ? $phRank : null,
        parse_rank_to_value($phRank),
        $note !== '' ? $note : null,
        $source !== '' ? $source : null,
        $verificationStatus
    ];
}

function admin_rankings_row(PDO $pdo, int $id): array {
    $query = $pdo->prepare('SELECT r.id, r.ranking_body_id, r.scope_id, scopes.name AS scope_name,
              r.ranking_type, r.level, r.edition, r.rank_low, r.rank_high, r.source, r.verification_status, r.seed_managed,
              r.year, r.category, r.global_rank,
              CASE WHEN r.rank_low IS NOT NULL AND r.rank_high IS NOT NULL THEN (r.rank_low + r.rank_high) / 2
                  WHEN r.rank_low IS NOT NULL THEN r.rank_low ELSE r.rank_value END AS rank_value,
              r.ph_rank, r.ph_rank_value, r.note,
            bodies.name AS body_name, bodies.short_name AS body_short_name
        FROM rankings r INNER JOIN ranking_bodies bodies ON bodies.id = r.ranking_body_id
        LEFT JOIN ranking_scopes scopes ON scopes.id = r.scope_id WHERE r.id = ?');
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) admin_rankings_bad('Ranking row not found.', 404);
    return $row;
}

try {
    $pdo = db();
    admin_rankings_require_admin();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (($_GET['resource'] ?? '') === 'scopes') {
        if ($method === 'GET') {
            $scopes = $pdo->query('SELECT id, name, sort_order FROM ranking_scopes ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($scopes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        admin_rankings_verify_csrf();
        $scopeData = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($scopeData)) admin_rankings_bad('Invalid request body.');
        if ($method === 'POST') {
            $name = trim((string)($scopeData['name'] ?? ''));
            if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 80) {
                admin_rankings_bad('Scope name must be between 1 and 80 characters.');
            }
            $sortOrder = isset($scopeData['sort_order']) ? filter_var($scopeData['sort_order'], FILTER_VALIDATE_INT) : (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM ranking_scopes')->fetchColumn();
            if ($sortOrder === false) admin_rankings_bad('Scope order must be a whole number.');
            try {
                $insert = $pdo->prepare('INSERT INTO ranking_scopes (name, sort_order) VALUES (?, ?)');
                $insert->execute([$name, $sortOrder]);
            } catch (PDOException $exception) {
                admin_rankings_bad('A scope with that name already exists.', 409);
            }
            $query = $pdo->prepare('SELECT id, name, sort_order FROM ranking_scopes WHERE id = ?');
            $query->execute([(int)$pdo->lastInsertId()]);
            echo json_encode($query->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        $scopeId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($scopeId === false || $scopeId === null || $scopeId < 1) admin_rankings_bad('Scope id is required.');
        if ($method === 'PUT' || $method === 'PATCH') {
            $sets = [];
            $values = [];
            if (array_key_exists('name', $scopeData)) {
                $name = trim((string)$scopeData['name']);
                if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 80) {
                    admin_rankings_bad('Scope name must be between 1 and 80 characters.');
                }
                $sets[] = 'name = ?';
                $values[] = $name;
            }
            if (array_key_exists('sort_order', $scopeData)) {
                $sortOrder = filter_var($scopeData['sort_order'], FILTER_VALIDATE_INT);
                if ($sortOrder === false) admin_rankings_bad('Scope order must be a whole number.');
                $sets[] = 'sort_order = ?';
                $values[] = $sortOrder;
            }
            if (!$sets) admin_rankings_bad('No scope fields to update.');
            $values[] = $scopeId;
            try {
                $pdo->prepare('UPDATE ranking_scopes SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
            } catch (PDOException $exception) {
                admin_rankings_bad('A scope with that name already exists.', 409);
            }
            $query = $pdo->prepare('SELECT id, name, sort_order FROM ranking_scopes WHERE id = ?');
            $query->execute([$scopeId]);
            $scope = $query->fetch(PDO::FETCH_ASSOC);
            if (!$scope) admin_rankings_bad('Scope not found.', 404);
            echo json_encode($scope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($method === 'DELETE') {
            $delete = $pdo->prepare('DELETE FROM ranking_scopes WHERE id = ?');
            $delete->execute([$scopeId]);
            echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
            exit;
        }
        admin_rankings_bad('Method not allowed.', 405);
    }

    if (($_GET['resource'] ?? '') === 'levels') {
        if ($method === 'GET') {
            $levels = $pdo->query('SELECT id, name, sort_order FROM ranking_levels ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($levels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        admin_rankings_verify_csrf();
        $levelData = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($levelData)) admin_rankings_bad('Invalid request body.');
        if ($method === 'POST') {
            $name = trim((string)($levelData['name'] ?? ''));
            if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 80) {
                admin_rankings_bad('Level name must be between 1 and 80 characters.');
            }
            $sortOrder = isset($levelData['sort_order']) ? filter_var($levelData['sort_order'], FILTER_VALIDATE_INT) : (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM ranking_levels')->fetchColumn();
            if ($sortOrder === false) admin_rankings_bad('Level order must be a whole number.');
            try {
                $insert = $pdo->prepare('INSERT INTO ranking_levels (name, sort_order) VALUES (?, ?)');
                $insert->execute([$name, $sortOrder]);
            } catch (PDOException $exception) {
                admin_rankings_bad('A level with that name already exists.', 409);
            }
            $query = $pdo->prepare('SELECT id, name, sort_order FROM ranking_levels WHERE id = ?');
            $query->execute([(int)$pdo->lastInsertId()]);
            echo json_encode($query->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        $levelId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($levelId === false || $levelId === null || $levelId < 1) admin_rankings_bad('Level id is required.');
        if ($method === 'PUT' || $method === 'PATCH') {
            $sets = [];
            $values = [];
            if (array_key_exists('name', $levelData)) {
                $name = trim((string)$levelData['name']);
                if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 80) {
                    admin_rankings_bad('Level name must be between 1 and 80 characters.');
                }
                $sets[] = 'name = ?';
                $values[] = $name;
            }
            if (array_key_exists('sort_order', $levelData)) {
                $sortOrder = filter_var($levelData['sort_order'], FILTER_VALIDATE_INT);
                if ($sortOrder === false) admin_rankings_bad('Level order must be a whole number.');
                $sets[] = 'sort_order = ?';
                $values[] = $sortOrder;
            }
            if (!$sets) admin_rankings_bad('No level fields to update.');
            $values[] = $levelId;
            try {
                $pdo->prepare('UPDATE ranking_levels SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
            } catch (PDOException $exception) {
                admin_rankings_bad('A level with that name already exists.', 409);
            }
            $query = $pdo->prepare('SELECT id, name, sort_order FROM ranking_levels WHERE id = ?');
            $query->execute([$levelId]);
            $levelItem = $query->fetch(PDO::FETCH_ASSOC);
            if (!$levelItem) admin_rankings_bad('Level not found.', 404);
            echo json_encode($levelItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($method === 'DELETE') {
            $delete = $pdo->prepare('DELETE FROM ranking_levels WHERE id = ?');
            $delete->execute([$levelId]);
            echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
            exit;
        }
        admin_rankings_bad('Method not allowed.', 405);
    }

    if ($method === 'GET') {
        $bodies = $pdo->query('SELECT id, name, short_name FROM ranking_bodies ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $scopes = $pdo->query('SELECT id, name, sort_order FROM ranking_scopes ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $levels = $pdo->query('SELECT id, name, sort_order FROM ranking_levels ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $rankings = $pdo->query('SELECT r.id, r.ranking_body_id, r.scope_id, scopes.name AS scope_name,
            r.ranking_type, r.level, r.edition, r.rank_low, r.rank_high, r.source, r.verification_status, r.seed_managed,
            r.year, r.category, r.global_rank,
            CASE WHEN r.rank_low IS NOT NULL AND r.rank_high IS NOT NULL THEN (r.rank_low + r.rank_high) / 2
                 WHEN r.rank_low IS NOT NULL THEN r.rank_low ELSE r.rank_value END AS rank_value,
            r.ph_rank, r.ph_rank_value, r.note,
                bodies.name AS body_name, bodies.short_name AS body_short_name
            FROM rankings r INNER JOIN ranking_bodies bodies ON bodies.id = r.ranking_body_id
            LEFT JOIN ranking_scopes scopes ON scopes.id = r.scope_id
            ORDER BY r.year DESC, bodies.name ASC, r.category ASC, r.rank_value IS NULL ASC, r.rank_value ASC')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['bodies' => $bodies, 'scopes' => $scopes, 'levels' => $levels, 'rankings' => $rankings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    admin_rankings_verify_csrf();
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($data)) admin_rankings_bad('Invalid request body.');

    if ($method === 'POST') {
        $values = admin_rankings_payload($data, $pdo, 0);
        $insert = $pdo->prepare('INSERT INTO rankings (ranking_body_id, year, category, scope_id, ranking_type, level, edition, global_rank, rank_low, rank_high, rank_value, ph_rank, ph_rank_value, note, source, verification_status, seed_managed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
        $insert->execute($values);
        echo json_encode(admin_rankings_row($pdo, (int)$pdo->lastInsertId()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) admin_rankings_bad('Ranking row id is required.');
        $values = admin_rankings_payload($data, $pdo, (int)$id);
        $values[] = (int)$id;
        $pdo->prepare('UPDATE rankings SET ranking_body_id = ?, year = ?, category = ?, scope_id = ?, ranking_type = ?, level = ?, edition = ?, global_rank = ?, rank_low = ?, rank_high = ?, rank_value = ?, ph_rank = ?, ph_rank_value = ?, note = ?, source = ?, verification_status = ?, seed_managed = 0 WHERE id = ?')->execute($values);
        echo json_encode(admin_rankings_row($pdo, (int)$id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    if ($method === 'DELETE') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) admin_rankings_bad('Ranking row id is required.');
        $delete = $pdo->prepare('DELETE FROM rankings WHERE id = ?');
        $delete->execute([(int)$id]);
        echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
        exit;
    }

    admin_rankings_bad('Method not allowed.', 405);
} catch (Throwable $exception) {
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['error' => 'Unable to manage ranking data.']);
}
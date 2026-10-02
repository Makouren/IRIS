<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/LatestYearResolver.php';
require_once __DIR__ . '/../includes/helpers/SummaryCardHistory.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');

$pdo = db();

function ensure_admin_for_mutation(): void {
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}
$resource = $_GET['resource'] ?? '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
$recordId = $_GET['record_id'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    requireRole(['super_admin'], true);
    ensure_json_csrf();
}
if (($_SESSION['role'] ?? '') !== 'super_admin'
    && !in_array($resource, ['summary_cards', 'summary_card_history', 'summary_card_categories', 'field_colors'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function json_col($v, $fallback = null) {
    if ($v === null || $v === '') return $fallback;
    if (is_string($v)) {
        $decoded = json_decode($v, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }
    return $v;
}
function output_record(array $r): array {
    $r['extractedData'] = json_col($r['extractedData'], []);
    $r['graphDrafts'] = json_col($r['graphDrafts'], []);
    $r['metadata'] = json_col($r['metadata'], []);
    return $r;
}
function load_record(PDO $pdo, int $recordId, bool $lock = false): ?array {
    $sql = 'SELECT records.record_id AS id, records.file_name AS fileName, records.file_type AS fileType,
        records.file_size AS fileSize, records.scanned_at AS scannedAt, records.status, records.doc_type AS docType,
        records.raw_text AS rawText, records.extracted_data AS extractedData, records.graph_drafts AS graphDrafts,
        records.admin_notes AS adminNotes, records.metadata, records.updated_at AS updatedAt, records.uploaded_by,
        records.office_id, offices.office_name, records.uploaded_at, records.template_id, records.import_profile_id,
        COALESCE((SELECT import_profiles.destination FROM template_import_profiles import_profiles WHERE import_profiles.import_profile_id = records.import_profile_id LIMIT 1),
            (SELECT template_profiles.destination FROM template_import_profiles template_profiles WHERE template_profiles.template_id = records.template_id LIMIT 1)) AS import_destination,
        records.opened_at, templates.name AS template_name
        FROM records LEFT JOIN templates ON templates.template_id = records.template_id
        LEFT JOIN offices ON offices.office_id = records.office_id WHERE records.record_id = ?';
    if ($lock) $sql .= ' FOR UPDATE';
    $query = $pdo->prepare($sql);
    $query->execute([$recordId]);
    $record = $query->fetch(PDO::FETCH_ASSOC);
    return $record ?: null;
}
function output_graph(array $g): array {
    $g['labels'] = json_col($g['labels'], []);
    $g['values_data'] = json_col($g['values_data'], []);
    $g['chart_data'] = json_col($g['chart_data'] ?? null, json_col($g['chartData'] ?? null, null));
    if (in_array(strtolower((string)($g['chart_type'] ?? '')), ['polararea', 'polar-area', 'rose', 'nightingale'], true)) {
        $g['chart_type'] = 'bar';
        $g['chart_data'] = is_array($g['chart_data']) ? $g['chart_data'] : [];
        $g['chart_data']['irisConfig'] = array_merge($g['chart_data']['irisConfig'] ?? [], ['type' => 'bar']);
        unset($g['chart_data']['irisConfig']['roseMode']);
    }
    $g['colors'] = valid_graph_colors(json_col($g['colors'] ?? null, null));
    $g['value_axis_reversed'] = !empty($g['value_axis_reversed']);
    $g['rank_semantic'] = in_array(strtolower((string)($g['rank_semantic'] ?? '')), ['rank', 'true', '1'], true);
    $g['is_published'] = isset($g['is_published']) ? (bool)$g['is_published'] : false;
    return $g;
}
function load_saved_graph(PDO $pdo, int $graphId): ?array {
    $query = $pdo->prepare('SELECT saved_graphs.*, saved_graphs.graph_id AS id, saved_graphs.chart_options AS chart_data,
            records.record_id AS source_file_id, records.file_name AS source_file_name, records.file_type AS source_file_type
        FROM saved_graphs LEFT JOIN records ON records.record_id = saved_graphs.record_id
        WHERE saved_graphs.graph_id = ?');
    $query->execute([$graphId]);
    $graph = $query->fetch(PDO::FETCH_ASSOC);
    if (!$graph) return null;
    $options = json_col($graph['chart_data'] ?? null, []);
    $seriesQuery = $pdo->prepare('SELECT series_id, series_name, display_order FROM graph_series WHERE graph_id = ? ORDER BY display_order, series_id');
    $seriesQuery->execute([$graphId]);
    $seriesRows = $seriesQuery->fetchAll(PDO::FETCH_ASSOC);
    $series = [];
    foreach ($seriesRows as $seriesRow) {
        $pointsQuery = $pdo->prepare('SELECT label, value FROM graph_points WHERE series_id = ? ORDER BY display_order, point_id');
        $pointsQuery->execute([(int)$seriesRow['series_id']]);
        $points = $pointsQuery->fetchAll(PDO::FETCH_ASSOC);
        $series[] = [
            'name' => $seriesRow['series_name'],
            'data' => array_map(static fn(array $point): array => ['name' => $point['label'], 'value' => $point['value'] !== null ? (float)$point['value'] : null], $points)
        ];
    }
    $firstSeries = $series[0] ?? ['name' => null, 'data' => []];
    $graph['labels'] = array_map(static fn(array $point): ?string => $point['name'] === null ? null : (string)$point['name'], $firstSeries['data']);
    $graph['values_data'] = array_map(static fn(array $point) => $point['value'], $firstSeries['data']);
    $storedSeries = $options['series'] ?? [];
    if (is_array($storedSeries) && $storedSeries) {
        foreach ($storedSeries as $index => &$stored) {
            if (!is_array($stored)) continue;
            if (isset($series[$index])) {
                $stored['name'] = $series[$index]['name'] ?? ($stored['name'] ?? null);
                $stored['data'] = $series[$index]['data'];
            }
        }
        unset($stored);
        $options['series'] = $storedSeries;
    } elseif ($series) {
        $options['series'] = $series;
    }
    $graph['chart_data'] = $options;
    $colorQuery = $pdo->prepare('SELECT color FROM graph_colors WHERE graph_id = ? AND series_id IS NULL ORDER BY color_id');
    $colorQuery->execute([$graphId]);
    $colors = $colorQuery->fetchAll(PDO::FETCH_COLUMN);
    $graph['colors'] = $colors ?: null;
    return output_graph($graph);
}
function save_graph_relations(PDO $pdo, int $graphId, array $data): void {
    $pdo->prepare('DELETE FROM graph_colors WHERE graph_id = ?')->execute([$graphId]);
    $pdo->prepare('DELETE FROM graph_series WHERE graph_id = ?')->execute([$graphId]);
    $chartData = $data['chart_data'] ?? $data['chartData'] ?? $data['option'] ?? $data['config'] ?? [];
    $sourceSeries = is_array($chartData) && is_array($chartData['series'] ?? null) ? $chartData['series'] : [];
    if (!$sourceSeries && is_array($chartData) && is_array($chartData['datasets'] ?? null)) $sourceSeries = $chartData['datasets'];
    $labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
    $values = $data['values_data'] ?? $data['valuesData'] ?? $data['data'] ?? [];
    if (!is_array($values)) $values = [];
    if (!$sourceSeries) $sourceSeries = [['name' => $data['title'] ?? 'Value', 'data' => $values]];
    $insertSeries = $pdo->prepare('INSERT INTO graph_series (graph_id, series_name, display_order) VALUES (?, ?, ?)');
    $insertPoint = $pdo->prepare('INSERT INTO graph_points (series_id, label, value, display_order) VALUES (?, ?, ?, ?)');
    foreach (array_values($sourceSeries) as $seriesIndex => $series) {
        if (!is_array($series)) continue;
        $seriesIdQuery = $insertSeries;
        $seriesIdQuery->execute([$graphId, isset($series['name']) ? (string)$series['name'] : null, $seriesIndex]);
        $seriesId = (int)$pdo->lastInsertId();
        $points = is_array($series['data'] ?? null) ? $series['data'] : ($seriesIndex === 0 ? $values : []);
        foreach (array_values($points) as $pointIndex => $point) {
            $pointLabel = is_array($point) ? ($point['name'] ?? $point['label'] ?? null) : null;
            if ($pointLabel === null && $seriesIndex === 0) $pointLabel = $labels[$pointIndex] ?? null;
            $pointValue = is_array($point) ? ($point['rawValue'] ?? $point['value'] ?? null) : $point;
            $numericValue = is_numeric($pointValue) ? (float)$pointValue : null;
            $insertPoint->execute([$seriesId, $pointLabel !== null ? (string)$pointLabel : null, $numericValue, $pointIndex]);
        }
    }
    $colors = valid_graph_colors($data['colors'] ?? null) ?? [];
    $insertColor = $pdo->prepare('INSERT INTO graph_colors (graph_id, series_id, color) VALUES (?, NULL, ?)');
    foreach ($colors as $color) $insertColor->execute([$graphId, $color]);
}
function normalize_graph_type($type): string {
    $value = (string)($type ?? 'bar');
    if (in_array(strtolower($value), ['polararea', 'polar-area', 'rose', 'nightingale'], true)) return 'bar';
    foreach (['bar', 'line', 'pie', 'doughnut', 'rankedBar', 'nestedPie'] as $allowed) {
        if (strtolower($value) === strtolower($allowed)) return $allowed;
    }
    return 'bar';
}
function valid_graph_colors($colors): ?array {
    if (!is_array($colors) || !array_is_list($colors) || count($colors) > 1000) return null;
    foreach ($colors as $color) if (!is_string($color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) return null;
    return $colors ? array_map('strtoupper', $colors) : null;
}
function require_graph_colors(array $data): ?array {
    if (!array_key_exists('colors', $data) || $data['colors'] === null || $data['colors'] === []) return null;
    $colors = valid_graph_colors($data['colors']);
    if ($colors === null) bad('colors must be an array of six-digit HEX values.');
    return $colors;
}
function upload_storage_root(): ?string {
    $storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
    $realStorage = realpath($storagePath);
    if (!$realStorage || !is_dir($realStorage)) return null;
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot && (strcasecmp($realStorage, $documentRoot) === 0 || strncasecmp($realStorage, $documentRoot . DIRECTORY_SEPARATOR, strlen($documentRoot) + 1) === 0)) return null;
    return $realStorage;
}
function stored_upload_path(?string $name): ?string {
    if (!is_string($name) || !preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $name)) return null;
    $root = upload_storage_root();
    if (!$root) return null;
    $path = realpath($root . DIRECTORY_SEPARATOR . $name);
    return $path && dirname($path) === $root && is_file($path) ? $path : null;
}
function merge_trash_directory(bool $create = false): ?string {
    $root = upload_storage_root();
    if (!$root) return null;
    $path = $root . DIRECTORY_SEPARATOR . 'trash';
    if (!is_dir($path) && $create && !mkdir($path, 0750, true) && !is_dir($path)) return null;
    $real = realpath($path);
    return $real && dirname($real) === $root && is_dir($real) ? $real : null;
}
function valid_trash_id($trashId): bool {
    return is_string($trashId) && preg_match('/^[a-f0-9]{24}$/', $trashId) === 1;
}
function stored_file_in_use(PDO $pdo, string $name, string $exceptRecordId): bool {
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $name);
    $query = $pdo->prepare("SELECT record_id AS id, metadata FROM records WHERE record_id <> ? AND metadata LIKE ? ESCAPE '!' ");
    $query->execute([$exceptRecordId, '%"stored_file"%' . $escaped . '%']);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $metadata = json_col($row['metadata'] ?? null, []);
        if (is_array($metadata) && ($metadata['stored_file'] ?? null) === $name) return true;
    }
    return false;
}
function unlink_stored_upload_after_delete(PDO $pdo, array $record): void {
    $metadata = json_col($record['metadata'] ?? null, []);
    $name = is_array($metadata) ? (string)($metadata['stored_file'] ?? '') : '';
    if ($name === '') return;
    $path = stored_upload_path($name);
    if (!$path || stored_file_in_use($pdo, $name, (string)$record['id'])) return;
    if (!@unlink($path)) error_log('IRIS could not unlink stored upload after record deletion: ' . $name);
}
function cleanup_expired_merge_trash(string $trashDirectory): void {
    $cutoff = time() - 30 * 24 * 60 * 60;
    foreach (glob($trashDirectory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $manifestPath) {
        $trashId = basename($manifestPath, '.json');
        if (!valid_trash_id($trashId)) continue;
        $manifest = json_decode((string)@file_get_contents($manifestPath), true);
        $createdAt = is_array($manifest) ? strtotime((string)($manifest['created_at'] ?? '')) : false;
        if ($createdAt === false || $createdAt >= $cutoff) continue;
        $oldName = is_array($manifest) ? (string)($manifest['old_stored_file'] ?? '') : '';
        if (preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $oldName, $match)) {
            $trashedFile = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $match[1];
            if (is_file($trashedFile) && !@unlink($trashedFile)) error_log('IRIS could not expire trashed merge file: ' . $trashId);
        }
        if (!@unlink($manifestPath)) error_log('IRIS could not expire merge manifest: ' . $trashId);
    }
    foreach (glob($trashDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        $name = basename($path);
        if (!preg_match('/^([a-f0-9]{24})\.(xlsx|csv|tsv)$/', $name, $match) || !is_file($path)) continue;
        if (is_file($trashDirectory . DIRECTORY_SEPARATOR . $match[1] . '.json')) continue;
        $modified = filemtime($path);
        if ($modified !== false && $modified < $cutoff && !@unlink($path)) error_log('IRIS could not expire orphaned merge file: ' . $name);
    }
}
function load_merge_manifest(string $trashId): array {
    if (!valid_trash_id($trashId)) bad('Invalid recovery id.', 400);
    $trashDirectory = merge_trash_directory();
    $path = $trashDirectory ? $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.json' : '';
    if ($path === '' || !is_file($path)) bad('Recovery data has expired or was not found.', 404);
    $manifest = json_decode((string)file_get_contents($path), true);
    if (!is_array($manifest)) bad('Recovery manifest is unreadable.', 500);
    return [$trashDirectory, $path, $manifest];
}
function insert_row_from_snapshot(PDO $pdo, string $table, array $row): void {
    $primaryKeys = [
        'records' => 'record_id',
        'saved_graphs' => 'graph_id',
        'graph_series' => 'series_id',
        'graph_points' => 'point_id',
        'graph_colors' => 'color_id'
    ];
    if (!isset($primaryKeys[$table])) bad('Invalid recovery table.', 500);
    $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
    $fields = array_values(array_intersect($columns, array_keys($row)));
    if (!$fields || !in_array($primaryKeys[$table], $fields, true)) bad('Recovery snapshot does not match the current database schema.', 409);
    $quoted = array_map(static fn(string $field): string => '`' . $field . '`', $fields);
    $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $quoted) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
    $values = array_map(static fn(string $field) => $row[$field], $fields);
    $pdo->prepare($sql)->execute($values);
}
function insert_graph_from_snapshot(PDO $pdo, array $graph): void {
    $seriesRows = $graph['_series'] ?? [];
    $colorRows = $graph['_colors'] ?? [];
    unset($graph['_series'], $graph['_colors']);
    insert_row_from_snapshot($pdo, 'saved_graphs', $graph);
    foreach ($seriesRows as $series) {
        $points = $series['_points'] ?? [];
        unset($series['_points']);
        insert_row_from_snapshot($pdo, 'graph_series', $series);
        foreach ($points as $point) insert_row_from_snapshot($pdo, 'graph_points', $point);
    }
    foreach ($colorRows as $color) insert_row_from_snapshot($pdo, 'graph_colors', $color);
}
function normalize_field_key($value): string {
    return strtolower(preg_replace('/\s+/', ' ', trim((string)$value)) ?? '');
}
function valid_field_color($color): bool {
    return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1;
}
function bad(string $message, int $status=400): never {
    http_response_code($status); echo json_encode(['error'=>$message]); exit;
}
function ensure_json_csrf(): void {
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        bad('Invalid CSRF token.', 419);
    }
}
function summary_card_period_identity(string $cardId, string $display): array {
    try {
        return LatestYearResolver::normalize($display);
    } catch (RuntimeException) {
        return [
            'period_key' => 'L:' . substr(hash('sha256', $cardId . "\0" . $display), 0, 16),
            'period_sort' => 0,
            'period_precision' => 0,
            'period_label' => $display !== '' ? $display : 'Legacy current period'
        ];
    }
}
function summary_card_import_key(mixed $value): string {
    $key = (string)$value;
    if ($key === '' || $key !== trim($key) || strlen($key) > 100 || preg_match('/[\x00-\x1F\x7F]/', $key)) {
        bad('Global Label must contain 1 to 100 characters, have no surrounding whitespace, and contain no control characters.');
    }
    return $key;
}
function summary_card_manual_snapshot(PDO $pdo, array $card, bool $published, array $values = []): array {
    $cardId = (int)($card['card_id'] ?? $card['id'] ?? 0);
    $yearDateInput = trim((string)($values['year_date'] ?? ''));
    $period = summary_card_period_identity((string)$cardId, $yearDateInput);
    $mainValue = trim((string)($values['main_value'] ?? ''));
    $secondaryValue = trim((string)($values['secondary_value'] ?? ''));
    if ($mainValue === '') bad('main_value cannot be blank.');
    $secondaryValue = $secondaryValue !== '' ? $secondaryValue : null;
    $yearDate = null;
    if (preg_match('/^\d{4}$/', $yearDateInput)) $yearDate = $yearDateInput . '-01-01';
    elseif ($yearDateInput !== '' && ($timestamp = strtotime($yearDateInput)) !== false) $yearDate = date('Y-m-d', $timestamp);
    $existing = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE card_id = ? AND period_key = ? FOR UPDATE');
    $existing->execute([$cardId, $period['period_key']]);
    $snapshot = $existing->fetch(PDO::FETCH_ASSOC);
    if ($snapshot) return $snapshot;
    $periodValues = [
        $cardId, $period['period_key'], $period['period_label'], $period['period_sort'], $period['period_precision'],
        $mainValue, trim((string)($values['main_label'] ?? '')), trim((string)($values['secondary_label'] ?? '')),
        $secondaryValue, $yearDate, trim((string)($values['description'] ?? '')),
        trim((string)($values['secondary_description'] ?? '')), trim((string)($values['info_text'] ?? '')),
        trim((string)($values['source_info'] ?? 'Manual entry')), $published ? 1 : 0
    ];
    $pdo->prepare('INSERT INTO summary_card_periods (card_id, period_key, period_label, period_sort, period_precision, main_value, main_label, secondary_label, secondary_value, year_date, description, secondary_description, info_text, source_info, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute($periodValues);
    $insert = $pdo->prepare('INSERT INTO summary_card_snapshots (card_id, title, period_key, period_label, period_sort, period_precision, is_published, main_value, main_label, secondary_label, secondary_value, year_date, description, secondary_description, info_text, source_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $cardId, $card['title'], $period['period_key'], $period['period_label'], $period['period_sort'], $period['period_precision'], $published ? 1 : 0,
        $mainValue, $values['main_label'] ?? '', $values['secondary_label'] ?? '', $secondaryValue, $yearDate,
        $values['description'] ?? '', $values['secondary_description'] ?? '', $values['info_text'] ?? '',
        $values['source_info'] ?? 'Manual entry'
    ]);
    $existing->execute([$card['id'], $period['period_key']]);
    $saved = $existing->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare('INSERT INTO summary_card_period_changes (card_id, period_key, action, before_state, after_state, changed_by) VALUES (?, ?, "create", NULL, ?, ?)')->execute([
        $cardId, $period['period_key'], json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)($_SESSION['user_id'] ?? 0)
    ]);
    return $saved;
}
function summary_card_manual_audit(PDO $pdo, string $cardId, string $periodKey, string $action, array $before, array $after): void {
    $pdo->prepare('INSERT INTO summary_card_period_changes (card_id, period_key, action, before_state, after_state, changed_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $cardId, $periodKey, $action,
        json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (int)($_SESSION['user_id'] ?? 0)
    ]);
}
function summary_category_slug(string $name): string {
    $name = trim($name);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($length < 1 || $length > 40) bad('Category name must be between 1 and 40 characters.');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $normalized = strtolower($ascii === false ? $name : $ascii);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '', '-');
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) bad('Category name must contain letters or numbers.');
    return $slug;
}
function resolve_summary_categories(PDO $pdo, array $data): array {
    $categoryIds = [];
    if (array_key_exists('category_ids', $data)) {
        if (!is_array($data['category_ids'])) bad('category_ids must be an array.');
        $categoryIds = $data['category_ids'];
    } elseif (array_key_exists('category_id', $data) && $data['category_id'] !== null && $data['category_id'] !== '') {
        $categoryIds = [$data['category_id']];
    }

    foreach ($categoryIds as $categoryId) {
        if (!filter_var($categoryId, FILTER_VALIDATE_INT) || (int)$categoryId < 1) bad('category_ids must contain valid category ids.');
    }
    if ($categoryIds) {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $check = $pdo->prepare("SELECT category_id FROM summary_card_categories WHERE category_id IN ($placeholders)");
        $check->execute($categoryIds);
        if (count($check->fetchAll(PDO::FETCH_COLUMN)) !== count($categoryIds)) bad('One or more categories were not found.');
    }

    $categoryNames = $data['category_names'] ?? (array_key_exists('category_name', $data) ? [$data['category_name']] : []);
    if (!is_array($categoryNames)) bad('category_names must be an array.');
    foreach ($categoryNames as $rawName) {
        $name = trim((string)$rawName);
        $slug = summary_category_slug($name);
        $existing = $pdo->prepare('SELECT category_id FROM summary_card_categories WHERE slug = ? LIMIT 1');
        $existing->execute([$slug]);
        $categoryId = $existing->fetchColumn();
        if ($categoryId === false) {
            $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM summary_card_categories')->fetchColumn();
            try {
                $insert = $pdo->prepare('INSERT INTO summary_card_categories (name, slug, sort_order) VALUES (?, ?, ?)');
                $insert->execute([$name, $slug, $nextOrder]);
                $categoryId = $pdo->lastInsertId();
            } catch (PDOException $exception) {
                $existing->execute([$slug]);
                $categoryId = $existing->fetchColumn();
                if ($categoryId === false) throw $exception;
            }
        }
        $categoryIds[] = (int)$categoryId;
    }
    return array_values(array_unique(array_map('intval', $categoryIds)));
}
function save_summary_card_categories(PDO $pdo, string $cardId, array $categoryIds): void {
    $pdo->prepare('DELETE FROM summary_card_category_map WHERE card_id = ?')->execute([$cardId]);
    $insert = $pdo->prepare('INSERT INTO summary_card_category_map (card_id, category_id) VALUES (?, ?)');
    foreach ($categoryIds as $categoryId) $insert->execute([$cardId, $categoryId]);
}
function attach_summary_card_categories(PDO $pdo, array $cards): array {
    if (!$cards) return [];
    $cardIds = array_column($cards, 'id');
    $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
    $query = $pdo->prepare("SELECT mapping.card_id AS summary_card_id, categories.category_id AS id, categories.name, categories.slug
        FROM summary_card_category_map mapping
        INNER JOIN summary_card_categories categories ON categories.category_id = mapping.category_id
        WHERE mapping.card_id IN ($placeholders)
        ORDER BY categories.sort_order ASC, categories.name ASC");
    $query->execute($cardIds);
    $byCard = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $category) $byCard[$category['summary_card_id']][] = $category;
    foreach ($cards as &$card) {
        $assigned = $byCard[$card['id']] ?? [];
        $card['category_ids'] = array_map(static fn(array $category): int => (int)$category['id'], $assigned);
        $card['categories'] = array_map(static fn(array $category): array => [
            'id' => (int)$category['id'], 'name' => $category['name'], 'slug' => $category['slug']
        ], $assigned);
        $card['category_names'] = array_column($assigned, 'name');
        $card['category_slugs'] = array_column($assigned, 'slug');
        $card['category_name'] = implode(', ', $card['category_names']);
        $card['category_slug'] = $card['category_slugs'][0] ?? null;
    }
    unset($card);
    return $cards;
}

try {
    if ($resource === 'field_colors') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = $pdo->query('SELECT field_name AS field_key, field_name AS label, color, updated_at FROM field_colors ORDER BY field_name ASC')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['field_key'] = normalize_field_key($row['field_key']);
                $row['color'] = valid_field_color($row['color']) ? strtoupper($row['color']) : null;
            }
            unset($row);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            $data = json_input();
            $label = trim((string)($data['label'] ?? ''));
            $fieldKey = normalize_field_key($data['field_key'] ?? $label);
            $color = $data['color'] ?? null;
            if ($fieldKey === '' || $label === '') bad('A field label is required.');
            if (!valid_field_color($color)) bad('color must be a six-digit HEX value.');
            $stmt = $pdo->prepare('INSERT INTO field_colors (field_name, color) VALUES (?, ?) ON DUPLICATE KEY UPDATE color = VALUES(color), updated_at = CURRENT_TIMESTAMP');
            $stmt->execute([$fieldKey, strtoupper($color)]);
            $q = $pdo->prepare('SELECT field_name AS field_key, field_name AS label, color, updated_at FROM field_colors WHERE field_name = ?');
            $q->execute([$fieldKey]);
            echo json_encode($q->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            $fieldKey = normalize_field_key($_GET['field_key'] ?? '');
            if ($fieldKey === '') bad('field_key is required.');
            $stmt = $pdo->prepare('DELETE FROM field_colors WHERE field_name = ?');
            $stmt->execute([$fieldKey]);
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount() > 0]);
            exit;
        }
    }

    if ($resource === 'summary_card_categories') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = ($_SESSION['role'] ?? '') === 'super_admin'
                ? $pdo->query('SELECT category_id AS id, name, slug, sort_order FROM summary_card_categories ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC)
                : $pdo->query("SELECT DISTINCT categories.category_id AS id, categories.name, categories.slug, categories.sort_order
                    FROM summary_card_categories categories
                    INNER JOIN summary_card_category_map mapping ON mapping.category_id = categories.category_id
                    INNER JOIN summary_cards cards ON cards.card_id = mapping.card_id
                    WHERE cards.is_published = 1 ORDER BY categories.sort_order ASC, categories.name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        ensure_admin_for_mutation();
        ensure_json_csrf();
        $data = json_input();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string)($data['name'] ?? ''));
            $slug = summary_category_slug($name);
            $existing = $pdo->prepare('SELECT category_id AS id, name, slug, sort_order FROM summary_card_categories WHERE slug = ? LIMIT 1');
            $existing->execute([$slug]);
            $category = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                $insert = $pdo->prepare('INSERT INTO summary_card_categories (name, slug, sort_order) VALUES (?, ?, ?)');
                $insert->execute([$name, $slug, isset($data['sort_order']) ? (int)$data['sort_order'] : 0]);
                $id = (int)$pdo->lastInsertId();
                $existing->execute([$slug]);
                $category = $existing->fetch(PDO::FETCH_ASSOC);
            }
            echo json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            if ($id === null) bad('Category id is required.');
            $sets = [];
            $values = [];
            if (array_key_exists('name', $data)) {
                $name = trim((string)$data['name']);
                $slug = summary_category_slug($name);
                $sets[] = 'name = ?';
                $values[] = $name;
                $sets[] = 'slug = ?';
                $values[] = $slug;
            }
            if (array_key_exists('sort_order', $data)) {
                $sets[] = 'sort_order = ?';
                $values[] = (int)$data['sort_order'];
            }
            if (!$sets) bad('No category fields to update.');
            $values[] = (int)$id;
            try {
                $pdo->prepare('UPDATE summary_card_categories SET ' . implode(', ', $sets) . ' WHERE category_id = ?')->execute($values);
            } catch (PDOException $exception) {
                bad('A category with that name or slug already exists.', 409);
            }
            $query = $pdo->prepare('SELECT category_id AS id, name, slug, sort_order FROM summary_card_categories WHERE category_id = ?');
            $query->execute([(int)$id]);
            $category = $query->fetch(PDO::FETCH_ASSOC);
            if (!$category) bad('Category not found.', 404);
            echo json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            if ($id === null) bad('Category id is required.');
            $delete = $pdo->prepare('DELETE FROM summary_card_categories WHERE category_id = ?');
            $delete->execute([(int)$id]);
            echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
            exit;
        }
    }

    if ($resource === 'summary_card_history') {
        if ($id === null || trim((string)$id) === '') bad('Summary Card id is required.');
        $cardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ?');
        $cardQuery->execute([(string)$id]);
        $card = $cardQuery->fetch(PDO::FETCH_ASSOC);
        if (!$card) bad('Summary Card not found.', 404);
        $isSuperAdmin = (($_SESSION['role'] ?? '') === 'super_admin');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $allPeriods = SummaryCardHistory::periods($pdo, (string)$id);
            $showAdminHistory = $isSuperAdmin && (($_GET['view'] ?? '') === 'admin');
            $current = SummaryCardHistory::latest($allPeriods, true);
            $latestImported = SummaryCardHistory::latest($allPeriods);
            $historyVersion = SummaryCardHistory::version($card, $allPeriods);
            $changeRows = $pdo->prepare('SELECT period_key, action, changed_by, created_at FROM summary_card_period_changes WHERE card_id = ? ORDER BY created_at DESC, change_id DESC');
            $changeRows->execute([(string)$id]);
            $changesByPeriod = [];
            foreach ($changeRows->fetchAll(PDO::FETCH_ASSOC) as $change) $changesByPeriod[$change['period_key']][] = $change;
            $periods = $showAdminHistory ? $allPeriods : array_values(array_filter($allPeriods, static fn(array $period): bool => !empty($period['is_published'])));
            foreach ($periods as &$period) {
                $period['is_current_public'] = $current && $current['period_key'] === $period['period_key'];
                $period['row_version'] = $historyVersion;
                $period['changes'] = $changesByPeriod[$period['period_key']] ?? [];
            }
            unset($period);
            $selectedPeriod = trim((string)($_GET['period'] ?? ''));
            if ($selectedPeriod !== '') {
                if (!preg_match('/^[YQMDL]:/', $selectedPeriod)) {
                    try { $selectedPeriod = LatestYearResolver::normalize($selectedPeriod)['period_key']; }
                    catch (RuntimeException $exception) { bad($exception->getMessage()); }
                }
                $periods = array_values(array_filter($periods, static fn(array $period): bool => $period['period_key'] === $selectedPeriod));
                if (!$periods) bad('Summary Card period not found.', 404);
            }
            echo json_encode([
                'card' => ['id' => $card['id'], 'title' => $card['title']],
                'current_public_period' => $current['period_label'] ?? null,
                'latest_imported_period' => $latestImported['period_label'] ?? null,
                'periods' => $periods
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Method not allowed.', 405);
        ensure_admin_for_mutation();
        $data = json_input();
        $action = (string)($data['action'] ?? '');
        if (!in_array($action, ['correct', 'publish', 'unpublish'], true)) bad('Unknown Summary Card history action.');
        $periodInput = trim((string)($data['period_key'] ?? ''));
        if ($periodInput === '') bad('Period key is required.');
        try { $periodKey = preg_match('/^[YQMDL]:/', $periodInput) ? $periodInput : LatestYearResolver::normalize($periodInput)['period_key']; }
        catch (RuntimeException $exception) { bad($exception->getMessage()); }
        $expectedVersion = (string)($data['row_version'] ?? '');
        if ($expectedVersion === '') bad('Refresh the history before changing a period.');
        $pdo->beginTransaction();
        try {
            $lockedCardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
            $lockedCardQuery->execute([(string)$id]);
            $lockedCard = $lockedCardQuery->fetch(PDO::FETCH_ASSOC);
            $allPeriods = SummaryCardHistory::periods($pdo, (string)$id, true);
            if (!$lockedCard || !hash_equals($expectedVersion, SummaryCardHistory::version($lockedCard, $allPeriods))) {
                throw new RuntimeException('Summary Card history changed. Refresh before applying this action.', 409);
            }
            $before = null;
            foreach ($allPeriods as $period) if ($period['period_key'] === $periodKey) { $before = $period; break; }
            if (!$before) throw new RuntimeException('Summary Card period not found.', 404);
            if ($action === 'correct') {
                $allowed = ['title', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text', 'source_info'];
                $sets = [];
                $values = [];
                foreach ($allowed as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    $value = trim((string)$data[$field]);
                    if (strlen($value) > (in_array($field, ['description', 'secondary_description', 'info_text', 'source_info'], true) ? 65535 : 255)) {
                        throw new InvalidArgumentException($field . ' exceeds its storage limit.');
                    }
                    $sets[] = '`' . $field . '` = ?';
                    $values[] = $value;
                }
                if (!$sets) throw new InvalidArgumentException('Provide at least one period field to correct.');
                if (array_key_exists('main_value', $data) && trim((string)$data['main_value']) === '') throw new InvalidArgumentException('main_value cannot be blank.');
                $pdo->prepare('UPDATE summary_card_snapshots SET ' . implode(', ', $sets) . ' WHERE snapshot_id = ?')->execute([...$values, $before['snapshot_id']]);
            } else {
                $pdo->prepare('UPDATE summary_card_snapshots SET is_published = ? WHERE snapshot_id = ?')->execute([$action === 'publish' ? 1 : 0, $before['snapshot_id']]);
            }
            $changedQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ?');
            $changedQuery->execute([$before['snapshot_id']]);
            $after = $changedQuery->fetch(PDO::FETCH_ASSOC);
            SummaryCardHistory::syncLive($pdo, (string)$id);
            $pdo->prepare('INSERT INTO summary_card_period_changes (card_id, period_key, action, before_state, after_state, changed_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([
                (string)$id, $periodKey, $action,
                json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int)$_SESSION['user_id']
            ]);
            $pdo->commit();
            echo json_encode(['success' => true, 'period' => $after], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $status = in_array($exception->getCode(), [400, 404, 409], true) ? (int)$exception->getCode() : 422;
            http_response_code($status);
            echo json_encode(['error' => $exception->getMessage()]);
        }
        exit;
    }

    if ($resource === 'summary_cards') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = ($_SESSION['role'] ?? '') === 'super_admin'
                ? SummaryCardHistory::publishedCards($pdo, false)
                : SummaryCardHistory::publishedCards($pdo);
            $rows = attach_summary_card_categories($pdo, $rows);
            echo json_encode(array_map(static function (array $card): array {
                $card['display_precision'] = (int)($card['display_precision'] ?? 2);
                $card['display_order'] = (int)($card['display_order'] ?? 0);
                $card['is_published'] = (bool)($card['is_published'] ?? false);
                return $card;
            }, $rows));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            $data = json_input();
            $importKey = summary_card_import_key($data['import_key'] ?? bin2hex(random_bytes(12)));
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') bad('title is required');
            $mainValue = trim((string)($data['main_value'] ?? ''));
            if ($mainValue === '') bad('main_value cannot be blank.');
            $secondaryValue = trim((string)($data['secondary_value'] ?? ''));

            $displayPrecision = isset($data['display_precision']) ? (int)$data['display_precision'] : 2;
            $displayPrecision = max(0, min(2, $displayPrecision));
            $published = !empty($data['is_published']) || (!array_key_exists('is_published', $data) && !empty($data['published']));

            $pdo->beginTransaction();
            try {
                $categoryIds = resolve_summary_categories($pdo, $data);
                $collision = $pdo->prepare('SELECT card_id FROM summary_cards WHERE import_key = ? FOR UPDATE');
                $collision->execute([$importKey]);
                if ($collision->fetchColumn()) throw new RuntimeException('Another Summary Card already uses this Global Label.', 409);
                $stmt = $pdo->prepare('INSERT INTO summary_cards (import_key, title, display_order, display_precision, is_published) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $importKey,
                    $title,
                    isset($data['display_order']) ? (int)$data['display_order'] : 0,
                    $displayPrecision,
                    $published ? 1 : 0
                ]);
                $id = (int)$pdo->lastInsertId();
                save_summary_card_categories($pdo, (string)$id, $categoryIds);
                $createdQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
                $createdQuery->execute([$id]);
                $createdCard = $createdQuery->fetch(PDO::FETCH_ASSOC);
                summary_card_manual_snapshot($pdo, $createdCard, $published, $data);
                SummaryCardHistory::syncLive($pdo, (string)$id);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($exception instanceof RuntimeException && $exception->getCode() === 409) bad($exception->getMessage(), 409);
                if ($exception instanceof PDOException && (string)($exception->errorInfo[0] ?? '') === '23000') bad('Another Summary Card already uses this Global Label.', 409);
                throw $exception;
            }

            $savedCards = array_values(array_filter(SummaryCardHistory::publishedCards($pdo, false), static fn(array $card): bool => (int)$card['id'] === $id));
            echo json_encode(attach_summary_card_categories($pdo, $savedCards)[0] ?? null);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            if ($id === null) bad('Summary card id is required');
            $data = json_input();
            $contentFields = ['title','main_value','main_label','year_date','secondary_label','secondary_value','description','secondary_description','info_text'];
            $configFields = ['display_order', 'display_precision'];
            $hasIdentityUpdate = array_key_exists('import_key', $data);
            $importKey = $hasIdentityUpdate ? summary_card_import_key($data['import_key']) : null;
            $hasContentUpdate = (bool)array_intersect($contentFields, array_keys($data));
            $hasConfigUpdate = (bool)array_intersect($configFields, array_keys($data));
            $hasPublicationUpdate = array_key_exists('is_published', $data);
            $hasCategoryUpdate = array_key_exists('category_ids', $data) || array_key_exists('category_names', $data) || array_key_exists('category_id', $data) || array_key_exists('category_name', $data);
            $categoryIds = $hasCategoryUpdate ? resolve_summary_categories($pdo, $data) : [];
            if (!$hasIdentityUpdate && !$hasContentUpdate && !$hasConfigUpdate && !$hasPublicationUpdate && !$hasCategoryUpdate) bad('No fields to update');
            if (array_key_exists('main_value', $data) && trim((string)$data['main_value']) === '') bad('main_value cannot be blank');
            $pdo->beginTransaction();
            try {
                $locked = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
                $locked->execute([(string)$id]);
                $card = $locked->fetch(PDO::FETCH_ASSOC);
                if (!$card) throw new RuntimeException('Summary Card not found.', 404);
                $periods = SummaryCardHistory::periods($pdo, (string)$id, true);
                $beforeIdentity = $card;
                if ($hasIdentityUpdate && $importKey !== (string)$card['import_key']) {
                    $collision = $pdo->prepare('SELECT card_id FROM summary_cards WHERE import_key = ? AND card_id <> ? FOR UPDATE');
                    $collision->execute([$importKey, $id]);
                    if ($collision->fetchColumn()) throw new RuntimeException('Another Summary Card already uses this Global Label.', 409);
                }
                if (!$periods) {
                    summary_card_manual_snapshot($pdo, $card, (bool)$card['is_published'], $data);
                    $periods = SummaryCardHistory::periods($pdo, (string)$id, true);
                }
                if ($hasContentUpdate) {
                    $target = SummaryCardHistory::latest($periods, true) ?? SummaryCardHistory::latest($periods);
                    $sets = [];
                    $values = [];
                    foreach ($contentFields as $field) {
                        if (!array_key_exists($field, $data)) continue;
                        $value = trim((string)$data[$field]);
                        if (strlen($value) > (in_array($field, ['description', 'secondary_description', 'info_text'], true) ? 65535 : 255)) {
                            throw new InvalidArgumentException($field . ' exceeds its storage limit.');
                        }
                        if ($field === 'year_date' && $value !== '') {
                            if (preg_match('/^\d{4}$/', $value)) $value .= '-01-01';
                            elseif (($timestamp = strtotime($value)) !== false) $value = date('Y-m-d', $timestamp);
                            else throw new InvalidArgumentException('year_date must be a valid date or year.');
                        }
                        $sets[] = '`' . $field . '` = ?';
                        $values[] = $value;
                    }
                    $before = $target;
                    $pdo->prepare('UPDATE summary_card_snapshots SET ' . implode(', ', $sets) . ' WHERE snapshot_id = ?')->execute([...$values, $target['snapshot_id']]);
                    $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ?');
                    $snapshotQuery->execute([$target['snapshot_id']]);
                    $after = $snapshotQuery->fetch(PDO::FETCH_ASSOC);
                    summary_card_manual_audit($pdo, (string)$id, (string)$target['period_key'], 'correct', $before, $after);
                    $periods = SummaryCardHistory::periods($pdo, (string)$id, true);
                }
                if ($hasPublicationUpdate && !empty($data['is_published']) !== !empty($card['is_published'])) {
                    $target = !empty($data['is_published'])
                        ? SummaryCardHistory::latest($periods)
                        : SummaryCardHistory::latest($periods, true);
                    if ($target) {
                        $pdo->prepare('UPDATE summary_card_snapshots SET is_published = ? WHERE snapshot_id = ?')->execute([!empty($data['is_published']) ? 1 : 0, $target['snapshot_id']]);
                        $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ?');
                        $snapshotQuery->execute([$target['snapshot_id']]);
                        $after = $snapshotQuery->fetch(PDO::FETCH_ASSOC);
                        summary_card_manual_audit($pdo, (string)$id, (string)$target['period_key'], !empty($data['is_published']) ? 'publish' : 'unpublish', $target, $after);
                    }
                }
                $cardSets = [];
                $cardValues = [];
                if ($hasIdentityUpdate) { $cardSets[] = 'import_key = ?'; $cardValues[] = $importKey; }
                if (array_key_exists('display_order', $data)) { $cardSets[] = 'display_order = ?'; $cardValues[] = (int)$data['display_order']; }
                if (array_key_exists('display_precision', $data)) { $cardSets[] = 'display_precision = ?'; $cardValues[] = max(0, min(2, (int)$data['display_precision'])); }
                if ($cardSets) $pdo->prepare('UPDATE summary_cards SET ' . implode(', ', $cardSets) . ' WHERE card_id = ?')->execute([...$cardValues, $id]);
                if ($hasCategoryUpdate) save_summary_card_categories($pdo, (string)$id, $categoryIds);
                SummaryCardHistory::syncLive($pdo, (string)$id);
                if ($hasIdentityUpdate && $importKey !== (string)$beforeIdentity['import_key']) {
                    $period = SummaryCardHistory::latest($periods);
                    if ($period) {
                        $updatedCardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ?');
                        $updatedCardQuery->execute([(string)$id]);
                        summary_card_manual_audit($pdo, (string)$id, (string)$period['period_key'], 'identity', $beforeIdentity, $updatedCardQuery->fetch(PDO::FETCH_ASSOC));
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($exception instanceof RuntimeException && in_array($exception->getCode(), [404, 409], true)) bad($exception->getMessage(), (int)$exception->getCode());
                if ($exception instanceof PDOException && (string)($exception->errorInfo[0] ?? '') === '23000') bad('Another Summary Card already uses this Global Label.', 409);
                throw $exception;
            }
            $savedCards = array_values(array_filter(SummaryCardHistory::publishedCards($pdo, false), static fn(array $card): bool => (int)$card['id'] === (int)$id));
            echo json_encode(attach_summary_card_categories($pdo, $savedCards)[0] ?? null);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            if ($id === null) bad('Summary card id is required');
            $pdo->prepare('DELETE FROM summary_cards WHERE card_id = ?')->execute([$id]);
            echo json_encode(['success' => true, 'deleted_id' => $id]);
            exit;
        }
    }

    if ($resource === 'records') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $excludeImportRecords = ($_GET['exclude_import_records'] ?? '') === '1';
            $importRecordExclusion = $excludeImportRecords
                ? " AND COALESCE(
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(records.metadata, '$.upload_purpose')), ''),
                        (SELECT upload_profiles.destination FROM template_import_profiles upload_profiles WHERE upload_profiles.import_profile_id = records.import_profile_id LIMIT 1),
                        ''
                    ) NOT IN ('ranking_history', 'summary_cards')"
                : '';
            if ($id !== null) {
                $pdo->prepare('UPDATE records SET opened_at = COALESCE(opened_at, NOW()) WHERE record_id = ?')->execute([(int)$id]);
                $q = $pdo->prepare('SELECT records.record_id AS id, records.file_name AS fileName, records.file_type AS fileType,
                    records.file_size AS fileSize, records.scanned_at AS scannedAt, records.status, records.doc_type AS docType,
                    records.raw_text AS rawText, records.extracted_data AS extractedData, records.graph_drafts AS graphDrafts,
                    records.admin_notes AS adminNotes, records.metadata, records.updated_at AS updatedAt, records.uploaded_by,
                    records.office_id, offices.office_name, records.uploaded_at, records.template_id, records.import_profile_id,
                    COALESCE((SELECT import_profiles.destination FROM template_import_profiles import_profiles WHERE import_profiles.import_profile_id = records.import_profile_id LIMIT 1),
                        (SELECT template_profiles.destination FROM template_import_profiles template_profiles WHERE template_profiles.template_id = records.template_id LIMIT 1)) AS import_destination,
                    records.opened_at, templates.name AS template_name
                    FROM records LEFT JOIN templates ON templates.template_id = records.template_id
                    LEFT JOIN offices ON offices.office_id = records.office_id WHERE records.record_id=?' . $importRecordExclusion); $q->execute([(int)$id]);
                $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) bad('Record not found',404);
                echo json_encode(output_record($r)); exit;
            }
            $rows = $pdo->query('SELECT records.record_id AS id, records.file_name AS fileName, records.file_type AS fileType,
                records.file_size AS fileSize, records.scanned_at AS scannedAt, records.status, records.doc_type AS docType,
                records.raw_text AS rawText, records.extracted_data AS extractedData, records.graph_drafts AS graphDrafts,
                records.admin_notes AS adminNotes, records.metadata, records.updated_at AS updatedAt, records.uploaded_by,
                records.office_id, offices.office_name, records.uploaded_at, records.template_id, records.import_profile_id,
                COALESCE((SELECT import_profiles.destination FROM template_import_profiles import_profiles WHERE import_profiles.import_profile_id = records.import_profile_id LIMIT 1),
                    (SELECT template_profiles.destination FROM template_import_profiles template_profiles WHERE template_profiles.template_id = records.template_id LIMIT 1)) AS import_destination,
                records.opened_at, templates.name AS template_name
                FROM records LEFT JOIN templates ON templates.template_id = records.template_id
                LEFT JOIN offices ON offices.office_id = records.office_id WHERE 1=1' . $importRecordExclusion . ' ORDER BY records.scanned_at DESC, records.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array_map('output_record',$rows)); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'stage-restore') {
            ensure_admin_for_mutation();
            $existingId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$existingId || $existingId < 1) bad('Existing record id is required.');
            $data = json_input();
            $officeId = filter_var($data['office_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$officeId || $officeId < 1 || $officeId === $existingId) bad('A different office record id is required.');
            $trashDirectory = merge_trash_directory(true);
            if (!$trashDirectory) bad('Private merge recovery storage is unavailable.', 500);
            cleanup_expired_merge_trash($trashDirectory);
            $trashId = bin2hex(random_bytes(12));
            $manifestPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.json';
            $temporaryPath = $manifestPath . '.tmp';
            $pdo->beginTransaction();
            try {
                $oldQuery = $pdo->prepare('SELECT * FROM records WHERE record_id=? FOR UPDATE');
                $oldQuery->execute([$existingId]);
                $oldRow = $oldQuery->fetch(PDO::FETCH_ASSOC);
                if (!$oldRow) bad('Existing record not found.', 404);
                $officeQuery = $pdo->prepare('SELECT records.*, offices.office_name FROM records LEFT JOIN offices ON offices.office_id = records.office_id WHERE records.record_id=? FOR UPDATE');
                $officeQuery->execute([$officeId]);
                $officeRow = $officeQuery->fetch(PDO::FETCH_ASSOC);
                if (!$officeRow) bad('Office upload not found.', 404);
                if (strtolower((string)($officeRow['status'] ?? '')) !== 'pending review') bad('The office upload must still be Pending Review.', 409);
                if (empty($officeRow['uploaded_by']) || empty($officeRow['office_name'])) bad('The selected record is not an office upload.', 409);
                if (!in_array(strtolower((string)($officeRow['file_type'] ?? '')), ['xlsx', 'csv', 'tsv'], true)) bad('The office upload is not a supported spreadsheet.', 409);
                if (empty($oldRow['template_id']) || empty($officeRow['template_id']) || (string)$oldRow['template_id'] !== (string)$officeRow['template_id']) bad('Both records must be assigned to the same template.', 409);
                $oldMetadata = json_col($oldRow['metadata'] ?? null, []);
                $officeMetadata = json_col($officeRow['metadata'] ?? null, []);
                if (!is_array($oldMetadata)) $oldMetadata = [];
                if (!is_array($officeMetadata)) $officeMetadata = [];
                $graphQuery = $pdo->prepare('SELECT * FROM saved_graphs WHERE record_id=?');
                $graphQuery->execute([$officeId]);
                $officeGraphs = $graphQuery->fetchAll(PDO::FETCH_ASSOC);
                foreach ($officeGraphs as &$graph) {
                    $seriesQuery = $pdo->prepare('SELECT * FROM graph_series WHERE graph_id = ? ORDER BY display_order, series_id');
                    $seriesQuery->execute([(int)$graph['graph_id']]);
                    $graph['_series'] = $seriesQuery->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($graph['_series'] as &$series) {
                        $pointsQuery = $pdo->prepare('SELECT * FROM graph_points WHERE series_id = ? ORDER BY display_order, point_id');
                        $pointsQuery->execute([(int)$series['series_id']]);
                        $series['_points'] = $pointsQuery->fetchAll(PDO::FETCH_ASSOC);
                    }
                    unset($series);
                    $colorsQuery = $pdo->prepare('SELECT * FROM graph_colors WHERE graph_id = ? ORDER BY color_id');
                    $colorsQuery->execute([(int)$graph['graph_id']]);
                    $graph['_colors'] = $colorsQuery->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($graph);
                $oldStoredFile = (string)($oldMetadata['stored_file'] ?? '');
                if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $oldStoredFile)) $oldStoredFile = null;
                $manifest = [
                    'trash_id' => $trashId,
                    'created_at' => gmdate('c'),
                    'old_id' => (string)$existingId,
                    'office_id' => (string)$officeId,
                    'old_row' => $oldRow,
                    'office_row' => $officeRow,
                    'office_saved_graphs' => $officeGraphs,
                    'old_stored_file' => $oldStoredFile,
                ];
                $encoded = json_encode($manifest, JSON_THROW_ON_ERROR);
                if (file_put_contents($temporaryPath, $encoded, LOCK_EX) === false || !rename($temporaryPath, $manifestPath)) {
                    @unlink($temporaryPath);
                    bad('Unable to write merge recovery manifest.', 500);
                }
                chmod($manifestPath, 0640);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                @unlink($temporaryPath);
                @unlink($manifestPath);
                throw $exception;
            }
            echo json_encode(['success' => true, 'trash_id' => $trashId]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'trash-stored-file') {
            ensure_admin_for_mutation();
            if ($id === null || $id === '') bad('Existing record id is required.');
            $data = json_input();
            $trashId = (string)($data['trash_id'] ?? '');
            [$trashDirectory, $manifestPath, $manifest] = load_merge_manifest($trashId);
            if ((string)($manifest['old_id'] ?? '') !== (string)$id) bad('Recovery manifest does not match this record.', 409);
            $recordQuery = $pdo->prepare('SELECT metadata FROM records WHERE record_id=?');
            $recordQuery->execute([(int)$id]);
            $recordMetadata = json_col($recordQuery->fetchColumn(), []);
            if (!is_array($recordMetadata) || (string)($recordMetadata['merge']['trash_id'] ?? '') !== $trashId) bad('The record has not been staged for this merge.', 409);
            $name = $manifest['old_stored_file'] ?? null;
            if (!$name) {
                echo json_encode(['success' => true, 'moved' => false]);
                exit;
            }
            $sourcePath = stored_upload_path((string)$name);
            if (!$sourcePath) {
                $extension = pathinfo((string)$name, PATHINFO_EXTENSION);
                $existingTrashPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
                if (is_file($existingTrashPath)) {
                    echo json_encode(['success' => true, 'moved' => true]);
                    exit;
                }
                bad('The previous stored file could not be found.', 404);
            }
            if (stored_file_in_use($pdo, (string)$name, (string)$id)) bad('The previous file is still referenced by another record.', 409);
            $extension = pathinfo((string)$name, PATHINFO_EXTENSION);
            $destination = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
            if (file_exists($destination)) bad('A recovery file already exists for this merge.', 409);
            if (!rename($sourcePath, $destination)) bad('Unable to move the previous file to trash.', 500);
            chmod($destination, 0640);
            echo json_encode(['success' => true, 'moved' => true]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore-merge') {
            ensure_admin_for_mutation();
            $existingId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$existingId || $existingId < 1) bad('Existing record id is required.');
            $data = json_input();
            $trashId = (string)($data['trash_id'] ?? '');
            [$trashDirectory, $manifestPath, $manifest] = load_merge_manifest($trashId);
            if ((string)($manifest['old_id'] ?? '') !== (string)$existingId) bad('Recovery manifest does not match this record.', 409);
            $createdAt = strtotime((string)($manifest['created_at'] ?? ''));
            if ($createdAt === false || time() - $createdAt > 30 * 24 * 60 * 60) bad('Merge recovery has expired.', 410);
            $oldRow = $manifest['old_row'] ?? null;
            $officeRow = $manifest['office_row'] ?? null;
            if (!is_array($oldRow) || !is_array($officeRow) || (string)($officeRow['record_id'] ?? '') !== (string)($manifest['office_id'] ?? '')) bad('Recovery manifest is incomplete.', 500);
            $currentQuery = $pdo->prepare('SELECT metadata FROM records WHERE record_id=?');
            $currentQuery->execute([$existingId]);
            $currentRow = $currentQuery->fetch(PDO::FETCH_ASSOC);
            if (!$currentRow) bad('Merged record not found.', 404);
            $currentMetadata = json_col($currentRow['metadata'] ?? null, []);
            if (!is_array($currentMetadata) || (string)($currentMetadata['merge']['trash_id'] ?? '') !== $trashId) bad('This merge was already restored or replaced.', 409);
            $officeCheck = $pdo->prepare('SELECT record_id FROM records WHERE record_id=?');
            $officeCheck->execute([$manifest['office_id']]);
            if ($officeCheck->fetchColumn()) bad('The office upload id is already in use.', 409);

            $pdo->beginTransaction();
            try {
                $restoreFields = array_values(array_diff($pdo->query('SHOW COLUMNS FROM records')->fetchAll(PDO::FETCH_COLUMN), ['record_id']));
                $sets = [];
                $values = [];
                foreach ($restoreFields as $field) {
                    if (!array_key_exists($field, $oldRow)) continue;
                    $sets[] = '`' . $field . '`=?';
                    $values[] = $oldRow[$field];
                }
                $values[] = $existingId;
                $pdo->prepare('UPDATE records SET ' . implode(',', $sets) . ' WHERE record_id=?')->execute($values);
                insert_row_from_snapshot($pdo, 'records', $officeRow);
                foreach (($manifest['office_saved_graphs'] ?? []) as $graphRow) {
                    if (is_array($graphRow)) insert_graph_from_snapshot($pdo, $graphRow);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }

            $fileRestored = true;
            $oldName = $manifest['old_stored_file'] ?? null;
            if ($oldName) {
                $existingOriginal = stored_upload_path((string)$oldName);
                if (!$existingOriginal) {
                    $root = upload_storage_root();
                    $extension = pathinfo((string)$oldName, PATHINFO_EXTENSION);
                    $trashedFile = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
                    $destination = $root ? $root . DIRECTORY_SEPARATOR . $oldName : '';
                    if (!$root || !$destination || file_exists($destination) || !is_file($trashedFile) || !rename($trashedFile, $destination)) {
                        $fileRestored = false;
                        error_log('IRIS merge restore could not return prior file for recovery id ' . $trashId);
                    } else {
                        chmod($destination, 0640);
                    }
                }
            }
            if (!@unlink($manifestPath)) error_log('IRIS merge restore could not delete manifest ' . $trashId);
            $restoredRecord = load_record($pdo, $existingId);
            echo json_encode(['success' => true, 'record' => output_record($restoredRecord), 'file_restored' => $fileRestored]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-approve') {
            ensure_admin_for_mutation();
            $data=json_input();
            $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $q=$pdo->prepare("SELECT record_id AS id FROM records WHERE record_id IN ($ph)"); $q->execute(array_map('intval', $ids));
            $existing=$q->fetchAll(PDO::FETCH_COLUMN);
            if ($existing) {
                $ph2=implode(',',array_fill(0,count($existing),'?'));
                $pdo->prepare("UPDATE records SET status='Approved', updated_at=NOW() WHERE record_id IN ($ph2)")->execute($existing);
            }
            $set=array_fill_keys($existing,true); $results=[];
            foreach($ids as $x) $results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['bulk-publish', 'bulk-unpublish'], true)) {
            ensure_admin_for_mutation();
            $data = json_input();
            $ids = array_values(array_unique(array_filter($data['ids'] ?? [], fn($x) => is_scalar($x) && $x !== '')));
            if (!$ids) bad('ids must be a non-empty array');
            $published = $action === 'bulk-publish';
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->beginTransaction();
            try {
                $query = $pdo->prepare("SELECT record_id AS id FROM records WHERE record_id IN ($placeholders) FOR UPDATE");
                $query->execute(array_map('intval', $ids));
                $existing = $query->fetchAll(PDO::FETCH_COLUMN);
                $graphCount = 0;
                if ($existing) {
                    $recordPlaceholders = implode(',', array_fill(0, count($existing), '?'));
                    $pdo->prepare('UPDATE records SET status=?, updated_at=NOW() WHERE record_id IN ('.$recordPlaceholders.')')->execute(array_merge([$published ? 'Approved' : 'Pending Review'], $existing));
                    $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=? WHERE record_id IN ('.$recordPlaceholders.') AND is_published<>?');
                    $graphs->execute(array_merge([$published ? 1 : 0], $existing, [$published ? 1 : 0]));
                    $graphCount = $graphs->rowCount();
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $existingSet = array_fill_keys($existing, true);
            $results = [];
            foreach ($ids as $recordId) $results[] = ['id'=>$recordId,'success'=>isset($existingSet[$recordId]),'error'=>isset($existingSet[$recordId]) ? null : 'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing),'published_graph_count'=>$graphCount]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'unpublish') {
            ensure_admin_for_mutation();
            if ($id === null) bad('Record id is required');
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare('SELECT record_id FROM records WHERE record_id=? FOR UPDATE');
                $q->execute([(int)$id]);
                if (!$q->fetch()) {
                    $pdo->rollBack();
                    bad('Record not found', 404);
                }
                $pdo->prepare("UPDATE records SET status='Pending Review', updated_at=NOW() WHERE record_id=?")->execute([(int)$id]);
                $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=0 WHERE record_id=? AND is_published=1');
                $graphs->execute([(int)$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $q = $pdo->prepare('SELECT record_id AS id, file_name AS fileName, file_type AS fileType, file_size AS fileSize, scanned_at AS scannedAt, status, doc_type AS docType, raw_text AS rawText, extracted_data AS extractedData, graph_drafts AS graphDrafts, admin_notes AS adminNotes, metadata, updated_at AS updatedAt FROM records WHERE record_id=?');
            $q->execute([(int)$id]);
            echo json_encode(['success'=>true,'record'=>output_record($q->fetch(PDO::FETCH_ASSOC)),'unpublished_graph_count'=>$graphs->rowCount()]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-delete') {
            ensure_admin_for_mutation();
            $data=json_input(); $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $existingRows = [];
            $pdo->beginTransaction();
            try {
                $ph=implode(',',array_fill(0,count($ids),'?'));
                $q=$pdo->prepare("SELECT record_id AS id, metadata FROM records WHERE record_id IN ($ph) FOR UPDATE");
                $q->execute(array_map('intval', $ids));
                $existingRows=$q->fetchAll(PDO::FETCH_ASSOC);
                $existing=array_column($existingRows,'id');
                if ($existing) {
                    $ph2=implode(',',array_fill(0,count($existing),'?'));
                    $pdo->prepare("DELETE FROM saved_graphs WHERE record_id IN ($ph2)")->execute($existing);
                    $q=$pdo->prepare("DELETE FROM records WHERE record_id IN ($ph2)");$q->execute($existing);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $existing=array_column($existingRows,'id');
            foreach ($existingRows as $deletedRecord) unlink_stored_upload_after_delete($pdo, $deletedRecord);
            $set=array_fill_keys($existing,true); $results=[]; foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]); exit;
        }
        $data=json_input();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            if (!ALLOW_SUPER_ADMIN_UPLOAD && ($data['fileType'] ?? $data['type'] ?? '') !== 'manual') bad('File uploads are disabled for Super Admin.', 403);
            $account = $pdo->prepare('SELECT office_id FROM users WHERE user_id = ?');
            $account->execute([(int)$_SESSION['user_id']]);
            $officeId = $account->fetchColumn();
            $scannedAt = strtotime((string)($data['scannedAt'] ?? 'now'));
            $stmt = $pdo->prepare('INSERT INTO records (file_name, file_type, file_size, scanned_at, status, uploaded_by, office_id, uploaded_at, template_id, import_profile_id, doc_type, raw_text, extracted_data, graph_drafts, admin_notes, metadata, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $data['fileName'] ?? $data['name'] ?? 'Untitled', $data['fileType'] ?? $data['type'] ?? 'unknown',
                max(0, (int)($data['fileSize'] ?? $data['size'] ?? 0)), date('Y-m-d H:i:s', $scannedAt === false ? time() : $scannedAt),
                $data['status'] ?? 'Pending Review', (int)$_SESSION['user_id'], $officeId ?: null,
                filter_var($data['template_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                filter_var($data['import_profile_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                $data['docType'] ?? 'General Institutional Data', $data['rawText'] ?? '',
                json_encode($data['extractedData'] ?? $data['sheetsData'] ?? []), json_encode($data['graphDrafts'] ?? []),
                $data['adminNotes'] ?? '', json_encode($data['metadata'] ?? [])
            ]);
            $id2 = (int)$pdo->lastInsertId();
            echo json_encode(output_record(load_record($pdo, $id2) ?? []));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            $recordId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$recordId || $recordId < 1) bad('Record id is required'); $data=json_input();
            $fieldMap = ['fileName' => 'file_name', 'fileType' => 'file_type', 'fileSize' => 'file_size', 'status' => 'status', 'docType' => 'doc_type', 'rawText' => 'raw_text', 'adminNotes' => 'admin_notes'];
            $sets=[];$vals=[];
            if (array_key_exists('status',$data) && !in_array($data['status'], ['Pending Review','Approved','Needs Revision'], true)) bad('Invalid record status');
            foreach($fieldMap as $field => $column) if(array_key_exists($field,$data)){ $sets[]="`$column`=?";$vals[]=$field === 'fileSize' ? max(0, (int)$data[$field]) : $data[$field]; }
            foreach(['extractedData' => 'extracted_data', 'graphDrafts' => 'graph_drafts', 'metadata' => 'metadata'] as $field => $column) if(array_key_exists($field,$data)){ $sets[]="`$column`=?";$vals[]=json_encode($data[$field]); }
            if(array_key_exists('scannedAt',$data)){ $timestamp = strtotime((string)$data['scannedAt']); if ($timestamp === false) bad('Invalid scannedAt value.'); $sets[]='scanned_at=?';$vals[]=date('Y-m-d H:i:s',$timestamp); }
            if(!$sets) bad('No fields to update'); $sets[]='updated_at=NOW()';$vals[]=$recordId;$stmt=$pdo->prepare('UPDATE records SET '.implode(',',$sets).' WHERE record_id=?');$stmt->execute($vals);if(!$stmt->rowCount() && !load_record($pdo, $recordId))bad('Record not found',404);
            echo json_encode(output_record(load_record($pdo, $recordId) ?? []));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            $recordId = filter_var($id, FILTER_VALIDATE_INT);
            if(!$recordId || $recordId < 1)bad('Record id is required');
            $pdo->beginTransaction();
            try {
                $r = load_record($pdo, $recordId, true);if(!$r)bad('Record not found',404);
                $pdo->prepare('DELETE FROM saved_graphs WHERE record_id=?')->execute([$recordId]);
                $q=$pdo->prepare('DELETE FROM records WHERE record_id=?');$q->execute([$recordId]);
                if($q->rowCount() < 1) bad('Record not found',404);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            unlink_stored_upload_after_delete($pdo,$r);
            echo json_encode(['message'=>'Record and published graphs deleted','record'=>$r]);exit;
        }
    }

    if ($resource === 'graphs') {
        if ($_SERVER['REQUEST_METHOD']==='GET') {
            if($id!==null){$g=load_saved_graph($pdo,(int)$id);if(!$g)bad('Graph not found',404);echo json_encode($g);exit;}
            $query = $recordId !== null
                ? $pdo->prepare('SELECT graph_id FROM saved_graphs WHERE record_id = ? ORDER BY created_at DESC')
                : $pdo->query('SELECT graph_id FROM saved_graphs ORDER BY created_at DESC');
            if ($recordId !== null) $query->execute([(int)$recordId]);
            $rows = [];
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $graphId) {
                $graph = load_saved_graph($pdo, (int)$graphId);
                if ($graph) $rows[] = $graph;
            }
            echo json_encode($rows);exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='bulk-delete'){
            ensure_admin_for_mutation();
            $d=json_input();$ids=array_values(array_unique(array_filter(array_map('intval', $d['ids']??[]), static fn(int $value): bool => $value > 0)));if(!$ids)bad('ids must be a non-empty array');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT graph_id FROM saved_graphs WHERE graph_id IN ($ph)");$q->execute($ids);$existing=$q->fetchAll(PDO::FETCH_COLUMN);if($existing){$ph2=implode(',',array_fill(0,count($existing),'?'));$pdo->prepare("DELETE FROM saved_graphs WHERE graph_id IN ($ph2)")->execute($existing);} $set=array_fill_keys(array_map('intval', $existing),true);$results=[];foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Graph not found'];echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]);exit;}
        if ($_SERVER['REQUEST_METHOD']==='POST' && ($action==='publish' || $action==='unpublish' || $action==='toggle-publish')) {
            ensure_admin_for_mutation();
            $d=json_input();
            $published = isset($d['published']) ? (bool)$d['published'] : ($action === 'publish');
            if($id===null)bad('Graph id is required');
            $q=$pdo->prepare('SELECT graph_id FROM saved_graphs WHERE graph_id=?');$q->execute([(int)$id]);if(!$q->fetch())bad('Graph not found',404);
            $pdo->prepare('UPDATE saved_graphs SET is_published = ? WHERE graph_id = ?')->execute([$published ? 1 : 0,(int)$id]);
            echo json_encode(['success'=>true,'id'=>$id,'is_published'=>$published,'message'=>$published ? 'Graph published to the Observatory.' : 'Graph removed from the Observatory.']);exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'export') {
            ensure_admin_for_mutation();
            $d = json_input();
            $ids = array_values(array_unique(array_filter($d['snapshot_ids'] ?? [])));
            $mode = $d['mode'] ?? '';
            if (!$ids) bad('snapshot_ids must contain at least one graph id');
            if (!in_array($mode, ['database', 'script'], true)) bad('mode must be database or script');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query = $pdo->prepare("SELECT graph_id FROM saved_graphs WHERE graph_id IN ($placeholders) ORDER BY created_at DESC");
            $query->execute(array_map('intval', $ids));
            $graphs = [];
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $graphId) {
                $graph = load_saved_graph($pdo, (int)$graphId);
                if ($graph) $graphs[] = $graph;
            }
            if (!$graphs) bad('No saved graphs found', 404);
            if ($mode === 'script') {
                $out = '';
                foreach ($graphs as $graph) {
                    $labels = json_col($graph['labels'], []);
                    $values = json_col($graph['values_data'], []);
                    $chartData = json_col($graph['chart_data'] ?? null, []);
                    $nestedGroups = $chartData['irisConfig']['nestedGroups'] ?? [];
                    $nested = strtolower((string)($graph['chart_type'] ?? '')) === 'nestedpie' && is_array($nestedGroups) && count($nestedGroups) > 0;
                    $out .= 'Title: '.($graph['title'] ?? 'Saved Chart')."\nChart Type: ".strtoupper($graph['chart_type'] ?? 'bar')."\nSource Record ID: ".$graph['record_id']."\n\n".($nested ? 'Group: Category: Value' : 'Category: Value')."\n";
                    if ($nested) {
                        foreach ($nestedGroups as $group) {
                            foreach (($group['children'] ?? []) as $child) {
                                $out .= json_encode((string)($group['label'] ?? ''), JSON_UNESCAPED_UNICODE).': '.json_encode((string)($child['label'] ?? ''), JSON_UNESCAPED_UNICODE).': '.($child['rawValue'] ?? $child['value'] ?? '')."\n";
                            }
                        }
                    } else {
                        foreach ($labels as $index => $label) {
                            $out .= json_encode((string)($label ?: 'Item '.($index + 1)), JSON_UNESCAPED_UNICODE).': '.($values[$index] ?? '')."\n";
                        }
                    }
                    $out .= "\n\n";
                }
                header('Content-Type:text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="iris_saved_graphs_'.time().'.txt"');
                header('X-Export-Count: '.count($graphs));
                echo $out;
                exit;
            }
            $new = [];
            $pdo->beginTransaction();
            try {
                foreach ($graphs as $graph) {
                    $stmt = $pdo->prepare('INSERT INTO saved_graphs (record_id, title, chart_type, orientation, value_axis_reversed, value_axis_min, value_axis_max, rank_semantic, rank_value_min, rank_value_max, is_published, chart_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)');
                    $stmt->execute([(int)$graph['record_id'], $graph['title'], $graph['chart_type'], $graph['orientation'], (int)$graph['value_axis_reversed'], $graph['value_axis_min'], $graph['value_axis_max'], !empty($graph['rank_semantic']) ? 'rank' : null, $graph['rank_value_min'], $graph['rank_value_max'], json_encode($graph['chart_data'] ?? [])]);
                    $newId = (int)$pdo->lastInsertId();
                    save_graph_relations($pdo, $newId, $graph);
                    $new[] = $newId;
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            echo json_encode(['mode' => 'database', 'count' => count($new), 'exported_ids' => $new]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            ensure_admin_for_mutation();
            if ($id === null || $id === '') bad('Graph id is required.');
            $data = json_input();
            if (!array_key_exists('scope', $data) || !is_string($data['scope'])) bad('Scope must be text.');
            $scope = trim($data['scope']);
            $scopeLength = function_exists('mb_strlen') ? mb_strlen($scope, 'UTF-8') : strlen($scope);
            if ($scopeLength > 150) bad('Scope must not exceed 150 characters.');
            $pdo->prepare('UPDATE saved_graphs SET scope = ?, updated_at = NOW() WHERE graph_id = ?')->execute([$scope !== '' ? $scope : null, (int)$id]);
            $saved = load_saved_graph($pdo, (int)$id);
            if (!$saved) bad('Graph not found', 404);
            echo json_encode($saved);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            if (($_SESSION['role'] ?? '') !== 'super_admin') bad('Forbidden', 403);
            if ($id === null || $id === '') bad('Graph id is required.');
            $data = json_input();
            $existingQuery = $pdo->prepare('SELECT graph_id AS id, record_id FROM saved_graphs WHERE graph_id = ?');
            $existingQuery->execute([(int)$id]);
            $existing = $existingQuery->fetch(PDO::FETCH_ASSOC);
            if (!$existing) bad('Graph not found', 404);
            $recordId = $data['record_id'] ?? $data['recordId'] ?? null;
            if ($recordId !== null && (string)$recordId !== (string)$existing['record_id']) bad('A saved graph cannot be moved to another record.', 409);

            $colors = require_graph_colors($data);
            $chartData = $data['chart_data'] ?? $data['chartData'] ?? null;
            $sets = [
                'title = ?',
                'chart_type = ?',
                'orientation = ?',
                'value_axis_reversed = ?',
                'value_axis_min = ?',
                'value_axis_max = ?',
                'rank_semantic = ?',
                'rank_value_min = ?',
                'rank_value_max = ?',
                'chart_options = ?'
            ];
            $values = [
                $data['title'] ?? 'Saved Chart',
                normalize_graph_type($data['chart_type'] ?? $data['chartType'] ?? 'bar'),
                $data['orientation'] ?? 'vertical',
                !empty($data['valueAxisReversed']) ? 1 : 0,
                is_numeric($data['valueAxisMin'] ?? null) ? $data['valueAxisMin'] : null,
                is_numeric($data['valueAxisMax'] ?? null) ? $data['valueAxisMax'] : null,
                !empty($data['rankSemantic']) ? 'rank' : null,
                is_numeric($data['rankValueMin'] ?? null) ? $data['rankValueMin'] : null,
                is_numeric($data['rankValueMax'] ?? null) ? $data['rankValueMax'] : null,
                json_encode($chartData ?? [])
            ];
            if (array_key_exists('is_published', $data)) {
                $sets[] = 'is_published = ?';
                $values[] = (int)(bool)$data['is_published'];
            }
            $sets[] = 'updated_at = NOW()';
            $values[] = (int)$id;
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare('UPDATE saved_graphs SET ' . implode(', ', $sets) . ' WHERE graph_id = ?');
                $update->execute($values);
                save_graph_relations($pdo, (int)$id, $data);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $saved = load_saved_graph($pdo, (int)$id);
            if (!$saved) bad('Graph not found', 404);
            echo json_encode(output_graph($saved));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            ensure_admin_for_mutation();
            $d=json_input();
            $colors = require_graph_colors($d);
            $rid=$d['record_id']??$d['recordId']??null;
            if(!$rid)bad('record_id is required');
            $rid = filter_var($rid, FILTER_VALIDATE_INT);
            if (!$rid || $rid < 1) bad('record_id must be a valid record id.');
            $q=$pdo->prepare('SELECT record_id FROM records WHERE record_id=?');
            $q->execute([(int)$rid]);
            if(!$q->fetch())bad('Record not found',404);
            $published = isset($d['is_published']) ? (int)(bool)$d['is_published'] : 0;
            $chartData = $d['chart_data'] ?? $d['chartData'] ?? null;
            $pdo->beginTransaction();
            try {
                $stmt=$pdo->prepare('INSERT INTO saved_graphs (record_id, title, chart_type, orientation, value_axis_reversed, value_axis_min, value_axis_max, rank_semantic, rank_value_min, rank_value_max, chart_options, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([(int)$rid,$d['title']??'Saved Chart',normalize_graph_type($d['chart_type']??$d['chartType']??'bar'),$d['orientation']??'vertical',!empty($d['valueAxisReversed'])?1:0,is_numeric($d['valueAxisMin']??null)?$d['valueAxisMin']:null,is_numeric($d['valueAxisMax']??null)?$d['valueAxisMax']:null,!empty($d['rankSemantic'])?'rank':null,is_numeric($d['rankValueMin']??null)?$d['rankValueMin']:null,is_numeric($d['rankValueMax']??null)?$d['rankValueMax']:null,json_encode($chartData ?? []),$published]);
                $gid=(int)$pdo->lastInsertId();
                save_graph_relations($pdo, $gid, $d);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            echo json_encode(load_saved_graph($pdo, $gid));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='DELETE'){ ensure_admin_for_mutation(); if($id===null)bad('Graph id is required');$g=load_saved_graph($pdo,(int)$id);if(!$g)bad('Graph not found',404);$pdo->prepare('DELETE FROM saved_graphs WHERE graph_id=?')->execute([(int)$id]);echo json_encode(['message'=>'Graph deleted','graph'=>$g]);exit;}
    }
    bad('Unknown API resource',404);
} catch(Throwable $e) { error_log($e->getMessage()); bad('Server error: '.$e->getMessage(),500); }

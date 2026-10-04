<?php
function ensure_admin_for_mutation(): void {
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
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
    foreach (['line', 'stackedArea', 'bar', 'pie', 'doughnut', 'nestedPie'] as $allowed) {
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

function record_file_history_group_id(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function record_merge_record_digest(array $record): string {
    return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function record_file_history_capture(PDO $pdo, array $record): array {
    $recordId = (int)$record['record_id'];
    $graphs = $pdo->prepare('SELECT * FROM saved_graphs WHERE record_id = ? ORDER BY graph_id');
    $graphs->execute([$recordId]);
    $savedGraphs = $graphs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($savedGraphs as &$graph) {
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
    return ['record' => $record, 'saved_graphs' => $savedGraphs];
}

function record_file_history_copy_record_file(array $record): ?array {
    $metadata = json_col($record['metadata'] ?? null, []);
    $storedName = is_array($metadata) ? (string)($metadata['stored_file'] ?? '') : '';
    if ($storedName === '') return null;
    $sourcePath = stored_upload_path($storedName);
    if (!$sourcePath) throw new RuntimeException('A record references a source file that cannot be read; the merge was not applied.');
    $root = upload_storage_root();
    if (!$root) throw new RuntimeException('Private File History storage is unavailable.');
    return RecordFileHistory::storeFile($sourcePath, $root, (string)($record['file_name'] ?? 'source file'), (string)($record['file_type'] ?? ''));
}

function record_file_history_insert(
    PDO $pdo,
    string $mergeGroupId,
    int $recordId,
    int $sourceRecordId,
    int $targetRecordId,
    string $entryType,
    string $method,
    int $actorId,
    array $snapshot,
    ?array $file
): int {
    return RecordFileHistory::insertVersion($pdo, [
        'merge_group_id' => $mergeGroupId,
        'record_id' => $recordId,
        'source_record_id' => $sourceRecordId,
        'target_record_id' => $targetRecordId,
        'entry_type' => $entryType,
        'merge_method' => $method,
        'acting_super_admin_id' => $actorId,
        'snapshot' => $snapshot,
        'file' => $file,
    ]);
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

function record_file_history_display_snapshot(string $snapshotJson): array {
    $snapshot = json_decode($snapshotJson, true);
    if (!is_array($snapshot) || !is_array($snapshot['record'] ?? null)) throw new RuntimeException('The archived version is invalid.');
    $record = $snapshot['record'];
    return [
        'record' => [
            'id' => (int)$record['record_id'],
            'fileName' => $record['file_name'] ?? '',
            'fileType' => $record['file_type'] ?? '',
            'fileSize' => (int)($record['file_size'] ?? 0),
            'scannedAt' => $record['scanned_at'] ?? null,
            'status' => $record['status'] ?? '',
            'docType' => $record['doc_type'] ?? '',
            'rawText' => $record['raw_text'] ?? '',
            'extractedData' => json_col($record['extracted_data'] ?? null, []),
            'graphDrafts' => json_col($record['graph_drafts'] ?? null, []),
            'adminNotes' => $record['admin_notes'] ?? '',
            'metadata' => json_col($record['metadata'] ?? null, []),
            'updatedAt' => $record['updated_at'] ?? null,
            'template_id' => isset($record['template_id']) ? (int)$record['template_id'] : null,
            'import_profile_id' => isset($record['import_profile_id']) ? (int)$record['import_profile_id'] : null,
            'office_id' => isset($record['office_id']) ? (int)$record['office_id'] : null,
        ],
        'saved_graphs' => $snapshot['saved_graphs'] ?? [],
    ];
}

function record_file_history_insert_generated_id(PDO $pdo, string $table, string $primaryKey, array $row): int {
    unset($row[$primaryKey]);
    $columns = array_keys($row);
    if (!$columns) throw new RuntimeException('The archived graph data is empty.');
    $quoted = implode(', ', array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $statement = $pdo->prepare("INSERT INTO `$table` ($quoted) VALUES ($placeholders)");
    $statement->execute(array_values($row));
    return (int)$pdo->lastInsertId();
}

function record_file_history_restore_graphs(PDO $pdo, int $recordId, array $savedGraphs): void {
    foreach ($savedGraphs as $graph) {
        if (!is_array($graph)) continue;
        $seriesRows = $graph['_series'] ?? [];
        $colorRows = $graph['_colors'] ?? [];
        unset($graph['_series'], $graph['_colors']);
        $graph['record_id'] = $recordId;
        $graphId = record_file_history_insert_generated_id($pdo, 'saved_graphs', 'graph_id', $graph);
        $seriesIdMap = [];
        foreach ($seriesRows as $series) {
            if (!is_array($series)) continue;
            $oldSeriesId = (int)($series['series_id'] ?? 0);
            $points = $series['_points'] ?? [];
            unset($series['_points']);
            $series['graph_id'] = $graphId;
            $seriesId = record_file_history_insert_generated_id($pdo, 'graph_series', 'series_id', $series);
            if ($oldSeriesId > 0) $seriesIdMap[$oldSeriesId] = $seriesId;
            foreach ($points as $point) {
                if (!is_array($point)) continue;
                $point['series_id'] = $seriesId;
                record_file_history_insert_generated_id($pdo, 'graph_points', 'point_id', $point);
            }
        }
        foreach ($colorRows as $color) {
            if (!is_array($color)) continue;
            $color['graph_id'] = $graphId;
            if (isset($color['series_id']) && $color['series_id'] !== null) {
                $color['series_id'] = $seriesIdMap[(int)$color['series_id']] ?? null;
            }
            record_file_history_insert_generated_id($pdo, 'graph_colors', 'color_id', $color);
        }
    }
}

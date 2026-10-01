<?php
require_once __DIR__ . '/../includes/functions.php';
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
    && !in_array($resource, ['summary_cards', 'summary_card_categories', 'field_colors'], true)) {
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
    $g['is_published'] = isset($g['is_published']) ? (bool)$g['is_published'] : false;
    return $g;
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
    $query = $pdo->prepare("SELECT id, metadata FROM records WHERE id <> ? AND metadata LIKE ? ESCAPE '!' ");
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
    if (!in_array($table, ['records', 'saved_graphs'], true)) bad('Invalid recovery table.', 500);
    $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
    $fields = array_values(array_intersect($columns, array_keys($row)));
    if (!$fields || !in_array('id', $fields, true)) bad('Recovery snapshot does not match the current database schema.', 409);
    $quoted = array_map(static fn(string $field): string => '`' . $field . '`', $fields);
    $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $quoted) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
    $values = array_map(static fn(string $field) => $row[$field], $fields);
    $pdo->prepare($sql)->execute($values);
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
        $check = $pdo->prepare("SELECT id FROM summary_card_categories WHERE id IN ($placeholders)");
        $check->execute($categoryIds);
        if (count($check->fetchAll(PDO::FETCH_COLUMN)) !== count($categoryIds)) bad('One or more categories were not found.');
    }

    $categoryNames = $data['category_names'] ?? (array_key_exists('category_name', $data) ? [$data['category_name']] : []);
    if (!is_array($categoryNames)) bad('category_names must be an array.');
    foreach ($categoryNames as $rawName) {
        $name = trim((string)$rawName);
        $slug = summary_category_slug($name);
        $existing = $pdo->prepare('SELECT id FROM summary_card_categories WHERE slug = ? LIMIT 1');
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
    $pdo->prepare('DELETE FROM summary_card_category_map WHERE summary_card_id = ?')->execute([$cardId]);
    $insert = $pdo->prepare('INSERT INTO summary_card_category_map (summary_card_id, category_id) VALUES (?, ?)');
    foreach ($categoryIds as $categoryId) $insert->execute([$cardId, $categoryId]);
}
function attach_summary_card_categories(PDO $pdo, array $cards): array {
    if (!$cards) return [];
    $cardIds = array_column($cards, 'id');
    $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
    $query = $pdo->prepare("SELECT mapping.summary_card_id, categories.id, categories.name, categories.slug
        FROM summary_card_category_map mapping
        INNER JOIN summary_card_categories categories ON categories.id = mapping.category_id
        WHERE mapping.summary_card_id IN ($placeholders)
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
            $rows = $pdo->query('SELECT field_key, label, color, updated_at FROM field_colors ORDER BY label ASC')->fetchAll(PDO::FETCH_ASSOC);
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
            $stmt = $pdo->prepare('INSERT INTO field_colors (field_key, label, color) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), color = VALUES(color), updated_at = CURRENT_TIMESTAMP');
            $stmt->execute([$fieldKey, $label, strtoupper($color)]);
            $q = $pdo->prepare('SELECT field_key, label, color, updated_at FROM field_colors WHERE field_key = ?');
            $q->execute([$fieldKey]);
            echo json_encode($q->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            $fieldKey = normalize_field_key($_GET['field_key'] ?? '');
            if ($fieldKey === '') bad('field_key is required.');
            $stmt = $pdo->prepare('DELETE FROM field_colors WHERE field_key = ?');
            $stmt->execute([$fieldKey]);
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount() > 0]);
            exit;
        }
    }

    if ($resource === 'summary_card_categories') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = ($_SESSION['role'] ?? '') === 'super_admin'
                ? $pdo->query('SELECT id, name, slug, sort_order FROM summary_card_categories ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC)
                : $pdo->query("SELECT DISTINCT categories.id, categories.name, categories.slug, categories.sort_order
                    FROM summary_card_categories categories
                    INNER JOIN summary_card_category_map mapping ON mapping.category_id = categories.id
                    INNER JOIN summary_cards cards ON cards.id = mapping.summary_card_id
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
            $existing = $pdo->prepare('SELECT id, name, slug, sort_order FROM summary_card_categories WHERE slug = ? LIMIT 1');
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
                $pdo->prepare('UPDATE summary_card_categories SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
            } catch (PDOException $exception) {
                bad('A category with that name or slug already exists.', 409);
            }
            $query = $pdo->prepare('SELECT id, name, slug, sort_order FROM summary_card_categories WHERE id = ?');
            $query->execute([(int)$id]);
            $category = $query->fetch(PDO::FETCH_ASSOC);
            if (!$category) bad('Category not found.', 404);
            echo json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            if ($id === null) bad('Category id is required.');
            $delete = $pdo->prepare('DELETE FROM summary_card_categories WHERE id = ?');
            $delete->execute([(int)$id]);
            echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
            exit;
        }
    }

    if ($resource === 'summary_cards') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = ($_SESSION['role'] ?? '') === 'super_admin'
                ? $pdo->query('SELECT * FROM summary_cards ORDER BY display_order ASC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC)
                : $pdo->query('SELECT * FROM summary_cards WHERE is_published = 1 ORDER BY display_order ASC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
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
            $id = $data['id'] ?? ('summary_card_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)));
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') bad('title is required');

            $displayPrecision = isset($data['display_precision']) ? (int)$data['display_precision'] : 2;
            $displayPrecision = max(0, min(2, $displayPrecision));
            $published = !empty($data['is_published']) || (!array_key_exists('is_published', $data) && !empty($data['published']));

            $pdo->beginTransaction();
            try {
                $categoryIds = resolve_summary_categories($pdo, $data);
                $stmt = $pdo->prepare('INSERT INTO summary_cards (id, title, main_value, main_label, year_date, secondary_label, secondary_value, description, secondary_description, info_text, display_order, display_precision, is_published, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $id,
                    $title,
                    (string)($data['main_value'] ?? ''),
                    (string)($data['main_label'] ?? ''),
                    (string)($data['year_date'] ?? ''),
                    (string)($data['secondary_label'] ?? ''),
                    (string)($data['secondary_value'] ?? ''),
                    (string)($data['description'] ?? ''),
                    (string)($data['secondary_description'] ?? ''),
                    (string)($data['info_text'] ?? ''),
                    isset($data['display_order']) ? (int)$data['display_order'] : 0,
                    $displayPrecision,
                    $published ? 1 : 0,
                    $categoryIds[0] ?? null
                ]);
                save_summary_card_categories($pdo, (string)$id, $categoryIds);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }

            $q = $pdo->prepare('SELECT * FROM summary_cards WHERE id=?');
            $q->execute([$id]);
            $saved = $q->fetch(PDO::FETCH_ASSOC);
            echo json_encode(attach_summary_card_categories($pdo, $saved ? [$saved] : [])[0] ?? null);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            if ($id === null) bad('Summary card id is required');
            $data = json_input();
            $allowed = ['title','main_value','main_label','year_date','secondary_label','secondary_value','description','secondary_description','info_text','display_order','display_precision','is_published'];
            $sets = [];
            $values = [];
            $hasCategoryUpdate = array_key_exists('category_ids', $data) || array_key_exists('category_names', $data) || array_key_exists('category_id', $data) || array_key_exists('category_name', $data);
            $categoryIds = $hasCategoryUpdate ? resolve_summary_categories($pdo, $data) : [];
            if ($hasCategoryUpdate) {
                $sets[] = 'category_id = ?';
                $values[] = $categoryIds[0] ?? null;
            }
            foreach ($allowed as $field) {
                if (!array_key_exists($field, $data)) continue;
                if ($field === 'display_order') {
                    $sets[] = 'display_order = ?';
                    $values[] = (int)$data[$field];
                    continue;
                }
                if ($field === 'display_precision') {
                    $precision = max(0, min(2, (int)$data[$field]));
                    $sets[] = 'display_precision = ?';
                    $values[] = $precision;
                    continue;
                }
                if ($field === 'is_published') {
                    $sets[] = 'is_published = ?';
                    $values[] = !empty($data[$field]) ? 1 : 0;
                    continue;
                }
                $sets[] = $field . ' = ?';
                $values[] = (string)$data[$field];
            }
            if (!$sets) bad('No fields to update');
            $values[] = $id;
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE summary_cards SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
                if ($hasCategoryUpdate) save_summary_card_categories($pdo, (string)$id, $categoryIds);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $q = $pdo->prepare('SELECT * FROM summary_cards WHERE id=?');
            $q->execute([$id]);
            $saved = $q->fetch(PDO::FETCH_ASSOC);
            echo json_encode(attach_summary_card_categories($pdo, $saved ? [$saved] : [])[0] ?? null);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            if ($id === null) bad('Summary card id is required');
            $pdo->prepare('DELETE FROM summary_cards WHERE id=?')->execute([$id]);
            echo json_encode(['success' => true, 'deleted_id' => $id]);
            exit;
        }
    }

    if ($resource === 'records') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($id !== null) {
                $pdo->prepare('UPDATE records SET opened_at = COALESCE(opened_at, NOW()) WHERE id = ?')->execute([$id]);
                $q = $pdo->prepare('SELECT records.*, templates.name AS template_name FROM records LEFT JOIN templates ON templates.id = records.template_id WHERE records.id=?'); $q->execute([$id]);
                $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) bad('Record not found',404);
                echo json_encode(output_record($r)); exit;
            }
            $rows = $pdo->query('SELECT records.*, templates.name AS template_name FROM records LEFT JOIN templates ON templates.id = records.template_id ORDER BY records.scannedAt DESC, records.updatedAt DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array_map('output_record',$rows)); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'stage-restore') {
            ensure_admin_for_mutation();
            if ($id === null || $id === '') bad('Existing record id is required.');
            $data = json_input();
            $officeId = $data['office_id'] ?? null;
            if (!is_scalar($officeId) || (string)$officeId === '' || (string)$officeId === (string)$id) bad('A different office record id is required.');
            $trashDirectory = merge_trash_directory(true);
            if (!$trashDirectory) bad('Private merge recovery storage is unavailable.', 500);
            cleanup_expired_merge_trash($trashDirectory);
            $trashId = bin2hex(random_bytes(12));
            $manifestPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.json';
            $temporaryPath = $manifestPath . '.tmp';
            $pdo->beginTransaction();
            try {
                $oldQuery = $pdo->prepare('SELECT * FROM records WHERE id=? FOR UPDATE');
                $oldQuery->execute([$id]);
                $oldRow = $oldQuery->fetch(PDO::FETCH_ASSOC);
                if (!$oldRow) bad('Existing record not found.', 404);
                $officeQuery = $pdo->prepare('SELECT * FROM records WHERE id=? FOR UPDATE');
                $officeQuery->execute([$officeId]);
                $officeRow = $officeQuery->fetch(PDO::FETCH_ASSOC);
                if (!$officeRow) bad('Office upload not found.', 404);
                if (strtolower((string)($officeRow['status'] ?? '')) !== 'pending review') bad('The office upload must still be Pending Review.', 409);
                if (empty($officeRow['uploaded_by']) || empty($officeRow['office_name'])) bad('The selected record is not an office upload.', 409);
                if (!in_array(strtolower((string)($officeRow['fileType'] ?? '')), ['xlsx', 'csv', 'tsv'], true)) bad('The office upload is not a supported spreadsheet.', 409);
                if (empty($oldRow['template_id']) || empty($officeRow['template_id']) || (string)$oldRow['template_id'] !== (string)$officeRow['template_id']) bad('Both records must be assigned to the same template.', 409);
                $oldMetadata = json_col($oldRow['metadata'] ?? null, []);
                $officeMetadata = json_col($officeRow['metadata'] ?? null, []);
                if (!is_array($oldMetadata)) $oldMetadata = [];
                if (!is_array($officeMetadata)) $officeMetadata = [];
                $graphQuery = $pdo->prepare('SELECT * FROM saved_graphs WHERE record_id=?');
                $graphQuery->execute([$officeId]);
                $oldStoredFile = (string)($oldMetadata['stored_file'] ?? '');
                if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $oldStoredFile)) $oldStoredFile = null;
                $manifest = [
                    'trash_id' => $trashId,
                    'created_at' => gmdate('c'),
                    'old_id' => (string)$id,
                    'office_id' => (string)$officeId,
                    'old_row' => $oldRow,
                    'office_row' => $officeRow,
                    'office_saved_graphs' => $graphQuery->fetchAll(PDO::FETCH_ASSOC),
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
            $recordQuery = $pdo->prepare('SELECT metadata FROM records WHERE id=?');
            $recordQuery->execute([$id]);
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
            if ($id === null || $id === '') bad('Existing record id is required.');
            $data = json_input();
            $trashId = (string)($data['trash_id'] ?? '');
            [$trashDirectory, $manifestPath, $manifest] = load_merge_manifest($trashId);
            if ((string)($manifest['old_id'] ?? '') !== (string)$id) bad('Recovery manifest does not match this record.', 409);
            $createdAt = strtotime((string)($manifest['created_at'] ?? ''));
            if ($createdAt === false || time() - $createdAt > 30 * 24 * 60 * 60) bad('Merge recovery has expired.', 410);
            $oldRow = $manifest['old_row'] ?? null;
            $officeRow = $manifest['office_row'] ?? null;
            if (!is_array($oldRow) || !is_array($officeRow) || (string)($officeRow['id'] ?? '') !== (string)($manifest['office_id'] ?? '')) bad('Recovery manifest is incomplete.', 500);
            $currentQuery = $pdo->prepare('SELECT metadata FROM records WHERE id=?');
            $currentQuery->execute([$id]);
            $currentRow = $currentQuery->fetch(PDO::FETCH_ASSOC);
            if (!$currentRow) bad('Merged record not found.', 404);
            $currentMetadata = json_col($currentRow['metadata'] ?? null, []);
            if (!is_array($currentMetadata) || (string)($currentMetadata['merge']['trash_id'] ?? '') !== $trashId) bad('This merge was already restored or replaced.', 409);
            $officeCheck = $pdo->prepare('SELECT id FROM records WHERE id=?');
            $officeCheck->execute([$manifest['office_id']]);
            if ($officeCheck->fetchColumn()) bad('The office upload id is already in use.', 409);

            $pdo->beginTransaction();
            try {
                $restoreFields = ['fileName','fileType','fileSize','docType','rawText','extractedData','adminNotes','metadata'];
                $sets = [];
                $values = [];
                foreach ($restoreFields as $field) {
                    if (!array_key_exists($field, $oldRow)) continue;
                    $sets[] = '`' . $field . '`=?';
                    $values[] = $oldRow[$field];
                }
                $sets[] = 'updatedAt=NOW()';
                $values[] = $id;
                $pdo->prepare('UPDATE records SET ' . implode(',', $sets) . ' WHERE id=?')->execute($values);
                insert_row_from_snapshot($pdo, 'records', $officeRow);
                foreach (($manifest['office_saved_graphs'] ?? []) as $graphRow) {
                    if (is_array($graphRow)) insert_row_from_snapshot($pdo, 'saved_graphs', $graphRow);
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
            $resultQuery = $pdo->prepare('SELECT * FROM records WHERE id=?');
            $resultQuery->execute([$id]);
            $restoredRecord = $resultQuery->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'record' => output_record($restoredRecord), 'file_restored' => $fileRestored]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-approve') {
            ensure_admin_for_mutation();
            $data=json_input();
            $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $q=$pdo->prepare("SELECT id FROM records WHERE id IN ($ph)"); $q->execute($ids);
            $existing=$q->fetchAll(PDO::FETCH_COLUMN);
            if ($existing) {
                $ph2=implode(',',array_fill(0,count($existing),'?'));
                $pdo->prepare("UPDATE records SET status='Approved', updatedAt=NOW() WHERE id IN ($ph2)")->execute($existing);
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
                $query = $pdo->prepare("SELECT id FROM records WHERE id IN ($placeholders) FOR UPDATE");
                $query->execute($ids);
                $existing = $query->fetchAll(PDO::FETCH_COLUMN);
                $graphCount = 0;
                if ($existing) {
                    $recordPlaceholders = implode(',', array_fill(0, count($existing), '?'));
                    $pdo->prepare('UPDATE records SET status=?, updatedAt=NOW() WHERE id IN ('.$recordPlaceholders.')')->execute(array_merge([$published ? 'Approved' : 'Pending Review'], $existing));
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
                $q = $pdo->prepare('SELECT id FROM records WHERE id=? FOR UPDATE');
                $q->execute([$id]);
                if (!$q->fetch()) {
                    $pdo->rollBack();
                    bad('Record not found', 404);
                }
                $pdo->prepare("UPDATE records SET status='Pending Review', updatedAt=NOW() WHERE id=?")->execute([$id]);
                $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=0 WHERE record_id=? AND is_published=1');
                $graphs->execute([$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $q = $pdo->prepare('SELECT * FROM records WHERE id=?');
            $q->execute([$id]);
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
                $q=$pdo->prepare("SELECT * FROM records WHERE id IN ($ph) FOR UPDATE");
                $q->execute($ids);
                $existingRows=$q->fetchAll(PDO::FETCH_ASSOC);
                $existing=array_column($existingRows,'id');
                if ($existing) {
                    $ph2=implode(',',array_fill(0,count($existing),'?'));
                    $pdo->prepare("DELETE FROM saved_graphs WHERE record_id IN ($ph2)")->execute($existing);
                    $q=$pdo->prepare("DELETE FROM records WHERE id IN ($ph2)");$q->execute($existing);
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
            $id2=$data['id']??('rec_'.date('YmdHis').'_'.bin2hex(random_bytes(3)));
            if (!ALLOW_SUPER_ADMIN_UPLOAD && ($data['fileType'] ?? $data['type'] ?? '') !== 'manual') bad('File uploads are disabled for Super Admin.', 403);
            $stmt=$pdo->prepare('INSERT INTO records (id,fileName,fileType,fileSize,scannedAt,status,docType,rawText,extractedData,graphDrafts,adminNotes,metadata,updatedAt) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$id2,$data['fileName']??$data['name']??'Untitled',$data['fileType']??$data['type']??'unknown',(int)($data['fileSize']??$data['size']??0),date('Y-m-d H:i:s',strtotime($data['scannedAt']??'now')),$data['status']??'Pending Review',$data['docType']??'General Institutional Data',$data['rawText']??'',json_encode($data['extractedData']??$data['sheetsData']??[]),json_encode($data['graphDrafts']??[]),$data['adminNotes']??'',json_encode($data['metadata']??[]),null]);
            $q=$pdo->prepare('SELECT * FROM records WHERE id=?');$q->execute([$id2]);echo json_encode(output_record($q->fetch(PDO::FETCH_ASSOC)));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            if ($id===null) bad('Record id is required'); $data=json_input(); $allowed=['fileName','fileType','fileSize','status','docType','rawText','adminNotes'];$sets=[];$vals=[];
            if (array_key_exists('status',$data) && !in_array($data['status'], ['Pending Review','Approved','Needs Revision'], true)) bad('Invalid record status');
            foreach($allowed as $f) if(array_key_exists($f,$data)){ $sets[]="$f=?";$vals[]=$data[$f]; }
            foreach(['extractedData','graphDrafts','metadata'] as $f) if(array_key_exists($f,$data)){ $col=$f;$sets[]="$col=?";$vals[]=json_encode($data[$f]); }
            if(array_key_exists('scannedAt',$data)){ $sets[]='scannedAt=?';$vals[]=date('Y-m-d H:i:s',strtotime($data['scannedAt'])); }
            if(!$sets) bad('No fields to update'); $sets[]='updatedAt=NOW()';$vals[]=$id;$stmt=$pdo->prepare('UPDATE records SET '.implode(',',$sets).' WHERE id=?');$stmt->execute($vals);if(!$stmt->rowCount()){$q=$pdo->prepare('SELECT id FROM records WHERE id=?');$q->execute([$id]);if(!$q->fetch())bad('Record not found',404);}
            $q=$pdo->prepare('SELECT * FROM records WHERE id=?');$q->execute([$id]);echo json_encode(output_record($q->fetch(PDO::FETCH_ASSOC)));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            if($id===null)bad('Record id is required');
            $pdo->beginTransaction();
            try {
                $q=$pdo->prepare('SELECT * FROM records WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)bad('Record not found',404);
                $pdo->prepare('DELETE FROM saved_graphs WHERE record_id=?')->execute([$id]);
                $q=$pdo->prepare('DELETE FROM records WHERE id=?');$q->execute([$id]);
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
            if($id!==null){$q=$pdo->prepare('SELECT saved_graphs.* FROM saved_graphs WHERE saved_graphs.id=?');$q->execute([$id]);$g=$q->fetch(PDO::FETCH_ASSOC);if(!$g)bad('Graph not found',404);echo json_encode(output_graph($g));exit;}
            if($recordId!==null){$q=$pdo->prepare('SELECT saved_graphs.*, records.id AS source_file_id, records.fileName AS source_file_name, records.fileType AS source_file_type FROM saved_graphs LEFT JOIN records ON records.id=saved_graphs.record_id WHERE saved_graphs.record_id=? ORDER BY saved_graphs.created_at DESC');$q->execute([$recordId]);echo json_encode(array_map('output_graph',$q->fetchAll(PDO::FETCH_ASSOC)));exit;}
            $rows=$pdo->query('SELECT saved_graphs.*, records.id AS source_file_id, records.fileName AS source_file_name, records.fileType AS source_file_type FROM saved_graphs LEFT JOIN records ON records.id=saved_graphs.record_id ORDER BY saved_graphs.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);echo json_encode(array_map('output_graph',$rows));exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='bulk-delete'){
            ensure_admin_for_mutation();
            $d=json_input();$ids=array_values(array_unique(array_filter($d['ids']??[])));if(!$ids)bad('ids must be a non-empty array');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT id FROM saved_graphs WHERE id IN ($ph)");$q->execute($ids);$existing=$q->fetchAll(PDO::FETCH_COLUMN);if($existing){$ph2=implode(',',array_fill(0,count($existing),'?'));$pdo->prepare("DELETE FROM saved_graphs WHERE id IN ($ph2)")->execute($existing);} $set=array_fill_keys($existing,true);$results=[];foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Graph not found'];echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]);exit;}
        if ($_SERVER['REQUEST_METHOD']==='POST' && ($action==='publish' || $action==='unpublish' || $action==='toggle-publish')) {
            ensure_admin_for_mutation();
            $d=json_input();
            $published = isset($d['published']) ? (bool)$d['published'] : ($action === 'publish');
            if($id===null)bad('Graph id is required');
            $q=$pdo->prepare('SELECT id FROM saved_graphs WHERE id=?');$q->execute([$id]);if(!$q->fetch())bad('Graph not found',404);
            $pdo->prepare('UPDATE saved_graphs SET is_published = ? WHERE id = ?')->execute([$published ? 1 : 0,$id]);
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
            $query = $pdo->prepare("SELECT saved_graphs.*, records.fileName AS source_file_name FROM saved_graphs LEFT JOIN records ON records.id=saved_graphs.record_id WHERE saved_graphs.id IN ($placeholders) ORDER BY saved_graphs.created_at DESC");
            $query->execute($ids);
            $graphs = $query->fetchAll(PDO::FETCH_ASSOC);
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
            foreach ($graphs as $graph) {
                $newId = 'export_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
                $colors = valid_graph_colors(json_col($graph['colors'] ?? null, null));
                $stmt = $pdo->prepare('INSERT INTO saved_graphs (id,record_id,title,chart_type,orientation,value_axis_reversed,value_axis_min,value_axis_max,rank_semantic,rank_value_min,rank_value_max,labels,values_data,colors) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$newId,$graph['record_id'],$graph['title'],$graph['chart_type'],$graph['orientation'],(int)$graph['value_axis_reversed'],$graph['value_axis_min'],$graph['value_axis_max'],(int)$graph['rank_semantic'],$graph['rank_value_min'],$graph['rank_value_max'],$graph['labels'],$graph['values_data'],$colors === null ? null : json_encode($colors)]);
                $new[] = $newId;
            }
            echo json_encode(['mode' => 'database', 'count' => count($new), 'exported_ids' => $new]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            if (($_SESSION['role'] ?? '') !== 'super_admin') bad('Forbidden', 403);
            if ($id === null || $id === '') bad('Graph id is required.');
            $data = json_input();
            $existingQuery = $pdo->prepare('SELECT id, record_id FROM saved_graphs WHERE id = ?');
            $existingQuery->execute([$id]);
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
                'labels = ?',
                'values_data = ?',
                'chart_data = ?',
                'colors = ?'
            ];
            $values = [
                $data['title'] ?? 'Saved Chart',
                normalize_graph_type($data['chart_type'] ?? $data['chartType'] ?? 'bar'),
                $data['orientation'] ?? 'vertical',
                !empty($data['valueAxisReversed']) ? 1 : 0,
                is_numeric($data['valueAxisMin'] ?? null) ? $data['valueAxisMin'] : null,
                is_numeric($data['valueAxisMax'] ?? null) ? $data['valueAxisMax'] : null,
                !empty($data['rankSemantic']) ? 1 : 0,
                is_numeric($data['rankValueMin'] ?? null) ? $data['rankValueMin'] : null,
                is_numeric($data['rankValueMax'] ?? null) ? $data['rankValueMax'] : null,
                json_encode($data['labels'] ?? []),
                json_encode($data['values_data'] ?? $data['valuesData'] ?? $data['data'] ?? []),
                json_encode($chartData ?? []),
                $colors === null ? null : json_encode($colors)
            ];
            if (array_key_exists('is_published', $data)) {
                $sets[] = 'is_published = ?';
                $values[] = (int)(bool)$data['is_published'];
            }
            $updatedAtCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saved_graphs' AND COLUMN_NAME = 'updated_at'");
            $updatedAtCheck->execute();
            if ((int)$updatedAtCheck->fetchColumn() > 0) $sets[] = 'updated_at = NOW()';
            $values[] = $id;
            $update = $pdo->prepare('UPDATE saved_graphs SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $update->execute($values);
            $query = $pdo->prepare('SELECT * FROM saved_graphs WHERE id = ?');
            $query->execute([$id]);
            $saved = $query->fetch(PDO::FETCH_ASSOC);
            if (!$saved) bad('Graph not found', 404);
            echo json_encode(output_graph($saved));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            ensure_admin_for_mutation();
            $d=json_input();
            $colors = require_graph_colors($d);
            $gid=$d['id']??('graph_'.date('YmdHis').'_'.bin2hex(random_bytes(4)));
            $rid=$d['record_id']??$d['recordId']??null;
            if(!$rid)bad('record_id is required');
            $q=$pdo->prepare('SELECT id FROM records WHERE id=?');
            $q->execute([$rid]);
            if(!$q->fetch())bad('Record not found',404);
            $published = isset($d['is_published']) ? (int)(bool)$d['is_published'] : 0;
            $chartData = $d['chart_data'] ?? $d['chartData'] ?? null;
            $stmt=$pdo->prepare('INSERT INTO saved_graphs (id,record_id,title,chart_type,orientation,value_axis_reversed,value_axis_min,value_axis_max,rank_semantic,rank_value_min,rank_value_max,labels,values_data,chart_data,colors,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$gid,$rid,$d['title']??'Saved Chart',normalize_graph_type($d['chart_type']??$d['chartType']??'bar'),$d['orientation']??'vertical',!empty($d['valueAxisReversed'])?1:0,is_numeric($d['valueAxisMin']??null)?$d['valueAxisMin']:null,is_numeric($d['valueAxisMax']??null)?$d['valueAxisMax']:null,!empty($d['rankSemantic'])?1:0,is_numeric($d['rankValueMin']??null)?$d['rankValueMin']:null,is_numeric($d['rankValueMax']??null)?$d['rankValueMax']:null,json_encode($d['labels']??[]),json_encode($d['values_data']??$d['valuesData']??$d['data']??[]),json_encode($chartData ?? []),$colors === null ? null : json_encode($colors),$published]);
            $q=$pdo->prepare('SELECT * FROM saved_graphs WHERE id=?');
            $q->execute([$gid]);
            echo json_encode(output_graph($q->fetch(PDO::FETCH_ASSOC)));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='DELETE'){ ensure_admin_for_mutation(); if($id===null)bad('Graph id is required');$q=$pdo->prepare('SELECT * FROM saved_graphs WHERE id=?');$q->execute([$id]);$g=$q->fetch(PDO::FETCH_ASSOC);if(!$g)bad('Graph not found',404);$pdo->prepare('DELETE FROM saved_graphs WHERE id=?')->execute([$id]);echo json_encode(['message'=>'Graph deleted','graph'=>$g]);exit;}
    }
    bad('Unknown API resource',404);
} catch(Throwable $e) { error_log($e->getMessage()); bad('Server error: '.$e->getMessage(),500); }

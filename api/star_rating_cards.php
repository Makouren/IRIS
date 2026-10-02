<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function star_rating_bad(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function star_rating_require_admin(): void {
    requireRole(['super_admin'], true);
}

function star_rating_verify_csrf(): void {
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        star_rating_bad('Invalid CSRF token.', 419);
    }
}

function star_rating_category_slug(string $name): string {
    $name = trim($name);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($length < 1 || $length > 40) star_rating_bad('Category name must be between 1 and 40 characters.');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $normalized = strtolower($ascii === false ? $name : $ascii);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '', '-');
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) star_rating_bad('Category name must contain letters or numbers.');
    return $slug;
}

function star_rating_create_or_find_category(PDO $pdo, string $name): int {
    $name = trim($name);
    $slug = star_rating_category_slug($name);
    $query = $pdo->prepare('SELECT category_id FROM star_rating_categories WHERE slug = ? LIMIT 1');
    $query->execute([$slug]);
    $id = $query->fetchColumn();
    if ($id !== false) return (int)$id;
    $sortOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM star_rating_categories')->fetchColumn();
    try {
        $insert = $pdo->prepare('INSERT INTO star_rating_categories (name, slug, sort_order) VALUES (?, ?, ?)');
        $insert->execute([$name, $slug, $sortOrder]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $exception) {
        $query->execute([$slug]);
        $id = $query->fetchColumn();
        if ($id === false) throw $exception;
        return (int)$id;
    }
}

function star_rating_json_input(): array {
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($data) ? $data : [];
}

function star_rating_remove_logo(?string $relativePath, string $logoDirectory): void {
    if (!$relativePath) return;
    $base = realpath($logoDirectory);
    $file = realpath(dirname(__DIR__) . '/' . $relativePath);
    if ($base && $file && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) {
        unlink($file);
    }
}

function star_rating_rows(PDO $pdo, array $cards): array {
    if (!$cards) return [];
    $ids = array_column($cards, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare("SELECT rating_rows.row_id AS id, rating_rows.card_id, rating_rows.label,
            COALESCE(CAST(row_settings.setting_value AS UNSIGNED), 5) AS max_stars,
            rating_rows.value AS score, rating_rows.display_order
        FROM star_rating_rows rating_rows
        LEFT JOIN star_rating_settings row_settings ON row_settings.card_id = rating_rows.card_id
            AND row_settings.setting_key = CONCAT('row:', rating_rows.row_id, ':max_stars')
        WHERE rating_rows.card_id IN ($placeholders) ORDER BY rating_rows.display_order ASC, rating_rows.row_id ASC");
    $query->execute($ids);
    $rowsByCard = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['max_stars'] = (int)$row['max_stars'];
        $row['score'] = (float)$row['score'];
        $row['display_order'] = (int)$row['display_order'];
        $rowsByCard[$row['card_id']][] = $row;
    }
    $categoryQuery = $pdo->prepare("SELECT mapping.card_id, categories.category_id AS id, categories.name, categories.slug
        FROM star_rating_card_category_map mapping
        INNER JOIN star_rating_categories categories ON categories.category_id = mapping.category_id
        WHERE mapping.card_id IN ($placeholders)
        ORDER BY categories.sort_order ASC, categories.name ASC");
    $categoryQuery->execute($ids);
    $categoriesByCard = [];
    foreach ($categoryQuery->fetchAll(PDO::FETCH_ASSOC) as $category) $categoriesByCard[$category['card_id']][] = $category;
    foreach ($cards as &$card) {
        $card['display_order'] = (int)$card['display_order'];
        $card['is_published'] = (bool)$card['is_published'];
        $card['rows'] = $rowsByCard[$card['id']] ?? [];
        $assigned = $categoriesByCard[$card['id']] ?? [];
        $card['category_ids'] = array_map(static fn(array $category): int => (int)$category['id'], $assigned);
        $card['categories'] = array_map(static fn(array $category): array => [
            'id' => (int)$category['id'], 'name' => $category['name'], 'slug' => $category['slug']
        ], $assigned);
        $card['category_names'] = array_column($assigned, 'name');
        $card['category_slugs'] = array_column($assigned, 'slug');
    }
    unset($card);
    return $cards;
}

function star_rating_validate_rows(string $rawRows): array {
    $rows = json_decode($rawRows, true);
    if (!is_array($rows) || !array_is_list($rows) || count($rows) > 10) {
        star_rating_bad('Provide no more than 10 star rating rows.');
    }
    $validated = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) star_rating_bad('Each rating row must be valid.');
        $label = trim((string)($row['label'] ?? ''));
        $labelLength = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
        if ($labelLength < 1 || $labelLength > 80) star_rating_bad('Each category label must be between 1 and 80 characters.');
        $maxStars = filter_var($row['max_stars'] ?? null, FILTER_VALIDATE_INT);
        if ($maxStars === false || $maxStars < 1 || $maxStars > 10) star_rating_bad('Number of stars must be between 1 and 10.');
        $score = $row['score'] ?? null;
        if (!is_numeric($score)) star_rating_bad('Each score must be a number.');
        $score = (float)$score;
        if (!is_finite($score) || $score < 0 || $score > $maxStars || abs(($score * 2) - round($score * 2)) > 0.000001) {
            star_rating_bad('Each score must be between 0 and its star count, in 0.5 steps.');
        }
        $validated[] = [
            'label' => $label,
            'max_stars' => $maxStars,
            'score' => number_format($score, 1, '.', ''),
            'display_order' => $index
        ];
    }
    return $validated;
}

function star_rating_save_logo(?array $upload, string $logoDirectory): ?string {
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
        star_rating_bad('The logo upload could not be read.');
    }
    if ((int)$upload['size'] < 1 || (int)$upload['size'] > 1024 * 1024) {
        star_rating_bad('Logo files must be no larger than 1 MB.');
    }
    $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $types = [
        'png' => ['image/png', 'png'],
        'jpg' => ['image/jpeg', 'jpg'],
        'jpeg' => ['image/jpeg', 'jpg'],
        'webp' => ['image/webp', 'webp'],
        'svg' => ['image/svg+xml', 'svg']
    ];
    if (!isset($types[$extension]) || $mime !== $types[$extension][0]) {
        star_rating_bad('Logo must be a valid PNG, JPG, WebP, or SVG image.');
    }
    if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0755, true) && !is_dir($logoDirectory)) {
        star_rating_bad('Logo storage is unavailable.', 500);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $types[$extension][1];
    if (!move_uploaded_file($upload['tmp_name'], $logoDirectory . DIRECTORY_SEPARATOR . $filename)) {
        star_rating_bad('Unable to store the uploaded logo.', 500);
    }
    return 'uploads/star-rating-logos/' . $filename;
}

try {
    $pdo = db();
    $logoDirectory = __DIR__ . '/../uploads/star-rating-logos';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (($_GET['resource'] ?? '') === 'categories') {
        if ($method === 'GET') {
            $isAdmin = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'super_admin';
            $sql = 'SELECT categories.category_id AS id, categories.name, categories.slug, categories.sort_order
                FROM star_rating_categories categories ' . ($isAdmin ? '' : 'WHERE EXISTS (
                    SELECT 1 FROM star_rating_card_category_map mapping
                    INNER JOIN star_rating_cards cards ON cards.card_id = mapping.card_id
                    WHERE mapping.category_id = categories.category_id AND cards.is_published = 1
                ) ') . 'ORDER BY categories.sort_order ASC, categories.name ASC';
            $query = $pdo->query($sql);
            echo json_encode($query->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }

        star_rating_require_admin();
        star_rating_verify_csrf();
        $data = star_rating_json_input();
        if ($method === 'POST') {
            $name = trim((string)($data['name'] ?? ''));
            $slug = star_rating_category_slug($name);
            $requestedSortOrder = null;
            if (array_key_exists('sort_order', $data)) {
                $requestedSortOrder = filter_var($data['sort_order'], FILTER_VALIDATE_INT);
                if ($requestedSortOrder === false || $requestedSortOrder < 0) star_rating_bad('Category sort order must be a non-negative whole number.');
            }
            $query = $pdo->prepare('SELECT category_id AS id, name, slug, sort_order FROM star_rating_categories WHERE slug = ? LIMIT 1');
            $query->execute([$slug]);
            $category = $query->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                $sortOrder = $requestedSortOrder !== null
                    ? $requestedSortOrder
                    : (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM star_rating_categories')->fetchColumn();
                try {
                    $insert = $pdo->prepare('INSERT INTO star_rating_categories (name, slug, sort_order) VALUES (?, ?, ?)');
                    $insert->execute([$name, $slug, $sortOrder]);
                } catch (PDOException $exception) {
                    $query->execute([$slug]);
                    $category = $query->fetch(PDO::FETCH_ASSOC);
                    if (!$category) throw $exception;
                }
                if (!$category) {
                    $query->execute([$slug]);
                    $category = $query->fetch(PDO::FETCH_ASSOC);
                }
            }
            echo json_encode($category, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            $categoryId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            if ($categoryId === false || $categoryId === null) star_rating_bad('Category id is required.');
            $sets = [];
            $values = [];
            if (array_key_exists('name', $data)) {
                $name = trim((string)$data['name']);
                $sets[] = 'name = ?';
                $values[] = $name;
                $sets[] = 'slug = ?';
                $values[] = star_rating_category_slug($name);
            }
            if (array_key_exists('sort_order', $data)) {
                $sortOrder = filter_var($data['sort_order'], FILTER_VALIDATE_INT);
                if ($sortOrder === false || $sortOrder < 0) star_rating_bad('Category sort order must be a non-negative whole number.');
                $sets[] = 'sort_order = ?';
                $values[] = $sortOrder;
            }
            if (!$sets) star_rating_bad('No category fields to update.');
            $values[] = $categoryId;
            try {
                $pdo->prepare('UPDATE star_rating_categories SET ' . implode(', ', $sets) . ' WHERE category_id = ?')->execute($values);
            } catch (PDOException $exception) {
                star_rating_bad('A category with that name or slug already exists.', 409);
            }
            $query = $pdo->prepare('SELECT category_id AS id, name, slug, sort_order FROM star_rating_categories WHERE category_id = ?');
            $query->execute([$categoryId]);
            $category = $query->fetch(PDO::FETCH_ASSOC);
            if (!$category) star_rating_bad('Category not found.', 404);
            echo json_encode($category, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($method === 'DELETE') {
            $categoryId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            if ($categoryId === false || $categoryId === null) star_rating_bad('Category id is required.');
            $delete = $pdo->prepare('DELETE FROM star_rating_categories WHERE category_id = ?');
            $delete->execute([$categoryId]);
            echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
            exit;
        }
        star_rating_bad('Method not allowed.', 405);
    }

    if ($method === 'GET') {
        $isAdmin = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'super_admin';
        $query = $pdo->prepare("SELECT cards.card_id AS id, cards.title,
                (SELECT setting_value FROM star_rating_settings WHERE card_id = cards.card_id AND setting_key = 'logo_path' LIMIT 1) AS logo_path,
                cards.year,
                CAST(COALESCE((SELECT setting_value FROM star_rating_settings WHERE card_id = cards.card_id AND setting_key = 'display_order' LIMIT 1), cards.card_id) AS UNSIGNED) AS display_order,
                cards.is_published, cards.created_at, cards.updated_at
            FROM star_rating_cards cards " . ($isAdmin ? '' : 'WHERE cards.is_published = 1 ') . 'ORDER BY display_order ASC, cards.created_at DESC');
        $query->execute();
        echo json_encode(star_rating_rows($pdo, $query->fetchAll(PDO::FETCH_ASSOC)), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    star_rating_require_admin();
    star_rating_verify_csrf();

    if ($method === 'POST') {
        $rawId = $_GET['id'] ?? $_POST['id'] ?? '';
        if ($rawId !== '' && filter_var($rawId, FILTER_VALIDATE_INT) === false) star_rating_bad('Star rating card id must be a whole number.');
        $id = $rawId === '' ? null : (int)$rawId;
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '' || strlen($title) > 255) star_rating_bad('Title is required and must not exceed 255 characters.');
        $year = trim((string)($_POST['year'] ?? ''));
        if (strlen($year) > 10) star_rating_bad('Year/Date must not exceed 10 characters.');
        $rows = star_rating_validate_rows((string)($_POST['rows'] ?? '[]'));
        $categoryIds = json_decode((string)($_POST['category_ids'] ?? '[]'), true);
        if (!is_array($categoryIds) || !array_is_list($categoryIds)) star_rating_bad('Categories must be a list of category ids.');
        foreach ($categoryIds as $categoryId) {
            if (!filter_var($categoryId, FILTER_VALIDATE_INT) || (int)$categoryId < 1) star_rating_bad('One or more categories are invalid.');
        }
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if ($categoryIds) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $categoryCheck = $pdo->prepare("SELECT category_id FROM star_rating_categories WHERE category_id IN ($placeholders)");
            $categoryCheck->execute($categoryIds);
            if (count($categoryCheck->fetchAll(PDO::FETCH_COLUMN)) !== count($categoryIds)) star_rating_bad('One or more categories were not found.');
        }
        $newCategoryName = trim((string)($_POST['new_category_name'] ?? ''));
        if ($newCategoryName !== '') star_rating_category_slug($newCategoryName);
        $publishValue = (string)($_POST['is_published'] ?? '0');
        if (!in_array($publishValue, ['0', '1'], true)) star_rating_bad('Publication state must be 0 or 1.');
        $isPublished = $publishValue === '1' ? 1 : 0;
        if ($isPublished && !$rows) star_rating_bad('Add at least one rating row before publishing.');
        $displayOrder = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($displayOrder === false || $displayOrder < 0) star_rating_bad('Display order must be a non-negative whole number.');

        $existing = null;
        if ($id !== null) {
            $query = $pdo->prepare('SELECT card_id AS id FROM star_rating_cards WHERE card_id = ?');
            $query->execute([$id]);
            $existing = $query->fetch(PDO::FETCH_ASSOC);
            if (!$existing) star_rating_bad('Star rating card not found.', 404);
        }
        $oldLogo = $existing ? star_rating_setting($pdo, (int)$id, 'logo_path') : null;
        $newLogo = star_rating_save_logo($_FILES['logo'] ?? null, $logoDirectory);
        $logoPath = $newLogo ?? (!empty($_POST['remove_logo']) ? null : $oldLogo);
        $pdo->beginTransaction();
        try {
            if ($existing) {
                $save = $pdo->prepare('UPDATE star_rating_cards SET title = ?, year = ?, is_published = ? WHERE card_id = ?');
                $save->execute([$title, $year !== '' ? $year : null, $isPublished, $id]);
                $cardId = (int)$id;
                $pdo->prepare("DELETE FROM star_rating_settings WHERE card_id = ? AND setting_key LIKE 'row:%:max_stars'")->execute([$cardId]);
                $pdo->prepare('DELETE FROM star_rating_rows WHERE card_id = ?')->execute([$cardId]);
            } else {
                $save = $pdo->prepare('INSERT INTO star_rating_cards (title, year, is_published) VALUES (?, ?, ?)');
                $save->execute([$title, $year !== '' ? $year : null, $isPublished]);
                $cardId = (int)$pdo->lastInsertId();
            }
            star_rating_save_setting($pdo, $cardId, 'logo_path', $logoPath);
            star_rating_save_setting($pdo, $cardId, 'display_order', (string)$displayOrder);
            $insertRow = $pdo->prepare('INSERT INTO star_rating_rows (card_id, label, value, display_order) VALUES (?, ?, ?, ?)');
            foreach ($rows as $row) {
                $insertRow->execute([$cardId, $row['label'], $row['score'], $row['display_order']]);
                $rowId = (int)$pdo->lastInsertId();
                star_rating_save_setting($pdo, $cardId, 'row:' . $rowId . ':max_stars', (string)$row['max_stars']);
            }
            if ($newCategoryName !== '') $categoryIds[] = star_rating_create_or_find_category($pdo, $newCategoryName);
            $categoryIds = array_values(array_unique($categoryIds));
            $pdo->prepare('DELETE FROM star_rating_card_category_map WHERE card_id = ?')->execute([$cardId]);
            $insertCategory = $pdo->prepare('INSERT INTO star_rating_card_category_map (card_id, category_id) VALUES (?, ?)');
            foreach ($categoryIds as $categoryId) $insertCategory->execute([$cardId, $categoryId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($newLogo) star_rating_remove_logo($newLogo, $logoDirectory);
            throw $exception;
        }
        if ($oldLogo && $oldLogo !== $logoPath) star_rating_remove_logo($oldLogo, $logoDirectory);
        $query = $pdo->prepare("SELECT cards.card_id AS id, cards.title,
                (SELECT setting_value FROM star_rating_settings WHERE card_id = cards.card_id AND setting_key = 'logo_path' LIMIT 1) AS logo_path,
                cards.year,
                CAST(COALESCE((SELECT setting_value FROM star_rating_settings WHERE card_id = cards.card_id AND setting_key = 'display_order' LIMIT 1), cards.card_id) AS UNSIGNED) AS display_order,
                cards.is_published, cards.created_at, cards.updated_at
            FROM star_rating_cards cards WHERE cards.card_id = ?");
        $query->execute([$cardId]);
        $cards = star_rating_rows($pdo, $query->fetchAll(PDO::FETCH_ASSOC));
        echo json_encode($cards[0], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    if ($method === 'DELETE') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null) star_rating_bad('Star rating card id is required.');
        $query = $pdo->prepare("SELECT setting_value FROM star_rating_settings WHERE card_id = ? AND setting_key = 'logo_path' LIMIT 1");
        $query->execute([$id]);
        $logoPath = $query->fetchColumn();
        $delete = $pdo->prepare('DELETE FROM star_rating_cards WHERE card_id = ?');
        $delete->execute([$id]);
        if ($delete->rowCount()) star_rating_remove_logo($logoPath ?: null, $logoDirectory);
        echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
        exit;
    }

    star_rating_bad('Method not allowed.', 405);
} catch (Throwable $exception) {
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['error' => 'Unable to process star rating cards.']);
}

function star_rating_setting(PDO $pdo, int $cardId, string $key): ?string {
    $query = $pdo->prepare('SELECT setting_value FROM star_rating_settings WHERE card_id = ? AND setting_key = ? LIMIT 1');
    $query->execute([$cardId, $key]);
    $value = $query->fetchColumn();
    return $value === false ? null : (string)$value;
}

function star_rating_save_setting(PDO $pdo, int $cardId, string $key, ?string $value): void {
    if ($value === null || $value === '') {
        $pdo->prepare('DELETE FROM star_rating_settings WHERE card_id = ? AND setting_key = ?')->execute([$cardId, $key]);
        return;
    }
    $pdo->prepare('INSERT INTO star_rating_settings (card_id, setting_key, setting_value) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$cardId, $key, $value]);
}
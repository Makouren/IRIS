<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/SummaryCardHistory.php';
requireRole(['super_admin', 'admin', 'user'], true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = db();
        $sql = "SELECT sg.graph_id AS id, sg.record_id, sg.title, sg.scope, sg.chart_type, sg.orientation, sg.chart_options AS chart_data,
                   sg.value_axis_reversed, sg.value_axis_min, sg.value_axis_max,
                   sg.rank_semantic, sg.rank_value_min, sg.rank_value_max,
                 sg.is_published, sg.created_at,
                   (SELECT COUNT(*) FROM saved_graphs versioned WHERE versioned.record_id = sg.record_id AND versioned.created_at <= sg.created_at) AS version,
                 r.file_name AS source_file_name, r.file_type AS source_file_type, r.status AS source_status
            FROM saved_graphs sg
             INNER JOIN records r ON r.record_id = sg.record_id
                        WHERE sg.is_published = 1
            ORDER BY sg.created_at DESC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $graphId = (int)$row['id'];
        $row['chart_data'] = json_decode((string)($row['chart_data'] ?? ''), true) ?: [];
        $seriesQuery = $pdo->prepare('SELECT series_id, series_name FROM graph_series WHERE graph_id = ? ORDER BY display_order, series_id');
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
        $first = $series[0]['data'] ?? [];
        $row['labels'] = array_map(static fn(array $point): ?string => $point['name'] === null ? null : (string)$point['name'], $first);
        $row['values_data'] = array_map(static fn(array $point) => $point['value'], $first);
        if (isset($row['chart_data']['series']) && is_array($row['chart_data']['series'])) {
            foreach ($row['chart_data']['series'] as $seriesIndex => &$storedSeries) {
                if (is_array($storedSeries) && isset($series[$seriesIndex])) $storedSeries['data'] = $series[$seriesIndex]['data'];
            }
            unset($storedSeries);
        } elseif ($series) {
            $row['chart_data']['series'] = $series;
        }
        $colorsQuery = $pdo->prepare('SELECT color FROM graph_colors WHERE graph_id = ? AND series_id IS NULL ORDER BY color_id');
        $colorsQuery->execute([$graphId]);
        $row['colors'] = $colorsQuery->fetchAll(PDO::FETCH_COLUMN) ?: null;
        if (in_array(strtolower((string)$row['chart_type']), ['polararea', 'polar-area', 'rose', 'nightingale'], true)) {
            $row['chart_type'] = 'bar';
            $row['chart_data']['irisConfig'] = array_merge($row['chart_data']['irisConfig'] ?? [], ['type' => 'bar']);
            unset($row['chart_data']['irisConfig']['roseMode']);
        }
        $colors = $row['colors'];
        $row['colors'] = is_array($colors) && array_is_list($colors) && count($colors) > 0 && count($colors) <= 1000
            && !array_filter($colors, static fn($color): bool => !is_string($color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color))
            ? array_map('strtoupper', $colors)
            : null;
        $row['value_axis_reversed'] = (bool)$row['value_axis_reversed'];
        $row['rank_semantic'] = in_array(strtolower((string)$row['rank_semantic']), ['rank', 'true', '1'], true);
        $row['value_axis_min'] = $row['value_axis_min'] !== null ? (float)$row['value_axis_min'] : null;
        $row['value_axis_max'] = $row['value_axis_max'] !== null ? (float)$row['value_axis_max'] : null;
        $row['rank_value_min'] = $row['rank_value_min'] !== null ? (float)$row['rank_value_min'] : null;
        $row['rank_value_max'] = $row['rank_value_max'] !== null ? (float)$row['rank_value_max'] : null;
    }
    unset($row);

    $fieldColorRows = $pdo->query('SELECT field_name AS field_key, color FROM field_colors')->fetchAll(PDO::FETCH_ASSOC);
    $fieldColors = [];
    foreach ($fieldColorRows as $fieldColor) {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$fieldColor['color'])) {
            $key = strtolower(preg_replace('/\s+/', ' ', trim((string)$fieldColor['field_key'])) ?? '');
            if ($key !== '') $fieldColors[$key] = strtoupper($fieldColor['color']);
        }
    }

    $cardRows = SummaryCardHistory::publishedCards($pdo);
    $publishedCardIds = array_column($cardRows, 'id');
    $cardCategories = [];
    if ($publishedCardIds) {
        $placeholders = implode(',', array_fill(0, count($publishedCardIds), '?'));
        $categoryQuery = $pdo->prepare("SELECT mapping.card_id AS summary_card_id, categories.category_id AS id, categories.name, categories.slug
            FROM summary_card_category_map mapping
            INNER JOIN summary_card_categories categories ON categories.category_id = mapping.category_id
            WHERE mapping.card_id IN ($placeholders)
            ORDER BY categories.sort_order ASC, categories.name ASC");
        $categoryQuery->execute($publishedCardIds);
        foreach ($categoryQuery->fetchAll(PDO::FETCH_ASSOC) as $category) $cardCategories[$category['summary_card_id']][] = $category;
    }
    $cards = array_map(static function (array $card): array {
        $card['display_precision'] = (int)($card['display_precision'] ?? 2);
        $card['display_order'] = (int)($card['display_order'] ?? 0);
        $card['is_published'] = (bool)($card['is_published'] ?? false);
        return $card;
    }, $cardRows);
    foreach ($cards as &$card) {
        $assigned = $cardCategories[$card['id']] ?? [];
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

    $categoryRows = $pdo->query("SELECT categories.category_id AS id, categories.name, categories.slug, categories.sort_order
        FROM summary_card_categories categories
        WHERE EXISTS (
            SELECT 1 FROM summary_card_category_map mapping
            INNER JOIN summary_cards cards ON cards.card_id = mapping.card_id
            WHERE mapping.category_id = categories.category_id AND cards.is_published = 1
        )
        ORDER BY categories.sort_order ASC, categories.name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $starCardRows = $pdo->query("SELECT cards.card_id AS id, cards.title,
            (SELECT setting_value FROM star_rating_settings WHERE card_id = cards.card_id AND setting_key = 'logo_path' LIMIT 1) AS logo_path,
            cards.year,
            CAST(COALESCE((SELECT setting_value FROM star_rating_settings WHERE card_id = cards.card_id AND setting_key = 'display_order' LIMIT 1), cards.card_id) AS UNSIGNED) AS display_order,
            cards.is_published
        FROM star_rating_cards cards WHERE cards.is_published = 1 ORDER BY display_order ASC, cards.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $starCardIds = array_column($starCardRows, 'id');
    if ($starCardIds) {
        $placeholders = implode(',', array_fill(0, count($starCardIds), '?'));
        $starRowsQuery = $pdo->prepare("SELECT rating_rows.card_id, rating_rows.label,
                COALESCE(CAST(row_settings.setting_value AS UNSIGNED), 5) AS max_stars,
                rating_rows.value AS score, rating_rows.display_order
            FROM star_rating_rows rating_rows
            LEFT JOIN star_rating_settings row_settings ON row_settings.card_id = rating_rows.card_id
                AND row_settings.setting_key = CONCAT('row:', rating_rows.row_id, ':max_stars')
            WHERE rating_rows.card_id IN ($placeholders)
            ORDER BY rating_rows.display_order ASC, rating_rows.row_id ASC");
        $starRowsQuery->execute($starCardIds);
        $starRowsByCard = [];
        foreach ($starRowsQuery->fetchAll(PDO::FETCH_ASSOC) as $starRow) {
            $starRow['max_stars'] = (int)$starRow['max_stars'];
            $starRow['score'] = (float)$starRow['score'];
            $starRow['display_order'] = (int)$starRow['display_order'];
            $starRowsByCard[$starRow['card_id']][] = $starRow;
        }
        foreach ($starCardRows as &$starCard) {
            $starCard['display_order'] = (int)$starCard['display_order'];
            $starCard['is_published'] = (bool)$starCard['is_published'];
            $starCard['rows'] = $starRowsByCard[$starCard['id']] ?? [];
        }
        $starCategoryQuery = $pdo->prepare("SELECT mapping.card_id, categories.category_id AS id, categories.name, categories.slug
            FROM star_rating_card_category_map mapping
            INNER JOIN star_rating_categories categories ON categories.category_id = mapping.category_id
            WHERE mapping.card_id IN ($placeholders)
            ORDER BY categories.sort_order ASC, categories.name ASC");
        $starCategoryQuery->execute($starCardIds);
        $starCategoriesByCard = [];
        foreach ($starCategoryQuery->fetchAll(PDO::FETCH_ASSOC) as $category) $starCategoriesByCard[$category['card_id']][] = $category;
        foreach ($starCardRows as &$starCard) {
            $assigned = $starCategoriesByCard[$starCard['id']] ?? [];
            $starCard['category_ids'] = array_map(static fn(array $category): int => (int)$category['id'], $assigned);
            $starCard['categories'] = array_map(static fn(array $category): array => [
                'id' => (int)$category['id'], 'name' => $category['name'], 'slug' => $category['slug']
            ], $assigned);
            $starCard['category_names'] = array_column($assigned, 'name');
            $starCard['category_slugs'] = array_column($assigned, 'slug');
        }
        unset($starCard);
    }

    $starCategoryRows = $pdo->query("SELECT categories.category_id AS id, categories.name, categories.slug, categories.sort_order
        FROM star_rating_categories categories
        WHERE EXISTS (
            SELECT 1 FROM star_rating_card_category_map mapping
            INNER JOIN star_rating_cards cards ON cards.card_id = mapping.card_id
            WHERE mapping.category_id = categories.category_id AND cards.is_published = 1
        )
        ORDER BY categories.sort_order ASC, categories.name ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'graphs' => $rows, 'cards' => $cards, 'categories' => $categoryRows, 'star_rating_cards' => $starCardRows, 'star_rating_categories' => $starCategoryRows, 'field_colors' => $fieldColors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load published dashboard graphs.']);
}

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = db();
    $rows = $pdo->query("SELECT r.id, r.ranking_body_id, r.year, r.category, r.scope_id, scopes.name AS scope_name,
                                r.ranking_type, r.level, r.edition, r.rank_low, r.rank_high, r.global_rank,
                                CASE
                                    WHEN r.rank_low IS NOT NULL AND r.rank_high IS NOT NULL THEN (r.rank_low + r.rank_high) / 2
                                    WHEN r.rank_low IS NOT NULL THEN r.rank_low
                                    ELSE r.rank_value
                                END AS rank_value,
                                r.ph_rank, r.ph_rank_value, r.note,
                                rb.name AS body_name, rb.short_name AS body_short_name
                         FROM rankings r
                         INNER JOIN ranking_bodies rb ON rb.id = r.ranking_body_id
                         LEFT JOIN ranking_scopes scopes ON scopes.id = r.scope_id
                         WHERE rb.short_name <> 'DEMO'
                         ORDER BY r.year DESC, rb.name ASC, r.rank_value IS NULL ASC, r.rank_value ASC")->fetchAll(PDO::FETCH_ASSOC);
    $scopes = $pdo->query('SELECT id, name, sort_order FROM ranking_scopes ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $levels = $pdo->query('SELECT id, name, sort_order FROM ranking_levels ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $rankingTypes = $pdo->query("SELECT DISTINCT COALESCE(NULLIF(r.ranking_type, ''), rb.short_name) AS ranking_type
        FROM ranking_bodies rb INNER JOIN rankings r ON r.ranking_body_id = rb.id
        WHERE rb.short_name <> 'DEMO'
        ORDER BY ranking_type ASC")->fetchAll(PDO::FETCH_COLUMN);
    $years = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['year'], $rows)));
    rsort($years, SORT_NUMERIC);

    echo json_encode([
        'success' => true,
        'years' => $years,
        'scopes' => $scopes,
        'levels' => $levels,
        'ranking_types' => $rankingTypes,
        'rankings' => $rows
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load ranking data.']);
}

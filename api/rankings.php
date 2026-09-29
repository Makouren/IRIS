<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $rows = db()->query("SELECT r.id, r.year, r.category, r.global_rank, r.rank_value,
                                r.ph_rank, r.ph_rank_value, r.note,
                                rb.name AS body_name, rb.short_name AS body_short_name
                         FROM rankings r
                         INNER JOIN ranking_bodies rb ON rb.id = r.ranking_body_id
                         ORDER BY r.year DESC, rb.name ASC, r.rank_value IS NULL ASC, r.rank_value ASC")->fetchAll(PDO::FETCH_ASSOC);
    $years = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['year'], $rows)));
    rsort($years, SORT_NUMERIC);

    echo json_encode([
        'success' => true,
        'years' => $years,
        'rankings' => $rows
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load ranking data.']);
}

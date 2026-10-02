<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = db();
    $rows = $pdo->query("SELECT r.id, rb.name AS organization, r.ranking_type, r.year,
                                r.global_rank, r.rank_value, r.info_text
                         FROM rankings r
                         INNER JOIN ranking_bodies rb ON rb.id = r.ranking_body_id
                         WHERE rb.short_name <> 'DEMO'
                         ORDER BY r.year DESC, rb.name ASC, r.rank_value IS NULL ASC, r.rank_value ASC")->fetchAll(PDO::FETCH_ASSOC);
    $defaultsQuery = $pdo->query('SELECT default_organization, default_list FROM ranking_history_display_settings WHERE id = 1');
    $defaults = $defaultsQuery->fetch(PDO::FETCH_ASSOC) ?: ['default_organization' => null, 'default_list' => null];

    echo json_encode([
        'success' => true,
        'rankings' => $rows,
        'chart_defaults' => $defaults
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load ranking data.']);
}

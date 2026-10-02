<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/CustomImportFields.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = db();
    $customFieldsColumn = CustomImportFields::columnExists($pdo, 'rankings') ? 'r.custom_fields' : 'NULL AS custom_fields';
    $rows = $pdo->query("SELECT r.ranking_id AS id, rb.name AS organization, rb.short_name AS organization_short_name,
                                rb.sort_order AS organization_sort_order, rt.name AS ranking_type, r.year,
                                COALESCE(r.global_rank_display, CAST(r.global_rank AS CHAR)) AS global_rank,
                                r.rank_value, r.info_text, {$customFieldsColumn}
                         FROM rankings r
                         INNER JOIN ranking_bodies rb ON rb.ranking_body_id = r.ranking_body_id
                         LEFT JOIN ranking_types rt ON rt.ranking_type_id = r.ranking_type_id
                         WHERE rb.short_name <> 'DEMO'
                         ORDER BY rb.sort_order ASC, CASE
                             WHEN UPPER(rb.short_name) = 'WURI' AND LOWER(rt.name) LIKE '%overall%' THEN 0
                             WHEN UPPER(rb.short_name) = 'QS' AND LOWER(rt.name) LIKE '%asia%' AND LOWER(rt.name) NOT LIKE '%south eastern%' THEN 1
                             WHEN UPPER(rb.short_name) = 'QS' AND LOWER(rt.name) LIKE '%south eastern%' THEN 2
                             WHEN LOWER(rb.name) LIKE '%webometrics%' THEN 3
                             ELSE 4
                         END,
                         rb.name ASC, rt.name ASC, r.year ASC,
                         r.rank_value IS NULL ASC, r.rank_value ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) $row['custom_fields'] = CustomImportFields::decode($row['custom_fields'] ?? []);
    unset($row);
    $stateQuery = $pdo->query('SELECT state_data FROM app_change_state WHERE id = 1');
    $state = json_decode((string)$stateQuery->fetchColumn(), true);
    $defaults = is_array($state['ranking_history'] ?? null)
        ? $state['ranking_history']
        : ['default_organization' => null, 'default_list' => null];

    echo json_encode([
        'success' => true,
        'rankings' => $rows,
        'chart_defaults' => $defaults
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load ranking data.']);
}

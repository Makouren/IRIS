<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$parts = [];

$best = $pdo->query("SELECT COALESCE(r.global_rank_display, CAST(r.global_rank AS CHAR)) AS global_rank, rb.name AS body_name, r.year
    FROM rankings r JOIN ranking_bodies rb ON rb.ranking_body_id = r.ranking_body_id
    WHERE r.rank_value IS NOT NULL ORDER BY r.year DESC, r.rank_value LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$national = $pdo->query("SELECT COALESCE(ph_rank_display, CAST(ph_rank AS CHAR)) AS ph_rank, year FROM rankings WHERE ph_rank IS NOT NULL OR ph_rank_display IS NOT NULL ORDER BY year DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$headlineCount = (int)$pdo->query('SELECT COUNT(*) FROM rankings')->fetchColumn();
$programCount = (int)$pdo->query('SELECT COUNT(*) FROM programs')->fetchColumn();
$bodyCount = (int)$pdo->query('SELECT COUNT(*) FROM ranking_bodies')->fetchColumn();
$breakdownCount = (int)$pdo->query('SELECT COUNT(*) FROM ranking_breakdowns')->fetchColumn();
$accreditationCount = (int)$pdo->query('SELECT COUNT(DISTINCT program_id) FROM accreditations')->fetchColumn();
$publishedCount = (int)$pdo->query("SELECT COUNT(*) FROM saved_graphs sg INNER JOIN records r ON r.record_id = sg.record_id WHERE r.status = 'Approved' AND sg.is_published = 1")->fetchColumn();

$uploaded = $pdo->query("SELECT file_name, extracted_data, scanned_at
    FROM records WHERE status = 'Approved' ORDER BY scanned_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;

if ($uploaded) {
    $sheets = json_decode((string)$uploaded['extracted_data'], true) ?: [];
    $sheetCount = 0;
    $rowCount = 0;
    $metricCount = 0;
    $metricAverages = [];
    $collegeNames = [];
    $programLikeRows = 0;

    foreach ($sheets as $sheet) {
        if (!is_array($sheet)) continue;
        $sheetCount++;
        $rowCount += (int)($sheet['rowCount'] ?? count($sheet['rows'] ?? []));
        $headers = array_map(fn($header) => strtolower(trim((string)$header)), (array)($sheet['headers'] ?? []));
        $rows = array_values(array_filter((array)($sheet['rows'] ?? []), 'is_array'));
        foreach ((array)($sheet['numericStats'] ?? []) as $header => $stats) {
            $normalized = strtolower((string)$header);
            if (preg_match('/year|date|id|rank|code/', $normalized)) continue;
            $metricCount++;
            if (isset($stats['avg']) && is_numeric($stats['avg'])) $metricAverages[$header] = (float)$stats['avg'];
        }
        foreach ($headers as $index => $header) {
            if (preg_match('/college|faculty|department|school|academic unit/', $header)) {
                foreach ($rows as $row) {
                    $name = trim((string)($row[$index] ?? ''));
                    if ($name !== '') $collegeNames[$name] = true;
                }
            }
            if (preg_match('/program|course|degree/', $header)) $programLikeRows += count($rows);
        }
    }

    arsort($metricAverages, SORT_NUMERIC);
    $strongestMetric = array_key_first($metricAverages);
    $strongestValue = $strongestMetric !== null ? $metricAverages[$strongestMetric] : null;
    $parts[] = 'The latest published upload, ' . $uploaded['file_name'] . ', is the active source for this dashboard.';
    $parts[] = 'It contains ' . $sheetCount . ' worksheet' . ($sheetCount === 1 ? '' : 's') . ' and approximately ' . $rowCount . ' data rows.';
    if ($metricCount) {
        $metricText = $metricCount . ' meaningful numeric metric' . ($metricCount === 1 ? '' : 's');
        if ($strongestMetric !== null) $metricText .= ', led by ' . $strongestMetric . ' with an average of ' . number_format($strongestValue, 2);
        $parts[] = 'The workbook contributes ' . $metricText . '.';
    }
    if (count($collegeNames)) $parts[] = 'It includes data for ' . count($collegeNames) . ' college or academic unit' . (count($collegeNames) === 1 ? '' : 's') . '.';
    if ($programLikeRows) $parts[] = 'Program-related sheets contribute approximately ' . $programLikeRows . ' rows to the program results table.';
} else {
    $parts[] = 'No published workbook is currently active, so the dashboard is using the institutional database values.';
}

if (!empty($best['global_rank'])) $parts[] = 'The best global rank displayed is ' . $best['global_rank'] . (!empty($best['body_name']) ? ' from ' . $best['body_name'] : '') . '.';
if (!empty($national['ph_rank'])) $parts[] = 'The latest national Philippines rank displayed is ' . $national['ph_rank'] . '.';
$parts[] = 'The dashboard currently covers ' . $bodyCount . ' monitored ranking bodies, ' . $headlineCount . ' headline ranking entries, ' . $programCount . ' stored program records, ' . $breakdownCount . ' breakdown indicators, and ' . $accreditationCount . ' accredited programs.';
$parts[] = $publishedCount
    ? $publishedCount . ' published chart' . ($publishedCount === 1 ? ' is' : 's are') . ' available in Data & Report Visualization for registered users.'
    : 'No charts have been published to Data & Report Visualization yet.';

echo json_encode(['summary' => implode(' ', $parts)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

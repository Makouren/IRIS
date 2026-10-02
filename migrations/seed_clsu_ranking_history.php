<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/RankBoundsParser.php';
require_once __DIR__ . '/../includes/helpers/SummaryCardHistory.php';

$sources = [
    'CLSU news' => 'CLSU news (https://clsu.edu.ph)',
    'QS' => 'QS TopUniversities (https://www.topuniversities.com/universities/central-luzon-state-university)',
    'THE' => 'Times Higher Education (https://www.timeshighereducation.com)',
    'Webometrics' => 'Webometrics (https://www.webometrics.info)',
    'SCImago' => 'SCImago (https://www.scimagoir.com)',
    'uniRank' => 'uniRank (https://www.unirank.org)',
    'AppliedHE' => 'AppliedHE (https://www.appliedhe.com)',
    'AD Scientific Index' => 'AD Scientific Index (https://www.adscientificindex.com)'
];
$rows = [
    ['Webometrics', 'Webometrics - Philippines / Local', 2020, '15', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - Philippines / Local', 2021, '13', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - Philippines / Local', 2022, '18 of 287', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - Philippines / Local', 2023, '14 of 366', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - Philippines / Local', 2024, '14 of 364', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - Philippines / Local', 2025, '16', null, 'Webometrics'],
    ['SCImago', 'SCImago - Philippines / Local', 2025, '7', null, 'SCImago'],
    ['AD Scientific Index', 'AD Scientific Index', 2025, '16', null, 'AD Scientific Index'],
    ['uniRank', 'uniRank', 2026, '59', null, 'uniRank'],
    ['QS', 'QS Asia - South Eastern Asia / ASEAN', 2023, '118', null, 'QS'],
    ['QS', 'QS Asia - South Eastern Asia / ASEAN', 2025, '153', '18 of 25', 'QS'],
    ['QS', 'QS Asia - South Eastern Asia / ASEAN', 2026, '=161', null, 'QS'],
    ['QS', 'QS Asia - Asia / Asia', 2021, '601+', null, 'QS'],
    ['QS', 'QS Asia - Asia / Asia', 2022, '601-650', null, 'QS'],
    ['QS', 'QS Asia - Asia / Asia', 2023, '701-750', null, 'QS'],
    ['QS', 'QS Asia - Asia / Asia', 2025, '851-900', null, 'QS'],
    ['QS', 'QS Asia - Asia / Asia', 2026, '1001-1100', null, 'QS'],
    ['Webometrics', 'Webometrics - Southeast Asia / ASEAN', 2024, '239', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - Asia / Asia', 2024, '2201', null, 'Webometrics'],
    ['Webometrics', 'Webometrics - World / World', 2024, '5239', null, 'Webometrics'],
    ['AppliedHE', 'AppliedHE', 2024, '59', null, 'AppliedHE'],
    ['THE', 'THE Impact', 2022, '601-800', null, 'THE'],
    ['THE', 'THE Impact', 2023, '801-1000', null, 'THE'],
    ['THE', 'THE Impact', 2024, '801-1000', null, 'THE'],
    ['THE', 'THE Impact', 2026, '601-800', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 2', 2026, '201-300', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 3', 2026, '1001+', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 4', 2026, '401-600', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 5', 2026, '401-600', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 7', 2026, '601-800', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 11', 2026, '601-800', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 13', 2026, '601-800', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 14', 2026, '201-300', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 15', 2026, '401-600', null, 'THE'],
    ['THE', 'THE Impact SDG - SDG 17', 2026, '801-1000', null, 'THE'],
    ['WURI', 'WURI Innovation', 2023, '86', null, 'CLSU news'],
    ['WURI', 'WURI Innovation', 2024, '135', null, 'CLSU news'],
    ['WURI', 'WURI Innovation', 2025, '118 of 400', null, 'CLSU news'],
    ['WURI', 'WURI Innovation', 2026, '83', null, 'CLSU news'],
    ['SCImago', 'SCImago - World / World', 2025, '3195', null, 'SCImago']
];

$pdo = db();
$inserted = 0;
$updated = 0;
$skipped = 0;
$summaryCardsUpdated = 0;
$summaryCardsSkipped = 0;

function seed_summary_card_period(PDO $pdo, string $title, string $baselineValue, string $baselineYear, ?string $baselineLabel, string $nextValue, string $nextYear, ?string $nextLabel): bool
{
    $query = $pdo->prepare('SELECT card_id FROM summary_cards WHERE title = ? ORDER BY created_at DESC, card_id DESC LIMIT 1');
    $query->execute([$title]);
    $cardId = $query->fetchColumn();
    if ($cardId === false) return false;

    $periods = SummaryCardHistory::periods($pdo, (string)$cardId, true);
    $latest = SummaryCardHistory::latest($periods);
    if (!$latest) return false;
    $currentYear = substr((string)($latest['period_label'] ?: $latest['year_date'] ?? ''), 0, 4);
    $currentValue = (float)($latest['main_value'] ?? 0);
    $currentLabel = (string)($latest['main_label'] ?? '');
    $matchesBaseline = $currentValue === (float)$baselineValue && $currentYear === $baselineYear
        && ($baselineLabel === null || $currentLabel === $baselineLabel);
    $matchesSeeded = $currentValue === (float)$nextValue && $currentYear === $nextYear
        && ($nextLabel === null || $currentLabel === $nextLabel);
    if (!$matchesBaseline || $matchesSeeded) return false;

    $existingPeriod = $pdo->prepare('SELECT snapshot_id FROM summary_card_snapshots WHERE card_id = ? AND period_key = ? FOR UPDATE');
    $existingPeriod->execute([(int)$cardId, $nextYear]);
    if ($existingPeriod->fetchColumn()) return false;

    $snapshot = $pdo->prepare('INSERT INTO summary_card_snapshots (
            card_id, period_key, period_label, period_sort, period_precision, is_published,
            main_value, main_label, secondary_label, secondary_value, year_date, title,
            description, secondary_description, info_text, source_info, source_record_id,
            last_source_record_id, batch_id, last_batch_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $snapshot->execute([
        (int)$cardId, $nextYear, $nextYear, (int)$nextYear, 0, (int)($latest['is_published'] ?? 0),
        $nextValue, $nextLabel ?? $currentLabel, $latest['secondary_label'], $latest['secondary_value'],
        $nextYear . '-01-01', $latest['title'] ?? $title, $latest['description'],
        $latest['secondary_description'], $latest['info_text'], $latest['source_info'],
        $latest['source_record_id'], $latest['last_source_record_id'], $latest['batch_id'], $latest['last_batch_id']
    ]);
    SummaryCardHistory::syncLive($pdo, (string)$cardId);
    return true;
}

try {
    $pdo->beginTransaction();
    $bodyIds = [];
    $findBody = $pdo->prepare('SELECT ranking_body_id FROM ranking_bodies WHERE short_name = ? LIMIT 1');
    $insertBody = $pdo->prepare('INSERT INTO ranking_bodies (name, short_name) VALUES (?, ?)');
    $findType = $pdo->prepare('SELECT ranking_type_id FROM ranking_types WHERE ranking_body_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
    $insertType = $pdo->prepare('INSERT INTO ranking_types (ranking_body_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE ranking_type_id = LAST_INSERT_ID(ranking_type_id)');
    foreach ([
        'QS' => 'QS World University Rankings',
        'THE' => 'Times Higher Education',
        'Webometrics' => 'Webometrics Ranking',
        'SCImago' => 'SCImago Institutions Rankings',
        'AppliedHE' => 'AppliedHE',
        'AD Scientific Index' => 'AD Scientific Index',
        'WURI' => 'The World University Rankings for Innovation',
        'uniRank' => 'uniRank'
    ] as $shortName => $name) {
        $findBody->execute([$shortName]);
        $bodyId = $findBody->fetchColumn();
        if ($bodyId === false) {
            $insertBody->execute([$name, $shortName]);
            $bodyId = $pdo->lastInsertId();
        }
        $bodyIds[$shortName] = (int)$bodyId;
    }

    $findRows = $pdo->prepare('SELECT rankings.ranking_id AS id, rankings.seed_managed
        FROM rankings INNER JOIN ranking_types ON ranking_types.ranking_type_id = rankings.ranking_type_id
        WHERE rankings.ranking_body_id = ? AND LOWER(ranking_types.name) = LOWER(?) AND rankings.year = ?
        ORDER BY rankings.ranking_id FOR UPDATE');
    $insertRow = $pdo->prepare('INSERT INTO rankings (ranking_body_id, ranking_type_id, year, global_rank, global_rank_display, rank_low, rank_high, rank_value, ph_rank, ph_rank_display, ph_rank_value, source, seed_managed)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $updateRow = $pdo->prepare('UPDATE rankings SET ranking_type_id = ?, global_rank = ?, global_rank_display = ?, rank_low = ?, rank_high = ?, rank_value = ?, ph_rank = ?, ph_rank_display = ?, ph_rank_value = ?, source = ?, seed_managed = 1 WHERE ranking_id = ? AND seed_managed = 1');
    foreach ($rows as [$organization, $type, $year, $rank, $phRank, $sourceKey]) {
        $bodyId = $bodyIds[$organization];
        $findType->execute([$bodyId, $type]);
        $typeId = $findType->fetchColumn();
        if ($typeId === false) {
            $insertType->execute([$bodyId, $type]);
            $typeId = (int)$pdo->lastInsertId();
        }
        $findRows->execute([$bodyId, $type, $year]);
        $matches = $findRows->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) > 1 || ($matches && !(int)$matches[0]['seed_managed'])) {
            $skipped++;
            continue;
        }
        [$rankLow, $rankHigh, $rankValue] = RankBoundsParser::parse($rank);
        $phValue = $phRank === null ? null : parse_rank_to_value($phRank);
        $source = $sources[$sourceKey];
        if ($matches) {
            $updateRow->execute([(int)$typeId, $rankLow, $rank, $rankLow, $rankHigh, $rankValue, $phValue, $phRank, $phValue, $source, (int)$matches[0]['id']]);
            $updated++;
        } else {
            $insertRow->execute([$bodyId, (int)$typeId, $year, $rankLow, $rank, $rankLow, $rankHigh, $rankValue, $phValue, $phRank, $phValue, $source]);
            $inserted++;
        }
    }

    if (seed_summary_card_period($pdo, 'QS Rank - South East Asia', '153', '2025', null, '161', '2026', 'CLSU Rank in South East ASIA')) $summaryCardsUpdated++;
    else $summaryCardsSkipped++;
    if (seed_summary_card_period($pdo, 'BEST GLOBAL RANK', '86', '2023', 'WURI', '83', '2026', 'WURI 2026')) $summaryCardsUpdated++;
    else $summaryCardsSkipped++;
    $pdo->commit();
    printf("Inserted: %d\nUpdated seed-managed rows: %d\nSkipped manual or ambiguous rows: %d\nSummary cards updated: %d\nSummary cards skipped as manually changed: %d\n", $inserted, $updated, $skipped, $summaryCardsUpdated, $summaryCardsSkipped);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
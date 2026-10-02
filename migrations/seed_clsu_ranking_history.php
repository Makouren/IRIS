<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/RankBoundsParser.php';

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

try {
    $pdo->beginTransaction();
    $bodyIds = [];
    $findBody = $pdo->prepare('SELECT id FROM ranking_bodies WHERE short_name = ? LIMIT 1');
    $insertBody = $pdo->prepare('INSERT INTO ranking_bodies (name, short_name) VALUES (?, ?)');
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

    $findRows = $pdo->prepare('SELECT id, seed_managed FROM rankings WHERE ranking_body_id = ? AND LOWER(ranking_type) = LOWER(?) AND year = ? ORDER BY id FOR UPDATE');
    $insertRow = $pdo->prepare('INSERT INTO rankings (ranking_body_id, ranking_type, year, global_rank, rank_low, rank_high, rank_value, ph_rank, ph_rank_value, source, seed_managed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $updateRow = $pdo->prepare('UPDATE rankings SET ranking_type = ?, global_rank = ?, rank_low = ?, rank_high = ?, rank_value = ?, ph_rank = ?, ph_rank_value = ?, source = ?, seed_managed = 1 WHERE id = ? AND seed_managed = 1');
    foreach ($rows as [$organization, $type, $year, $rank, $phRank, $sourceKey]) {
        $bodyId = $bodyIds[$organization];
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
            $updateRow->execute([$type, $rank, $rankLow, $rankHigh, $rankValue, $phRank, $phValue, $source, (int)$matches[0]['id']]);
            $updated++;
        } else {
            $insertRow->execute([$bodyId, $type, $year, $rank, $rankLow, $rankHigh, $rankValue, $phRank, $phValue, $source]);
            $inserted++;
        }
    }

    $findCard = $pdo->prepare('SELECT id, main_value, year_date, main_label FROM summary_cards WHERE title = ? ORDER BY created_at DESC LIMIT 1');
    $updateCard = $pdo->prepare('UPDATE summary_cards SET main_value = ?, year_date = ?, main_label = ? WHERE id = ?');
    $findCard->execute(['QS Rank - South East Asia']);
    $card = $findCard->fetch(PDO::FETCH_ASSOC);
    if ($card) {
        $baseline = (string)$card['main_value'] === '153' && (string)$card['year_date'] === '2025';
        $seeded = (string)$card['main_value'] === '161' && (string)$card['year_date'] === '2026';
        if ($baseline || $seeded) { $updateCard->execute(['161', '2026', 'CLSU Rank in South East ASIA', $card['id']]); $summaryCardsUpdated++; }
        else $summaryCardsSkipped++;
    }
    $findCard->execute(['BEST GLOBAL RANK']);
    $card = $findCard->fetch(PDO::FETCH_ASSOC);
    if ($card) {
        $baseline = (string)$card['main_value'] === '86' && (string)$card['year_date'] === '2023' && (string)$card['main_label'] === 'WURI';
        $seeded = (string)$card['main_value'] === '83' && (string)$card['year_date'] === '2026' && (string)$card['main_label'] === 'WURI 2026';
        if ($baseline || $seeded) { $updateCard->execute(['83', '2026', 'WURI 2026', $card['id']]); $summaryCardsUpdated++; }
        else $summaryCardsSkipped++;
    }
    $pdo->commit();
    printf("Inserted: %d\nUpdated seed-managed rows: %d\nSkipped manual or ambiguous rows: %d\nSummary cards updated: %d\nSummary cards skipped as manually changed: %d\n", $inserted, $updated, $skipped, $summaryCardsUpdated, $summaryCardsSkipped);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
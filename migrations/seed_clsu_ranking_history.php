<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

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
$seedRows = [];
$add = static function (
    string $body,
    string $type,
    string $level,
    string $scope,
    int $year,
    string $edition,
    string $display,
    ?int $low,
    ?int $high,
    string $source,
    string $status = 'verified',
    ?string $note = null,
    ?string $phRank = null,
    ?string $category = null
) use (&$seedRows): void {
    $seedRows[] = compact('body', 'type', 'level', 'scope', 'year', 'edition', 'display', 'low', 'high', 'source', 'status', 'note', 'phRank', 'category');
};

$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2020, 'Annual', '15', 15, 15, 'Webometrics', 'verified', 'CLSU national rank.');
$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2021, 'Annual', '13', 13, 13, 'Webometrics', 'verified', 'CLSU national rank.');
$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2022, 'January', '18 of 287', 18, 18, 'Webometrics', 'verified', '18th among 287 Philippine institutions.');
$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2023, 'Early edition', '12', 12, 12, 'CLSU news', 'inferred', 'Inferred from the sequence reported in the source article.');
$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2023, 'July', '14 of 366', 14, 14, 'Webometrics', 'verified', '14th among 366 Philippine institutions.');
$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2024, 'July', '14 of 364', 14, 14, 'Webometrics', 'verified', '14th among 364 Philippine institutions.');
$add('Webometrics', 'Webometrics', 'Local', 'Philippines', 2025, 'Annual', '16', 16, 16, 'Webometrics', 'verified', 'CLSU national rank.');
$add('SCImago', 'SCImago', 'Local', 'Philippines', 2025, 'March', '7', 7, 7, 'SCImago', 'verified', 'CLSU national rank.');
$add('AD Scientific Index', 'AD Scientific Index', 'Local', 'Philippines', 2025, 'Annual', '16', 16, 16, 'AD Scientific Index', 'verified', 'CLSU national rank.');
$add('uniRank', 'uniRank', 'Local', 'Philippines', 2026, 'Annual', '59', 59, 59, 'uniRank', 'assumed', 'Assumed national rank; source page is ambiguous.');

$add('QS', 'QS Asia', 'ASEAN', 'South Eastern Asia', 2023, 'Annual', '118', 118, 118, 'QS');
$add('QS', 'QS Asia', 'ASEAN', 'South Eastern Asia', 2025, 'Annual', '153', 153, 153, 'QS', 'verified', '18th of 25 in the Philippines.', '18 of 25');
$add('QS', 'QS Asia', 'ASEAN', 'South Eastern Asia', 2026, 'Annual', '=161', 161, 161, 'QS', 'verified', 'Tied at rank 161.');
$add('Webometrics', 'Webometrics', 'ASEAN', 'Southeast Asia', 2024, 'July', '239', 239, 239, 'Webometrics');
$add('AppliedHE', 'AppliedHE', 'ASEAN', 'ASEAN public universities', 2024, 'Annual', '59', 59, 59, 'AppliedHE');

$add('QS', 'QS Asia', 'Asia', 'Asia', 2021, 'Annual', '601+', 601, null, 'QS');
$add('QS', 'QS Asia', 'Asia', 'Asia', 2022, 'Annual', '601-650', 601, 650, 'QS');
$add('QS', 'QS Asia', 'Asia', 'Asia', 2023, 'Annual', '701-750', 701, 750, 'QS');
$add('QS', 'QS Asia', 'Asia', 'Asia', 2025, 'Annual', '851-900', 851, 900, 'QS');
$add('QS', 'QS Asia', 'Asia', 'Asia', 2026, 'Annual', '1001-1100', 1001, 1100, 'QS');
$add('Webometrics', 'Webometrics', 'Asia', 'Asia', 2024, 'July', '2201', 2201, 2201, 'Webometrics');

$add('THE', 'THE Impact', 'World', 'Overall', 2022, 'Annual', '601-800', 601, 800, 'THE');
$add('THE', 'THE Impact', 'World', 'Overall', 2023, 'Annual', '801-1000', 801, 1000, 'THE');
$add('THE', 'THE Impact', 'World', 'Overall', 2024, 'Annual', '801-1000', 801, 1000, 'THE');
$add('THE', 'THE Impact', 'World', 'Overall', 2026, 'Annual', '601-800', 601, 800, 'THE');
$theImpactSdgs = [
    'SDG 2' => [201, 300], 'SDG 3' => [1001, null], 'SDG 4' => [401, 600],
    'SDG 5' => [401, 600], 'SDG 7' => [601, 800], 'SDG 11' => [601, 800],
    'SDG 13' => [601, 800], 'SDG 14' => [201, 300], 'SDG 15' => [401, 600],
    'SDG 17' => [801, 1000]
];
foreach ($theImpactSdgs as $sdg => [$low, $high]) {
    $display = $high === null ? $low . '+' : $low . '-' . $high;
    $add('THE', 'THE Impact SDG', 'World', $sdg, 2026, 'Annual', $display, $low, $high, 'THE', 'verified', $sdg . ' band.');
}

$add('WURI', 'WURI Innovation', 'World', 'Overall', 2023, 'Annual', '86', 86, 86, 'CLSU news');
$add('WURI', 'WURI Innovation', 'World', 'Overall', 2024, 'Annual', '135', 135, 135, 'CLSU news');
$add('WURI', 'WURI Innovation', 'World', 'Overall', 2025, 'Annual', '118 of 400', 118, 118, 'CLSU news', 'verified', 'Ranked 118th of 400.');
$add('WURI', 'WURI Innovation', 'World', 'Overall', 2026, 'Annual', '83', 83, 83, 'CLSU news');
$add('Webometrics', 'Webometrics', 'World', 'World', 2024, 'July', '5239', 5239, 5239, 'Webometrics', 'conflicting', 'An aggregator lists 4845 for the same edition.');
$add('SCImago', 'SCImago', 'World', 'World', 2025, 'March', '3195', 3195, 3195, 'SCImago');

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
    foreach (['QS' => 'QS World University Rankings', 'THE' => 'Times Higher Education', 'Webometrics' => 'Webometrics Ranking', 'SCImago' => 'SCImago Institutions Rankings', 'AppliedHE' => 'AppliedHE', 'AD Scientific Index' => 'AD Scientific Index', 'WURI' => 'The World University Rankings for Innovation', 'uniRank' => 'uniRank'] as $shortName => $bodyName) {
        $findBody->execute([$shortName]);
        $bodyId = $findBody->fetchColumn();
        if ($bodyId === false) {
            $insertBody->execute([$bodyName, $shortName]);
            $bodyId = $pdo->lastInsertId();
        }
        $bodyIds[$shortName] = (int)$bodyId;
    }

    $findScope = $pdo->prepare('SELECT id FROM ranking_scopes WHERE name = ? LIMIT 1');
    $insertScope = $pdo->prepare('INSERT INTO ranking_scopes (name, sort_order) VALUES (?, ?)');
    $nextScopeOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM ranking_scopes')->fetchColumn();
    $scopeIds = [];
    $findRow = $pdo->prepare('SELECT id, seed_managed FROM rankings WHERE ranking_body_id = ? AND scope_id = ? AND year = ? AND edition = ? LIMIT 1');
    $insertRow = $pdo->prepare('INSERT INTO rankings (ranking_body_id, scope_id, year, edition, category, ranking_type, level, global_rank, rank_low, rank_high, rank_value, ph_rank, ph_rank_value, note, source, verification_status, seed_managed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $updateRow = $pdo->prepare('UPDATE rankings SET category = ?, ranking_type = ?, level = ?, global_rank = ?, rank_low = ?, rank_high = ?, rank_value = ?, ph_rank = ?, ph_rank_value = ?, note = ?, source = ?, verification_status = ?, seed_managed = 1 WHERE id = ? AND seed_managed = 1');

    foreach ($seedRows as $row) {
        if (!isset($scopeIds[$row['scope']])) {
            $findScope->execute([$row['scope']]);
            $scopeId = $findScope->fetchColumn();
            if ($scopeId === false) {
                $insertScope->execute([$row['scope'], $nextScopeOrder++]);
                $scopeId = $pdo->lastInsertId();
            }
            $scopeIds[$row['scope']] = (int)$scopeId;
        }
        $bodyId = $bodyIds[$row['body']];
        $scopeId = $scopeIds[$row['scope']];
        $rankValue = $row['low'] === null ? null : ($row['high'] === null ? (float)$row['low'] : ($row['low'] + $row['high']) / 2);
        $phValue = $row['phRank'] !== null ? parse_rank_to_value($row['phRank']) : null;
        $findRow->execute([$bodyId, $scopeId, $row['year'], $row['edition']]);
        $existing = $findRow->fetch(PDO::FETCH_ASSOC);
        if ($existing && !(int)$existing['seed_managed']) {
            $skipped++;
            continue;
        }
        $values = [
            $bodyId, $scopeId, $row['year'], $row['edition'], $row['category'], $row['type'], $row['level'],
            $row['display'], $row['low'], $row['high'], $rankValue, $row['phRank'], $phValue,
            $row['note'], $sources[$row['source']], $row['status']
        ];
        if ($existing) {
            $updateRow->execute(array_merge(array_slice($values, 4), [(int)$existing['id']]));
            $updated++;
        } else {
            $insertRow->execute($values);
            $inserted++;
        }
    }

    $findCard = $pdo->prepare('SELECT id, main_value, year_date, main_label FROM summary_cards WHERE title = ? ORDER BY created_at DESC LIMIT 1');
    $updateCard = $pdo->prepare('UPDATE summary_cards SET main_value = ?, year_date = ?, main_label = ? WHERE id = ?');
    $findCard->execute(['QS Rank - South East Asia']);
    $card = $findCard->fetch(PDO::FETCH_ASSOC);
    if ($card) {
        $matchesBaseline = (string)$card['main_value'] === '153' && (string)$card['year_date'] === '2025';
        $matchesSeed = (string)$card['main_value'] === '161' && (string)$card['year_date'] === '2026';
        if ($matchesBaseline || $matchesSeed) {
            $updateCard->execute(['161', '2026', 'CLSU Rank in South East ASIA', $card['id']]);
            $summaryCardsUpdated++;
        } else {
            $summaryCardsSkipped++;
        }
    }
    $findCard->execute(['BEST GLOBAL RANK']);
    $card = $findCard->fetch(PDO::FETCH_ASSOC);
    if ($card) {
        $matchesBaseline = (string)$card['main_value'] === '86' && (string)$card['year_date'] === '2023' && (string)$card['main_label'] === 'WURI';
        $matchesSeed = (string)$card['main_value'] === '83' && (string)$card['year_date'] === '2026' && (string)$card['main_label'] === 'WURI 2026';
        if ($matchesBaseline || $matchesSeed) {
            $updateCard->execute(['83', '2026', 'WURI 2026', $card['id']]);
            $summaryCardsUpdated++;
        } else {
            $summaryCardsSkipped++;
        }
    }
    $pdo->commit();
    printf("Inserted: %d\nUpdated seed-managed rows: %d\nSkipped manual rows: %d\nSummary cards updated: %d\nSummary cards skipped as manually changed: %d\n", $inserted, $updated, $skipped, $summaryCardsUpdated, $summaryCardsSkipped);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
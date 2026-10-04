<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers/RecordSheetMerge.php';

function assertMergeSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
    }
}

$target = [
    'headers' => ['Category', 'Population'],
    'rows' => [['A', 14000], ['Target only', 3]],
    'rowCount' => 3,
    'numericStats' => [],
];
$source = [
    'headers' => ['Category', 'Population'],
    'rows' => [['A', 15000], ['Source only', 2]],
    'rowCount' => 3,
    'numericStats' => [],
];

$preview = record_merge_sheet($target, $source, [0]);
assertMergeSame([], $preview['conflicts'], 'differing values should be replaced automatically, not presented as conflicts.');
assertMergeSame(0, $preview['unresolved'], 'automatic source replacement should leave no unresolved conflicts.');
assertMergeSame([['A', 15000], ['Target only', 3], ['Source only', 2]], $preview['sheet']['rows'], 'source values should replace matching target values and union new rows.');
assertMergeSame(['Population' => 1], $preview['stats']['updatedByColumn'], 'source replacements should be counted by column.');

$blankSource = ['headers' => ['Category', 'Population'], 'rows' => [['A', '  ']], 'rowCount' => 2];
$preserveTarget = record_merge_sheet($target, $blankSource, [0]);
assertMergeSame(14000, $preserveTarget['sheet']['rows'][0][1], 'blank source values should not erase target values.');

$missingKey = record_merge_sheet($target, ['headers' => ['Rank'], 'rows' => [[4]], 'rowCount' => 2], [0]);
assertMergeSame(null, $missingKey['sheet'], 'a source missing the selected key must be rejected.');
assertMergeSame(true, isset($missingKey['error']), 'invalid merge input should report a clear error.');

echo "record sheet merge tests passed\n";

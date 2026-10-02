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
    'headers' => ['Category', 'Rank'],
    'rows' => [['A', 5], ['Target only', 3]],
    'rowCount' => 3,
    'numericStats' => [],
];
$source = [
    'headers' => ['Category', 'Rank'],
    'rows' => [['A', 4], ['Source only', 2]],
    'rowCount' => 3,
    'numericStats' => [],
];

$preview = record_merge_sheet($target, $source, [0]);
assertMergeSame(1, count($preview['conflicts']), 'different values for the same key and column should conflict.');
assertMergeSame(null, $preview['sheet'], 'an unresolved merge must not return an applicable sheet.');
assertMergeSame(1, $preview['unresolved'], 'the conflicting value should be unresolved.');
assertMergeSame(5, $preview['conflicts'][0]['targetValue'], 'the conflict should include the target value.');
assertMergeSame(4, $preview['conflicts'][0]['sourceValue'], 'the conflict should include the source value.');

$conflictId = $preview['conflicts'][0]['id'];
$keepTarget = record_merge_sheet($target, $source, [0], [$conflictId => 'target']);
$takeSource = record_merge_sheet($target, $source, [0], [$conflictId => 'source']);
assertMergeSame([['A', 5], ['Target only', 3], ['Source only', 2]], $keepTarget['sheet']['rows'], 'target resolution should preserve target values and union new rows.');
assertMergeSame([['A', 4], ['Target only', 3], ['Source only', 2]], $takeSource['sheet']['rows'], 'source resolution should apply source values and union new rows.');
assertMergeSame(0, $takeSource['unresolved'], 'the explicit source choice should resolve the conflict.');

$reverse = record_merge_sheet($source, $target, [0], ['0:1' => 'source']);
assertMergeSame([['A', 5], ['Source only', 2], ['Target only', 3]], $reverse['sheet']['rows'], 'reversing direction should make the selected source value win.');

$badResolution = record_merge_sheet($target, $source, [0], [$conflictId => 'discard']);
assertMergeSame(null, $badResolution['sheet'], 'unsupported conflict choices must not produce an applicable sheet.');
assertMergeSame(1, $badResolution['unresolved'], 'unsupported conflict choices must remain unresolved.');

$missingKey = record_merge_sheet($target, ['headers' => ['Rank'], 'rows' => [[4]], 'rowCount' => 2], [0]);
assertMergeSame(null, $missingKey['sheet'], 'a source missing the selected key must be rejected.');
assertMergeSame(true, isset($missingKey['error']), 'invalid merge input should report a clear error.');

echo "record sheet merge tests passed\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers/RecordFileHistory.php';

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iris-history-test-' . bin2hex(random_bytes(8));
$temporarySource = tempnam(sys_get_temp_dir(), 'iris-history-source-');
if ($temporarySource === false || !mkdir($root, 0750)) {
    throw new RuntimeException('Unable to create File History test fixtures.');
}
$source = $temporarySource . '.xlsx';
if (!rename($temporarySource, $source)) throw new RuntimeException('Unable to create the source file fixture.');

try {
    file_put_contents($source, 'history fixture');
    $snapshot = RecordFileHistory::storeFile($source, $root, 'fixture.xlsx', 'xlsx');
    $stored = RecordFileHistory::resolveFile($snapshot['key'], $root);
    if (!$stored || file_get_contents($stored) !== 'history fixture') {
        throw new RuntimeException('A File History snapshot did not preserve its file contents.');
    }
    if ($snapshot['file_size'] !== strlen('history fixture') || strlen($snapshot['file_sha256']) !== 64) {
        throw new RuntimeException('A File History snapshot has invalid size or hash metadata.');
    }
    if (RecordFileHistory::resolveFile('../outside.xlsx', $root) !== null) {
        throw new RuntimeException('File History accepted a path outside its private directory.');
    }
    RecordFileHistory::removeFile($snapshot['key'], $root);
    if (RecordFileHistory::resolveFile($snapshot['key'], $root) !== null) {
        throw new RuntimeException('File History cleanup did not remove an unreferenced fixture.');
    }
} finally {
    @unlink($source);
    $historyDirectory = $root . DIRECTORY_SEPARATOR . 'history';
    if (is_dir($historyDirectory)) @rmdir($historyDirectory);
    @rmdir($root);
}

echo "record file history tests passed\n";

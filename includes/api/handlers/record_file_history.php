<?php

        ensure_admin_for_mutation();
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
            $historyRecordId = filter_var($_GET['record_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$historyRecordId || $historyRecordId < 1) bad('A record id is required.');
            echo json_encode(['versions' => RecordFileHistory::listForRecord($pdo, $historyRecordId)]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'version') {
            $versionId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$versionId || $versionId < 1) bad('A version id is required.');
            $version = RecordFileHistory::loadVersion($pdo, $versionId);
            if (!$version) bad('File History version not found.', 404);
            $version['snapshot'] = record_file_history_display_snapshot((string)$version['snapshot_json']);
            unset($version['snapshot_json']);
            echo json_encode(['version' => $version]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download') {
            $versionId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$versionId || $versionId < 1) bad('A version id is required.');
            $version = RecordFileHistory::loadVersion($pdo, $versionId);
            if (!$version || empty($version['file_snapshot_key'])) bad('Archived file not found.', 404);
            $root = upload_storage_root();
            $path = $root ? RecordFileHistory::resolveFile((string)$version['file_snapshot_key'], $root) : null;
            if (!$path) bad('Archived file not found.', 404);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', (string)$version['original_file_name']) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore') {
            $versionId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$versionId || $versionId < 1) bad('A version id is required.');
            $actorId = (int)($_SESSION['user_id'] ?? 0);
            if ($actorId < 1) bad('A valid Super Admin session is required.', 403);
            $version = RecordFileHistory::loadVersion($pdo, $versionId);
            if (!$version) bad('File History version not found.', 404);
            $payload = json_decode((string)$version['snapshot_json'], true);
            if (!is_array($payload) || !is_array($payload['record'] ?? null)) bad('The archived version is invalid.', 409);
            $restored = $payload['record'];
            $restoredRecordId = (int)$version['record_id'];
            $root = upload_storage_root();
            $restoredUploadName = null;
            $newHistoryFiles = [];
            $apiRestoredRecord = null;
            $pdo->beginTransaction();
            try {
                $currentQuery = $pdo->prepare('SELECT * FROM records WHERE record_id = ? FOR UPDATE');
                $currentQuery->execute([$restoredRecordId]);
                $current = $currentQuery->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($current) {
                    $currentFile = record_file_history_copy_record_file($current);
                    if ($currentFile) $newHistoryFiles[] = $currentFile['key'];
                    $currentSnapshot = record_file_history_capture($pdo, $current);
                    record_file_history_insert($pdo, (string)$version['merge_group_id'], $restoredRecordId, (int)$version['source_record_id'], (int)$version['target_record_id'], 'pre-restore', 'restore', $actorId, $currentSnapshot, $currentFile);
                }

                if (!empty($version['file_snapshot_key'])) {
                    if (!$root) throw new RuntimeException('Private upload storage is unavailable.');
                    $archivedPath = RecordFileHistory::resolveFile((string)$version['file_snapshot_key'], $root);
                    if (!$archivedPath) throw new RuntimeException('The archived source file is unavailable.');
                    $extension = strtolower(pathinfo($archivedPath, PATHINFO_EXTENSION));
                    $restoredUploadName = bin2hex(random_bytes(24)) . '.' . $extension;
                    $destination = $root . DIRECTORY_SEPARATOR . $restoredUploadName;
                    if (!copy($archivedPath, $destination)) throw new RuntimeException('Unable to restore the archived source file.');
                    chmod($destination, 0640);
                    $restored['metadata'] = json_encode(array_merge(json_col($restored['metadata'] ?? null, []), ['stored_file' => $restoredUploadName]), JSON_THROW_ON_ERROR);
                    $restored['file_size'] = filesize($destination);
                } else {
                    $metadata = json_col($restored['metadata'] ?? null, []);
                    if (is_array($metadata)) unset($metadata['stored_file']);
                    $restored['metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
                }

                $pdo->prepare('DELETE FROM saved_graphs WHERE record_id = ?')->execute([$restoredRecordId]);
                if ($current) {
                    $fields = array_values(array_diff($pdo->query('SHOW COLUMNS FROM records')->fetchAll(PDO::FETCH_COLUMN), ['record_id']));
                    $setParts = [];
                    $values = [];
                    foreach ($fields as $field) {
                        if (!array_key_exists($field, $restored)) continue;
                        $setParts[] = '`' . str_replace('`', '``', $field) . '` = ?';
                        $values[] = $restored[$field];
                    }
                    $values[] = $restoredRecordId;
                    $pdo->prepare('UPDATE records SET ' . implode(', ', $setParts) . ' WHERE record_id = ?')->execute($values);
                } else {
                    $restored['record_id'] = $restoredRecordId;
                    insert_row_from_snapshot($pdo, 'records', $restored);
                }
                record_file_history_restore_graphs($pdo, $restoredRecordId, $payload['saved_graphs'] ?? []);
                $finalQuery = $pdo->prepare('SELECT * FROM records WHERE record_id = ?');
                $finalQuery->execute([$restoredRecordId]);
                $finalRecord = $finalQuery->fetch(PDO::FETCH_ASSOC);
                if (!$finalRecord) throw new RuntimeException('The restored record could not be verified.');
                $finalFile = record_file_history_copy_record_file($finalRecord);
                if ($finalFile) $newHistoryFiles[] = $finalFile['key'];
                record_file_history_insert($pdo, (string)$version['merge_group_id'], $restoredRecordId, (int)$version['source_record_id'], (int)$version['target_record_id'], 'restore-result', 'restore', $actorId, record_file_history_capture($pdo, $finalRecord), $finalFile);
                $loadedRestoredRecord = load_record($pdo, $restoredRecordId);
                if (!$loadedRestoredRecord) throw new RuntimeException('The restored record could not be reloaded.');
                $apiRestoredRecord = output_record($loadedRestoredRecord);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($newHistoryFiles as $fileKey) RecordFileHistory::removeFile($fileKey, (string)$root);
                if ($restoredUploadName && $root) @unlink($root . DIRECTORY_SEPARATOR . $restoredUploadName);
                throw $exception;
            }
            echo json_encode(['success' => true, 'record' => $apiRestoredRecord]);
            exit;
        }
        bad('Unsupported File History action.', 404);

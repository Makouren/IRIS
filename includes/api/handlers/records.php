<?php

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['preview-record-merge', 'merge-records'], true)) {
            $data = json_input();
            $sourceId = filter_var($data['source_id'] ?? null, FILTER_VALIDATE_INT);
            $targetId = filter_var($data['target_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$sourceId || $sourceId < 1 || !$targetId || $targetId < 1) bad('Choose an explicit source record and target record.');
            if ($sourceId === $targetId) bad('The source and target records must be different.');
            if (($data['method'] ?? 'merge') !== 'merge') bad('IRIS supports the merge method only.');
            $sourceSheetName = trim((string)($data['source_sheet_name'] ?? $data['sheet_name'] ?? ''));
            $targetSheetName = trim((string)($data['target_sheet_name'] ?? $data['sheet_name'] ?? ''));
            if ($sourceSheetName === '' || $targetSheetName === '') bad('Choose a worksheet to merge.');
            $keyColumns = $data['key_columns'] ?? null;
            if (!is_array($keyColumns) || !$keyColumns) bad('Select at least one key column.');
            foreach ($keyColumns as &$keyColumn) {
                $keyColumn = filter_var($keyColumn, FILTER_VALIDATE_INT);
                if ($keyColumn === false || $keyColumn < 0) bad('A selected key column is invalid.');
            }
            unset($keyColumn);
            $readPair = static function (bool $lock) use ($pdo, $sourceId, $targetId): array {
                $query = $pdo->prepare('SELECT records.*, offices.office_name FROM records LEFT JOIN offices ON offices.office_id = records.office_id
                    WHERE records.record_id IN (?, ?) ORDER BY records.record_id' . ($lock ? ' FOR UPDATE' : ''));
                $query->execute([$sourceId, $targetId]);
                $records = [];
                foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $record) $records[(int)$record['record_id']] = $record;
                if (!isset($records[$sourceId]) || !isset($records[$targetId])) bad('Source or target record not found.', 404);
                $source = $records[$sourceId];
                $target = $records[$targetId];
                $templateCompatibilityError = record_merge_template_compatibility_error($source, $target);
                if ($templateCompatibilityError !== null) bad($templateCompatibilityError, 409);
                foreach ([$source, $target] as $record) {
                    if (!in_array(strtolower((string)($record['file_type'] ?? '')), ['xlsx', 'csv', 'tsv'], true)) bad('Both records must use a supported spreadsheet type.', 409);
                }
                return [$source, $target];
            };
            [$source, $target] = $readPair(false);
            $sourceDigest = record_merge_record_digest($source);
            $targetDigest = record_merge_record_digest($target);
            $sourceData = json_col($source['extracted_data'] ?? null, []);
            $targetData = json_col($target['extracted_data'] ?? null, []);
            if (!is_array($sourceData) || !is_array($targetData)
                || !is_array($sourceData[$sourceSheetName] ?? null) || !is_array($targetData[$targetSheetName] ?? null)) {
                bad('The selected worksheet must exist in both records.', 409);
            }
            $allowPartialHeaderOverlap = record_merge_records_are_general($source, $target);
            $mergeResult = record_merge_sheet($targetData[$targetSheetName], $sourceData[$sourceSheetName], $keyColumns, $allowPartialHeaderOverlap);
            if (!empty($mergeResult['error'])) bad((string)$mergeResult['error'], 409);
            if ($action === 'preview-record-merge') {
                echo json_encode([
                    'success' => true,
                    'source_id' => $sourceId,
                    'target_id' => $targetId,
                    'source_sheet_name' => $sourceSheetName,
                    'target_sheet_name' => $targetSheetName,
                    'source_digest' => $sourceDigest,
                    'target_digest' => $targetDigest,
                    'conflicts' => $mergeResult['conflicts'],
                    'unresolved' => $mergeResult['unresolved'],
                    'stats' => $mergeResult['stats'] ?? null,
                    'sheet' => $mergeResult['sheet'],
                ]);
                exit;
            }
            if (!hash_equals((string)($data['source_digest'] ?? ''), $sourceDigest)
                || !hash_equals((string)($data['target_digest'] ?? ''), $targetDigest)) bad('The records changed after preview. Refresh the merge preview.', 409);
            if (!empty($mergeResult['unresolved']) || !is_array($mergeResult['sheet'] ?? null)) {
                http_response_code(409);
                echo json_encode(['error' => 'Resolve every conflict before merging.', 'conflicts' => $mergeResult['conflicts'], 'unresolved' => $mergeResult['unresolved']]);
                exit;
            }

            $mergeGroupId = record_file_history_group_id();
            $actorId = (int)($_SESSION['user_id'] ?? 0);
            if ($actorId < 1) bad('A valid Super Admin session is required.', 403);
            $consumeSource = ($data['consume_source'] ?? false) === true;
            $historyFiles = [];
            $movedTargetFile = null;
            $trashPath = null;
            $manifestPath = null;
            $temporaryManifestPath = null;
            $trashId = null;
            $apiResultRecord = null;
            $pdo->beginTransaction();
            try {
                [$source, $target] = $readPair(true);
                $lockedSourceDigest = record_merge_record_digest($source);
                $lockedTargetDigest = record_merge_record_digest($target);
                if (!hash_equals($sourceDigest, $lockedSourceDigest) || !hash_equals($targetDigest, $lockedTargetDigest)) {
                    throw new RuntimeException('The records changed after preview. Refresh the merge preview.');
                }
                $sourceData = json_col($source['extracted_data'] ?? null, []);
                $targetData = json_col($target['extracted_data'] ?? null, []);
                $allowPartialHeaderOverlap = record_merge_records_are_general($source, $target);
                $mergeResult = record_merge_sheet($targetData[$targetSheetName], $sourceData[$sourceSheetName], $keyColumns, $allowPartialHeaderOverlap);
                if (!empty($mergeResult['error'])) throw new RuntimeException((string)$mergeResult['error']);
                if (!empty($mergeResult['unresolved']) || !is_array($mergeResult['sheet'] ?? null)) {
                    throw new RuntimeException('The merge preview is no longer valid. Refresh it before merging.');
                }

                $targetSnapshot = record_file_history_capture($pdo, $target);
                $sourceSnapshot = record_file_history_capture($pdo, $source);
                $targetFile = record_file_history_copy_record_file($target);
                if ($targetFile) $historyFiles[] = $targetFile['key'];
                $sourceFile = record_file_history_copy_record_file($source);
                if ($sourceFile) $historyFiles[] = $sourceFile['key'];
                record_file_history_insert($pdo, $mergeGroupId, $targetId, $sourceId, $targetId, 'pre-merge-target', 'merge', $actorId, $targetSnapshot, $targetFile);
                record_file_history_insert($pdo, $mergeGroupId, $sourceId, $sourceId, $targetId, 'source-at-merge', 'merge', $actorId, $sourceSnapshot, $sourceFile);

                if ($consumeSource) {
                    $trashDirectory = merge_trash_directory(true);
                    if (!$trashDirectory) throw new RuntimeException('Private merge recovery storage is unavailable.');
                    cleanup_expired_merge_trash($trashDirectory);
                    $trashId = bin2hex(random_bytes(12));
                    $manifestPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.json';
                    $temporaryManifestPath = $manifestPath . '.tmp';
                    $targetMetadataBefore = json_col($target['metadata'] ?? null, []);
                    $oldStoredFile = is_array($targetMetadataBefore) ? (string)($targetMetadataBefore['stored_file'] ?? '') : '';
                    if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $oldStoredFile)) $oldStoredFile = null;
                    $sourceGraphs = $sourceSnapshot['saved_graphs'] ?? [];
                    $manifest = [
                        'trash_id' => $trashId,
                        'created_at' => gmdate('c'),
                        'old_id' => (string)$targetId,
                        'office_id' => (string)$sourceId,
                        'old_row' => $target,
                        'office_row' => $source,
                        'office_saved_graphs' => $sourceGraphs,
                        'old_stored_file' => $oldStoredFile,
                    ];
                    $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR);
                    if (file_put_contents($temporaryManifestPath, $encodedManifest, LOCK_EX) === false || !rename($temporaryManifestPath, $manifestPath)) {
                        throw new RuntimeException('Unable to write merge recovery manifest.');
                    }
                    chmod($manifestPath, 0640);
                }

                $mergedData = $targetData;
                $mergedData[$targetSheetName] = $mergeResult['sheet'];
                $sourceMetadata = json_col($source['metadata'] ?? null, []);
                $targetMetadata = is_array($sourceMetadata) ? $sourceMetadata : [];
                $mergeMetadata = [
                    'merged_at' => gmdate('c'),
                    'replaced_file' => (string)($target['file_name'] ?? 'previous file'),
                    'source_record_id' => $sourceId,
                    'source_office' => (string)($source['office_name'] ?? ''),
                    'trash_id' => $trashId,
                    'merge_group_id' => $mergeGroupId,
                ];
                $targetMetadata['merge'] = $mergeMetadata;
                $adminNotes = array_values(array_filter([
                    (string)($target['admin_notes'] ?? ''),
                    'Merged from ' . (string)($source['file_name'] ?? 'source record') . ' on ' . $mergeMetadata['merged_at'],
                ], static fn(string $note): bool => $note !== ''));
                $pdo->prepare('UPDATE records SET file_name = ?, file_type = ?, file_size = ?, extracted_data = ?, metadata = ?, admin_notes = ?, updated_at = NOW() WHERE record_id = ?')
                    ->execute([
                        $source['file_name'],
                        $source['file_type'],
                        $source['file_size'],
                        json_encode($mergedData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        json_encode($targetMetadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        implode("\n", $adminNotes),
                        $targetId,
                    ]);
                if ($consumeSource) {
                    $pdo->prepare('DELETE FROM saved_graphs WHERE record_id = ?')->execute([$sourceId]);
                    ImportedRecordDataCleanup::remove($pdo, (int)$sourceId);
                    $pdo->prepare('DELETE FROM records WHERE record_id = ?')->execute([$sourceId]);
                    $oldStoredFile = (string)($manifest['old_stored_file'] ?? '');
                    $newStoredFile = (string)($targetMetadata['stored_file'] ?? '');
                    if ($oldStoredFile !== '' && $oldStoredFile !== $newStoredFile && !stored_file_in_use($pdo, $oldStoredFile, (string)$targetId)) {
                        $sourcePath = stored_upload_path($oldStoredFile);
                        $extension = pathinfo($oldStoredFile, PATHINFO_EXTENSION);
                        $trashPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
                        if ($sourcePath && !file_exists($trashPath)) {
                            if (!rename($sourcePath, $trashPath)) throw new RuntimeException('Unable to move the previous target file to recovery storage.');
                            chmod($trashPath, 0640);
                            $movedTargetFile = $sourcePath;
                        }
                    }
                }
                $resultQuery = $pdo->prepare('SELECT * FROM records WHERE record_id = ?');
                $resultQuery->execute([$targetId]);
                $resultRecord = $resultQuery->fetch(PDO::FETCH_ASSOC);
                if (!$resultRecord) throw new RuntimeException('The merge result could not be verified.');
                $resultFile = record_file_history_copy_record_file($resultRecord);
                if ($resultFile) $historyFiles[] = $resultFile['key'];
                record_file_history_insert($pdo, $mergeGroupId, $targetId, $sourceId, $targetId, 'post-merge-result', 'merge', $actorId, record_file_history_capture($pdo, $resultRecord), $resultFile);
                $loadedResult = load_record($pdo, $targetId);
                if (!$loadedResult) throw new RuntimeException('The merge result could not be reloaded.');
                $apiResultRecord = output_record($loadedResult);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($movedTargetFile && $trashPath && is_file($trashPath)) @rename($trashPath, $movedTargetFile);
                if ($manifestPath) @unlink($manifestPath);
                if ($temporaryManifestPath) @unlink($temporaryManifestPath);
                $historyRoot = upload_storage_root();
                if ($historyRoot) foreach ($historyFiles as $fileKey) RecordFileHistory::removeFile($fileKey, $historyRoot);
                http_response_code(str_contains($exception->getMessage(), 'changed after preview') || str_contains($exception->getMessage(), 'conflict') ? 409 : 500);
                echo json_encode(['error' => $exception->getMessage()]);
                exit;
            }
            echo json_encode([
                'success' => true,
                'merge_group_id' => $mergeGroupId,
                'trash_id' => $trashId,
                'source_deleted' => $consumeSource,
                'stats' => $mergeResult['stats'] ?? null,
                'record' => $apiResultRecord,
            ]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $excludeImportRecords = ($_GET['exclude_import_records'] ?? '') === '1';
            $importRecordExclusion = $excludeImportRecords
                ? " AND COALESCE(
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(records.metadata, '$.upload_purpose')), ''),
                        (SELECT upload_profiles.destination FROM template_import_profiles upload_profiles WHERE upload_profiles.import_profile_id = records.import_profile_id LIMIT 1),
                        ''
                    ) NOT IN ('ranking_history', 'summary_cards')"
                : '';
            if ($id !== null) {
                $pdo->prepare('UPDATE records SET opened_at = COALESCE(opened_at, NOW()) WHERE record_id = ?')->execute([(int)$id]);
                $q = $pdo->prepare('SELECT records.record_id AS id, records.file_name AS fileName, records.file_type AS fileType,
                    records.file_size AS fileSize, records.scanned_at AS scannedAt, records.status, records.doc_type AS docType,
                    records.raw_text AS rawText, records.extracted_data AS extractedData, records.graph_drafts AS graphDrafts,
                    records.admin_notes AS adminNotes, records.metadata, records.updated_at AS updatedAt, records.uploaded_by,
                    records.office_id, offices.office_name, records.uploaded_at, records.template_id, records.import_profile_id,
                    COALESCE((SELECT import_profiles.destination FROM template_import_profiles import_profiles WHERE import_profiles.import_profile_id = records.import_profile_id LIMIT 1),
                        (SELECT template_profiles.destination FROM template_import_profiles template_profiles WHERE template_profiles.template_id = records.template_id LIMIT 1)) AS import_destination,
                    records.opened_at, templates.name AS template_name
                    FROM records LEFT JOIN templates ON templates.template_id = records.template_id
                    LEFT JOIN offices ON offices.office_id = records.office_id WHERE records.record_id=?' . $importRecordExclusion); $q->execute([(int)$id]);
                $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) bad('Record not found',404);
                echo json_encode(output_record($r)); exit;
            }
            $rows = $pdo->query('SELECT records.record_id AS id, records.file_name AS fileName, records.file_type AS fileType,
                records.file_size AS fileSize, records.scanned_at AS scannedAt, records.status, records.doc_type AS docType,
                records.raw_text AS rawText, records.extracted_data AS extractedData, records.graph_drafts AS graphDrafts,
                records.admin_notes AS adminNotes, records.metadata, records.updated_at AS updatedAt, records.uploaded_by,
                records.office_id, offices.office_name, records.uploaded_at, records.template_id, records.import_profile_id,
                COALESCE((SELECT import_profiles.destination FROM template_import_profiles import_profiles WHERE import_profiles.import_profile_id = records.import_profile_id LIMIT 1),
                    (SELECT template_profiles.destination FROM template_import_profiles template_profiles WHERE template_profiles.template_id = records.template_id LIMIT 1)) AS import_destination,
                records.opened_at, templates.name AS template_name
                FROM records LEFT JOIN templates ON templates.template_id = records.template_id
                LEFT JOIN offices ON offices.office_id = records.office_id WHERE 1=1' . $importRecordExclusion . ' ORDER BY records.scanned_at DESC, records.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array_map('output_record',$rows)); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'stage-restore') {
            ensure_admin_for_mutation();
            $existingId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$existingId || $existingId < 1) bad('Existing record id is required.');
            $data = json_input();
            $officeId = filter_var($data['office_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$officeId || $officeId < 1 || $officeId === $existingId) bad('A different office record id is required.');
            $trashDirectory = merge_trash_directory(true);
            if (!$trashDirectory) bad('Private merge recovery storage is unavailable.', 500);
            cleanup_expired_merge_trash($trashDirectory);
            $trashId = bin2hex(random_bytes(12));
            $manifestPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.json';
            $temporaryPath = $manifestPath . '.tmp';
            $pdo->beginTransaction();
            try {
                $oldQuery = $pdo->prepare('SELECT * FROM records WHERE record_id=? FOR UPDATE');
                $oldQuery->execute([$existingId]);
                $oldRow = $oldQuery->fetch(PDO::FETCH_ASSOC);
                if (!$oldRow) bad('Existing record not found.', 404);
                $officeQuery = $pdo->prepare('SELECT records.*, offices.office_name FROM records LEFT JOIN offices ON offices.office_id = records.office_id WHERE records.record_id=? FOR UPDATE');
                $officeQuery->execute([$officeId]);
                $officeRow = $officeQuery->fetch(PDO::FETCH_ASSOC);
                if (!$officeRow) bad('Office upload not found.', 404);
                if (strtolower((string)($officeRow['status'] ?? '')) !== 'pending review') bad('The office upload must still be Pending Review.', 409);
                if (empty($officeRow['uploaded_by']) || empty($officeRow['office_name'])) bad('The selected record is not an office upload.', 409);
                if (!in_array(strtolower((string)($officeRow['file_type'] ?? '')), ['xlsx', 'csv', 'tsv'], true)) bad('The office upload is not a supported spreadsheet.', 409);
                if (empty($oldRow['template_id']) || empty($officeRow['template_id']) || (string)$oldRow['template_id'] !== (string)$officeRow['template_id']) bad('Both records must be assigned to the same template.', 409);
                $oldMetadata = json_col($oldRow['metadata'] ?? null, []);
                $officeMetadata = json_col($officeRow['metadata'] ?? null, []);
                if (!is_array($oldMetadata)) $oldMetadata = [];
                if (!is_array($officeMetadata)) $officeMetadata = [];
                $graphQuery = $pdo->prepare('SELECT * FROM saved_graphs WHERE record_id=?');
                $graphQuery->execute([$officeId]);
                $officeGraphs = $graphQuery->fetchAll(PDO::FETCH_ASSOC);
                foreach ($officeGraphs as &$graph) {
                    $seriesQuery = $pdo->prepare('SELECT * FROM graph_series WHERE graph_id = ? ORDER BY display_order, series_id');
                    $seriesQuery->execute([(int)$graph['graph_id']]);
                    $graph['_series'] = $seriesQuery->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($graph['_series'] as &$series) {
                        $pointsQuery = $pdo->prepare('SELECT * FROM graph_points WHERE series_id = ? ORDER BY display_order, point_id');
                        $pointsQuery->execute([(int)$series['series_id']]);
                        $series['_points'] = $pointsQuery->fetchAll(PDO::FETCH_ASSOC);
                    }
                    unset($series);
                    $colorsQuery = $pdo->prepare('SELECT * FROM graph_colors WHERE graph_id = ? ORDER BY color_id');
                    $colorsQuery->execute([(int)$graph['graph_id']]);
                    $graph['_colors'] = $colorsQuery->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($graph);
                $oldStoredFile = (string)($oldMetadata['stored_file'] ?? '');
                if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $oldStoredFile)) $oldStoredFile = null;
                $manifest = [
                    'trash_id' => $trashId,
                    'created_at' => gmdate('c'),
                    'old_id' => (string)$existingId,
                    'office_id' => (string)$officeId,
                    'old_row' => $oldRow,
                    'office_row' => $officeRow,
                    'office_saved_graphs' => $officeGraphs,
                    'old_stored_file' => $oldStoredFile,
                ];
                $encoded = json_encode($manifest, JSON_THROW_ON_ERROR);
                if (file_put_contents($temporaryPath, $encoded, LOCK_EX) === false || !rename($temporaryPath, $manifestPath)) {
                    @unlink($temporaryPath);
                    bad('Unable to write merge recovery manifest.', 500);
                }
                chmod($manifestPath, 0640);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                @unlink($temporaryPath);
                @unlink($manifestPath);
                throw $exception;
            }
            echo json_encode(['success' => true, 'trash_id' => $trashId]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'trash-stored-file') {
            ensure_admin_for_mutation();
            if ($id === null || $id === '') bad('Existing record id is required.');
            $data = json_input();
            $trashId = (string)($data['trash_id'] ?? '');
            [$trashDirectory, $manifestPath, $manifest] = load_merge_manifest($trashId);
            if ((string)($manifest['old_id'] ?? '') !== (string)$id) bad('Recovery manifest does not match this record.', 409);
            $recordQuery = $pdo->prepare('SELECT metadata FROM records WHERE record_id=?');
            $recordQuery->execute([(int)$id]);
            $recordMetadata = json_col($recordQuery->fetchColumn(), []);
            if (!is_array($recordMetadata) || (string)($recordMetadata['merge']['trash_id'] ?? '') !== $trashId) bad('The record has not been staged for this merge.', 409);
            $name = $manifest['old_stored_file'] ?? null;
            if (!$name) {
                echo json_encode(['success' => true, 'moved' => false]);
                exit;
            }
            $sourcePath = stored_upload_path((string)$name);
            if (!$sourcePath) {
                $extension = pathinfo((string)$name, PATHINFO_EXTENSION);
                $existingTrashPath = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
                if (is_file($existingTrashPath)) {
                    echo json_encode(['success' => true, 'moved' => true]);
                    exit;
                }
                bad('The previous stored file could not be found.', 404);
            }
            if (stored_file_in_use($pdo, (string)$name, (string)$id)) bad('The previous file is still referenced by another record.', 409);
            $extension = pathinfo((string)$name, PATHINFO_EXTENSION);
            $destination = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
            if (file_exists($destination)) bad('A recovery file already exists for this merge.', 409);
            if (!rename($sourcePath, $destination)) bad('Unable to move the previous file to trash.', 500);
            chmod($destination, 0640);
            echo json_encode(['success' => true, 'moved' => true]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore-merge') {
            ensure_admin_for_mutation();
            $existingId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$existingId || $existingId < 1) bad('Existing record id is required.');
            $data = json_input();
            $trashId = (string)($data['trash_id'] ?? '');
            [$trashDirectory, $manifestPath, $manifest] = load_merge_manifest($trashId);
            if ((string)($manifest['old_id'] ?? '') !== (string)$existingId) bad('Recovery manifest does not match this record.', 409);
            $createdAt = strtotime((string)($manifest['created_at'] ?? ''));
            if ($createdAt === false || time() - $createdAt > 30 * 24 * 60 * 60) bad('Merge recovery has expired.', 410);
            $oldRow = $manifest['old_row'] ?? null;
            $officeRow = $manifest['office_row'] ?? null;
            if (!is_array($oldRow) || !is_array($officeRow) || (string)($officeRow['record_id'] ?? '') !== (string)($manifest['office_id'] ?? '')) bad('Recovery manifest is incomplete.', 500);
            $currentQuery = $pdo->prepare('SELECT metadata FROM records WHERE record_id=?');
            $currentQuery->execute([$existingId]);
            $currentRow = $currentQuery->fetch(PDO::FETCH_ASSOC);
            if (!$currentRow) bad('Merged record not found.', 404);
            $currentMetadata = json_col($currentRow['metadata'] ?? null, []);
            if (!is_array($currentMetadata) || (string)($currentMetadata['merge']['trash_id'] ?? '') !== $trashId) bad('This merge was already restored or replaced.', 409);
            $officeCheck = $pdo->prepare('SELECT record_id FROM records WHERE record_id=?');
            $officeCheck->execute([$manifest['office_id']]);
            if ($officeCheck->fetchColumn()) bad('The office upload id is already in use.', 409);

            $pdo->beginTransaction();
            try {
                $restoreFields = array_values(array_diff($pdo->query('SHOW COLUMNS FROM records')->fetchAll(PDO::FETCH_COLUMN), ['record_id']));
                $sets = [];
                $values = [];
                foreach ($restoreFields as $field) {
                    if (!array_key_exists($field, $oldRow)) continue;
                    $sets[] = '`' . $field . '`=?';
                    $values[] = $oldRow[$field];
                }
                $values[] = $existingId;
                $pdo->prepare('UPDATE records SET ' . implode(',', $sets) . ' WHERE record_id=?')->execute($values);
                insert_row_from_snapshot($pdo, 'records', $officeRow);
                foreach (($manifest['office_saved_graphs'] ?? []) as $graphRow) {
                    if (is_array($graphRow)) insert_graph_from_snapshot($pdo, $graphRow);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }

            $fileRestored = true;
            $oldName = $manifest['old_stored_file'] ?? null;
            if ($oldName) {
                $existingOriginal = stored_upload_path((string)$oldName);
                if (!$existingOriginal) {
                    $root = upload_storage_root();
                    $extension = pathinfo((string)$oldName, PATHINFO_EXTENSION);
                    $trashedFile = $trashDirectory . DIRECTORY_SEPARATOR . $trashId . '.' . $extension;
                    $destination = $root ? $root . DIRECTORY_SEPARATOR . $oldName : '';
                    if (!$root || !$destination || file_exists($destination) || !is_file($trashedFile) || !rename($trashedFile, $destination)) {
                        $fileRestored = false;
                        error_log('IRIS merge restore could not return prior file for recovery id ' . $trashId);
                    } else {
                        chmod($destination, 0640);
                    }
                }
            }
            if (!@unlink($manifestPath)) error_log('IRIS merge restore could not delete manifest ' . $trashId);
            $restoredRecord = load_record($pdo, $existingId);
            echo json_encode(['success' => true, 'record' => output_record($restoredRecord), 'file_restored' => $fileRestored]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-approve') {
            ensure_admin_for_mutation();
            $data=json_input();
            $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $q=$pdo->prepare("SELECT record_id AS id FROM records WHERE record_id IN ($ph)"); $q->execute(array_map('intval', $ids));
            $existing=$q->fetchAll(PDO::FETCH_COLUMN);
            if ($existing) {
                $ph2=implode(',',array_fill(0,count($existing),'?'));
                $pdo->prepare("UPDATE records SET status='Approved', updated_at=NOW() WHERE record_id IN ($ph2)")->execute($existing);
            }
            $set=array_fill_keys($existing,true); $results=[];
            foreach($ids as $x) $results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['bulk-publish', 'bulk-unpublish'], true)) {
            ensure_admin_for_mutation();
            $data = json_input();
            $ids = array_values(array_unique(array_filter($data['ids'] ?? [], fn($x) => is_scalar($x) && $x !== '')));
            if (!$ids) bad('ids must be a non-empty array');
            $published = $action === 'bulk-publish';
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->beginTransaction();
            try {
                $query = $pdo->prepare("SELECT record_id AS id FROM records WHERE record_id IN ($placeholders) FOR UPDATE");
                $query->execute(array_map('intval', $ids));
                $existing = $query->fetchAll(PDO::FETCH_COLUMN);
                $graphCount = 0;
                if ($existing) {
                    $recordPlaceholders = implode(',', array_fill(0, count($existing), '?'));
                    $pdo->prepare('UPDATE records SET status=?, updated_at=NOW() WHERE record_id IN ('.$recordPlaceholders.')')->execute(array_merge([$published ? 'Approved' : 'Pending Review'], $existing));
                    $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=? WHERE record_id IN ('.$recordPlaceholders.') AND is_published<>?');
                    $graphs->execute(array_merge([$published ? 1 : 0], $existing, [$published ? 1 : 0]));
                    $graphCount = $graphs->rowCount();
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $existingSet = array_fill_keys($existing, true);
            $results = [];
            foreach ($ids as $recordId) $results[] = ['id'=>$recordId,'success'=>isset($existingSet[$recordId]),'error'=>isset($existingSet[$recordId]) ? null : 'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing),'published_graph_count'=>$graphCount]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'unpublish') {
            ensure_admin_for_mutation();
            if ($id === null) bad('Record id is required');
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare('SELECT record_id FROM records WHERE record_id=? FOR UPDATE');
                $q->execute([(int)$id]);
                if (!$q->fetch()) {
                    $pdo->rollBack();
                    bad('Record not found', 404);
                }
                $pdo->prepare("UPDATE records SET status='Pending Review', updated_at=NOW() WHERE record_id=?")->execute([(int)$id]);
                $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=0 WHERE record_id=? AND is_published=1');
                $graphs->execute([(int)$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $q = $pdo->prepare('SELECT record_id AS id, file_name AS fileName, file_type AS fileType, file_size AS fileSize, scanned_at AS scannedAt, status, doc_type AS docType, raw_text AS rawText, extracted_data AS extractedData, graph_drafts AS graphDrafts, admin_notes AS adminNotes, metadata, updated_at AS updatedAt FROM records WHERE record_id=?');
            $q->execute([(int)$id]);
            echo json_encode(['success'=>true,'record'=>output_record($q->fetch(PDO::FETCH_ASSOC)),'unpublished_graph_count'=>$graphs->rowCount()]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-delete') {
            ensure_admin_for_mutation();
            $data=json_input(); $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $override = !empty($data['override_protection']);
            $existingRows = [];
            $pdo->beginTransaction();
            try {
                $ph=implode(',',array_fill(0,count($ids),'?'));
                $q=$pdo->prepare("SELECT record_id AS id, metadata FROM records WHERE record_id IN ($ph) FOR UPDATE");
                $q->execute(array_map('intval', $ids));
                $existingRows=$q->fetchAll(PDO::FETCH_ASSOC);
                $existing=array_column($existingRows,'id');
                if ($existing) {
                    ImportedRecordDataCleanup::removeMany($pdo, array_map('intval', $existing), $override);
                    $ph2=implode(',',array_fill(0,count($existing),'?'));
                    $pdo->prepare("DELETE FROM saved_graphs WHERE record_id IN ($ph2)")->execute($existing);
                    $q=$pdo->prepare("DELETE FROM records WHERE record_id IN ($ph2)");$q->execute($existing);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $existing=array_column($existingRows,'id');
            foreach ($existingRows as $deletedRecord) unlink_stored_upload_after_delete($pdo, $deletedRecord);
            $set=array_fill_keys($existing,true); $results=[]; foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]); exit;
        }
        $data=json_input();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            if (!ALLOW_SUPER_ADMIN_UPLOAD && ($data['fileType'] ?? $data['type'] ?? '') !== 'manual') bad('File uploads are disabled for Super Admin.', 403);
            $fileName = trim((string)($data['fileName'] ?? $data['name'] ?? 'Untitled'));
            $fileType = trim((string)($data['fileType'] ?? $data['type'] ?? 'unknown'));
            $docType = trim((string)($data['docType'] ?? 'General Institutional Data'));
            $status = (string)($data['status'] ?? 'Pending Review');
            $fileNameLength = function_exists('mb_strlen') ? mb_strlen($fileName, 'UTF-8') : strlen($fileName);
            $fileTypeLength = function_exists('mb_strlen') ? mb_strlen($fileType, 'UTF-8') : strlen($fileType);
            $docTypeLength = function_exists('mb_strlen') ? mb_strlen($docType, 'UTF-8') : strlen($docType);
            if ($fileName === '' || $fileNameLength > 255) bad('File name is required and must not exceed 255 characters.');
            if ($fileType === '' || $fileTypeLength > 100) bad('File type is required and must not exceed 100 characters.');
            if ($docType === '' || $docTypeLength > 100) bad('Classification category is required and must not exceed 100 characters.');
            if (!in_array($status, ['Pending Review', 'Approved', 'Needs Revision'], true)) bad('Invalid record status.');
            $account = $pdo->prepare('SELECT office_id FROM users WHERE user_id = ?');
            $account->execute([(int)$_SESSION['user_id']]);
            $officeId = $account->fetchColumn();
            $scannedAt = strtotime((string)($data['scannedAt'] ?? 'now'));
            $stmt = $pdo->prepare('INSERT INTO records (file_name, file_type, file_size, scanned_at, status, uploaded_by, office_id, uploaded_at, template_id, import_profile_id, doc_type, raw_text, extracted_data, graph_drafts, admin_notes, metadata, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $fileName, $fileType,
                max(0, (int)($data['fileSize'] ?? $data['size'] ?? 0)), date('Y-m-d H:i:s', $scannedAt === false ? time() : $scannedAt),
                $status, (int)$_SESSION['user_id'], $officeId ?: null,
                filter_var($data['template_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                filter_var($data['import_profile_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                $docType, $data['rawText'] ?? '',
                json_encode($data['extractedData'] ?? $data['sheetsData'] ?? []), json_encode($data['graphDrafts'] ?? []),
                $data['adminNotes'] ?? '', json_encode($data['metadata'] ?? [])
            ]);
            $id2 = (int)$pdo->lastInsertId();
            echo json_encode(output_record(load_record($pdo, $id2) ?? []));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            $recordId = filter_var($id, FILTER_VALIDATE_INT);
            if (!$recordId || $recordId < 1) bad('Record id is required'); $data=json_input();
            $fieldMap = ['fileName' => 'file_name', 'fileType' => 'file_type', 'fileSize' => 'file_size', 'status' => 'status', 'docType' => 'doc_type', 'rawText' => 'raw_text', 'adminNotes' => 'admin_notes'];
            $sets=[];$vals=[];
            if (array_key_exists('status',$data) && !in_array($data['status'], ['Pending Review','Approved','Needs Revision'], true)) bad('Invalid record status');
            foreach (['fileName' => 255, 'fileType' => 100, 'docType' => 100] as $field => $maximum) {
                if (!array_key_exists($field, $data)) continue;
                $value = trim((string)$data[$field]);
                $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
                $label = $field === 'fileName' ? 'File name' : ($field === 'docType' ? 'Classification category' : 'File type');
                if ($value === '' || $length > $maximum) bad("$label is required and must not exceed $maximum characters.");
                $data[$field] = $value;
            }
            if (array_key_exists('fileSize', $data) && (!is_numeric($data['fileSize']) || (float)$data['fileSize'] < 0 || (float)$data['fileSize'] > PHP_INT_MAX)) bad('File size must be a non-negative number.');
            if (array_key_exists('adminNotes', $data) && strlen((string)$data['adminNotes']) > 65535) bad('Admin verification notes must not exceed 65,535 bytes.');
            foreach($fieldMap as $field => $column) if(array_key_exists($field,$data)){ $sets[]="`$column`=?";$vals[]=$field === 'fileSize' ? (int)$data[$field] : $data[$field]; }
            foreach(['extractedData' => 'extracted_data', 'graphDrafts' => 'graph_drafts', 'metadata' => 'metadata'] as $field => $column) if(array_key_exists($field,$data)){ $sets[]="`$column`=?";$vals[]=json_encode($data[$field]); }
            if(array_key_exists('scannedAt',$data)){ $timestamp = strtotime((string)$data['scannedAt']); if ($timestamp === false) bad('Invalid scannedAt value.'); $sets[]='scanned_at=?';$vals[]=date('Y-m-d H:i:s',$timestamp); }
            if(!$sets) bad('No fields to update'); $sets[]='updated_at=NOW()';$vals[]=$recordId;$stmt=$pdo->prepare('UPDATE records SET '.implode(',',$sets).' WHERE record_id=?');$stmt->execute($vals);if(!$stmt->rowCount() && !load_record($pdo, $recordId))bad('Record not found',404);
            echo json_encode(output_record(load_record($pdo, $recordId) ?? []));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            $recordId = filter_var($id, FILTER_VALIDATE_INT);
            if(!$recordId || $recordId < 1)bad('Record id is required');
            $pdo->beginTransaction();
            try {
                $r = load_record($pdo, $recordId, true);if(!$r)bad('Record not found',404);
                ImportedRecordDataCleanup::remove($pdo, $recordId);
                $pdo->prepare('DELETE FROM saved_graphs WHERE record_id=?')->execute([$recordId]);
                $q=$pdo->prepare('DELETE FROM records WHERE record_id=?');$q->execute([$recordId]);
                if($q->rowCount() < 1) bad('Record not found',404);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            unlink_stored_upload_after_delete($pdo,$r);
            echo json_encode(['message'=>'Record and published graphs deleted','record'=>$r]);exit;
        }

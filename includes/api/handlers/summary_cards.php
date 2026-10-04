<?php

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = ($_SESSION['role'] ?? '') === 'super_admin'
                ? SummaryCardHistory::publishedCards($pdo, false)
                : SummaryCardHistory::publishedCards($pdo);
            $rows = attach_summary_card_categories($pdo, $rows);
            echo json_encode(array_map(static function (array $card): array {
                $card['display_precision'] = (int)($card['display_precision'] ?? 2);
                $card['display_order'] = (int)($card['display_order'] ?? 0);
                $card['is_published'] = (bool)($card['is_published'] ?? false);
                return $card;
            }, $rows));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            $data = json_input();
            $importKey = summary_card_import_key($data['import_key'] ?? bin2hex(random_bytes(12)));
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '' || strlen($title) > 255) bad('title is required and must not exceed 255 characters.');
            $mainValue = trim((string)($data['main_value'] ?? ''));
            if ($mainValue === '' || strlen($mainValue) > 255) bad('main_value is required and must not exceed 255 characters.');
            $mainLabel = trim((string)($data['main_label'] ?? ''));
            if ($mainLabel === '' || strlen($mainLabel) > 255) bad('main_label is required and must not exceed 255 characters.');
            foreach (['year_date', 'secondary_label', 'secondary_value'] as $field) {
                if (strlen((string)($data[$field] ?? '')) > 255) bad($field . ' must not exceed 255 characters.');
            }
            foreach (['description', 'secondary_description', 'info_text'] as $field) {
                if (strlen((string)($data[$field] ?? '')) > 65535) bad($field . ' exceeds its storage limit.');
            }
            $displayOrder = filter_var($data['display_order'] ?? 0, FILTER_VALIDATE_INT);
            if ($displayOrder === false || $displayOrder < 0) bad('display_order must be a non-negative whole number.');
            $secondaryValue = trim((string)($data['secondary_value'] ?? ''));

            $displayPrecision = isset($data['display_precision']) ? (int)$data['display_precision'] : 2;
            $displayPrecision = max(0, min(2, $displayPrecision));
            $published = !empty($data['is_published']) || (!array_key_exists('is_published', $data) && !empty($data['published']));

            $pdo->beginTransaction();
            try {
                $categoryIds = resolve_summary_categories($pdo, $data);
                $collision = $pdo->prepare('SELECT card_id FROM summary_cards WHERE import_key = ? FOR UPDATE');
                $collision->execute([$importKey]);
                if ($collision->fetchColumn()) throw new RuntimeException('Another Summary Card already uses this Global Label.', 409);
                $stmt = $pdo->prepare('INSERT INTO summary_cards (import_key, title, display_order, display_precision, is_published) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $importKey,
                    $title,
                    $displayOrder,
                    $displayPrecision,
                    $published ? 1 : 0
                ]);
                $id = (int)$pdo->lastInsertId();
                save_summary_card_categories($pdo, (string)$id, $categoryIds);
                $createdQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
                $createdQuery->execute([$id]);
                $createdCard = $createdQuery->fetch(PDO::FETCH_ASSOC);
                summary_card_manual_snapshot($pdo, $createdCard, $published, $data);
                SummaryCardHistory::syncLive($pdo, (string)$id);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($exception instanceof RuntimeException && $exception->getCode() === 409) bad($exception->getMessage(), 409);
                if ($exception instanceof PDOException && (string)($exception->errorInfo[0] ?? '') === '23000') bad('Another Summary Card already uses this Global Label.', 409);
                throw $exception;
            }

            $savedCards = array_values(array_filter(SummaryCardHistory::publishedCards($pdo, false), static fn(array $card): bool => (int)$card['id'] === $id));
            echo json_encode(attach_summary_card_categories($pdo, $savedCards)[0] ?? null);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            if ($id === null) bad('Summary card id is required');
            $data = json_input();
            $contentFields = ['title','main_value','main_label','year_date','secondary_label','secondary_value','description','secondary_description','info_text'];
            $configFields = ['display_order', 'display_precision'];
            if (array_key_exists('display_order', $data)) {
                $displayOrder = filter_var($data['display_order'], FILTER_VALIDATE_INT);
                if ($displayOrder === false || $displayOrder < 0) bad('display_order must be a non-negative whole number.');
            }
            $hasIdentityUpdate = array_key_exists('import_key', $data);
            $importKey = $hasIdentityUpdate ? summary_card_import_key($data['import_key']) : null;
            $hasContentUpdate = (bool)array_intersect($contentFields, array_keys($data));
            $hasConfigUpdate = (bool)array_intersect($configFields, array_keys($data));
            $hasPublicationUpdate = array_key_exists('is_published', $data);
            $hasCategoryUpdate = array_key_exists('category_ids', $data) || array_key_exists('category_names', $data) || array_key_exists('category_id', $data) || array_key_exists('category_name', $data);
            $categoryIds = $hasCategoryUpdate ? resolve_summary_categories($pdo, $data) : [];
            if (!$hasIdentityUpdate && !$hasContentUpdate && !$hasConfigUpdate && !$hasPublicationUpdate && !$hasCategoryUpdate) bad('No fields to update');
            foreach (['title', 'main_value', 'main_label'] as $requiredField) {
                if (array_key_exists($requiredField, $data) && trim((string)$data[$requiredField]) === '') {
                    bad($requiredField . ' cannot be blank.');
                }
            }
            $pdo->beginTransaction();
            try {
                $locked = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
                $locked->execute([(string)$id]);
                $card = $locked->fetch(PDO::FETCH_ASSOC);
                if (!$card) throw new RuntimeException('Summary Card not found.', 404);
                $periods = SummaryCardHistory::periods($pdo, (string)$id, true);
                $beforeIdentity = $card;
                if ($hasIdentityUpdate && $importKey !== (string)$card['import_key']) {
                    $collision = $pdo->prepare('SELECT card_id FROM summary_cards WHERE import_key = ? AND card_id <> ? FOR UPDATE');
                    $collision->execute([$importKey, $id]);
                    if ($collision->fetchColumn()) throw new RuntimeException('Another Summary Card already uses this Global Label.', 409);
                }
                if (!$periods) {
                    summary_card_manual_snapshot($pdo, $card, (bool)$card['is_published'], $data);
                    $periods = SummaryCardHistory::periods($pdo, (string)$id, true);
                }
                if ($hasContentUpdate) {
                    $target = SummaryCardHistory::latest($periods, true) ?? SummaryCardHistory::latest($periods);
                    $sets = [];
                    $values = [];
                    foreach ($contentFields as $field) {
                        if (!array_key_exists($field, $data)) continue;
                        $value = trim((string)$data[$field]);
                        if (strlen($value) > (in_array($field, ['description', 'secondary_description', 'info_text'], true) ? 65535 : 255)) {
                            throw new InvalidArgumentException($field . ' exceeds its storage limit.');
                        }
                        if ($field === 'year_date' && $value !== '') {
                            if (preg_match('/^\d{4}$/', $value)) $value .= '-01-01';
                            elseif (($timestamp = strtotime($value)) !== false) $value = date('Y-m-d', $timestamp);
                            else throw new InvalidArgumentException('year_date must be a valid date or year.');
                        }
                        $sets[] = '`' . $field . '` = ?';
                        $values[] = $value;
                    }
                    $before = $target;
                    $pdo->prepare('UPDATE summary_card_snapshots SET ' . implode(', ', $sets) . ' WHERE snapshot_id = ?')->execute([...$values, $target['snapshot_id']]);
                    $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ?');
                    $snapshotQuery->execute([$target['snapshot_id']]);
                    $after = $snapshotQuery->fetch(PDO::FETCH_ASSOC);
                    summary_card_manual_audit($pdo, (string)$id, (string)$target['period_key'], 'correct', $before, $after);
                    $periods = SummaryCardHistory::periods($pdo, (string)$id, true);
                }
                if ($hasPublicationUpdate && !empty($data['is_published']) !== !empty($card['is_published'])) {
                    $target = !empty($data['is_published'])
                        ? SummaryCardHistory::latest($periods)
                        : SummaryCardHistory::latest($periods, true);
                    if ($target) {
                        $pdo->prepare('UPDATE summary_card_snapshots SET is_published = ? WHERE snapshot_id = ?')->execute([!empty($data['is_published']) ? 1 : 0, $target['snapshot_id']]);
                        $snapshotQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ?');
                        $snapshotQuery->execute([$target['snapshot_id']]);
                        $after = $snapshotQuery->fetch(PDO::FETCH_ASSOC);
                        summary_card_manual_audit($pdo, (string)$id, (string)$target['period_key'], !empty($data['is_published']) ? 'publish' : 'unpublish', $target, $after);
                    }
                }
                $cardSets = [];
                $cardValues = [];
                if ($hasIdentityUpdate) { $cardSets[] = 'import_key = ?'; $cardValues[] = $importKey; }
                if (array_key_exists('display_order', $data)) { $cardSets[] = 'display_order = ?'; $cardValues[] = $displayOrder; }
                if (array_key_exists('display_precision', $data)) { $cardSets[] = 'display_precision = ?'; $cardValues[] = max(0, min(2, (int)$data['display_precision'])); }
                if ($cardSets) $pdo->prepare('UPDATE summary_cards SET ' . implode(', ', $cardSets) . ' WHERE card_id = ?')->execute([...$cardValues, $id]);
                if ($hasCategoryUpdate) save_summary_card_categories($pdo, (string)$id, $categoryIds);
                SummaryCardHistory::syncLive($pdo, (string)$id);
                if ($hasIdentityUpdate && $importKey !== (string)$beforeIdentity['import_key']) {
                    $period = SummaryCardHistory::latest($periods);
                    if ($period) {
                        $updatedCardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ?');
                        $updatedCardQuery->execute([(string)$id]);
                        summary_card_manual_audit($pdo, (string)$id, (string)$period['period_key'], 'identity', $beforeIdentity, $updatedCardQuery->fetch(PDO::FETCH_ASSOC));
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($exception instanceof RuntimeException && in_array($exception->getCode(), [404, 409], true)) bad($exception->getMessage(), (int)$exception->getCode());
                if ($exception instanceof PDOException && (string)($exception->errorInfo[0] ?? '') === '23000') bad('Another Summary Card already uses this Global Label.', 409);
                throw $exception;
            }
            $savedCards = array_values(array_filter(SummaryCardHistory::publishedCards($pdo, false), static fn(array $card): bool => (int)$card['id'] === (int)$id));
            echo json_encode(attach_summary_card_categories($pdo, $savedCards)[0] ?? null);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            ensure_json_csrf();
            if ($id === null) bad('Summary card id is required');
            $pdo->prepare('DELETE FROM summary_cards WHERE card_id = ?')->execute([$id]);
            echo json_encode(['success' => true, 'deleted_id' => $id]);
            exit;
        }

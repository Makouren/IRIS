<?php
/**
 * Purpose: API handler for summary card history operations; dispatched through the shared API router.
 */


        if ($id === null || trim((string)$id) === '') bad('Summary Card id is required.');
        $cardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ?');
        $cardQuery->execute([(string)$id]);
        $card = $cardQuery->fetch(PDO::FETCH_ASSOC);
        if (!$card) bad('Summary Card not found.', 404);
        $isSuperAdmin = (($_SESSION['role'] ?? '') === 'super_admin');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $allPeriods = SummaryCardHistory::periods($pdo, (string)$id);
            $showAdminHistory = $isSuperAdmin && (($_GET['view'] ?? '') === 'admin');
            $current = SummaryCardHistory::latest($allPeriods, true);
            $latestImported = SummaryCardHistory::latest($allPeriods);
            $historyVersion = SummaryCardHistory::version($card, $allPeriods);
            $changeRows = $pdo->prepare('SELECT period_key, action, changed_by, created_at FROM summary_card_period_changes WHERE card_id = ? ORDER BY created_at DESC, change_id DESC');
            $changeRows->execute([(string)$id]);
            $changesByPeriod = [];
            foreach ($changeRows->fetchAll(PDO::FETCH_ASSOC) as $change) $changesByPeriod[$change['period_key']][] = $change;
            $periods = $showAdminHistory ? $allPeriods : array_values(array_filter($allPeriods, static fn(array $period): bool => !empty($period['is_published'])));
            foreach ($periods as &$period) {
                $period['custom_fields'] = CustomImportFields::decode($period['custom_fields'] ?? []);
                $period['is_current_public'] = $current && $current['period_key'] === $period['period_key'];
                $period['row_version'] = $historyVersion;
                $period['changes'] = $changesByPeriod[$period['period_key']] ?? [];
            }
            unset($period);
            $selectedPeriod = trim((string)($_GET['period'] ?? ''));
            if ($selectedPeriod !== '') {
                if (!preg_match('/^[YQMDL]:/', $selectedPeriod)) {
                    try { $selectedPeriod = LatestYearResolver::normalize($selectedPeriod)['period_key']; }
                    catch (RuntimeException $exception) { bad($exception->getMessage()); }
                }
                $periods = array_values(array_filter($periods, static fn(array $period): bool => $period['period_key'] === $selectedPeriod));
                if (!$periods) bad('Summary Card period not found.', 404);
            }
            echo json_encode([
                'card' => ['id' => $card['id'], 'title' => $card['title']],
                'current_public_period' => $current['period_label'] ?? null,
                'latest_imported_period' => $latestImported['period_label'] ?? null,
                'periods' => $periods
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Method not allowed.', 405);
        ensure_admin_for_mutation();
        $data = json_input();
        $action = (string)($data['action'] ?? '');
        if (!in_array($action, ['correct', 'publish', 'unpublish'], true)) bad('Unknown Summary Card history action.');
        $periodInput = trim((string)($data['period_key'] ?? ''));
        if ($periodInput === '') bad('Period key is required.');
        try { $periodKey = preg_match('/^[YQMDL]:/', $periodInput) ? $periodInput : LatestYearResolver::normalize($periodInput)['period_key']; }
        catch (RuntimeException $exception) { bad($exception->getMessage()); }
        $expectedVersion = (string)($data['row_version'] ?? '');
        if ($expectedVersion === '') bad('Refresh the history before changing a period.');
        // Lock and compare the version before writing so a stale page cannot overwrite newer history.
        $pdo->beginTransaction();
        try {
            $lockedCardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
            $lockedCardQuery->execute([(string)$id]);
            $lockedCard = $lockedCardQuery->fetch(PDO::FETCH_ASSOC);
            $allPeriods = SummaryCardHistory::periods($pdo, (string)$id, true);
            // The version fingerprint rejects stale edits after the row lock; hash_equals avoids loose comparison.
            if (!$lockedCard || !hash_equals($expectedVersion, SummaryCardHistory::version($lockedCard, $allPeriods))) {
                throw new RuntimeException('Summary Card history changed. Refresh before applying this action.', 409);
            }
            $before = null;
            foreach ($allPeriods as $period) if ($period['period_key'] === $periodKey) { $before = $period; break; }
            if (!$before) throw new RuntimeException('Summary Card period not found.', 404);
            if ($action === 'correct') {
                $allowed = ['title', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text', 'source_info'];
                $sets = [];
                $values = [];
                foreach ($allowed as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    $value = trim((string)$data[$field]);
                    if (strlen($value) > (in_array($field, ['description', 'secondary_description', 'info_text', 'source_info'], true) ? 65535 : 255)) {
                        throw new InvalidArgumentException($field . ' exceeds its storage limit.');
                    }
                    $sets[] = '`' . $field . '` = ?';
                    $values[] = $value;
                }
                if (!$sets) throw new InvalidArgumentException('Provide at least one period field to correct.');
                if (array_key_exists('main_value', $data) && trim((string)$data['main_value']) === '') throw new InvalidArgumentException('main_value cannot be blank.');
                $pdo->prepare('UPDATE summary_card_snapshots SET ' . implode(', ', $sets) . ' WHERE snapshot_id = ?')->execute([...$values, $before['snapshot_id']]);
            } else {
                $pdo->prepare('UPDATE summary_card_snapshots SET is_published = ? WHERE snapshot_id = ?')->execute([$action === 'publish' ? 1 : 0, $before['snapshot_id']]);
            }
            $changedQuery = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE snapshot_id = ?');
            $changedQuery->execute([$before['snapshot_id']]);
            $after = $changedQuery->fetch(PDO::FETCH_ASSOC);
            SummaryCardHistory::syncLive($pdo, (string)$id);
            $pdo->prepare('INSERT INTO summary_card_period_changes (card_id, period_key, action, before_state, after_state, changed_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([
                (string)$id, $periodKey, $action,
                json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int)$_SESSION['user_id']
            ]);
            $pdo->commit();
            echo json_encode(['success' => true, 'period' => $after], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $status = in_array($exception->getCode(), [400, 404, 409], true) ? (int)$exception->getCode() : 422;
            http_response_code($status);
            echo json_encode(['error' => $exception->getMessage()]);
        }
        exit;

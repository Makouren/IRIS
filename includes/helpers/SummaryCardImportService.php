<?php
declare(strict_types=1);

require_once __DIR__ . '/LatestYearResolver.php';
require_once __DIR__ . '/SummaryCardHistory.php';
require_once __DIR__ . '/ImportBatchAudit.php';

final class SummaryCardImportService
{
    private const CONTENT_FIELDS = [
        'title', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value',
        'description', 'secondary_description', 'info_text', 'source_info'
    ];

    private static function importKey(array $values, string $sheet, int $rowNumber): string
    {
        $key = (string)($values['import_key'] ?? '');
        if ($key === '' || trim($key) !== $key || strlen($key) > 100 || preg_match('/[\x00-\x1F\x7F]/', $key)) {
            throw new RuntimeException("Sheet {$sheet}, row {$rowNumber}: import_key must be a stable, non-empty Global Label under 100 characters with no surrounding whitespace.");
        }
        return $key;
    }

    private static function mergedState(array $input, array $base, bool $existingPeriod, string $key, array $period, string $sourceFile, int $rowNumber): array
    {
        $values = [];
        $cleared = [];
        foreach (self::CONTENT_FIELDS as $field) {
            $previous = (string)($base[$field] ?? '');
            $incoming = trim((string)($input[$field] ?? ''));
            if ($incoming === '__CLEAR__') { $values[$field] = ''; $cleared[$field] = true; }
            elseif ($incoming !== '') $values[$field] = $incoming;
            else $values[$field] = $previous;
        }
        if ($values['title'] === '' && empty($cleared['title'])) {
            $values['title'] = (string)($base['title'] ?? '')
                ?: trim((string)($input['main_label'] ?? ''))
                ?: $key;
        }
        if (trim((string)($input['year_date'] ?? '')) === '' && empty($cleared['year_date']) && !$existingPeriod) {
            $values['year_date'] = (string)$period['period_label'];
        }
        if (trim((string)($input['source_info'] ?? '')) === '' && empty($cleared['source_info']) && !$existingPeriod) {
            $values['source_info'] = 'Imported from ' . basename($sourceFile);
        }
        if ($values['main_value'] === '') throw new RuntimeException("Row {$rowNumber}: main_value is required after preserving existing period values.");
        foreach ($values as $field => $value) {
            $limit = in_array($field, ['description', 'secondary_description', 'info_text', 'source_info'], true) ? 65535 : 255;
            if (strlen($value) > $limit) throw new RuntimeException("Row {$rowNumber}: {$field} exceeds its storage limit.");
        }
        return $values;
    }

    private static function equalState(array $left, array $right): bool
    {
        foreach (self::CONTENT_FIELDS as $field) {
            if ((string)($left[$field] ?? '') !== (string)($right[$field] ?? '')) return false;
        }
        return true;
    }

    private static function identityQuery(PDO $pdo, string $key, bool $lock): ?array
    {
        $query = $pdo->prepare('SELECT * FROM summary_cards WHERE import_key = ?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$key]);
        $card = $query->fetch(PDO::FETCH_ASSOC);
        return $card ?: null;
    }

    private static function legacyCard(PDO $pdo, string $legacyKey, bool $lock): ?array
    {
        $matches = [];
        $card = self::identityQuery($pdo, $legacyKey, $lock);
        if ($card) $matches[(string)$card['id']] = $card;
        $batches = $pdo->query("SELECT content_sha256 FROM import_batches WHERE destination = 'summary_cards' AND status = 'applied' ORDER BY id DESC");
        foreach ($batches->fetchAll(PDO::FETCH_COLUMN) as $contentHash) {
            $oldKey = 'snapshot-' . substr(hash('sha256', (string)$contentHash . "\0" . $legacyKey), 0, 90);
            $card = self::identityQuery($pdo, $oldKey, $lock);
            if ($card) $matches[(string)$card['id']] = $card;
        }
        if (count($matches) > 1) throw new RuntimeException('Multiple legacy Summary Cards match this Global Label. Resolve the duplicate cards and retry.', 409);
        return $matches ? reset($matches) : null;
    }

    private static function historyBefore(array $periods, array $incomingPeriod): ?array
    {
        $previous = null;
        foreach ($periods as $period) {
            if (LatestYearResolver::compare($period, $incomingPeriod) >= 0) continue;
            if (!$previous || LatestYearResolver::compare($period, $previous) > 0) $previous = $period;
        }
        return $previous;
    }

    private static function signature(?array $period): string
    {
        if (!$period) return '';
        return hash('sha256', json_encode(array_intersect_key($period, array_flip(self::CONTENT_FIELDS)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function build(PDO $pdo, array $parsed, bool $lock): array
    {
        $groups = [];
        $seen = [];
        $identityFields = $parsed['profile']['identity_fields'] ?? ['import_key'];
        $profileVersion = hash('sha256', json_encode([
            'id' => $parsed['profile']['id'] ?? null,
            'sheet_selector' => $parsed['profile']['sheet_selector'] ?? null,
            'identity_fields' => $identityFields,
            'header_aliases' => $parsed['profile']['header_aliases'] ?? [],
            'required_columns' => $parsed['profile']['required_columns'] ?? [],
            'mapping_rules' => $parsed['profile']['mapping_rules'] ?? [],
            'defaults_json' => $parsed['profile']['defaults_json'] ?? []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        foreach ($parsed['rows'] as $input) {
            $sheet = (string)$input['sheet_name'];
            $rowNumber = (int)$input['row_number'];
            $values = $input['values'];
            self::importKey($values, $sheet, $rowNumber);
            try {
                $period = LatestYearResolver::normalize($values['period_key'] ?? '');
            } catch (RuntimeException $exception) {
                throw new RuntimeException("Sheet {$sheet}, row {$rowNumber}: {$exception->getMessage()}");
            }
            $key = TemplateImportSupport::canonicalImportKey($values, $identityFields, $sheet, $rowNumber);
            $rowKey = TemplateImportSupport::validateUniqueIdentityPeriod($seen, $values, $identityFields, $period['period_key'], $sheet, $rowNumber);
            $groups[$key]['inputs'][] = [
                'row_key' => $rowKey,
                'values' => $values,
                'period' => $period,
                'legacy_import_key' => $input['legacy_import_key'] ?? null,
                'sheet_name' => $sheet,
                'row_number' => $rowNumber
            ];
        }

        $legacyOwners = [];
        foreach ($groups as $key => $group) {
            foreach ($group['inputs'] as $input) {
                $legacyKey = (string)($input['legacy_import_key'] ?? '');
                if ($legacyKey === '') continue;
                if (isset($legacyOwners[$legacyKey]) && $legacyOwners[$legacyKey] !== $key) {
                    throw new RuntimeException('Different Global Labels match the same legacy card identity. Configure an explicit card mapping before importing.', 409);
                }
                $legacyOwners[$legacyKey] = $key;
            }
        }

        $rows = [];
        foreach ($groups as $key => &$group) {
            $group['card'] = self::identityQuery($pdo, $key, $lock);
            if (!$group['card'] && !empty($group['inputs'][0]['legacy_import_key'])) {
                $group['card'] = self::legacyCard($pdo, (string)$group['inputs'][0]['legacy_import_key'], $lock);
            }
            $group['periods'] = $group['card']
                ? SummaryCardHistory::periods($pdo, (string)$group['card']['id'], $lock)
                : [];
            $group['version'] = hash('sha256', SummaryCardHistory::version($group['card'], $group['periods']) . "\0" . $profileVersion);
            $group['before_latest'] = SummaryCardHistory::latest($group['periods']);
            $group['before_public'] = SummaryCardHistory::latest($group['periods'], true);
            $group['simulation'] = $group['periods'];
            usort($group['inputs'], static fn(array $left, array $right): int => LatestYearResolver::compare($left['period'], $right['period']));

            foreach ($group['inputs'] as $input) {
                $existing = null;
                foreach ($group['periods'] as $period) {
                    if ((string)$period['period_key'] === $input['period']['period_key']) { $existing = $period; break; }
                }
                $base = $existing ?? self::historyBefore($group['simulation'], $input['period']) ?? [];
                $incoming = $input['values'];
                $logicalKey = self::importKey($incoming, $input['sheet_name'], $input['row_number']);
                if (!empty($incoming['card_title']) && trim((string)$incoming['card_title']) !== '') $incoming['title'] = $incoming['card_title'];
                $state = self::mergedState($incoming, $base, $existing !== null, $logicalKey, $input['period'], (string)$parsed['record']['fileName'], $input['row_number']);
                $kind = !$existing ? 'new_period' : (self::equalState($existing, $state) ? 'unchanged' : 'updated_period');
                $simulationPeriod = array_merge($existing ?? [], $state, $input['period'], [
                    'card_id' => (string)($group['card']['id'] ?? ''),
                    'period_key' => $input['period']['period_key'],
                    'is_published' => (int)($existing['is_published'] ?? 0)
                ]);
                $group['simulation'] = array_values(array_filter($group['simulation'], static fn(array $period): bool => $period['period_key'] !== $input['period']['period_key']));
                $group['simulation'][] = $simulationPeriod;
                $group['rows'][] = [
                    'key' => $input['row_key'], 'row_version' => $group['version'], 'kind' => $kind,
                    'import_key' => $logicalKey, 'card_id' => $group['card']['id'] ?? null,
                    'identity_migration' => $group['card'] && (string)$group['card']['import_key'] !== $key,
                    'card_title' => $state['title'], 'new_card' => !$group['card'],
                    'period_key' => $input['period']['period_key'], 'period_label' => $input['period']['period_label'],
                    'period_sort' => $input['period']['period_sort'], 'period_precision' => $input['period']['period_precision'],
                    'snapshot_before' => $existing, 'snapshot_after' => $state,
                    'sheet_name' => $input['sheet_name'], 'row_number' => $input['row_number']
                ];
            }
            $group['after_latest'] = SummaryCardHistory::latest($group['simulation']);
            $group['after_public'] = SummaryCardHistory::latest($group['simulation'], true);
            $group['current_public_unchanged'] = (string)($group['before_public']['period_key'] ?? '') === (string)($group['after_public']['period_key'] ?? '')
                && self::signature($group['before_public']) === self::signature($group['after_public']);
            foreach ($group['rows'] as &$row) {
                $row['is_historical_backfill'] = $group['before_latest'] && LatestYearResolver::compare($row, $group['before_latest']) < 0;
                $row['is_latest_imported'] = ($group['after_latest']['period_key'] ?? null) === $row['period_key'];
                $row['is_current_public'] = ($group['after_public']['period_key'] ?? null) === $row['period_key'];
                $row['current_public_period'] = $group['before_public']['period_label'] ?? null;
                $row['current_public_unchanged'] = $group['current_public_unchanged'];
                $row['preview_status'] = !$group['card'] ? 'NEW CARD'
                    : ($row['kind'] === 'unchanged' ? 'UNCHANGED'
                    : ($row['is_historical_backfill'] ? ($row['kind'] === 'updated_period' ? 'UPDATED PERIOD · HISTORICAL BACKFILL' : 'HISTORICAL BACKFILL')
                    : ($row['is_current_public'] ? 'CURRENT PUBLIC PERIOD'
                    : ($row['is_latest_imported'] ? 'LATEST IMPORTED PERIOD · UNPUBLISHED' : strtoupper(str_replace('_', ' ', $row['kind']))))));
                $rows[] = $row;
            }
            unset($row);
        }
        unset($group);
        usort($rows, static fn(array $left, array $right): int => [$right['period_sort'], $right['period_precision'], $left['import_key']] <=> [$left['period_sort'], $left['period_precision'], $right['import_key']]);
        return ['groups' => $groups, 'rows' => $rows];
    }

    public static function preview(PDO $pdo, array $parsed): array
    {
        $pdo->beginTransaction();
        try {
            $rows = self::build($pdo, $parsed, false)['rows'];
            $pdo->commit();
            return $rows;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    private static function insertCard(PDO $pdo, string $key, array $state): array
    {
        $id = 'summary_card_' . bin2hex(random_bytes(12));
        $query = $pdo->prepare('INSERT INTO summary_cards (id, import_key, title, main_value, main_label, year_date, secondary_label, secondary_value, description, secondary_description, info_text, is_published, display_order, display_precision) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 2)');
        $query->execute([$id, $key, $state['title'], $state['main_value'], $state['main_label'], $state['year_date'], $state['secondary_label'], $state['secondary_value'], $state['description'], $state['secondary_description'], $state['info_text']]);
        $saved = $pdo->prepare('SELECT * FROM summary_cards WHERE id = ?');
        $saved->execute([$id]);
        return $saved->fetch(PDO::FETCH_ASSOC);
    }

    private static function savePeriod(PDO $pdo, array $row, string $cardId, string $recordId, int $batchId): void
    {
        $before = $row['snapshot_before'];
        $values = $row['snapshot_after'];
        if (!$before) {
            $insert = $pdo->prepare('INSERT INTO summary_card_snapshots (card_id, title, period_key, period_label, period_sort, period_precision, is_published, main_value, main_label, secondary_label, secondary_value, year_date, description, secondary_description, info_text, source_info, source_record_id, batch_id, last_source_record_id, last_batch_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$cardId, $values['title'], $row['period_key'], $row['period_label'], $row['period_sort'], $row['period_precision'], $values['main_value'], $values['main_label'], $values['secondary_label'], $values['secondary_value'], $values['year_date'], $values['description'], $values['secondary_description'], $values['info_text'], $values['source_info'], $recordId, $batchId, $recordId, $batchId]);
        } else {
            $update = $pdo->prepare('UPDATE summary_card_snapshots SET title = ?, period_label = ?, period_sort = ?, period_precision = ?, main_value = ?, main_label = ?, secondary_label = ?, secondary_value = ?, year_date = ?, description = ?, secondary_description = ?, info_text = ?, source_info = ?, last_source_record_id = ?, last_batch_id = ? WHERE id = ?');
            $update->execute([$values['title'], $row['period_label'], $row['period_sort'], $row['period_precision'], $values['main_value'], $values['main_label'], $values['secondary_label'], $values['secondary_value'], $values['year_date'], $values['description'], $values['secondary_description'], $values['info_text'], $values['source_info'], $recordId, $batchId, $before['id']]);
        }
        $query = $pdo->prepare('SELECT * FROM summary_card_snapshots WHERE card_id = ? AND period_key = ?');
        $query->execute([$cardId, $row['period_key']]);
        ImportBatchAudit::row($pdo, $batchId, 'summary_card', 'snapshot:' . $cardId . ':' . $row['period_key'], $row['sheet_name'], $row['row_number'], $before, $query->fetch(PDO::FETCH_ASSOC));
    }

    public static function apply(PDO $pdo, array $parsed, array $versions, bool $reviewed, int $userId): array
    {
        if (!$reviewed) throw new InvalidArgumentException('Review the diff before applying this import.');
        $pdo->beginTransaction();
        try {
            $built = self::build($pdo, $parsed, true);
            foreach ($built['rows'] as $row) {
                if (!isset($versions[$row['key']]) || !hash_equals((string)$versions[$row['key']], $row['row_version'])) {
                    throw new RuntimeException('Summary Card history changed after preview. Refresh the preview before applying.', 409);
                }
            }
            $work = array_values(array_filter($built['rows'], static fn(array $row): bool => $row['kind'] !== 'unchanged' || $row['identity_migration']));
            if (!$work) {
                $pdo->commit();
                return ['success' => true, 'inserted' => 0, 'updated' => 0, 'message' => 'No changes detected.'];
            }
            $inserted = count(array_filter($work, static fn(array $row): bool => $row['kind'] === 'new_period'));
            $updated = count($work) - $inserted;
            $batchId = ImportBatchAudit::create($pdo, $parsed['record'], 'summary_cards', $userId, $inserted, $updated);
            foreach ($built['groups'] as $key => $group) {
                $changes = array_values(array_filter($group['rows'], static fn(array $row): bool => $row['kind'] !== 'unchanged' || $row['identity_migration']));
                if (!$changes) continue;
                $card = $group['card'];
                if (!$card) {
                    $initial = $group['after_latest'];
                    $card = self::insertCard($pdo, $key, $initial);
                    ImportBatchAudit::row($pdo, $batchId, 'summary_card', (string)$card['id'], $changes[0]['sheet_name'], $changes[0]['row_number'], null, $card);
                } elseif ((string)$card['import_key'] !== (string)$key) {
                    $beforeIdentity = $card;
                    $pdo->prepare('UPDATE summary_cards SET import_key = ? WHERE id = ?')->execute([$key, $card['id']]);
                    $card = self::identityQuery($pdo, $key, true);
                    ImportBatchAudit::row($pdo, $batchId, 'summary_card', (string)$card['id'], $changes[0]['sheet_name'], $changes[0]['row_number'], $beforeIdentity, $card);
                }
                foreach ($changes as $row) {
                    if ($row['kind'] !== 'unchanged') self::savePeriod($pdo, $row, (string)$card['id'], (string)$parsed['record']['id'], $batchId);
                }
                $beforeCard = $card;
                $savedCard = SummaryCardHistory::syncLive($pdo, (string)$card['id']);
                if (self::equalState($beforeCard, $savedCard) && (int)$beforeCard['is_published'] === (int)$savedCard['is_published']) continue;
                ImportBatchAudit::row($pdo, $batchId, 'summary_card', (string)$card['id'], $changes[0]['sheet_name'], $changes[0]['row_number'], $beforeCard, $savedCard);
            }
            $pdo->commit();
            return ['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'batch_id' => $batchId, 'message' => 'Summary Card periods were applied.'];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (in_array((string)($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '40001'], true)) {
                throw new RuntimeException('Another administrator created or changed this Global Label during approval. Preview again.', 409);
            }
            throw $exception;
         } catch (Throwable $exception) {
             if ($pdo->inTransaction()) $pdo->rollBack();
             throw $exception;
         }
     }
 }

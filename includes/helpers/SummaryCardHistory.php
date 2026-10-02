<?php
declare(strict_types=1);

require_once __DIR__ . '/CustomImportFields.php';

final class SummaryCardHistory
{
    public static function periods(PDO $pdo, string $cardId, bool $lock = false): array
    {
        $sql = 'SELECT summary_card_snapshots.*, snapshot_id AS id FROM summary_card_snapshots WHERE card_id = ? ORDER BY period_sort DESC, period_precision DESC, period_key ASC, snapshot_id DESC';
        if ($lock) $sql .= ' FOR UPDATE';
        $query = $pdo->prepare($sql);
        $query->execute([$cardId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function latest(array $periods, bool $publishedOnly = false): ?array
    {
        $latest = null;
        foreach ($periods as $period) {
            if ($publishedOnly && empty($period['is_published'])) continue;
            if (!$latest || LatestYearResolver::compare($period, $latest) > 0) $latest = $period;
        }
        return $latest;
    }

    public static function version(?array $card, array $periods): string
    {
        return hash('sha256', json_encode([$card, $periods], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function syncLive(PDO $pdo, string $cardId): array
    {
        $cardQuery = $pdo->prepare('SELECT summary_cards.*, card_id AS id FROM summary_cards WHERE card_id = ? FOR UPDATE');
        $cardQuery->execute([$cardId]);
        $card = $cardQuery->fetch(PDO::FETCH_ASSOC);
        if (!$card) throw new RuntimeException('Summary Card no longer exists.', 409);
        $periods = self::periods($pdo, $cardId, true);
        $published = self::latest($periods, true);
        $target = $published ?? self::latest($periods);
        if (!$target) {
            $pdo->prepare('UPDATE summary_cards SET is_published = 0 WHERE card_id = ?')->execute([$cardId]);
            return $card;
        }
        $yearDate = trim((string)($target['year_date'] ?? ''));
        $date = null;
        if ($yearDate !== '') {
            $timestamp = strtotime($yearDate);
            if ($timestamp !== false) $date = date('Y-m-d', $timestamp);
        }
        $values = [
            $cardId, $target['period_key'], $target['period_label'] ?? null, $target['period_sort'] ?? null,
            $target['period_precision'] ?? null, $target['main_value'] ?? null, $target['main_label'] ?? null,
            $target['secondary_label'] ?? null, $target['secondary_value'] ?? null, $date,
            $target['description'] ?? null, $target['secondary_description'] ?? null, $target['info_text'] ?? null,
            $target['source_info'] ?? null
        ];
        $columns = ['card_id', 'period_key', 'period_label', 'period_sort', 'period_precision', 'main_value', 'main_label', 'secondary_label', 'secondary_value', 'year_date', 'description', 'secondary_description', 'info_text', 'source_info'];
        if (CustomImportFields::columnExists($pdo, 'summary_card_periods')) {
            $columns[] = 'custom_fields';
            $values[] = CustomImportFields::encode($target['custom_fields'] ?? []);
        }
        $columns[] = 'is_published';
        $values[] = $published ? 1 : 0;
        $updates = array_values(array_filter($columns, static fn(string $column): bool => !in_array($column, ['card_id', 'period_key'], true)));
        $sql = 'INSERT INTO summary_card_periods (' . implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns)) . ')
            VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn(string $column): string => '`' . $column . '` = VALUES(`' . $column . '`)', $updates));
        $pdo->prepare($sql)->execute($values);
        $pdo->prepare('UPDATE summary_cards SET title = ?, is_published = ? WHERE card_id = ?')->execute([$target['title'] ?? $card['title'], $published ? 1 : 0, $cardId]);
        $cardQuery->execute([$cardId]);
        return $cardQuery->fetch(PDO::FETCH_ASSOC);
    }

    public static function publishedCards(PDO $pdo, bool $publishedOnly = true): array
    {
        $snapshotFilter = $publishedOnly ? ' AND candidate.is_published = 1' : '';
        $cardFilter = $publishedOnly ? 'WHERE cards.is_published = 1' : '';
        $historyFilter = $publishedOnly ? ' AND history.is_published = 1' : '';
        $snapshotCustomFields = CustomImportFields::columnExists($pdo, 'summary_card_snapshots');
        $periodCustomFields = CustomImportFields::columnExists($pdo, 'summary_card_periods');
        $customFieldsExpression = match (true) {
            $snapshotCustomFields && $periodCustomFields => 'COALESCE(periods.custom_fields, live.custom_fields)',
            $snapshotCustomFields => 'periods.custom_fields',
            $periodCustomFields => 'live.custom_fields',
            default => 'NULL'
        };
        $cards = $pdo->query("SELECT cards.card_id AS id, cards.import_key,
                COALESCE(periods.title, cards.title) AS title,
                COALESCE(periods.main_value, live.main_value) AS main_value,
                COALESCE(periods.main_label, live.main_label) AS main_label,
                COALESCE(periods.period_label, live.period_label) AS year_date,
                COALESCE(periods.secondary_label, live.secondary_label) AS secondary_label,
                COALESCE(periods.secondary_value, live.secondary_value) AS secondary_value,
                COALESCE(periods.description, live.description) AS description,
                COALESCE(periods.secondary_description, live.secondary_description) AS secondary_description,
                COALESCE(periods.info_text, live.info_text) AS info_text,
                {$customFieldsExpression} AS custom_fields,
                cards.display_order, cards.display_precision, cards.is_published,
                cards.created_at, cards.updated_at,
                COALESCE(periods.period_key, live.period_key) AS current_period_key,
                COALESCE(periods.period_label, live.period_label) AS current_period_label,
                COALESCE(periods.source_info, live.source_info) AS current_source_info,
                (SELECT COUNT(*) FROM summary_card_snapshots history
                    WHERE history.card_id = cards.card_id{$historyFilter}) AS history_count
            FROM summary_cards cards
            LEFT JOIN summary_card_snapshots periods ON periods.snapshot_id = (
                SELECT candidate.snapshot_id FROM summary_card_snapshots candidate
                WHERE candidate.card_id = cards.card_id{$snapshotFilter}
                ORDER BY candidate.period_sort DESC, candidate.period_precision DESC, candidate.period_key ASC, candidate.snapshot_id DESC
                LIMIT 1
            )
            LEFT JOIN summary_card_periods live ON live.card_id = cards.card_id AND live.period_key = periods.period_key
            {$cardFilter}
            ORDER BY cards.display_order ASC, cards.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cards as &$card) $card['custom_fields'] = CustomImportFields::decode($card['custom_fields'] ?? []);
        unset($card);
        return $cards;
    }
}
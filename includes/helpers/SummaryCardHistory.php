<?php
declare(strict_types=1);

final class SummaryCardHistory
{
    public static function periods(PDO $pdo, string $cardId, bool $lock = false): array
    {
        $sql = 'SELECT * FROM summary_card_snapshots WHERE card_id = ? ORDER BY period_sort DESC, period_precision DESC, period_key ASC';
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
        $cardQuery = $pdo->prepare('SELECT * FROM summary_cards WHERE id = ? FOR UPDATE');
        $cardQuery->execute([$cardId]);
        $card = $cardQuery->fetch(PDO::FETCH_ASSOC);
        if (!$card) throw new RuntimeException('Summary Card no longer exists.', 409);
        $periods = self::periods($pdo, $cardId, true);
        $published = self::latest($periods, true);
        $target = $published ?? self::latest($periods);
        if (!$target) return $card;
        $liveFields = ['title', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text'];
        $sameLive = true;
        foreach ($liveFields as $field) {
            if ((string)($card[$field] ?? '') !== (string)($target[$field] ?? '')) $sameLive = false;
        }
        if ($sameLive && (int)$card['is_published'] === ($published ? 1 : 0)) return $card;

        $fields = ['title', 'main_value', 'main_label', 'year_date', 'secondary_label', 'secondary_value', 'description', 'secondary_description', 'info_text'];
        $sets = array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields);
        $values = array_map(static fn(string $field) => $target[$field] ?? '', $fields);
        $sets[] = 'is_published = ?';
        $values[] = $published ? 1 : 0;
        $values[] = $cardId;
        $pdo->prepare('UPDATE summary_cards SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        $cardQuery->execute([$cardId]);
        return $cardQuery->fetch(PDO::FETCH_ASSOC);
    }

    public static function publishedCards(PDO $pdo): array
    {
        return $pdo->query("SELECT cards.id, cards.import_key,
            CASE WHEN periods.id IS NULL THEN cards.title ELSE periods.title END AS title,
            CASE WHEN periods.id IS NULL THEN cards.main_value ELSE COALESCE(periods.main_value, '') END AS main_value,
            CASE WHEN periods.id IS NULL THEN cards.main_label ELSE COALESCE(periods.main_label, '') END AS main_label,
            CASE WHEN periods.id IS NULL THEN cards.year_date ELSE COALESCE(periods.year_date, '') END AS year_date,
            CASE WHEN periods.id IS NULL THEN cards.secondary_label ELSE COALESCE(periods.secondary_label, '') END AS secondary_label,
            CASE WHEN periods.id IS NULL THEN cards.secondary_value ELSE COALESCE(periods.secondary_value, '') END AS secondary_value,
            CASE WHEN periods.id IS NULL THEN cards.description ELSE COALESCE(periods.description, '') END AS description,
            CASE WHEN periods.id IS NULL THEN cards.secondary_description ELSE COALESCE(periods.secondary_description, '') END AS secondary_description,
            CASE WHEN periods.id IS NULL THEN cards.info_text ELSE COALESCE(periods.info_text, '') END AS info_text,
                cards.display_order, cards.display_precision, cards.is_published,
                cards.created_at, cards.updated_at, cards.category_id,
                periods.period_key AS current_period_key,
                periods.period_label AS current_period_label,
                periods.source_info AS current_source_info,
                (SELECT COUNT(*) FROM summary_card_snapshots history
                    WHERE history.card_id = cards.id AND history.is_published = 1) AS history_count
            FROM summary_cards cards
            LEFT JOIN summary_card_snapshots periods ON periods.id = (
                SELECT candidate.id FROM summary_card_snapshots candidate
                WHERE candidate.card_id = cards.id AND candidate.is_published = 1
                ORDER BY candidate.period_sort DESC, candidate.period_precision DESC, candidate.period_key ASC
                LIMIT 1
            )
            WHERE cards.is_published = 1
            ORDER BY cards.display_order ASC, cards.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
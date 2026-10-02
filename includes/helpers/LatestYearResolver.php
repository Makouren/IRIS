<?php
declare(strict_types=1);

final class LatestYearResolver
{
    public static function normalize(mixed $input): array
    {
        $period = trim((string)$input);
        if (preg_match('/^(\d{4})$/', $period, $match)) {
            $year = (int)$match[1];
            return self::result('Y:' . $match[1], $year . '1231', 1, $period);
        }
        if (preg_match('/^(\d{4})-Q([1-4])$/i', $period, $match)) {
            $year = (int)$match[1];
            $month = (int)$match[2] * 3;
            $end = self::monthEnd($year, $month);
            return self::result(sprintf('Q:%04d-Q%d', $year, (int)$match[2]), $end, 2, strtoupper($period));
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $match) && (int)$match[2] >= 1 && (int)$match[2] <= 12) {
            $year = (int)$match[1];
            $month = (int)$match[2];
            return self::result(sprintf('M:%04d-%02d', $year, $month), self::monthEnd($year, $month), 3, $period);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $match) && checkdate((int)$match[2], (int)$match[3], (int)$match[1])) {
            return self::result('D:' . $period, str_replace('-', '', $period), 4, $period);
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $period) && (float)$period >= 0 && (float)$period <= 100000) {
            $days = (int)floor((float)$period);
            if ($days === 0 || $days === 60) throw new RuntimeException("Invalid Excel date serial '{$period}'.");
            $offset = $days > 60 ? $days - 1 : $days;
            $date = (new DateTimeImmutable('1899-12-31'))->modify('+' . $offset . ' days');
            return self::normalize($date->format('Y-m-d'));
        }
        throw new RuntimeException("Invalid period '{$period}'. Use YYYY, YYYY-Q1 to YYYY-Q4, YYYY-MM, YYYY-MM-DD, or a valid Excel date serial.");
    }

    private static function monthEnd(int $year, int $month): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-1');
        if (!$date) throw new RuntimeException('Unable to normalize the supplied period.');
        return $date->modify('last day of this month')->format('Ymd');
    }

    private static function result(string $key, string $end, int $precision, string $label): array
    {
        return ['period_key' => $key, 'period_sort' => (int)$end, 'period_precision' => $precision, 'period_label' => $label];
    }

    public static function periodEnd(string $period): int
    {
        return self::normalize($period)['period_sort'];
    }

    public static function compare(array $left, array $right): int
    {
        return [$left['period_sort'], $left['period_precision']] <=> [$right['period_sort'], $right['period_precision']];
    }

    public static function latestIndexes(array $rows): array
    {
        $latest = [];
        foreach ($rows as $index => $row) {
            $values = $row['values'] ?? [];
            $label = trim((string)($values['import_key'] ?? ''));
            $period = trim((string)($values['period_key'] ?? ''));
            if ($label === '' || $period === '') continue;
            $normalized = self::normalize($period);
            if (!isset($latest[$label]) || self::compare($normalized, $latest[$label]) > 0) {
                $latest[$label] = $normalized + ['index' => $index];
            }
        }
        return array_map(static fn(array $item): int => $item['index'], $latest);
    }
}
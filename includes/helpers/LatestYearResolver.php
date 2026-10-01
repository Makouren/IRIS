<?php
declare(strict_types=1);

final class LatestYearResolver
{
    public static function periodEnd(string $period): int
    {
        if (preg_match('/^(\d{4})$/', $period, $match)) return (int)($match[1] . '1231');
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $match)) {
            $month = (int)$match[2] * 3;
            return (int)sprintf('%04d%02d%02d', (int)$match[1], $month, (int)cal_days_in_month(CAL_GREGORIAN, $month, (int)$match[1]));
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $match) && (int)$match[2] >= 1 && (int)$match[2] <= 12) {
            return (int)sprintf('%04d%02d%02d', (int)$match[1], (int)$match[2], (int)cal_days_in_month(CAL_GREGORIAN, (int)$match[2], (int)$match[1]));
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $match) && checkdate((int)$match[2], (int)$match[3], (int)$match[1])) {
            return (int)str_replace('-', '', $period);
        }
        throw new RuntimeException("Invalid period '{$period}'. Use YYYY, YYYY-Qn, YYYY-MM, or YYYY-MM-DD.");
    }

    public static function latestIndexes(array $rows): array
    {
        $latest = [];
        foreach ($rows as $index => $row) {
            $values = $row['values'] ?? [];
            $label = trim((string)($values['import_key'] ?? ''));
            $period = trim((string)($values['period_key'] ?? ''));
            if ($label === '' || $period === '') continue;
            $end = self::periodEnd($period);
            if (!isset($latest[$label]) || $end > $latest[$label]['period_end']) {
                $latest[$label] = ['index' => $index, 'period_end' => $end];
            }
        }
        return array_map(static fn(array $item): int => $item['index'], $latest);
    }
}
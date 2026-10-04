<?php
/**
 * Purpose: Shared PHP helper for rank bounds parser operations used by application workflows.
 */

declare(strict_types=1);

final class RankBoundsParser
{
    public static function parse(string $display, mixed $lowValue = null, mixed $highValue = null): array
    {
        $low = $lowValue !== null && $lowValue !== '' ? filter_var($lowValue, FILTER_VALIDATE_INT) : null;
        $high = $highValue !== null && $highValue !== '' ? filter_var($highValue, FILTER_VALIDATE_INT) : null;
        if (($lowValue !== null && $lowValue !== '' && $low === false)
            || ($highValue !== null && $highValue !== '' && $high === false)) {
            throw new InvalidArgumentException('Rank bounds must be whole numbers.');
        }

        if ($low === null && $high === null && $display !== '') {
            $clean = str_replace(',', '', trim($display));
            if (preg_match('/^=?\s*(\d+)\s*[-–—]\s*(\d+)$/', $clean, $match)) {
                $low = (int)$match[1];
                $high = (int)$match[2];
            } elseif (preg_match('/^=?\s*(\d+)\+$/', $clean, $match)) {
                $low = (int)$match[1];
            } elseif (preg_match('/^top\s*(\d+)$/i', $clean, $match)) {
                $low = 1;
                $high = (int)$match[1];
            } elseif (preg_match('/^=?\s*(\d+)$/', $clean, $match)) {
                $low = (int)$match[1];
                $high = (int)$match[1];
            }
        }

        if ($low !== null && $low < 0) throw new InvalidArgumentException('Rank lower bound cannot be negative.');
        if ($high !== null && $high < 0) throw new InvalidArgumentException('Rank upper bound cannot be negative.');
        if ($low !== null && $high !== null && $high < $low) {
            throw new InvalidArgumentException('Rank upper bound must be greater than or equal to the lower bound.');
        }

        return [$low, $high, $low === null ? null : ($high === null ? (float)$low : ($low + $high) / 2)];
    }
}
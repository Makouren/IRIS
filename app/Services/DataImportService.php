<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DataImportService
{
    public function parseRankToValue(mixed $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(['–', '—'], '-', $raw);
        return preg_match('/\d+/', $normalized, $matches) ? (int) $matches[0] : null;
    }

    public function insertRanking(array $row): bool
    {
        $shortName = trim((string) ($row['ranking_body_short_name'] ?? ''));
        $bodyId = DB::table('ranking_bodies')->where('short_name', $shortName)->value('id');
        if (!$bodyId) {
            return false;
        }

        $globalRank = $this->nullableString($row['global_rank'] ?? null);
        $phRank = $this->nullableString($row['ph_rank'] ?? null);

        return (bool) DB::table('rankings')->insert([
            'ranking_body_id' => $bodyId,
            'year' => (int) ($row['year'] ?? 0),
            'category' => $this->nullableString($row['category'] ?? null),
            'global_rank' => $globalRank,
            'rank_value' => $this->parseRankToValue($globalRank),
            'ph_rank' => $phRank,
            'ph_rank_value' => $this->parseRankToValue($phRank),
            'note' => trim((string) ($row['note'] ?? '')),
        ]);
    }

    public function insertBreakdown(array $row): bool
    {
        $shortName = trim((string) ($row['ranking_body_short_name'] ?? ''));
        $bodyId = DB::table('ranking_bodies')->where('short_name', $shortName)->value('id');
        $itemLabel = trim((string) ($row['item_label'] ?? ''));
        if (!$bodyId || $itemLabel === '') {
            return false;
        }

        $rankDisplay = $this->nullableString($row['rank_display'] ?? null);

        return (bool) DB::table('ranking_breakdowns')->insert([
            'ranking_body_id' => $bodyId,
            'year' => (int) ($row['year'] ?? 0),
            'group_label' => $this->nullableString($row['group_label'] ?? null),
            'item_label' => $itemLabel,
            'rank_display' => $rankDisplay,
            'rank_value' => $this->parseRankToValue($rankDisplay),
            'note' => trim((string) ($row['note'] ?? '')),
        ]);
    }

    public function insertCollege(array $row): bool
    {
        $name = trim((string) ($row['name'] ?? ''));
        $shortCode = trim((string) ($row['short_code'] ?? ''));
        if ($name === '' || $shortCode === '') {
            return false;
        }

        return (bool) DB::table('colleges')->insert([
            'name' => $name,
            'short_code' => $shortCode,
            'contribution_percent' => (float) ($row['contribution_percent'] ?? 0),
            'year' => (int) ($row['year'] ?? 0),
        ]);
    }

    public function insertProgram(array $row): bool
    {
        $name = trim((string) ($row['name'] ?? ''));
        $collegeCode = trim((string) ($row['college_short_code'] ?? ''));
        if ($name === '' || $collegeCode === '') {
            return false;
        }

        $collegeId = DB::table('colleges')
            ->where('short_code', $collegeCode)
            ->orderByDesc('year')
            ->value('id');
        if (!$collegeId) {
            return false;
        }

        return (bool) DB::table('programs')->insert([
            'name' => $name,
            'college_id' => $collegeId,
            'national_rank' => (int) ($row['national_rank'] ?? 0),
            'score' => (float) ($row['score'] ?? 0),
            'movement' => (int) ($row['movement'] ?? 0),
            'year' => (int) ($row['year'] ?? 0),
        ]);
    }

    public function insertAccreditation(array $row): bool
    {
        $programName = trim((string) ($row['program_name'] ?? ''));
        $criterion = trim((string) ($row['criterion'] ?? ''));
        if ($programName === '' || $criterion === '') {
            return false;
        }

        $score = $this->nullableString($row['score'] ?? null);

        return (bool) DB::table('accreditations')->insert([
            'program_name' => $programName,
            'accrediting_body' => trim((string) ($row['accrediting_body'] ?? 'AUN-QA')) ?: 'AUN-QA',
            'year' => (int) ($row['year'] ?? 0),
            'assessment_date' => $this->nullableString($row['assessment_date'] ?? null),
            'criterion' => $criterion,
            'score' => $score,
            'numeric_score' => ($score !== null && is_numeric($score)) ? (float) $score : null,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

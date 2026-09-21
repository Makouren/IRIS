<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $best = DB::table('rankings as r')
            ->join('ranking_bodies as rb', 'rb.id', '=', 'r.ranking_body_id')
            ->whereNotNull('r.rank_value')
            ->orderByDesc('r.year')
            ->orderBy('r.rank_value')
            ->select('r.global_rank', 'rb.name as body_name', 'r.year')
            ->first();
        $best = $best ? (array) $best : null;

        $phRank = DB::table('rankings')
            ->whereNotNull('ph_rank')
            ->orderByDesc('year')
            ->select('ph_rank', 'year')
            ->first();
        $phRank = $phRank ? (array) $phRank : null;

        $bodyCount = DB::table('ranking_bodies')->count();

        $latestRankingYears = DB::table('rankings')
            ->select('ranking_body_id', DB::raw('MAX(year) as latest_year'))
            ->groupBy('ranking_body_id');

        $bodyCards = DB::query()
            ->fromSub($latestRankingYears, 'latest')
            ->join('rankings as r', function ($join) {
                $join->on('r.ranking_body_id', '=', 'latest.ranking_body_id')
                    ->on('r.year', '=', 'latest.latest_year');
            })
            ->join('ranking_bodies as rb', 'rb.id', '=', 'r.ranking_body_id')
            ->orderBy('rb.short_name')
            ->orderBy('r.category')
            ->select('rb.short_name', 'rb.name', 'r.category', 'r.global_rank', 'r.year', 'r.note')
            ->get()
            ->map(fn ($row) => (array) $row);

        $trend = DB::table('rankings as r')
            ->join('ranking_bodies as rb', 'rb.id', '=', 'r.ranking_body_id')
            ->where('rb.short_name', 'QS')
            ->whereNull('r.category')
            ->orderBy('r.year')
            ->select('r.year', 'r.rank_value', 'r.global_rank')
            ->get();

        $trendYears = $trend->pluck('year')->map(fn ($year) => (string) $year)->values()->all();
        $trendRanks = $trend->map(fn ($row) => $row->rank_value !== null ? (int) $row->rank_value : null)->values()->all();
        $trendDisplay = $trend->pluck('global_rank')->values()->all();

        $latestCollegeYear = DB::table('colleges')->max('year');
        $collegeRows = $latestCollegeYear
            ? DB::table('colleges')->where('year', $latestCollegeYear)->orderByDesc('contribution_percent')->get()
            : collect();
        $collegePieData = $collegeRows->map(fn ($row) => [
            'name' => $row->short_code . ' - ' . $row->name,
            'value' => (float) $row->contribution_percent,
        ])->values()->all();

        $latestProgramYear = DB::table('programs')->max('year');
        $programRows = $latestProgramYear
            ? DB::table('programs as p')
                ->join('colleges as c', 'c.id', '=', 'p.college_id')
                ->where('p.year', $latestProgramYear)
                ->orderBy('p.national_rank')
                ->select('p.national_rank', 'p.name', 'c.short_code', 'p.score', 'p.movement')
                ->get()
                ->map(fn ($row) => (array) $row)
            : collect();

        $latestBreakdownYears = DB::table('ranking_breakdowns as b')
            ->select('b.ranking_body_id', DB::raw('MAX(b.year) as latest_year'))
            ->groupBy('b.ranking_body_id');
        $breakdownGroups = DB::query()
            ->fromSub($latestBreakdownYears, 'latest')
            ->join('ranking_breakdowns as b', function ($join) {
                $join->on('b.ranking_body_id', '=', 'latest.ranking_body_id')
                    ->on('b.year', '=', 'latest.latest_year');
            })
            ->join('ranking_bodies as rb', 'rb.id', '=', 'b.ranking_body_id')
            ->groupBy('rb.id', 'rb.short_name', 'rb.name', 'b.year')
            ->orderBy('rb.short_name')
            ->select('rb.id as body_id', 'rb.short_name', 'rb.name', 'b.year')
            ->get();

        $breakdownSections = [];
        foreach ($breakdownGroups as $group) {
            $items = DB::table('ranking_breakdowns')
                ->where('ranking_body_id', $group->body_id)
                ->where('year', $group->year)
                ->orderByRaw('(rank_value IS NULL)')
                ->orderBy('rank_value')
                ->orderBy('item_label')
                ->select('group_label', 'item_label', 'rank_display', 'rank_value')
                ->get()
                ->all();

            if ($items) {
                $breakdownSections[] = [
                    'body' => (array) $group,
                    'items' => array_map(fn ($item) => (array) $item, $items),
                ];
            }
        }
        
        $accreditedCount = DB::table('accreditations')
             ->distinct()
             ->count('program_name');
        $accreditationRows = DB::table('accreditations')
            ->select(
                'program_name',
                'accrediting_body',
                'year',
                DB::raw('MAX(assessment_date) AS assessment_date'),
                DB::raw("MAX(CASE WHEN criterion = 'Overall Verdict' THEN score END) AS verdict"),
                DB::raw('AVG(numeric_score) AS avg_score')
            )
            ->groupBy('program_name', 'accrediting_body', 'year')
            ->orderByDesc('year')
            ->orderBy('program_name')
            ->get()
            ->map(fn ($row) => (array) $row);

        return view('user.dashboard', compact(
        'best', 'phRank', 'bodyCount', 'bodyCards', 'trendYears', 'trendRanks', 'trendDisplay',
        'latestCollegeYear', 'collegePieData', 'latestProgramYear', 'programRows',
        'breakdownSections', 'accreditationRows', 'accreditedCount'

        ));
    }
}

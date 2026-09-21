<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SummaryController extends Controller
{
    public function index(): JsonResponse
    {
        $best = DB::table('rankings as r')
            ->join('ranking_bodies as rb', 'rb.id', '=', 'r.ranking_body_id')
            ->whereNotNull('r.rank_value')
            ->orderByDesc('r.year')
            ->orderBy('r.rank_value')
            ->select('r.global_rank', 'rb.name as body_name', 'r.year')
            ->first();

        $phRank = DB::table('rankings')->whereNotNull('ph_rank')->orderByDesc('year')->first();

        $trend = DB::table('rankings as r')
            ->join('ranking_bodies as rb', 'rb.id', '=', 'r.ranking_body_id')
            ->where('rb.short_name', 'QS')
            ->whereNull('r.category')
            ->whereNotNull('r.rank_value')
            ->orderBy('r.year')
            ->select('r.year', 'r.rank_value as global_rank')
            ->get();

        $topProgram = DB::table('programs')->orderBy('national_rank')->first();
        $movement = null;
        if ($trend->count() >= 2) {
            $movement = $trend[$trend->count() - 2]->global_rank - $trend[$trend->count() - 1]->global_rank;
        }

        $snapshot = [
            'best_global_rank' => $best->global_rank ?? null,
            'best_body' => $best->body_name ?? null,
            'ph_rank' => $phRank->ph_rank ?? null,
            'positions_gained' => $movement,
            'top_program' => $topProgram->name ?? null,
            'top_program_rank' => $topProgram->national_rank ?? null,
        ];

        $useLlm = (bool) env('USE_LLM_SUMMARY', false);
        $summary = $useLlm ? $this->generateLlmSummary($snapshot) : $this->generateRuleBasedSummary($snapshot);

        return response()->json(['summary' => $summary]);
    }

    private function generateRuleBasedSummary(array $d): string
    {
        $parts = [];
        if ($d['best_global_rank']) {
            $parts[] = 'CLSU currently holds a best global rank of ' . $d['best_global_rank'] . ($d['best_body'] ? ' on ' . $d['best_body'] : '') . '.';
        }
        if ($d['ph_rank']) {
            $parts[] = 'Nationally, it ranks ' . $d['ph_rank'] . 'th among Philippine universities.';
        }
        if ($d['positions_gained'] !== null) {
            $parts[] = $d['positions_gained'] > 0
                ? 'It has climbed ' . $d['positions_gained'] . ' positions compared to the previous year.'
                : ($d['positions_gained'] < 0
                    ? 'It has dropped ' . abs($d['positions_gained']) . ' positions compared to the previous year.'
                    : 'Its rank held steady compared to the previous year.');
        }
        if ($d['top_program']) {
            $parts[] = 'Its top-performing program is ' . $d['top_program'] . ', ranked #' . $d['top_program_rank'] . ' nationally.';
        }

        return $parts ? implode(' ', $parts) : 'Not enough data has been uploaded yet to generate a summary.';
    }

    private function generateLlmSummary(array $data): string
    {
        $key = config('services.anthropic.key');
        if (!$key) return $this->generateRuleBasedSummary($data);

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 300,
            'messages' => [[
                'role' => 'user',
                'content' => 'Summarize this university ranking data in 2-3 friendly sentences for a dashboard viewer: ' . json_encode($data),
            ]],
        ]);

        return $response->json('content.0.text') ?: $this->generateRuleBasedSummary($data);
    }
}

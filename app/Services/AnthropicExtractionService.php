<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AnthropicExtractionService
{
    private const MODEL = 'claude-sonnet-4-6';

    private const SCHEMA_PROMPT = <<<'PROMPT'
You are extracting university ranking, accreditation, and organizational data
from a document (which may be a table, a report, an infographic, or a photo
of a printed page) for a dashboard system.

Return ONLY valid JSON (no markdown fences, no commentary) in exactly this shape:
{
  "rankings": [
    {"ranking_body_short_name": "QS", "year": 2024, "category": "World", "global_rank": "641", "ph_rank": "7", "note": "..."}
  ],
  "ranking_breakdowns": [
    {"ranking_body_short_name": "THE", "year": 2025, "group_label": "SDG", "item_label": "Zero Hunger", "rank_display": "301-400", "note": "..."}
  ],
  "colleges": [
    {"name": "College of Engineering", "short_code": "CEAT", "contribution_percent": 28, "year": 2024}
  ],
  "programs": [
    {"name": "Agricultural Engineering", "college_short_code": "CEAT", "national_rank": 1, "score": 94.2, "movement": 3, "year": 2024}
  ],
  "accreditations": [
    {"program_name": "Doctor of Veterinary Medicine", "accrediting_body": "AUN-QA", "year": 2024, "assessment_date": "July 9-11", "criterion": "Expected Learning Outcomes", "score": "4"}
  ]
}

Rules:
- Known ranking_body_short_name values: QS, THE, CWTS, Webometrics, URAP, SCImago, WURI, AppliedHE, "AD Scientific Index", EduRank. If the document names a different body, still include it with your best-guess short name.
- "global_rank" and "ph_rank" are STRINGS exactly as published. Never convert a band into a single number or invent a number.
- "category" distinguishes multiple lists the same body publishes in one year. Omit it if the body only publishes one list.
- Use "rankings" for headline ranks and "ranking_breakdowns" for sub-lists such as THE Impact SDGs, WURI categories, QS Stars categories, or AppliedHE splits.
- Use "accreditations" for program-level accreditation/certification assessments, one row per criterion per assessment, including an "Overall Verdict" row when present.
- If a field is not present, use null or omit it. Do not invent numbers or dates.
- If a category has no data, return an empty array for it.
- "movement" is the change in rank versus the prior period; use 0 if not mentioned.
PROMPT;

    public function requirements(): array
    {
        $key = (bool) config('services.anthropic.key');
        return [
            'api_key' => ['ok' => $key, 'label' => $key
                ? 'CLAUDE_API_KEY is set — Word, PDF, Excel, and image uploads can reach the AI.'
                : 'CLAUDE_API_KEY is not set for this PHP process — Word/PDF/Excel/image uploads will fail until it is.'],
            'curl' => ['ok' => function_exists('curl_init'), 'label' => function_exists('curl_init')
                ? 'PHP cURL extension enabled.'
                : 'PHP cURL extension is missing — the AI service cannot be reached without it.'],
            'zip' => ['ok' => class_exists('ZipArchive'), 'label' => class_exists('ZipArchive')
                ? 'ZipArchive available (needed to open .docx/.xlsx files).'
                : 'PHP zip extension is missing — Word (.docx) and Excel (.xlsx) files cannot be opened.'],
            'fileinfo' => ['ok' => function_exists('finfo_open'), 'label' => function_exists('finfo_open')
                ? 'fileinfo extension enabled.'
                : 'PHP fileinfo extension is missing — file type detection may be less reliable.'],
        ];
    }

    public function requirementsOk(array $checks): bool
    {
        foreach ($checks as $key => $check) {
            if ($key !== 'fileinfo' && !$check['ok']) {
                return false;
            }
        }
        return true;
    }

    public function extract(array $contentBlocks): array
    {
        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            throw new \RuntimeException('AI extraction is not configured: set CLAUDE_API_KEY in the .env file and restart Apache/XAMPP.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The PHP cURL extension is not enabled, so the AI service cannot be reached.');
        }

        $payload = [
            'model' => self::MODEL,
            'max_tokens' => 3000,
            'messages' => [[
                'role' => 'user',
                'content' => array_merge([['type' => 'text', 'text' => self::SCHEMA_PROMPT]], $contentBlocks),
            ]],
        ];

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', $payload);

        if ($response->failed()) {
            $message = $response->json('error.message') ?: $response->body();
            throw new \RuntimeException('The Anthropic API rejected the request (HTTP ' . $response->status() . '): ' . $message);
        }

        $text = $response->json('content.0.text');
        if (!$text) {
            throw new \RuntimeException("The AI response didn't contain the expected data.");
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new \RuntimeException("Could not find structured data in the AI response.");
        }

        $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($parsed)) {
            throw new \RuntimeException("Could not parse the AI response as valid JSON.");
        }

        return [
            'rankings' => $parsed['rankings'] ?? [],
            'ranking_breakdowns' => $parsed['ranking_breakdowns'] ?? [],
            'colleges' => $parsed['colleges'] ?? [],
            'programs' => $parsed['programs'] ?? [],
            'accreditations' => $parsed['accreditations'] ?? [],
        ];
    }

    public function textBlock(string $text): array
    {
        return ['type' => 'text', 'text' => "Document content:\n\n" . $text];
    }

    public function pdfBlock(string $filePath): array
    {
        return ['type' => 'document', 'source' => [
            'type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode(file_get_contents($filePath)),
        ]];
    }

    public function imageBlock(string $filePath, string $mimeType): array
    {
        return ['type' => 'image', 'source' => [
            'type' => 'base64', 'media_type' => $mimeType, 'data' => base64_encode(file_get_contents($filePath)),
        ]];
    }
}

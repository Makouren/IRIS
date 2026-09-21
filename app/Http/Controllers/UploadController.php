<?php

namespace App\Http\Controllers;

use App\Services\AnthropicExtractionService;
use App\Services\DataImportService;
use App\Services\DocumentExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function __construct(
        private readonly DataImportService $importer,
        private readonly DocumentExtractor $extractor,
        private readonly AnthropicExtractionService $ai,
    ) {}

    public function showSmartUpload(Request $request): View
    {
        $checks = $this->ai->requirements();
        $allOk = $this->ai->requirementsOk($checks);
        $msg = $request->session()->get('error') ?? $request->session()->get('success');

        return view('admin.smart_upload', compact('checks', 'allOk', 'msg'));
    }

    public function csv(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'upload_type' => ['required', 'in:rankings,colleges,programs'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:15360'],
        ]);

        $file = $request->file('csv_file');
        $originalName = basename($file->getClientOriginalName());
        $type = $validated['upload_type'];
        $rowsInserted = 0;

        try {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                throw new \RuntimeException('Could not read the uploaded CSV file.');
            }

            fgetcsv($handle); // header
            DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if ($type === 'rankings') {
                    [$shortName, $year, $globalRank, $phRank, $note] = array_pad($row, 5, null);
                    $ok = $this->importer->insertRanking([
                        'ranking_body_short_name' => $shortName,
                        'year' => $year,
                        'global_rank' => $globalRank,
                        'ph_rank' => $phRank,
                        'note' => $note,
                    ]);
                } elseif ($type === 'colleges') {
                    [$name, $shortCode, $percent, $year] = array_pad($row, 4, null);
                    $ok = $this->importer->insertCollege([
                        'name' => $name,
                        'short_code' => $shortCode,
                        'contribution_percent' => $percent,
                        'year' => $year,
                    ]);
                } else {
                    [$name, $collegeCode, $natRank, $score, $movement, $year] = array_pad($row, 6, null);
                    $ok = $this->importer->insertProgram([
                        'name' => $name,
                        'college_short_code' => $collegeCode,
                        'national_rank' => $natRank,
                        'score' => $score,
                        'movement' => $movement,
                        'year' => $year,
                    ]);
                }

                if ($ok) {
                    $rowsInserted++;
                }
            }

            fclose($handle);

            DB::table('uploads_log')->insert([
                'uploaded_by' => $request->session()->get('user_id'),
                'filename' => $originalName,
                'file_type' => 'csv',
                'upload_type' => $type,
                'rows_inserted' => $rowsInserted,
                'uploaded_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'CSV upload failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.dashboard')
            ->with('success', "Uploaded {$originalName} — {$rowsInserted} rows inserted.");
    }

    public function smartUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'upload_file' => ['required', 'file', 'max:15360', 'mimes:csv,xlsx,xls,docx,pdf,jpg,jpeg,png'],
        ]);

        $file = $request->file('upload_file');
        $originalName = basename($file->getClientOriginalName());
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        try {
            if ($ext === 'csv') {
                $result = $this->extractor->smartMapCsv($path);

                if ($result === null) {
                    $checks = $this->ai->requirements();
                    if (!$this->ai->requirementsOk($checks)) {
                        return back()->with('error', "This CSV's columns don't match the known templates, so it needs AI help — but AI extraction isn't configured yet.");
                    }
                    $result = $this->ai->extract([$this->ai->textBlock($this->extractor->csvRowsToText($path))]);
                }
            } else {
                $checks = $this->ai->requirements();
                if (!$this->ai->requirementsOk($checks)) {
                    return back()->with('error', "AI extraction isn't fully configured on this server yet. See the Setup Status panel below.");
                }

                $blocks = match ($ext) {
                    'xlsx', 'xls' => [$this->ai->textBlock($this->extractor->extractSpreadsheet($path))],
                    'docx' => [$this->ai->textBlock($this->extractor->extractDocx($path))],
                    'pdf' => [$this->ai->pdfBlock($path)],
                    'jpg', 'jpeg', 'png' => [$this->ai->imageBlock($path, $ext === 'png' ? 'image/png' : 'image/jpeg')],
                    default => throw new \RuntimeException('Unsupported file type.'),
                };

                $result = $this->ai->extract($blocks);
            }

            $request->session()->put([
                'pending_extraction' => $result,
                'pending_extraction_filename' => $originalName,
                'pending_extraction_file_type' => $ext,
            ]);

            return redirect()->route('admin.review-extraction');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reviewExtraction(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('pending_extraction')) {
            return redirect()->route('admin.smart-upload')->with('error', 'There is no extracted file waiting for review.');
        }

        $data = $request->session()->get('pending_extraction', []);
        $filename = $request->session()->get('pending_extraction_filename', 'uploaded file');
        $fileType = $request->session()->get('pending_extraction_file_type', '');
        $rankings = $data['rankings'] ?? [];
        $breakdowns = $data['ranking_breakdowns'] ?? [];
        $colleges = $data['colleges'] ?? [];
        $programs = $data['programs'] ?? [];
        $accreditations = $data['accreditations'] ?? [];
        $totalRows = count($rankings) + count($breakdowns) + count($colleges) + count($programs) + count($accreditations);

        $firstActiveTab = 'rankings';
        if (empty($rankings)) {
            if (!empty($breakdowns)) $firstActiveTab = 'breakdowns';
            elseif (!empty($colleges)) $firstActiveTab = 'colleges';
            elseif (!empty($programs)) $firstActiveTab = 'programs';
            elseif (!empty($accreditations)) $firstActiveTab = 'accreditations';
        }

        return view('admin.review_extraction', compact(
            'filename', 'fileType', 'rankings', 'breakdowns', 'colleges', 'programs', 'accreditations', 'totalRows', 'firstActiveTab'
        ));
    }

    public function confirmExtraction(Request $request): RedirectResponse
    {
        if (!$request->session()->has('pending_extraction')) {
            return redirect()->route('admin.smart-upload')->with('error', 'The extraction session expired. Please upload the file again.');
        }

        $inserted = 0;
        $skipped = 0;
        $categoriesUsed = [];

        DB::beginTransaction();
        try {
            foreach ($request->input('rankings', []) as $row) {
                if (!isset($row['include'])) continue;
                $ok = $this->importer->insertRanking($row);
                $ok ? ($inserted++) : ($skipped++);
                if ($ok) $categoriesUsed['rankings'] = true;
            }
            foreach ($request->input('breakdowns', []) as $row) {
                if (!isset($row['include'])) continue;
                $ok = $this->importer->insertBreakdown($row);
                $ok ? ($inserted++) : ($skipped++);
                if ($ok) $categoriesUsed['ranking_breakdowns'] = true;
            }
            foreach ($request->input('colleges', []) as $row) {
                if (!isset($row['include'])) continue;
                $ok = $this->importer->insertCollege($row);
                $ok ? ($inserted++) : ($skipped++);
                if ($ok) $categoriesUsed['colleges'] = true;
            }
            foreach ($request->input('programs', []) as $row) {
                if (!isset($row['include'])) continue;
                $ok = $this->importer->insertProgram($row);
                $ok ? ($inserted++) : ($skipped++);
                if ($ok) $categoriesUsed['programs'] = true;
            }
            foreach ($request->input('accreditations', []) as $row) {
                if (!isset($row['include'])) continue;
                $ok = $this->importer->insertAccreditation($row);
                $ok ? ($inserted++) : ($skipped++);
                if ($ok) $categoriesUsed['accreditations'] = true;
            }

            $uploadType = count($categoriesUsed) === 1
                ? array_key_first($categoriesUsed)
                : (count($categoriesUsed) > 1 ? 'mixed' : 'none');

            DB::table('uploads_log')->insert([
                'uploaded_by' => $request->session()->get('user_id'),
                'filename' => trim((string) $request->session()->get('pending_extraction_filename', 'unknown')),
                'file_type' => trim((string) $request->input('file_type', $request->session()->get('pending_extraction_file_type', ''))),
                'upload_type' => $uploadType,
                'rows_inserted' => $inserted,
                'uploaded_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Could not save the reviewed extraction: ' . $e->getMessage());
        }

        $request->session()->forget([
            'pending_extraction',
            'pending_extraction_filename',
            'pending_extraction_file_type',
        ]);

        $msg = "Saved {$inserted} row(s).";
        if ($skipped > 0) {
            $msg .= " {$skipped} row(s) skipped (for example, an unknown ranking body or a missing college code).";
        }

        return redirect()->route('admin.dashboard')->with('success', $msg);
    }
}

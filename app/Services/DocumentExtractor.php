<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class DocumentExtractor
{
    public function extractDocx(string $filePath): string
    {
        $phpWord = WordIOFactory::load($filePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractWordElement($element);
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('No readable text found in this Word document. If it contains scanned images, export it as a PDF or upload an image instead.');
        }

        return $text;
    }

    public function extractSpreadsheet(string $filePath): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($filePath);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            $hasContent = false;
            $sheetText = 'Sheet: ' . $sheet->getTitle() . "\n";

            foreach ($rows as $row) {
                if (count(array_filter($row, fn ($cell) => $cell !== null && $cell !== '')) === 0) {
                    continue;
                }
                $hasContent = true;
                $sheetText .= implode(' | ', array_map(fn ($cell) => $cell ?? '', $row)) . "\n";
            }

            if ($hasContent) {
                $text .= $sheetText;
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('This spreadsheet appears to be empty, or all its sheets are blank.');
        }

        return $text;
    }

    public function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the CSV file.');
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('The CSV file appears to be empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }
            $row = array_pad($row, count($header), null);
            $rows[] = array_combine($header, array_slice($row, 0, count($header)));
        }
        fclose($handle);

        return $rows;
    }

    public function smartMapCsv(string $filePath): ?array
    {
        $rows = $this->readCsvRows($filePath);
        if (empty($rows)) {
            return ['rankings' => [], 'ranking_breakdowns' => [], 'colleges' => [], 'programs' => [], 'accreditations' => []];
        }

        $columns = array_keys($rows[0]);
        $templates = [
            'rankings' => ['ranking_body_short_name', 'year', 'global_rank', 'ph_rank'],
            'colleges' => ['name', 'short_code', 'contribution_percent', 'year'],
            'programs' => ['name', 'college_short_code', 'national_rank', 'score'],
        ];

        $bestMatch = null;
        $bestScore = 0;
        foreach ($templates as $type => $requiredColumns) {
            $score = count(array_intersect($requiredColumns, $columns));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $type;
            }
        }

        if ($bestMatch === null || $bestScore < ceil(count($templates[$bestMatch]) / 2)) {
            return null;
        }

        $result = ['rankings' => [], 'ranking_breakdowns' => [], 'colleges' => [], 'programs' => [], 'accreditations' => []];
        foreach ($rows as $row) {
            if ($bestMatch === 'rankings') {
                $result['rankings'][] = [
                    'ranking_body_short_name' => $row['ranking_body_short_name'] ?? '',
                    'year' => $row['year'] ?? null,
                    'category' => $row['category'] ?? null,
                    'global_rank' => $row['global_rank'] ?? null,
                    'ph_rank' => $row['ph_rank'] ?? null,
                    'note' => $row['note'] ?? '',
                ];
            } elseif ($bestMatch === 'colleges') {
                $result['colleges'][] = [
                    'name' => $row['name'] ?? '',
                    'short_code' => $row['short_code'] ?? '',
                    'contribution_percent' => $row['contribution_percent'] ?? null,
                    'year' => $row['year'] ?? null,
                ];
            } else {
                $result['programs'][] = [
                    'name' => $row['name'] ?? '',
                    'college_short_code' => $row['college_short_code'] ?? '',
                    'national_rank' => $row['national_rank'] ?? null,
                    'score' => $row['score'] ?? null,
                    'movement' => $row['movement'] ?? 0,
                    'year' => $row['year'] ?? null,
                ];
            }
        }

        return $result;
    }

    public function csvRowsToText(string $filePath): string
    {
        $rows = $this->readCsvRows($filePath);
        if (empty($rows)) {
            throw new \RuntimeException('The CSV file appears to be empty.');
        }

        $columns = array_keys($rows[0]);
        $text = implode(' | ', $columns) . "\n";
        foreach ($rows as $row) {
            $text .= implode(' | ', array_map(fn ($value) => $value ?? '', $row)) . "\n";
        }

        return $text;
    }

    private function extractWordElement(mixed $element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $value = $element->getText();
            $text .= is_array($value) ? implode('', $value) : $value;
            return $text . ' ';
        }

        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                $cellTexts = [];
                foreach ($row->getCells() as $cell) {
                    $cellText = '';
                    foreach ($cell->getElements() as $cellElement) {
                        $cellText .= $this->extractWordElement($cellElement);
                    }
                    $cellTexts[] = trim($cellText);
                }
                $text .= implode(' | ', $cellTexts) . "\n";
            }
            return $text;
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extractWordElement($childElement);
            }
            return $text . "\n";
        }

        return $text;
    }
}

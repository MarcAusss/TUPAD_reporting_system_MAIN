<?php

namespace App\Services\Exports;

use Illuminate\Support\Str;

final class PdfTableWriter
{
    private const PAGE_WIDTH = 842;

    private const PAGE_HEIGHT = 595;

    private const MARGIN = 24;

    public function render(array $report): string
    {
        $pages = $this->pages($report);
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageReferences = [];

        foreach ($pages as $index => $content) {
            $contentObject = 5 + ($index * 2);
            $pageObject = $contentObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $objects[$contentObject] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($content),
                $content,
            );
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] '
                .' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                .' /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentObject,
            );
        }

        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', $pageReferences),
            count($pageReferences),
        );

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects));
        $pdf .= "xref\n0 ".($objectCount + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $objectCount; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= "trailer\n<< /Size ".($objectCount + 1).' /Root 1 0 R >>';
        $pdf .= "\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /** @return array<int, string> */
    private function pages(array $report): array
    {
        $columns = $report['columns'];
        $rows = $report['display_rows']->values();
        $columnCount = max(1, count($columns));
        $tableWidth = self::PAGE_WIDTH - (self::MARGIN * 2);
        $columnWidth = $tableWidth / $columnCount;
        $fontSize = max(5.2, min(8.5, 52 / $columnCount));
        $rowsPerPage = 29;
        $chunks = $rows->chunk($rowsPerPage);

        if ($chunks->isEmpty()) {
            $chunks = collect([collect()]);
        }

        return $chunks->values()->map(function ($pageRows, int $pageIndex) use (
            $report,
            $columns,
            $columnWidth,
            $fontSize,
            $tableWidth,
            $chunks,
        ): string {
            $commands = [];
            $y = self::PAGE_HEIGHT - self::MARGIN;

            $commands[] = $this->text(
                self::MARGIN,
                $y,
                13,
                (string) $report['title'],
                true,
            );
            $y -= 16;
            $commands[] = $this->text(
                self::MARGIN,
                $y,
                7.5,
                'TUPAD Reporting System | Generated '
                    .$report['generated_at']->format('M d, Y h:i A')
                    .' | Page '.($pageIndex + 1).' of '.$chunks->count(),
            );
            $y -= 12;

            $criteria = collect($report['criteria'])
                ->map(fn (string $value, string $label): string =>
                    $label.': '.$value)
                ->implode(' | ');
            $commands[] = $this->text(
                self::MARGIN,
                $y,
                6.8,
                $this->truncate($criteria, 185),
            );
            $y -= 17;

            $headerTop = $y;
            $commands[] = sprintf(
                '0.88 0.93 0.98 rg %.2F %.2F %.2F 14 re f',
                self::MARGIN,
                $headerTop - 11,
                $tableWidth,
            );

            foreach ($columns as $columnIndex => $column) {
                $x = self::MARGIN + ($columnIndex * $columnWidth);
                $commands[] = $this->text(
                    $x + 2,
                    $headerTop - 7,
                    $fontSize,
                    $this->fit((string) $column['label'], $columnWidth, $fontSize),
                    true,
                );
            }

            $y -= 14;

            if ($pageRows->isEmpty()) {
                $commands[] = $this->text(
                    self::MARGIN + 4,
                    $y - 9,
                    8,
                    'No records match the selected report criteria.',
                );
                $y -= 15;
            }

            foreach ($pageRows as $rowIndex => $row) {
                if ($rowIndex % 2 === 1) {
                    $commands[] = sprintf(
                        '0.97 0.98 0.99 rg %.2F %.2F %.2F 14 re f',
                        self::MARGIN,
                        $y - 11,
                        $tableWidth,
                    );
                }

                foreach ($columns as $columnIndex => $column) {
                    $x = self::MARGIN + ($columnIndex * $columnWidth);
                    $value = (string) ($row[$column['key']] ?? '—');
                    $commands[] = $this->text(
                        $x + 2,
                        $y - 7,
                        $fontSize,
                        $this->fit($value, $columnWidth, $fontSize),
                    );
                }

                $y -= 14;
            }

            $commands[] = sprintf(
                '0.72 0.76 0.80 RG 0.4 w %.2F %.2F %.2F %.2F re S',
                self::MARGIN,
                $y + 3,
                $tableWidth,
                $headerTop - $y - 14,
            );

            if ($report['warning']) {
                $commands[] = $this->text(
                    self::MARGIN,
                    max(12, $y - 12),
                    6.5,
                    'Note: '.$this->truncate((string) $report['warning'], 190),
                );
            }

            return implode("\n", $commands);
        })->all();
    }

    private function text(
        float $x,
        float $y,
        float $size,
        string $text,
        bool $bold = false,
    ): string {
        return sprintf(
            'BT /%s %.2F Tf 0 g 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $y,
            $this->escape($text),
        );
    }

    private function fit(string $value, float $width, float $fontSize): string
    {
        $characters = max(3, (int) floor(($width - 4) / ($fontSize * 0.52)));

        return $this->truncate($value, $characters);
    }

    private function truncate(string $value, int $characters): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', $value) ?? $value, $characters, '...');
    }

    private function escape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $encoded = $encoded === false ? preg_replace('/[^\x20-\x7E]/', '?', $value) : $encoded;

        return str_replace(
            ["\\", '(', ')', "\r", "\n"],
            ["\\\\", '\\(', '\\)', '', ' '],
            (string) $encoded,
        );
    }
}

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

            // Phase 14F print header: follows the TUPAD PPE Inventory print/header
            // composition only (brand left, report identity center, metadata right).
            $headerHeight = 54;
            $headerBottom = $y - $headerHeight;
            $leftWidth = 150;
            $rightWidth = 154;
            $centerX = self::MARGIN + $leftWidth;
            $centerWidth = $tableWidth - $leftWidth - $rightWidth;
            $rightX = self::MARGIN + $tableWidth - $rightWidth;

            $commands[] = sprintf(
                '0.05 0.61 0.75 rg %.2F %.2F %.2F 4 re f',
                self::MARGIN,
                $y - 4,
                $tableWidth,
            );
            $commands[] = sprintf(
                '0.92 0.95 0.97 rg %.2F %.2F %.2F %.2F re f',
                $rightX,
                $headerBottom,
                $rightWidth,
                $headerHeight - 4,
            );
            $commands[] = sprintf(
                '0.82 0.86 0.90 RG 0.5 w %.2F %.2F %.2F %.2F re S',
                self::MARGIN,
                $headerBottom,
                $tableWidth,
                $headerHeight,
            );
            $commands[] = sprintf(
                '0.86 0.89 0.92 RG 0.4 w %.2F %.2F m %.2F %.2F l S',
                $centerX,
                $headerBottom,
                $centerX,
                $y - 4,
            );
            $commands[] = sprintf(
                '0.86 0.89 0.92 RG 0.4 w %.2F %.2F m %.2F %.2F l S',
                $rightX,
                $headerBottom,
                $rightX,
                $y - 4,
            );

            $commands[] = $this->text(self::MARGIN + 10, $y - 20, 13, 'TUPAD', true);
            $commands[] = $this->text(self::MARGIN + 10, $y - 33, 7.2, 'TUPAD Reporting System', true);
            $commands[] = $this->text(self::MARGIN + 10, $y - 43, 6.2, 'DOLE Regional Office V');

            $title = $this->fit((string) $report['title'], $centerWidth - 18, 11.5);
            $titleApproxWidth = strlen($title) * 11.5 * 0.48;
            $titleX = $centerX + max(8, ($centerWidth - $titleApproxWidth) / 2);
            $commands[] = $this->text($centerX + 12, $y - 17, 6.8, 'DEPARTMENT OF LABOR AND EMPLOYMENT', true);
            $commands[] = $this->text($titleX, $y - 32, 11.5, $title, true);

            $headerContext = isset($report['dimension'])
                && is_object($report['dimension'])
                && method_exists($report['dimension'], 'label')
                    ? 'Grouped by '.$report['dimension']->label()
                    : (string) ($report['official_code'] ?? 'Official Report');
            $commands[] = $this->text(
                $centerX + 12,
                $y - 42,
                6.2,
                $this->fit($headerContext, $centerWidth - 24, 6.2),
            );

            if (filled($report['official_period'] ?? null)) {
                $commands[] = $this->text(
                    $centerX + 12,
                    $y - 50,
                    5.8,
                    $this->fit((string) $report['official_period'], $centerWidth - 24, 5.8),
                    true,
                );
            }

            $commands[] = $this->text($rightX + 8, $y - 17, 6.1, 'Generated', true);
            $commands[] = $this->text($rightX + 58, $y - 17, 6.1, $report['generated_at']->format('M d, Y'));
            $commands[] = $this->text($rightX + 8, $y - 29, 6.1, 'Time', true);
            $commands[] = $this->text($rightX + 58, $y - 29, 6.1, $report['generated_at']->format('h:i A'));
            $commands[] = $this->text($rightX + 8, $y - 41, 6.1, 'Page', true);
            $commands[] = $this->text($rightX + 58, $y - 41, 6.1, ($pageIndex + 1).' of '.$chunks->count());

            $y = $headerBottom - 10;

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

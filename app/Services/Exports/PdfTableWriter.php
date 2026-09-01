<?php

namespace App\Services\Exports;

use Illuminate\Support\Str;

final class PdfTableWriter
{
    private const PAGE_WIDTH = 842;

    private const PAGE_HEIGHT = 595;

    private const LETTER_PAGE_WIDTH = 612;

    private const LETTER_PAGE_HEIGHT = 792;

    private const MARGIN = 24;

    public function render(array $report): string
    {
        $isPhysicalFinancial =
            ($report['type']->value ?? null) === 'physical_financial'
            && is_array($report['physical_financial_matrix'] ?? null);

        $pages = $isPhysicalFinancial
            ? $this->physicalFinancialPages($report)
            : $this->pages($report);

        $pageWidth = $isPhysicalFinancial
            ? self::LETTER_PAGE_WIDTH
            : self::PAGE_WIDTH;
        $pageHeight = $isPhysicalFinancial
            ? self::LETTER_PAGE_HEIGHT
            : self::PAGE_HEIGHT;

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
                $pageWidth,
                $pageHeight,
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

    /** @return array<int, string> */
    private function physicalFinancialPages(array $report): array
    {
        $matrix = $report['physical_financial_matrix'];
        $dimension = $report['dimension']->value ?? 'overall';

        if ($dimension === 'overall') {
            return [$this->physicalFinancialOverallPage($report, $matrix)];
        }

        return collect($matrix['periods'] ?? [])
            ->map(
                fn (array $period): string =>
                    $this->physicalFinancialPeriodPage(
                        $report,
                        $matrix,
                        $period,
                    )
            )
            ->all();
    }

    private function physicalFinancialOverallPage(
        array $report,
        array $matrix,
    ): string {
        $commands = [];
        $y = $this->physicalFinancialHeader(
            $commands,
            $report,
            'Overall Accomplishment',
        );

        $x = self::MARGIN;
        $widths = [100, 76, 82, 76, 82, 70, 78];
        $headerOne = 20;
        $headerTwo = 16;
        $rowHeight = 20;

        $top = $y;
        $secondTop = $top - $headerOne;
        $bodyTop = $secondTop - $headerTwo;

        $commands[] = $this->filledRect($x, $bodyTop, $widths[0], $headerOne + $headerTwo, '0.80 0.25 0.11');
        $commands[] = $this->filledRect($x + $widths[0], $secondTop, $widths[1] + $widths[2], $headerOne, '0.80 0.25 0.11');
        $commands[] = $this->filledRect($x + array_sum(array_slice($widths, 0, 3)), $secondTop, $widths[3] + $widths[4], $headerOne, '0.97 0.83 0.36');
        $commands[] = $this->filledRect($x + array_sum(array_slice($widths, 0, 5)), $secondTop, $widths[5] + $widths[6], $headerOne, '0.94 0.69 0.48');

        $leafX = $x + $widths[0];
        for ($i = 1; $i < count($widths); $i++) {
            $commands[] = $this->filledRect($leafX, $bodyTop, $widths[$i], $headerTwo, '1.00 0.97 0.86');
            $leafX += $widths[$i];
        }

        $commands[] = $this->text($x + 6, $secondTop - 4, 7.2, 'Province', true);
        $commands[] = $this->centeredText($x + $widths[0], $secondTop + 6, $widths[1] + $widths[2], 7.2, 'Reformulated Target', true);
        $commands[] = $this->centeredText($x + array_sum(array_slice($widths, 0, 3)), $secondTop + 6, $widths[3] + $widths[4], 7.2, 'Accomplishment', true);
        $commands[] = $this->centeredText($x + array_sum(array_slice($widths, 0, 5)), $secondTop + 6, $widths[5] + $widths[6], 7.2, 'Balance', true);

        $leafLabels = ['Physical', 'Financial', 'Physical', 'Financial', 'Physical', 'Financial'];
        $leafX = $x + $widths[0];
        foreach ($leafLabels as $index => $label) {
            $commands[] = $this->centeredText($leafX, $bodyTop + 5, $widths[$index + 1], 6.6, $label, true);
            $leafX += $widths[$index + 1];
        }

        $rows = collect($matrix['rows'] ?? [])->push($matrix['total']);
        $currentY = $bodyTop;

        foreach ($rows as $index => $row) {
            $currentY -= $rowHeight;
            $isTotal = $index === $rows->count() - 1;

            if ($isTotal) {
                $commands[] = $this->filledRect(
                    $x,
                    $currentY,
                    array_sum($widths),
                    $rowHeight,
                    '0.25 0.25 0.25',
                );
            } elseif ($index % 2 === 1) {
                $commands[] = $this->filledRect(
                    $x,
                    $currentY,
                    array_sum($widths),
                    $rowHeight,
                    '0.97 0.98 0.99',
                );
            }

            $values = [
                (string) ($row['province'] ?? ''),
                $this->pfNumber(data_get($row, 'target.physical', 0)),
                $this->pfMoney(data_get($row, 'target.financial_cents', 0)),
                $this->pfNumber(data_get($row, 'accomplishment.physical', 0)),
                $this->pfMoney(data_get($row, 'accomplishment.financial_cents', 0)),
                $this->pfNumber(data_get($row, 'balance.physical', 0)),
                $this->pfMoney(data_get($row, 'balance.financial_cents', 0)),
            ];

            $cellX = $x;
            foreach ($values as $cellIndex => $value) {
                $textX = $cellIndex === 0
                    ? $cellX + 5
                    : $cellX + 3;
                $commands[] = $this->text(
                    $textX,
                    $currentY + 7,
                    $cellIndex === 0 ? 7.0 : 6.2,
                    $this->fit($value, $widths[$cellIndex] - 5, $cellIndex === 0 ? 7.0 : 6.2),
                    $isTotal || $cellIndex === 0,
                );
                $cellX += $widths[$cellIndex];
            }
        }

        $bottom = $currentY;
        $commands[] = $this->tableGrid(
            $x,
            $top,
            $bottom,
            $widths,
            [$top, $secondTop, $bodyTop],
            $rowHeight,
            $rows->count(),
        );

        $noteY = max(32, $bottom - 15);
        $commands[] = $this->text(
            self::MARGIN,
            $noteY,
            5.7,
            $this->truncate('Basis: '.($matrix['basis_note'] ?? ''), 180),
        );

        return implode("\n", array_filter($commands));
    }

    private function physicalFinancialPeriodPage(
        array $report,
        array $matrix,
        array $period,
    ): string {
        $commands = [];
        $y = $this->physicalFinancialHeader(
            $commands,
            $report,
            (string) $period['label'].' Accomplishment',
        );

        $x = self::MARGIN;
        $widths = [220, 172, 172];
        $headerOne = 20;
        $headerTwo = 16;
        $rowHeight = 24;
        $top = $y;
        $secondTop = $top - $headerOne;
        $bodyTop = $secondTop - $headerTwo;

        $commands[] = $this->filledRect($x, $bodyTop, $widths[0], $headerOne + $headerTwo, '0.80 0.25 0.11');
        $commands[] = $this->filledRect($x + $widths[0], $secondTop, $widths[1] + $widths[2], $headerOne, '0.97 0.83 0.36');
        $commands[] = $this->filledRect($x + $widths[0], $bodyTop, $widths[1], $headerTwo, '1.00 0.97 0.86');
        $commands[] = $this->filledRect($x + $widths[0] + $widths[1], $bodyTop, $widths[2], $headerTwo, '1.00 0.97 0.86');

        $commands[] = $this->text($x + 8, $secondTop - 4, 8, 'Province', true);
        $commands[] = $this->centeredText($x + $widths[0], $secondTop + 6, $widths[1] + $widths[2], 8, (string) $period['label'], true);
        $commands[] = $this->centeredText($x + $widths[0], $bodyTop + 5, $widths[1], 7.2, 'Physical', true);
        $commands[] = $this->centeredText($x + $widths[0] + $widths[1], $bodyTop + 5, $widths[2], 7.2, 'Financial', true);

        $rows = collect($matrix['rows'] ?? [])->push($matrix['total']);
        $currentY = $bodyTop;

        foreach ($rows as $index => $row) {
            $currentY -= $rowHeight;
            $isTotal = $index === $rows->count() - 1;

            if ($isTotal) {
                $commands[] = $this->filledRect($x, $currentY, array_sum($widths), $rowHeight, '0.25 0.25 0.25');
            } elseif ($index % 2 === 1) {
                $commands[] = $this->filledRect($x, $currentY, array_sum($widths), $rowHeight, '0.97 0.98 0.99');
            }

            $key = (string) $period['key'];
            $values = [
                (string) ($row['province'] ?? ''),
                $this->pfNumber(data_get($row, 'periods.'.$key.'.physical', 0)),
                $this->pfMoney(data_get($row, 'periods.'.$key.'.financial_cents', 0)),
            ];

            $cellX = $x;
            foreach ($values as $cellIndex => $value) {
                $commands[] = $this->text(
                    $cellX + ($cellIndex === 0 ? 8 : 6),
                    $currentY + 9,
                    $cellIndex === 0 ? 8 : 7.4,
                    $this->fit($value, $widths[$cellIndex] - 10, $cellIndex === 0 ? 8 : 7.4),
                    $isTotal || $cellIndex === 0,
                );
                $cellX += $widths[$cellIndex];
            }
        }

        $commands[] = $this->tableGrid(
            $x,
            $top,
            $currentY,
            $widths,
            [$top, $secondTop, $bodyTop],
            $rowHeight,
            $rows->count(),
        );

        $commands[] = $this->text(
            self::MARGIN,
            max(30, $currentY - 16),
            5.8,
            'Letter portrait layout - one reporting period per page. Short-Term and Long-Term subdivisions removed.',
        );

        return implode("\n", array_filter($commands));
    }

    /**
     * @param array<int, string> $commands
     */
    private function physicalFinancialHeader(
        array &$commands,
        array $report,
        string $subtitle,
    ): float {
        $tableWidth = self::LETTER_PAGE_WIDTH - (self::MARGIN * 2);
        $top = self::LETTER_PAGE_HEIGHT - self::MARGIN;
        $headerBottom = $top - 72;

        $commands[] = sprintf(
            '0.05 0.61 0.75 rg %.2F %.2F %.2F 4 re f',
            self::MARGIN,
            $top - 4,
            $tableWidth,
        );
        $commands[] = sprintf(
            '0.82 0.86 0.90 RG 0.6 w %.2F %.2F %.2F %.2F re S',
            self::MARGIN,
            $headerBottom,
            $tableWidth,
            72,
        );

        $commands[] = $this->text(self::MARGIN + 10, $top - 22, 13, 'TUPAD', true);
        $commands[] = $this->text(self::MARGIN + 10, $top - 36, 7, 'TUPAD Reporting System', true);
        $commands[] = $this->text(self::MARGIN + 10, $top - 48, 6, 'DOLE Regional Office V');

        $commands[] = $this->centeredText(
            self::MARGIN + 120,
            $top - 27,
            $tableWidth - 240,
            11,
            'Physical and Financial Accomplishment',
            true,
        );
        $commands[] = $this->centeredText(
            self::MARGIN + 120,
            $top - 43,
            $tableWidth - 240,
            7.2,
            $subtitle,
            true,
        );

        $period = (string) ($report['official_period'] ?? 'Current validated records');
        $commands[] = $this->text(self::LETTER_PAGE_WIDTH - self::MARGIN - 112, $top - 22, 6.2, 'Generated', true);
        $commands[] = $this->text(self::LETTER_PAGE_WIDTH - self::MARGIN - 62, $top - 22, 6.2, $report['generated_at']->format('M d, Y'));
        $commands[] = $this->text(self::LETTER_PAGE_WIDTH - self::MARGIN - 112, $top - 35, 6.2, 'Period', true);
        $commands[] = $this->text(
            self::LETTER_PAGE_WIDTH - self::MARGIN - 74,
            $top - 35,
            5.7,
            $this->fit($period, 70, 5.7),
        );

        $criteria = collect($report['criteria'] ?? [])
            ->reject(fn (mixed $value, string $label): bool => in_array($label, ['Report Type', 'Grouped By'], true))
            ->map(fn (string $value, string $label): string => $label.': '.$value)
            ->implode(' | ');

        if ($criteria !== '') {
            $commands[] = $this->text(
                self::MARGIN,
                $headerBottom - 14,
                6,
                $this->truncate($criteria, 145),
            );
        }

        return $headerBottom - 28;
    }

    private function pfNumber(mixed $value): string
    {
        return number_format((int) $value);
    }

    private function pfMoney(mixed $cents): string
    {
        return 'PHP '.number_format(((int) $cents) / 100, 2);
    }

    private function filledRect(
        float $x,
        float $y,
        float $width,
        float $height,
        string $rgb,
    ): string {
        return sprintf(
            '%s rg %.2F %.2F %.2F %.2F re f',
            $rgb,
            $x,
            $y,
            $width,
            $height,
        );
    }

    private function centeredText(
        float $x,
        float $y,
        float $width,
        float $size,
        string $text,
        bool $bold = false,
    ): string {
        $fitted = $this->fit($text, $width - 4, $size);
        $approxWidth = strlen($fitted) * $size * 0.48;

        return $this->text(
            $x + max(2, ($width - $approxWidth) / 2),
            $y,
            $size,
            $fitted,
            $bold,
        );
    }

    /**
     * @param array<int, float|int> $widths
     * @param array<int, float|int> $headerLines
     */
    private function tableGrid(
        float $x,
        float $top,
        float $bottom,
        array $widths,
        array $headerLines,
        float $rowHeight,
        int $rowCount,
    ): string {
        $commands = ['0.35 0.40 0.45 RG 0.55 w'];
        $totalWidth = array_sum($widths);
        $commands[] = sprintf('%.2F %.2F %.2F %.2F re S', $x, $bottom, $totalWidth, $top - $bottom);

        $cursorX = $x;
        foreach ($widths as $width) {
            $cursorX += $width;
            if ($cursorX < $x + $totalWidth - 0.1) {
                $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $cursorX, $bottom, $cursorX, $top);
            }
        }

        foreach (array_unique($headerLines) as $lineY) {
            if ($lineY < $top - 0.1 && $lineY > $bottom + 0.1) {
                $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $lineY, $x + $totalWidth, $lineY);
            }
        }

        $bodyTop = min($headerLines);
        for ($i = 1; $i <= $rowCount; $i++) {
            $lineY = $bodyTop - ($rowHeight * $i);
            if ($lineY > $bottom + 0.1) {
                $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $lineY, $x + $totalWidth, $lineY);
            }
        }

        return implode("\n", $commands);
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

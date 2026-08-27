<?php

namespace App\Services\Exports;

use RuntimeException;

final class XlsxTableWriter
{
    public function write(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tupad-report-');

        if ($path === false) {
            throw new RuntimeException('Unable to create the temporary Excel report.');
        }

        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'docProps/app.xml' => $this->appProperties(),
            'docProps/core.xml' => $this->coreProperties($report),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($report),
        ];

        if (file_put_contents($path, $this->zip($files)) === false) {
            @unlink($path);
            throw new RuntimeException('Unable to write the Excel report.');
        }

        return $path;
    }

    /**
     * Create a standards-compliant, uncompressed ZIP container without an
     * optional PHP extension. XLSX readers support the ZIP "store" method.
     *
     * @param array<string, string> $files
     */
    private function zip(array $files): string
    {
        $body = '';
        $directory = '';
        $offset = 0;
        $date = getdate();
        $year = max(1980, (int) $date['year']);
        $dosTime = ((int) $date['hours'] << 11)
            | ((int) $date['minutes'] << 5)
            | intdiv((int) $date['seconds'], 2);
        $dosDate = (($year - 1980) << 9)
            | ((int) $date['mon'] << 5)
            | (int) $date['mday'];

        foreach ($files as $name => $contents) {
            $nameLength = strlen($name);
            $length = strlen($contents);
            $crc = crc32($contents);
            $local = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $length,
                $length,
                $nameLength,
                0,
            ).$name.$contents;

            $directory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $length,
                $length,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset,
            ).$name;
            $body .= $local;
            $offset += strlen($local);
        }

        return $body.$directory.pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($files),
            count($files),
            strlen($directory),
            strlen($body),
            0,
        );
    }

    private function worksheet(array $report): string
    {
        $columns = $report['columns'];
        $columnCount = max(1, count($columns));
        $rows = [];
        $rowNumber = 1;

        $rows[] = $this->row($rowNumber++, [
            $this->inlineCell('A1', (string) $report['title'], 3),
        ]);
        $rows[] = $this->row($rowNumber++, [
            $this->inlineCell(
                'A2',
                'Generated '.$report['generated_at']->format('M d, Y h:i A'),
                5,
            ),
        ]);

        foreach ($report['criteria'] as $label => $value) {
            $rows[] = $this->row($rowNumber, [
                $this->inlineCell('A'.$rowNumber, (string) $label, 2),
                $this->inlineCell('B'.$rowNumber, (string) $value, 5),
            ]);
            $rowNumber++;
        }

        if ($report['warning']) {
            $rows[] = $this->row($rowNumber++, [
                $this->inlineCell(
                    'A'.($rowNumber - 1),
                    'Note: '.$report['warning'],
                    6,
                ),
            ]);
        }

        $rowNumber++;
        $headerRow = $rowNumber;
        $headerCells = [];

        foreach ($columns as $index => $column) {
            $headerCells[] = $this->inlineCell(
                $this->columnName($index + 1).$rowNumber,
                (string) $column['label'],
                2,
            );
        }

        $rows[] = $this->row($rowNumber++, $headerCells);

        foreach ($report['rows'] as $dataRow) {
            $cells = [];

            foreach ($columns as $index => $column) {
                $coordinate = $this->columnName($index + 1).$rowNumber;
                $value = $dataRow[$column['key']] ?? null;
                $format = $column['format'];

                if ($value === null) {
                    $cells[] = $this->inlineCell($coordinate, 'Not allocated', 5);
                } elseif ($format === 'money') {
                    $cells[] = $this->numberCell(
                        $coordinate,
                        $this->centsToDecimal((int) $value),
                        4,
                    );
                } elseif ($format === 'integer') {
                    $cells[] = $this->numberCell($coordinate, (string) ((int) $value), 1);
                } elseif ($format === 'boolean') {
                    $cells[] = $this->inlineCell($coordinate, $value ? 'Yes' : 'No', 5);
                } elseif ($format === 'list') {
                    $cells[] = $this->inlineCell(
                        $coordinate,
                        collect((array) $value)->filter()->implode('; ') ?: '—',
                        5,
                    );
                } else {
                    $cells[] = $this->inlineCell($coordinate, (string) $value, 5);
                }
            }

            $rows[] = $this->row($rowNumber++, $cells);
        }

        $lastRow = max($headerRow, $rowNumber - 1);
        $lastColumn = $this->columnName($columnCount);
        $columnXml = '';

        for ($column = 1; $column <= $columnCount; $column++) {
            $width = $columns[$column - 1]['format'] === 'money' ? 18 : 24;
            $columnXml .= sprintf(
                '<col min="%d" max="%d" width="%d" customWidth="1"/>',
                $column,
                $column,
                $width,
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<cols>'.$columnXml.'</cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .'<autoFilter ref="A'.$headerRow.':'.$lastColumn.$lastRow.'"/>'
            .'</worksheet>';
    }

    private function row(int $number, array $cells): string
    {
        return '<row r="'.$number.'">'.implode('', $cells).'</row>';
    }

    private function inlineCell(string $coordinate, string $value, int $style): string
    {
        return sprintf(
            '<c r="%s" t="inlineStr" s="%d"><is><t xml:space="preserve">%s</t></is></c>',
            $coordinate,
            $style,
            $this->xml($value),
        );
    }

    private function numberCell(string $coordinate, string $value, int $style): string
    {
        return sprintf(
            '<c r="%s" s="%d"><v>%s</v></c>',
            $coordinate,
            $style,
            $value,
        );
    }

    private function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);

        return sprintf(
            '%s%d.%02d',
            $negative ? '-' : '',
            intdiv($absolute, 100),
            $absolute % 100,
        );
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars(
            preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8',
        );
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="TUPAD Report" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF153E75"/><name val="Arial"/></font>'
            .'<font><i/><sz val="9"/><color rgb="FF7F1D1D"/><name val="Arial"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF153E75"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD7DEE8"/></left><right style="thin"><color rgb="FFD7DEE8"/></right><top style="thin"><color rgb="FFD7DEE8"/></top><bottom style="thin"><color rgb="FFD7DEE8"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            .'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>TUPAD Reporting System</Application></Properties>';
    }

    private function coreProperties(array $report): string
    {
        $created = $report['generated_at']->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml((string) $report['title']).'</dc:title>'
            .'<dc:creator>TUPAD Reporting System</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
            .'</cp:coreProperties>';
    }
}

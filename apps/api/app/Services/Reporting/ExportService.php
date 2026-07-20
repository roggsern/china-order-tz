<?php

namespace App\Services\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export report datasets. CSV + XLSX now; PDF reserved for future.
 * Never mutates business data.
 */
class ExportService
{
    /**
     * @param  array{type: string, columns: list<string>, rows: list<array<string, mixed>>, period?: array<string, string>}  $report
     */
    public function export(array $report, string $format): StreamedResponse
    {
        $format = strtolower($format);

        return match ($format) {
            'csv' => $this->toCsv($report),
            'xlsx', 'xls', 'excel' => $this->toXlsx($report),
            'pdf' => throw new \InvalidArgumentException('PDF export is not enabled yet.'),
            default => throw new \InvalidArgumentException("Unsupported export format [{$format}]."),
        };
    }

    /**
     * @param  array{type: string, columns: list<string>, rows: list<array<string, mixed>>}  $report
     */
    private function toCsv(array $report): StreamedResponse
    {
        $filename = $report['type'].'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $report['columns']);
            foreach ($report['rows'] as $row) {
                $line = [];
                foreach ($report['columns'] as $column) {
                    $line[] = $row[$column] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Minimal XLSX via ZipArchive (Office Open XML). No external package required.
     *
     * @param  array{type: string, columns: list<string>, rows: list<array<string, mixed>>}  $report
     */
    private function toXlsx(array $report): StreamedResponse
    {
        $filename = $report['type'].'-'.now()->format('Ymd-His').'.xlsx';
        $binary = $this->buildXlsxBinary($report);

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{columns: list<string>, rows: list<array<string, mixed>>}  $report
     */
    private function buildXlsxBinary(array $report): string
    {
        $sheetRows = [];
        $sheetRows[] = $this->xlsxRow($report['columns'], 1);
        $rowNum = 2;
        foreach ($report['rows'] as $row) {
            $values = [];
            foreach ($report['columns'] as $column) {
                $values[] = (string) ($row[$column] ?? '');
            }
            $sheetRows[] = $this->xlsxRow($values, $rowNum);
            $rowNum++;
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temp file for XLSX export.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to open ZipArchive for XLSX export.');
        }

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $binary = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $binary;
    }

    /** @param  list<string>  $values */
    private function xlsxRow(array $values, int $rowNum): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $col = $this->columnLetter($index);
            $escaped = htmlspecialchars($value, ENT_XML1);
            $cells .= '<c r="'.$col.$rowNum.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
        }

        return '<row r="'.$rowNum.'">'.$cells.'</row>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }
}

<?php

namespace App\Services\Pos\Receipt;

/**
 * Minimal PDF writer for POS receipts (no external PDF package required).
 * Suitable for cashier / customer text receipts; HTML layouts remain primary for branded print.
 */
class SimpleTextPdf
{
    /**
     * @param  list<string>  $lines
     */
    public function render(array $lines, string $title = 'Receipt'): string
    {
        $content = "BT /F1 10 Tf 40 800 Td 14 TL\n";
        $first = true;
        foreach ($lines as $line) {
            $safe = $this->escape(mb_substr($line, 0, 90));
            if ($first) {
                $content .= "({$safe}) Tj\n";
                $first = false;
            } else {
                $content .= "T* ({$safe}) Tj\n";
            }
        }
        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $n = $i + 1;
            $pdf .= "{$n} 0 obj\n{$object}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $count = count($offsets);
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer << /Size '.$count.' /Root 1 0 R /Info << /Title ('.$this->escape($title).") >> >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}

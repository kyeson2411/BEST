<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * Minimal text-only PDF writer (Helvetica, A4). Used by the demo seeder to create a
 * genuine multi-page PDF so page-level citations can be demonstrated end to end.
 */
final class SimplePdf
{
    /**
     * @param list<array{heading:string,paragraphs:list<string>}> $pages
     */
    public static function build(string $title, array $pages): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $next = 5;
        foreach ($pages as $index => $page) {
            $stream = self::pageStream($index === 0 ? $title : null, $page['heading'], $page['paragraphs']);
            $contentId = $next++;
            $pageId = $next++;
            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $kids[] = $pageId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R /Info << /Title (" . self::escape($title) . ") /Producer (Sibol demo seeder) >> >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    /** @param list<string> $paragraphs */
    private static function pageStream(?string $title, string $heading, array $paragraphs): string
    {
        $out = "BT\n/F2 11 Tf 56 800 Td 14 TL\n";
        if ($title !== null) {
            $out .= "/F2 18 Tf (" . self::escape($title) . ") Tj 0 -30 Td\n";
        }
        $out .= "/F2 14 Tf (" . self::escape($heading) . ") Tj 0 -22 Td\n/F1 11 Tf\n";
        foreach ($paragraphs as $para) {
            foreach (self::wrap($para, 92) as $line) {
                $out .= '(' . self::escape($line) . ") Tj T*\n";
            }
            $out .= "T*\n";
        }
        return $out . 'ET';
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && strlen($line . ' ' . $word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }

    private static function escape(string $text): string
    {
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $text = is_string($converted) ? $converted : $text;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }
}

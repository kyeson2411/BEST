<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * Text extraction with page / section / slide metadata, using only parsers that are
 * reliable here: plain text and Markdown natively, PDF via smalot/pdfparser,
 * DOCX and PPTX via ZipArchive + XML. Scanned (image-only) documents yield no text
 * and are reported as such rather than guessed at.
 */
final class Extractor
{
    public const SUPPORTED = ['txt', 'md', 'pdf', 'docx', 'pptx'];

    /**
     * @return list<array{page:int|null,section:string|null,text:string}>
     */
    public static function extract(string $path, string $ext): array
    {
        switch ($ext) {
            case 'txt':
            case 'md':
                return self::text((string) file_get_contents($path), $ext === 'md');
            case 'pdf':
                return self::pdf($path);
            case 'docx':
                return self::docx($path);
            case 'pptx':
                return self::pptx($path);
        }
        throw new ExtractionException(t('This file type cannot be read for the companion yet. It can still be downloaded by students.'), true);
    }

    /** @return list<array{page:int|null,section:string|null,text:string}> */
    public static function text(string $raw, bool $markdown): array
    {
        $raw = self::toUtf8($raw);
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        // Form feeds mark page breaks in some plain-text exports.
        $pages = explode("\f", $raw);
        $units = [];
        foreach ($pages as $pi => $pageText) {
            $page = count($pages) > 1 ? $pi + 1 : null;
            if (!$markdown) {
                $units[] = ['page' => $page, 'section' => null, 'text' => trim($pageText)];
                continue;
            }
            $section = null;
            $buffer = [];
            foreach (explode("\n", $pageText) as $line) {
                if (preg_match('/^\s{0,3}(#{1,4})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                    if (trim(implode("\n", $buffer)) !== '') {
                        $units[] = ['page' => $page, 'section' => $section, 'text' => trim(implode("\n", $buffer))];
                    }
                    $buffer = [];
                    $section = mb_substr(trim($m[2]), 0, 250);
                    continue;
                }
                $buffer[] = $line;
            }
            if (trim(implode("\n", $buffer)) !== '') {
                $units[] = ['page' => $page, 'section' => $section, 'text' => trim(implode("\n", $buffer))];
            }
        }
        return array_values(array_filter($units, static fn ($u) => $u['text'] !== ''));
    }

    /** @return list<array{page:int|null,section:string|null,text:string}> */
    private static function pdf(string $path): array
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new ExtractionException(t('PDF reading is not installed on this server. An administrator needs to run composer install.'));
        }
        try {
            $config = new \Smalot\PdfParser\Config();
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(64 * 1024 * 1024);
            $parser = new \Smalot\PdfParser\Parser([], $config);
            $document = $parser->parseFile($path);
        } catch (\Throwable $e) {
            $message = stripos($e->getMessage(), 'encrypt') !== false || stripos($e->getMessage(), 'secured') !== false
                ? t('This PDF is password-protected or encrypted. Save an unprotected copy and upload it again.')
                : t('This PDF could not be read. Try saving it again as a PDF, or upload a DOCX instead.');
            throw new ExtractionException($message);
        }
        $units = [];
        foreach ($document->getPages() as $i => $page) {
            try {
                $text = self::toUtf8((string) $page->getText());
            } catch (\Throwable $e) {
                $text = '';
            }
            $text = self::tidy($text);
            if ($text !== '') {
                $units[] = ['page' => $i + 1, 'section' => null, 'text' => $text];
            }
        }
        return $units;
    }

    /** @return list<array{page:int|null,section:string|null,text:string}> */
    private static function docx(string $path): array
    {
        $xml = self::zipEntry($path, 'word/document.xml');
        $dom = self::dom($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $units = [];
        $section = null;
        $buffer = [];
        foreach ($xp->query('//w:body//w:p') ?: [] as $p) {
            $text = '';
            foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $p) ?: [] as $node) {
                $text .= $node->localName === 't' ? $node->textContent : ($node->localName === 'tab' ? "\t" : "\n");
            }
            $text = trim($text);
            $style = (string) ($xp->evaluate('string(./w:pPr/w:pStyle/@w:val)', $p));
            $isHeading = $text !== '' && preg_match('/^(Heading[1-4]|Title|Subtitle)$/i', $style);
            if ($isHeading) {
                if ($buffer) {
                    $units[] = ['page' => null, 'section' => $section, 'text' => implode("\n\n", $buffer)];
                }
                $buffer = [];
                $section = mb_substr($text, 0, 250);
                continue;
            }
            if ($text !== '') {
                $isList = $xp->query('./w:pPr/w:numPr', $p)->length > 0;
                $buffer[] = ($isList ? '- ' : '') . $text;
            }
        }
        if ($buffer) {
            $units[] = ['page' => null, 'section' => $section, 'text' => implode("\n\n", $buffer)];
        }
        return $units;
    }

    /** @return list<array{page:int|null,section:string|null,text:string}> */
    private static function pptx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new ExtractionException(t('Reading PowerPoint files needs the PHP zip extension on the server.'));
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ExtractionException(t('The presentation could not be opened. Save it again and re-upload.'));
        }
        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slides[(int) $m[1]] = (string) $zip->getFromIndex($i);
            }
        }
        $zip->close();
        ksort($slides);
        $units = [];
        foreach ($slides as $number => $xml) {
            $dom = self::dom($xml);
            $xp = new \DOMXPath($dom);
            $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $lines = [];
            foreach ($xp->query('//a:p') ?: [] as $p) {
                $line = '';
                foreach ($xp->query('.//a:t', $p) ?: [] as $t) {
                    $line .= $t->textContent;
                }
                if (trim($line) !== '') {
                    $lines[] = trim($line);
                }
            }
            if ($lines) {
                $title = mb_substr($lines[0], 0, 200);
                $units[] = ['page' => $number, 'section' => t('Slide :n', ['n' => $number]) . ': ' . $title, 'text' => implode("\n", $lines)];
            }
        }
        return $units;
    }

    private static function zipEntry(string $path, string $entry): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new ExtractionException(t('Reading Word files needs the PHP zip extension on the server.'));
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ExtractionException(t('The document could not be opened. Save it again and re-upload.'));
        }
        $content = $zip->getFromName($entry);
        $zip->close();
        if ($content === false) {
            throw new ExtractionException(t('The document is missing its main text part.'));
        }
        return (string) $content;
    }

    private static function dom(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET blocks network access; entity substitution is never enabled.
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new ExtractionException(t('The document structure could not be read.'));
        }
        return $dom;
    }

    private static function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        $text = (string) preg_replace('/[ \x{00A0}]{2,}/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        // Join words hyphenated across line breaks.
        $text = (string) preg_replace('/(\p{L})-\n(\p{Ll})/u', '$1$2', $text);
        // Re-join lines that were wrapped mid-sentence by the PDF layout.
        $text = (string) preg_replace('/(?<![.!?:;])\n(?=[\p{Ll}\d(,])/u', ' ', $text);
        return trim($text);
    }

    public static function toUtf8(string $text): string
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            $text = is_string($converted) ? $converted : '';
        }
        return (string) preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]/u', '', $text);
    }
}

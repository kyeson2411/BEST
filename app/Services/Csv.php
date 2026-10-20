<?php

declare(strict_types=1);

namespace Sibol\Services;

/** Reading CSV files that people save from a spreadsheet (the people import, the competency list). */
final class Csv
{
    /**
     * UTF-8 (with or without a byte order mark) or Windows-1252, commas or semicolons, and a first row naming the
     * columns, matched against $aliases. Blank lines are skipped.
     *
     * @param array<string,list<string>> $aliases column key => header names it may have (lower case)
     * @param list<string> $required keys the header must have
     * @param string $noRows the message when there is a header but nothing under it
     * @return array{0:array<string,int>,1:list<array<int,string>>}|string the column positions and the rows, or a message
     */
    public static function read(string $content, array $aliases, array $required, int $maxRows, string $noRows)
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? '';
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!$headerRow) {
            return t('The file is empty.');
        }
        $header = [];
        foreach ($headerRow as $i => $name) {
            $name = mb_strtolower(trim((string) $name));
            foreach ($aliases as $key => $names) {
                if (in_array($name, $names, true) && !isset($header[$key])) {
                    $header[$key] = $i;
                }
            }
        }
        $missing = array_diff($required, array_keys($header));
        if ($missing) {
            return t('The first row must name the columns. Missing: :cols. Download the template to see the expected layout.', ['cols' => implode(', ', $missing)]);
        }
        $lines = [];
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === [null] || implode('', array_map('trim', array_map('strval', $line))) === '') {
                continue;
            }
            $lines[] = $line;
            if (count($lines) > $maxRows) {
                return t('The file has more than :n rows. Split it into smaller files.', ['n' => $maxRows]);
            }
        }
        fclose($handle);
        if (!$lines) {
            return $noRows;
        }
        return [$header, $lines];
    }
}

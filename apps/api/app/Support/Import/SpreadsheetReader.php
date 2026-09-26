<?php

namespace App\Support\Import;

use RuntimeException;
use ZipArchive;

/**
 * Reads the first sheet of an .xlsx file, or a CSV (UTF-8 with or without BOM, or Windows-1256;
 * comma, semicolon or tab), into rows of trimmed strings. No library: .xlsx is a zip of XML.
 * Guards: row/column caps and an uncompressed-size cap against zip bombs. PHP 8 never resolves
 * external XML entities, so the XML parsing is safe from XXE.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 3000;

    public const MAX_COLS = 30;

    private const MAX_XML_BYTES = 25 * 1024 * 1024;

    /** @return list<list<string>> */
    public static function read(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'xlsx' ? self::xlsx($path) : self::csv($path);

        // Drop fully empty rows; pad nothing (callers index by column).
        return array_values(array_filter($rows, fn (array $r) => implode('', $r) !== ''));
    }

    /** @return list<list<string>> */
    private static function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @iconv('Windows-1256', 'UTF-8//IGNORE', $raw);
            $raw = $converted === false ? $raw : $converted;
        }

        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $d) => substr_count($firstLine, $d))->first() ?? ',';

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Could not read the file.');
        }
        fwrite($stream, $raw);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false && count($rows) < self::MAX_ROWS + 1) {
            $rows[] = array_map(fn ($v) => trim((string) $v), array_slice($row, 0, self::MAX_COLS));
        }
        fclose($stream);

        return $rows;
    }

    /** @return list<list<string>> */
    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Not an .xlsx file.');
        }

        try {
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            if ($total > self::MAX_XML_BYTES) {
                throw new RuntimeException('The file is too large once unpacked.');
            }

            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($sharedXml)) {
                $doc = simplexml_load_string($sharedXml, options: LIBXML_NONET);
                foreach ($doc === false ? [] : $doc->si as $si) {
                    // Rich text is split into runs: join every <t>.
                    $shared[] = trim(implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: [])));
                }
            }

            $sheetXml = $zip->getFromName(self::firstSheet($zip));
            if (! is_string($sheetXml)) {
                throw new RuntimeException('The workbook has no sheet.');
            }
            $sheet = simplexml_load_string($sheetXml, options: LIBXML_NONET);
            if ($sheet === false) {
                throw new RuntimeException('The sheet could not be read.');
            }

            $rows = [];
            foreach ($sheet->sheetData->row ?? [] as $row) {
                if (count($rows) > self::MAX_ROWS) {
                    break;
                }
                $cells = [];
                $next = 0;
                foreach ($row->c as $c) {
                    // Some writers omit the cell reference: then cells simply follow each other.
                    $col = isset($c['r']) ? self::columnIndex((string) $c['r']) : $next;
                    if ($col === null || $col >= self::MAX_COLS) {
                        continue;
                    }
                    $next = $col + 1;
                    $type = (string) $c['t'];
                    $value = match ($type) {
                        's' => $shared[(int) $c->v] ?? '',
                        'inlineStr' => trim(implode('', array_map('strval', $c->is->xpath('.//*[local-name()="t"]') ?: []))),
                        'b' => ((string) $c->v) === '1' ? '1' : '0',
                        default => trim((string) $c->v),
                    };
                    $cells[$col] = $value;
                }
                if ($cells !== []) {
                    $line = array_fill(0, max(array_keys($cells)) + 1, '');
                    foreach ($cells as $i => $v) {
                        $line[$i] = $v;
                    }
                    $rows[] = $line;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** The path of the workbook's first sheet (usually xl/worksheets/sheet1.xml). */
    private static function firstSheet(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (is_string($workbook) && is_string($rels) && preg_match('/<(?:\w+:)?sheet\b[^>]*\br:id="([^"]+)"/', $workbook, $m)
            && preg_match('/<Relationship\b[^>]*Id="'.preg_quote($m[1], '/').'"[^>]*Target="([^"]+)"/', $rels, $t)) {
            $target = ltrim($t[1], '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** "B12" → 1 */
    private static function columnIndex(string $ref): ?int
    {
        if (preg_match('/^([A-Z]+)\d*$/', strtoupper($ref), $m) !== 1) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}

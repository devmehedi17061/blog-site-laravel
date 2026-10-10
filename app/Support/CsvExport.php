<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A CSV download, streamed row by row so a long list never has to fit in
 * memory. Starts with a UTF-8 byte-order mark: without it Excel reads
 * Bangla names and the Taka sign as gibberish.
 */
class CsvExport
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $name, array $headings, iterable $rows): StreamedResponse
    {
        $file = $name.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);

            foreach ($rows as $row) {
                // A cell starting with = + - @ would run as a formula in Excel.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));
            }

            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

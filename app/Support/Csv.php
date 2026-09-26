<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /** Stream a CSV. Cells starting with = + - @ are prefixed to prevent spreadsheet formula injection. */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], (array) $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function safe(mixed $v): string
    {
        $s = (string) $v;

        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($s) ? "'".$s : $s;
    }
}

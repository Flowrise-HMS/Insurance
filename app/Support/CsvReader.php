<?php

namespace Modules\Insurance\Support;

use RuntimeException;

class CsvReader
{
    /**
     * Read a CSV file into an array of header-keyed rows.
     *
     * @return array<int, array<string, string>>
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("CSV file [{$path}] does not exist or is not readable.");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file [{$path}].");
        }

        $rows = [];
        $headers = [];

        try {
            while (($line = fgetcsv($handle)) !== false) {
                if (empty($headers)) {
                    $headers = $this->normalizeHeaders($line);

                    continue;
                }

                if (count($line) < 1 || (count($line) === 1 && trim((string) $line[0]) === '')) {
                    continue;
                }

                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = trim((string) ($line[$index] ?? ''));
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $line
     * @return array<int, string>
     */
    protected function normalizeHeaders(array $line): array
    {
        return array_map(
            fn (mixed $header): string => strtolower((string) preg_replace('/[^A-Za-z0-9_]/', '_', trim((string) $header))),
            $line
        );
    }
}

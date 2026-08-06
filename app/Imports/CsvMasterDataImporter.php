<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Contracts\MasterDataImporter;
use Modules\Insurance\DTOs\ImportResult;
use Modules\Insurance\Support\CsvReader;

abstract class CsvMasterDataImporter implements MasterDataImporter
{
    public function __construct(
        protected CsvReader $reader,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function import(string $path, array $options = []): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($this->reader->read($path) as $index => $row) {
            try {
                $outcome = $this->upsert($row, $options);

                if (isset($outcome['error'])) {
                    $skipped++;
                    $errors[] = 'Row '.($index + 2).": {$outcome['error']}";
                } elseif ($outcome['created']) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = 'Row '.($index + 2).": {$e->getMessage()}";
            }
        }

        return new ImportResult(created: $created, updated: $updated, skipped: $skipped, errors: $errors);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array{created?: bool, error?: string}
     */
    abstract protected function upsert(array $row, array $options): array;
}

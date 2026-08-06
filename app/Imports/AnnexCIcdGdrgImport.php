<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Models\GdrgIcdMap;

class AnnexCIcdGdrgImport extends CsvMasterDataImporter
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array{created?: bool, error?: string}
     */
    protected function upsert(array $row, array $options): array
    {
        $icd10Code = trim((string) ($row['icd10_code'] ?? ''));
        $gdrgCode = trim((string) ($row['gdrg_code'] ?? ''));
        $serviceType = strtoupper(trim((string) ($row['service_type'] ?? 'OUT')));

        if ($icd10Code === '' || $gdrgCode === '') {
            return ['error' => 'Both icd10_code and gdrg_code are required.'];
        }

        $map = GdrgIcdMap::query()->updateOrCreate(
            ['icd10_code' => $icd10Code, 'gdrg_code' => $gdrgCode, 'service_type' => $serviceType],
            [
                'description' => $this->nullable($row['description'] ?? ''),
                'mdc' => $this->nullable($row['mdc'] ?? ''),
                'notes' => $this->nullable($row['notes'] ?? ''),
                'is_active' => in_array(strtolower($row['is_active'] ?? '1'), ['1', 'true', 'yes', 'active'], true),
                'source_file' => $options['source_file'] ?? null,
                'imported_at' => now(),
            ]
        );

        return ['created' => $map->wasRecentlyCreated];
    }

    protected function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}

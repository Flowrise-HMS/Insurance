<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Models\NhisMedicine;

class MedicineCatalogImport extends CsvMasterDataImporter
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array{created?: bool, error?: string}
     */
    protected function upsert(array $row, array $options): array
    {
        $code = trim((string) ($row['code'] ?? ''));

        if ($code === '') {
            return ['error' => 'Missing medicine code.'];
        }

        $attributes = [
            'name' => trim((string) ($row['name'] ?? '')),
            'strength' => $this->nullable($row['strength'] ?? ''),
            'form' => $this->nullable($row['form'] ?? ''),
            'prescribing_level' => max(1, min(3, (int) ($row['prescribing_level'] ?? 1))),
            'effective_from' => $this->nullableDate($row['effective_from'] ?? ''),
            'effective_to' => $this->nullableDate($row['effective_to'] ?? ''),
            'is_active' => $this->toBool($row['is_active'] ?? '1'),
            'source_file' => $options['source_file'] ?? null,
            'imported_at' => now(),
        ];

        if ($attributes['name'] === '') {
            return ['error' => "Medicine [{$code}] missing name."];
        }

        $medicine = NhisMedicine::query()->updateOrCreate(['code' => $code], $attributes);

        return ['created' => $medicine->wasRecentlyCreated];
    }

    protected function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    protected function nullableDate(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    protected function toBool(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'active'], true);
    }
}

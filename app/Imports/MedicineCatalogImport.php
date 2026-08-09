<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Enums\NhisPrescribingLevel;
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

        $level = NhisPrescribingLevel::resolve(
            $row['prescribing_level_code'] ?? null,
            $row['prescribing_level'] ?? null,
        );

        if ($level === null) {
            return ['error' => "Medicine [{$code}] has an invalid prescribing level."];
        }

        $attributes = [
            'name' => trim((string) ($row['name'] ?? '')),
            'strength' => $this->nullable($row['strength'] ?? ''),
            'form' => $this->nullable($row['form'] ?? ''),
            'prescribing_level_code' => $level['code'],
            'prescribing_level' => $level['ordinal'],
            'unit_of_pricing' => $this->nullable($row['unit_of_pricing'] ?? ''),
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

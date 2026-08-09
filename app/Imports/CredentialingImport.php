<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Models\ProviderCredentialing;

class CredentialingImport extends CsvMasterDataImporter
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array{created?: bool, error?: string}
     */
    protected function upsert(array $row, array $options): array
    {
        $staffId = $this->nullableNumeric($row['staff_id'] ?? '');
        $providerName = trim((string) ($row['provider_name'] ?? ''));

        if ($staffId === null && $providerName === '') {
            return ['error' => 'Either staff_id or provider_name is required.'];
        }

        $level = NhisPrescribingLevel::resolve(
            $row['prescribing_level_code'] ?? null,
            $row['prescribing_level'] ?? 'A',
        );

        if ($level === null) {
            return ['error' => 'Invalid prescribing level.'];
        }

        $specialities = array_values(array_filter(array_map(
            fn (string $value): string => strtoupper(trim($value)),
            preg_split('/[;,|]+/', (string) ($row['specialities'] ?? '')) ?: []
        ), fn (string $value): bool => $value !== ''));

        $attributes = [
            'provider_name' => $providerName !== '' ? $providerName : null,
            'prescribing_level_code' => $level['code'],
            'prescribing_level' => $level['ordinal'],
            'specialities' => $specialities,
            'accreditation_number' => $this->nullable($row['accreditation_number'] ?? ''),
            'level_of_care' => $this->nullable($row['level_of_care'] ?? ''),
            'valid_from' => $this->nullable($row['valid_from'] ?? ''),
            'valid_to' => $this->nullable($row['valid_to'] ?? ''),
            'is_active' => in_array(strtolower($row['is_active'] ?? '1'), ['1', 'true', 'yes', 'active'], true),
            'source_file' => $options['source_file'] ?? null,
            'imported_at' => now(),
        ];

        $query = $staffId !== null
            ? ProviderCredentialing::query()->where('staff_id', $staffId)
            : ProviderCredentialing::query()->whereNull('staff_id')->where('provider_name', $providerName);

        $credential = $query->first();

        if ($credential) {
            $credential->update($attributes);

            return ['created' => false];
        }

        ProviderCredentialing::query()->create([
            'staff_id' => $staffId,
            ...$attributes,
        ]);

        return ['created' => true];
    }

    protected function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    protected function nullableNumeric(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }
}

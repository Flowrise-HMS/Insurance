<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Models\MembersMaster;

class MembersMasterImport extends CsvMasterDataImporter
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array{created?: bool, error?: string}
     */
    protected function upsert(array $row, array $options): array
    {
        $memberNumber = trim((string) ($row['member_number'] ?? ''));
        $cardSerial = trim((string) ($row['card_serial_number'] ?? ''));

        if ($memberNumber === '' || $cardSerial === '') {
            return ['error' => 'Both member_number and card_serial_number are required.'];
        }

        $attributes = [
            'first_name' => $this->nullable($row['first_name'] ?? ''),
            'last_name' => $this->nullable($row['last_name'] ?? ''),
            'date_of_birth' => $this->nullable($row['date_of_birth'] ?? ''),
            'gender' => $this->nullable($row['gender'] ?? ''),
            'valid_from' => $this->nullable($row['valid_from'] ?? ''),
            'valid_to' => $this->nullable($row['valid_to'] ?? ''),
            'is_active' => in_array(strtolower($row['is_active'] ?? '1'), ['1', 'true', 'yes', 'active'], true),
            'source_file' => $options['source_file'] ?? null,
            'imported_at' => now(),
        ];

        $member = MembersMaster::query()->updateOrCreate(
            ['member_number' => $memberNumber, 'card_serial_number' => $cardSerial],
            $attributes
        );

        return ['created' => $member->wasRecentlyCreated];
    }

    protected function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}

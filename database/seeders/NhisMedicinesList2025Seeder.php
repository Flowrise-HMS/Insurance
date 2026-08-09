<?php

namespace Modules\Insurance\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\NhisMedicine;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffBook;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Settings\InsuranceSettings;

class NhisMedicinesList2025Seeder extends Seeder
{
    public const CSV_RELATIVE_PATH = 'database/data/nhis_medicines_list_2025.csv';

    public const TARIFF_BOOK_CODE = 'nhis-ml-2025';

    public const SOURCE_FILE = '2025 NHIS ML.pdf';

    public const EFFECTIVE_FROM = '2025-03-01';

    public function run(): void
    {
        $payer = Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            [
                'name' => 'National Health Insurance Scheme (NHIS)',
                'type' => PayerType::NHIS,
                'is_active' => true,
                'config' => [
                    'xml_version' => config('insurance.nhis.xml_version', '8.6'),
                ],
            ]
        );

        $book = TariffBook::query()->updateOrCreate(
            ['code' => self::TARIFF_BOOK_CODE],
            [
                'name' => 'NHIS Medicines List 2025',
                'effective_from' => self::EFFECTIVE_FROM,
                'effective_to' => null,
                'is_active' => true,
            ]
        );

        $path = $this->csvPath();

        if (! File::exists($path)) {
            throw new \RuntimeException("NHIS medicines CSV missing at [{$path}].");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open NHIS medicines CSV at [{$path}].");
        }

        try {
            $headers = fgetcsv($handle);
            if (! is_array($headers)) {
                throw new \RuntimeException('NHIS medicines CSV has no header row.');
            }

            $headers = array_map(static fn ($header): string => trim((string) $header), $headers);
            $count = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                /** @var array<string, string> $data */
                $data = [];
                foreach ($headers as $index => $header) {
                    $data[$header] = trim((string) ($row[$index] ?? ''));
                }

                if (($data['code'] ?? '') === '') {
                    continue;
                }

                $level = NhisPrescribingLevel::resolve(
                    $data['prescribing_level_code'] ?? null,
                    $data['prescribing_level'] ?? null,
                );

                if ($level === null) {
                    throw new \RuntimeException("Invalid prescribing level for medicine [{$data['code']}].");
                }

                $fullName = trim(implode(', ', array_filter([
                    trim(($data['name'] ?? '').' '.($data['form'] ?? '')),
                    $data['strength'] ?? '',
                ])));

                NhisMedicine::query()->updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'] !== '' ? $data['name'] : $fullName,
                        'strength' => $data['strength'] !== '' ? $data['strength'] : null,
                        'form' => $data['form'] !== '' ? $data['form'] : null,
                        'prescribing_level_code' => $level['code'],
                        'prescribing_level' => $level['ordinal'],
                        'unit_of_pricing' => $data['unit_of_pricing'] !== '' ? $data['unit_of_pricing'] : null,
                        'effective_from' => $data['effective_from'] !== '' ? $data['effective_from'] : self::EFFECTIVE_FROM,
                        'effective_to' => null,
                        'is_active' => in_array(strtolower($data['is_active'] ?? '1'), ['1', 'true', 'yes', 'active'], true),
                        'source_file' => self::SOURCE_FILE,
                        'imported_at' => now(),
                    ]
                );

                $displayName = $fullName !== '' ? $fullName : $data['name'];

                TariffItem::query()->updateOrCreate(
                    [
                        'payer_id' => $payer->id,
                        'item_type' => 'medication',
                        'external_code' => $data['code'],
                    ],
                    [
                        'tariff_book_id' => $book->id,
                        'name' => $displayName,
                        'price' => (string) max(0, (float) ($data['price'] ?? 0)),
                        'currency' => 'GHS',
                        'effective_from' => self::EFFECTIVE_FROM,
                        'effective_to' => null,
                        'is_active' => true,
                        'source_version' => '2025',
                        'source_updated_at' => now(),
                        'metadata' => [
                            'unit_of_pricing' => $data['unit_of_pricing'] !== '' ? $data['unit_of_pricing'] : null,
                            'prescribing_level_code' => $level['code'],
                            'catalog' => 'nhis-ml-2025',
                            'source_file' => self::SOURCE_FILE,
                        ],
                    ]
                );

                $count++;
            }
        } finally {
            fclose($handle);
        }

        if ($count !== 551) {
            throw new \RuntimeException("Expected to seed 551 medicines, seeded {$count}.");
        }

        $this->updateMedicineVersion();

        $this->command?->info("Seeded {$count} NHIS medicines and medication tariffs (book ".self::TARIFF_BOOK_CODE.').');
    }

    protected function csvPath(): string
    {
        return module_path('Insurance', self::CSV_RELATIVE_PATH);
    }

    protected function updateMedicineVersion(): void
    {
        try {
            $settings = app(InsuranceSettings::class);
            $versions = $settings->master_table_versions;
            $versions['MedicineVersion'] = '2025';
            $versions['TariffVersion'] = $versions['TariffVersion'] ?? '2025';
            $settings->master_table_versions = $versions;
            $settings->save();
        } catch (\Throwable) {
            // Settings table may be unavailable in isolated module tests.
        }
    }
}

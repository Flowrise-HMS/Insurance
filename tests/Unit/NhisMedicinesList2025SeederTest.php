<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Seeders\NhisMedicinesList2025Seeder;
use Modules\Insurance\Models\NhisMedicine;
use Modules\Insurance\Models\TariffBook;
use Modules\Insurance\Models\TariffItem;
use Tests\TestCase;

class NhisMedicinesList2025SeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_csv_fixture_has_expected_spot_checks(): void
    {
        $path = module_path('Insurance', NhisMedicinesList2025Seeder::CSV_RELATIVE_PATH);
        $this->assertFileExists($path);

        $handle = fopen($path, 'r');
        $this->assertNotFalse($handle);
        $headers = fgetcsv($handle);
        $this->assertIsArray($headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $data = array_combine($headers, $row);
            $rows[$data['code']] = $data;
        }
        fclose($handle);

        $this->assertCount(551, $rows);
        $this->assertSame('0.12', $rows['PARACETA1']['price']);
        $this->assertSame('A', $rows['PARACETA1']['prescribing_level_code']);
        $this->assertSame('Tablet', $rows['PARACETA1']['unit_of_pricing']);
        $this->assertSame('5.15', $rows['AMOARTTA1']['price']);
        $this->assertSame('1 Course', $rows['AMOARTTA1']['unit_of_pricing']);
        $this->assertSame('14.47', $rows['5FLUORIN1']['price']);
    }

    public function test_seeder_is_idempotent_and_loads_catalog_with_tariffs(): void
    {
        $seeder = app(NhisMedicinesList2025Seeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertSame(551, NhisMedicine::query()->count());
        $this->assertSame(551, TariffItem::query()->where('item_type', 'medication')->count());
        $this->assertTrue(TariffBook::query()->where('code', NhisMedicinesList2025Seeder::TARIFF_BOOK_CODE)->exists());

        $paracetamol = NhisMedicine::query()->where('code', 'PARACETA1')->first();
        $this->assertNotNull($paracetamol);
        $this->assertSame('A', $paracetamol->prescribing_level_code);
        $this->assertSame(1, $paracetamol->prescribing_level);
        $this->assertSame('Tablet', $paracetamol->unit_of_pricing);

        $tariff = TariffItem::query()
            ->where('item_type', 'medication')
            ->where('external_code', 'PARACETA1')
            ->first();
        $this->assertNotNull($tariff);
        $this->assertSame('0.12', (string) $tariff->price);
        $this->assertSame('Tablet', data_get($tariff->metadata, 'unit_of_pricing'));
    }
}

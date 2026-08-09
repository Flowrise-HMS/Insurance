<?php

namespace Modules\Insurance\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Imports\AnnexCIcdGdrgImport;
use Modules\Insurance\Imports\CredentialingImport;
use Modules\Insurance\Imports\MedicineCatalogImport;
use Modules\Insurance\Imports\MembersMasterImport;
use Modules\Insurance\Imports\TariffBookImport;
use Modules\Insurance\Models\GdrgIcdMap;
use Modules\Insurance\Models\MembersMaster;
use Modules\Insurance\Models\NhisMedicine;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\ProviderCredentialing;
use Modules\Insurance\Models\TariffBook;
use Modules\Insurance\Models\TariffItem;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_medicine_import_is_idempotent(): void
    {
        $path = $this->writeCsv([
            ['code', 'name', 'strength', 'form', 'prescribing_level'],
            ['MED001', 'Paracetamol', '500mg', 'Tablet', '1'],
            ['MED002', 'Amoxicillin', '250mg', 'Capsule', '2'],
        ]);

        $importer = app(MedicineCatalogImport::class);
        $first = $importer->import($path);
        $second = $importer->import($path);

        $this->assertSame(2, $first->created);
        $this->assertSame(0, $first->updated);
        $this->assertSame(0, $second->created);
        $this->assertSame(2, $second->updated);
        $this->assertSame(2, NhisMedicine::query()->count());
        $medicine = NhisMedicine::query()->where('code', 'MED001')->first();
        $this->assertSame(1, $medicine->prescribing_level);
        $this->assertSame('A', $medicine->prescribing_level_code);
    }

    public function test_medicine_import_accepts_official_level_codes_and_skips_invalid_rows(): void
    {
        $path = $this->writeCsv([
            ['code', 'name', 'prescribing_level_code'],
            ['MED003', 'Specialist Drug', 'SM'],
            ['MED004', 'Bad Level', 'ZZ'],
            ['', 'Missing Code', 'A'],
        ]);

        $result = app(MedicineCatalogImport::class)->import($path);

        $this->assertSame(1, $result->created);
        $this->assertSame(2, $result->skipped);
        $medicine = NhisMedicine::query()->where('code', 'MED003')->first();
        $this->assertSame('SM', $medicine->prescribing_level_code);
        $this->assertSame(7, $medicine->prescribing_level);
    }

    public function test_members_master_import_upserts_on_member_and_card_pair(): void
    {
        $path = $this->writeCsv([
            ['member_number', 'card_serial_number', 'first_name', 'last_name'],
            ['12345678', 'UWJPL120A0093', 'John', 'Doe'],
        ]);

        $importer = app(MembersMasterImport::class);
        $importer->import($path);
        $importer->import($path);

        $this->assertSame(1, MembersMaster::query()->count());
        $this->assertSame('John', MembersMaster::query()->first()->first_name);
    }

    public function test_members_master_import_skips_rows_missing_identity_pair(): void
    {
        $path = $this->writeCsv([
            ['member_number', 'card_serial_number'],
            ['12345678', ''],
        ]);

        $result = app(MembersMasterImport::class)->import($path);

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, MembersMaster::query()->count());
    }

    public function test_annex_c_import_upserts_icd_gdrg_maps(): void
    {
        $path = $this->writeCsv([
            ['icd10_code', 'gdrg_code', 'service_type', 'mdc'],
            ['A00.0', 'GDRG001', 'OUT', '01'],
        ]);

        $importer = app(AnnexCIcdGdrgImport::class);
        $importer->import($path);
        $importer->import($path);

        $this->assertSame(1, GdrgIcdMap::query()->count());
        $this->assertSame('01', GdrgIcdMap::query()->first()->mdc);
    }

    public function test_credentialing_import_upserts_by_staff_id(): void
    {
        User::factory()->create(['id' => 42]);

        $path = $this->writeCsv([
            ['staff_id', 'provider_name', 'prescribing_level', 'specialities'],
            ['42', 'Dr. Ama', '2', 'OPDC;ORTH'],
        ]);

        $importer = app(CredentialingImport::class);
        $first = $importer->import($path);
        $second = $importer->import($path);

        $this->assertSame(1, $first->created);
        $this->assertSame(1, $second->updated);
        $this->assertSame(1, ProviderCredentialing::query()->count());
        $credential = ProviderCredentialing::query()->first();
        $this->assertSame(['OPDC', 'ORTH'], $credential->specialities);
        $this->assertSame('M', $credential->prescribing_level_code);
        $this->assertSame(2, $credential->prescribing_level);
    }

    public function test_tariff_book_import_seeds_book_and_items(): void
    {
        $payer = Payer::factory()->create(['code' => 'nhis']);
        TariffBook::query()->create(['code' => 'CHAG', 'name' => 'CHAG Health Centre', 'is_active' => true]);

        $path = $this->writeCsv([
            ['code', 'name', 'price', 'item_type', 'admission_type'],
            ['GDRG001', 'Medical Admission', '120.00', 'service', 'OUT'],
            ['GDRG002', 'Surgical Admission', '250.00', 'service', 'INP'],
        ]);

        $importer = app(TariffBookImport::class);
        $result = $importer->import($path, ['book_code' => 'CHAG', 'payer' => 'nhis']);

        $this->assertSame(2, $result->created);
        $this->assertSame(2, TariffItem::query()->where('tariff_book_id', TariffBook::query()->where('code', 'CHAG')->value('id'))->count());
        $this->assertSame($payer->id, TariffItem::query()->where('external_code', 'GDRG001')->value('payer_id'));
    }

    protected function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nhis_import_').'.csv';

        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}

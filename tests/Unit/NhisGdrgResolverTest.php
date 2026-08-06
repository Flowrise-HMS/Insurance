<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\GdrgIcdMapFactory;
use Modules\Insurance\Database\Factories\TariffItemFactory;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Schemes\Nhis\NhisGdrgResolver;
use Tests\TestCase;

class NhisGdrgResolverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_resolves_gdrg_code_from_annex_c_map(): void
    {
        GdrgIcdMapFactory::new()->create([
            'icd10_code' => 'A00.0',
            'gdrg_code' => 'GDRG001',
            'service_type' => 'OUT',
        ]);

        $code = app(NhisGdrgResolver::class)->mapGdrgCode('A00.0', 'OUT');

        $this->assertSame('GDRG001', $code);
    }

    public function test_map_returns_null_for_unknown_icd(): void
    {
        $code = app(NhisGdrgResolver::class)->mapGdrgCode('Z99.9', 'OUT');

        $this->assertNull($code);
    }

    public function test_resolve_returns_gdrg_and_priced_tariff(): void
    {
        $payer = Payer::factory()->create(['code' => 'nhis', 'type' => PayerType::NHIS]);

        GdrgIcdMapFactory::new()->create([
            'icd10_code' => 'A00.0',
            'gdrg_code' => 'GDRG001',
            'service_type' => 'OUT',
        ]);

        TariffItemFactory::new()->create([
            'payer_id' => $payer->id,
            'item_type' => 'service',
            'external_code' => 'GDRG001',
            'price' => 120.00,
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->addMonth(),
            'admission_type' => 'OUT',
        ]);

        $result = app(NhisGdrgResolver::class)->resolve($payer, 'A00.0', 'OUT', now()->toDateString());

        $this->assertSame('GDRG001', $result['code']);
        $this->assertSame('120.00', $result['tariff']);
    }

    public function test_resolve_ignores_tariff_outside_effective_window(): void
    {
        $payer = Payer::factory()->create(['code' => 'nhis', 'type' => PayerType::NHIS]);

        GdrgIcdMapFactory::new()->create([
            'icd10_code' => 'A00.0',
            'gdrg_code' => 'GDRG001',
            'service_type' => 'OUT',
        ]);

        TariffItemFactory::new()->create([
            'payer_id' => $payer->id,
            'item_type' => 'service',
            'external_code' => 'GDRG001',
            'price' => 120.00,
            'effective_from' => now()->subMonths(6)->toDateString(),
            'effective_to' => now()->subMonths(5)->toDateString(),
        ]);

        $result = app(NhisGdrgResolver::class)->resolve($payer, 'A00.0', 'OUT', now()->toDateString());

        $this->assertSame('GDRG001', $result['code']);
        $this->assertSame('0.00', $result['tariff']);
    }
}

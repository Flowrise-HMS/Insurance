<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Service;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Services\DefaultInsurancePricingService;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class DefaultInsurancePricingServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected DefaultInsurancePricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);

        $this->service = new DefaultInsurancePricingService;
    }

    public function test_resolves_nhis_code_from_service_metadata(): void
    {
        $payer = Payer::factory()->create(['type' => 'nhis']);
        $patient = Patient::factory()->create();
        $svc = Service::factory()->create([
            'price' => 200,
            'metadata' => ['nhis_code' => 'CONS01'],
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'is_active' => true,
        ]);

        TariffItem::factory()->create([
            'payer_id' => $payer->id,
            'external_code' => 'CONS01',
            'item_type' => 'service',
            'price' => 150.00,
            'is_active' => true,
        ]);

        $result = $this->service->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $svc->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('150.00', $result['insurer_amount']);
        $this->assertEquals('50.00', $result['patient_amount']);
        $this->assertEquals('150.00', $result['tariff_price']);
    }

    public function test_returns_zero_when_no_tariff_found(): void
    {
        $payer = Payer::factory()->create(['type' => 'nhis']);
        $patient = Patient::factory()->create();
        $svc = Service::factory()->create([
            'price' => 200,
            'metadata' => ['nhis_code' => 'CONS99'],
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'is_active' => true,
        ]);

        $result = $this->service->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $svc->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('0.00', $result['insurer_amount']);
        $this->assertEquals('200.00', $result['patient_amount']);
        $this->assertNull($result['tariff_price']);
    }

    public function test_falls_back_to_raw_external_code_when_no_nhis_code(): void
    {
        $payer = Payer::factory()->create(['type' => 'nhis']);
        $patient = Patient::factory()->create();
        $svc = Service::factory()->create([
            'price' => 200,
            'metadata' => [],
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'is_active' => true,
        ]);

        TariffItem::factory()->create([
            'payer_id' => $payer->id,
            'external_code' => (string) $svc->id,
            'item_type' => 'service',
            'price' => 100.00,
            'is_active' => true,
        ]);

        $result = $this->service->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $svc->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('100.00', $result['insurer_amount']);
    }

    public function test_non_service_item_type_unchanged(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->service->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'medication',
            externalCode: 'some-code',
            fallbackAmount: '100.00',
        );

        $this->assertEquals('0.00', $result['insurer_amount']);
        $this->assertEquals('100.00', $result['patient_amount']);
    }

    public function test_returns_zero_when_no_active_policy(): void
    {
        $patient = Patient::factory()->create();
        $svc = Service::factory()->create([
            'metadata' => ['nhis_code' => 'CONS01'],
        ]);

        $result = $this->service->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $svc->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('0.00', $result['insurer_amount']);
        $this->assertEquals('200.00', $result['patient_amount']);
    }
}

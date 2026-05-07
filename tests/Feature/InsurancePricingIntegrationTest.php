<?php

namespace Modules\Insurance\Tests\Feature;

use Modules\Core\Contracts\InsurancePricingResolver;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class InsurancePricingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Patient', 'Clinical', 'Appointment', 'Billing', 'Insurance'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_pricing_resolver_applies_insurer_tariff_and_patient_copay(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
        ]));

        $payer = Payer::query()->create([
            'code' => 'nhis',
            'name' => 'NHIS',
            'type' => PayerType::Nhis,
            'is_active' => true,
        ]);

        $policy = PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => 'NHIS-12345',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
        ]);

        TariffItem::query()->create([
            'payer_id' => $payer->id,
            'item_type' => 'service',
            'external_code' => 'svc-001',
            'name' => 'Consultation',
            'price' => '35.00',
            'currency' => 'GHS',
            'source_version' => 'v8.6',
            'is_active' => true,
        ]);

        $resolved = app(InsurancePricingResolver::class)->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: 'svc-001',
            fallbackAmount: '50.00'
        );

        $this->assertSame('35.00', $resolved['insurer_amount']);
        $this->assertSame('15.00', $resolved['patient_amount']);
        $this->assertSame((string) $policy->id, (string) $resolved['policy_id']);
        $this->assertSame((string) $payer->id, (string) $resolved['payer_id']);
    }
}

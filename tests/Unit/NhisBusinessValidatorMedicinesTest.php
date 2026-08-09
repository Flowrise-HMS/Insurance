<?php

namespace Modules\Insurance\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimLineFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\NhisMedicine;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\ProviderCredentialing;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Insurance\Validation\NhisBusinessValidator;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class NhisBusinessValidatorMedicinesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Insurance']);

        $settings = app(InsuranceSettings::class);
        $settings->member_verification_mode = 'disabled';
        $settings->enable_prescribing_level_warning = true;
        $settings->save();
    }

    public function test_medicine_code_missing_from_catalog_returns_281(): void
    {
        $claim = $this->makeMedicineClaim('UNKNOWN01');

        $items = app(NhisBusinessValidator::class)->validate($claim);
        $codes = collect($items)->pluck('code')->all();

        $this->assertContains('281', $codes);
    }

    public function test_seeded_medicine_code_passes_281(): void
    {
        NhisMedicine::factory()->create([
            'code' => 'PARACETA1',
            'prescribing_level_code' => NhisPrescribingLevel::A->value,
            'prescribing_level' => NhisPrescribingLevel::A->ordinal(),
            'effective_from' => '2025-03-01',
            'is_active' => true,
        ]);

        $claim = $this->makeMedicineClaim('PARACETA1');

        $items = app(NhisBusinessValidator::class)->validate($claim);
        $codes = collect($items)->pluck('code')->all();

        $this->assertNotContains('281', $codes);
    }

    public function test_prescribing_level_warning_includes_official_codes(): void
    {
        $prescriber = User::factory()->create();

        ProviderCredentialing::factory()->create([
            'staff_id' => $prescriber->id,
            'prescribing_level_code' => NhisPrescribingLevel::B2->value,
            'prescribing_level' => NhisPrescribingLevel::B2->ordinal(),
            'is_active' => true,
        ]);

        NhisMedicine::factory()->create([
            'code' => 'SPECIALSM',
            'prescribing_level_code' => NhisPrescribingLevel::SM->value,
            'prescribing_level' => NhisPrescribingLevel::SM->ordinal(),
            'effective_from' => '2025-03-01',
            'is_active' => true,
        ]);

        $claim = $this->makeMedicineClaim('SPECIALSM', admittedBy: $prescriber);

        $items = app(NhisBusinessValidator::class)->validate($claim);
        $warning = collect($items)->firstWhere('code', '231');

        $this->assertNotNull($warning);
        $this->assertStringContainsString('B2', $warning->message);
        $this->assertStringContainsString('SM', $warning->message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function makeMedicineClaim(
        string $medicineCode,
        array $payload = [],
        ?User $admittedBy = null,
    ): InsuranceClaim {
        $branch = Branch::factory()->default()->create();
        $payer = Payer::factory()->create(['code' => 'nhis', 'type' => PayerType::NHIS]);
        $patient = PatientFactory::new()->create([
            'date_of_birth' => '1990-01-01',
            'branch_id' => $branch->id,
        ]);
        $policy = PatientPolicyFactory::new()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '12345678',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ]);

        $encounter = null;
        if ($admittedBy) {
            $encounter = Encounter::factory()
                ->active()
                ->create([
                    'patient_id' => $patient->id,
                    'branch_id' => $branch->id,
                    'admitted_by' => $admittedBy->id,
                ]);
        }

        $claim = InsuranceClaimFactory::new()->create([
            'payer_id' => $payer->id,
            'policy_id' => $policy->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter?->id,
            'total_billed_amount' => 1.20,
            'nhia_payload' => array_merge([
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'service_type' => 'OUT',
                'admission_date' => '2026-05-14',
                'pharmacy_included' => 'YES',
                'all_inclusive' => 'NO',
                'outpatient_tariff_amount' => '0.00',
            ], $payload),
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claim->id,
            'line_type' => ClaimLineType::MEDICINE,
            'external_item_code' => $medicineCode,
            'description' => 'Medicine',
            'quantity' => 1,
            'billed_amount' => 1.20,
            'metadata' => [
                'unit_price' => '1.20',
                'medicine_date' => '2026-05-14',
            ],
        ]);

        return $claim->fresh(['patient', 'policy', 'lines']);
    }
}

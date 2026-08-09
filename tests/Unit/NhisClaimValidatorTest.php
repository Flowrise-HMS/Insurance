<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimLineFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Schemes\Nhis\NhisClaimValidator;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class NhisClaimValidatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_validator_requires_card_serial_and_accepts_acu_admission_type(): void
    {
        $claim = $this->makeClaim([
            'metadata' => [],
        ], [
            'admission_type' => 'ACU',
            'speciality_code' => 'ORTH',
            'service_type' => 'OUT',
            'admission_date' => '2026-05-14',
        ]);

        $result = app(NhisClaimValidator::class)->validate($claim);

        $this->assertFalse($result->valid);
        $this->assertTrue(collect($result->codedErrors)->contains(fn ($e) => $e['code'] === '204'));
    }

    public function test_validator_passes_with_identity_and_allow_list_codes(): void
    {
        $claim = $this->makeClaim([
            'member_number' => '12345678',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ], [
            'admission_type' => 'ACU',
            'speciality_code' => 'ORTH',
            'service_type' => 'OUT',
            'admission_date' => '2026-05-14',
            'claim_check_code' => '14587',
        ]);

        $result = app(NhisClaimValidator::class)->validate($claim);

        $this->assertTrue($result->valid, implode(' ', $result->errors));
    }

    public function test_validator_rejects_invalid_ccc_length(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->require_claim_check_code = false;
        $settings->save();

        $claim = $this->makeClaim([
            'member_number' => '12345678',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ], [
            'admission_type' => 'ACU',
            'speciality_code' => 'ORTH',
            'service_type' => 'OUT',
            'admission_date' => '2026-05-14',
            'claim_check_code' => 'ABCD',
        ]);

        $result = app(NhisClaimValidator::class)->validate($claim);

        $this->assertFalse($result->valid);
        $this->assertTrue(collect($result->codedErrors)->contains(fn ($e) => $e['code'] === '237'));
    }

    public function test_validator_requires_admission_date(): void
    {
        $claim = $this->makeClaim([
            'member_number' => '12345678',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ], [
            'admission_type' => 'ACU',
            'speciality_code' => 'ORTH',
            'service_type' => 'OUT',
        ]);

        $result = app(NhisClaimValidator::class)->validate($claim);

        $this->assertFalse($result->valid);
        $this->assertTrue(collect($result->codedErrors)->contains(fn ($e) => $e['code'] === '214'));
    }

    public function test_validator_requires_mother_identity_for_infant_claim(): void
    {
        $claim = $this->makeClaim([
            'member_number' => '12345678',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ], [
            'admission_type' => 'ACU',
            'speciality_code' => 'PAED',
            'service_type' => 'OUT',
            'admission_date' => '2026-05-14',
        ], [
            'date_of_birth' => '2026-03-01',
        ]);

        $result = app(NhisClaimValidator::class)->validate($claim);

        $this->assertFalse($result->valid);
        $codes = collect($result->codedErrors)->pluck('code')->all();
        $this->assertContains('203', $codes);
        $this->assertContains('204', $codes);
    }

    public function test_validator_passes_for_infant_claim_with_mother_identity(): void
    {
        $claim = $this->makeClaim([
            'member_number' => '12345678',
            'metadata' => [
                'card_serial_number' => 'UWJPL120A0093',
                'mother_member_number' => '87654321',
                'mother_card_serial_number' => 'MOTHERSERIAL1',
            ],
        ], [
            'admission_type' => 'ACU',
            'speciality_code' => 'PAED',
            'service_type' => 'OUT',
            'admission_date' => '2026-05-14',
        ], [
            'date_of_birth' => '2026-03-01',
        ]);

        $result = app(NhisClaimValidator::class)->validate($claim);

        $this->assertTrue($result->valid, implode(' ', $result->errors));
    }

    /**
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $patient
     */
    protected function makeClaim(array $policy, array $payload, array $patient = []): InsuranceClaim
    {
        $payer = Payer::factory()->create(['code' => 'nhis', 'type' => PayerType::NHIS]);
        $patient = PatientFactory::new()->create(array_merge(['date_of_birth' => '1990-01-01'], $patient));
        $policyModel = PatientPolicyFactory::new()->create(array_merge([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '12345678',
        ], $policy));

        $claim = InsuranceClaimFactory::new()->create([
            'payer_id' => $payer->id,
            'policy_id' => $policyModel->id,
            'patient_id' => $patient->id,
            'total_billed_amount' => 20,
            'nhia_payload' => $payload,
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claim->id,
            'line_type' => ClaimLineType::TREATMENT,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 20,
            'metadata' => [
                'treatment_type' => 'Diagnosis',
                'icd_code' => 'J06.9',
                'tariff' => '20.00',
            ],
        ]);

        return $claim->fresh(['patient', 'policy', 'lines']);
    }
}

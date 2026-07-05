<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\ClaimBatchFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimLineFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Schemes\Nhis\NhisBatchXmlEncoder;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class NhisBatchXmlEncoderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_batch_encoder_generates_nhia_v8_6_structure(): void
    {
        $payer = Payer::factory()->create([
            'code' => 'nhis',
            'type' => PayerType::NHIS,
        ]);

        $patient = PatientFactory::new()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-04-12',
        ]);

        $policy = PatientPolicyFactory::new()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '12345678',
        ]);

        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-TEST-001',
            'batch_amount' => 75.50,
            'claims_count' => 1,
            'service_year' => 2026,
            'service_month' => 5,
        ]);

        $claim = InsuranceClaimFactory::new()->create([
            'batch_id' => $batch->id,
            'payer_id' => $payer->id,
            'policy_id' => $policy->id,
            'patient_id' => $patient->id,
            'claim_number' => 'C-1004523',
            'status' => ClaimStatus::VALIDATED,
            'total_billed_amount' => 75.50,
            'nhia_payload' => [
                'service_type' => 'OUT',
                'pharmacy_included' => 'YES',
                'all_inclusive' => 'NO',
                'outcome_type' => 'DIS',
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'admission_date' => '2026-05-14',
                'outpatient_code' => 'CONS01',
                'outpatient_tariff_amount' => '20.00',
            ],
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claim->id,
            'line_type' => ClaimLineType::TREATMENT,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 20.00,
            'metadata' => [
                'treatment_type' => 'Diagnosis',
                'icd_code' => 'J06.9',
                'tariff' => '20.00',
            ],
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claim->id,
            'line_type' => ClaimLineType::MEDICINE,
            'external_item_code' => 'DRUG02',
            'description' => 'Paracetamol',
            'quantity' => 20,
            'billed_amount' => 30.00,
            'metadata' => [
                'unit_price' => '1.50',
                'medicine_date' => '2026-05-14',
            ],
        ]);

        $encoder = app(NhisBatchXmlEncoder::class);
        $xml = $encoder->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringContainsString('<Batch>', $xml);
        $this->assertStringContainsString('<GeneralInformation>', $xml);
        $this->assertStringContainsString('<PatientData>', $xml);
        $this->assertStringContainsString('<ClaimIdentificationNumber>C-1004523</ClaimIdentificationNumber>', $xml);
        $this->assertStringContainsString('<MemberNumber>12345678</MemberNumber>', $xml);
        $this->assertStringContainsString('<ServiceType>OUT</ServiceType>', $xml);
        $this->assertStringContainsString('<TreatmentCode>CONS01</TreatmentCode>', $xml);
        $this->assertStringContainsString('<MedicineCode>DRUG02</MedicineCode>', $xml);
        $this->assertStringContainsString('<BatchAmount>75.50</BatchAmount>', $xml);
    }
}

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
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Schemes\Nhis\NhisBatchXmlEncoder;
use Modules\Insurance\Schemes\Nhis\NhsXmlSchemaValidator;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class NhisBatchXmlEncoderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);

        $settings = app(InsuranceSettings::class);
        $settings->provider_accreditation_number = '4563';
        $settings->eclaim_authorization_number = '12345567890';
        $settings->default_speciality_code = 'ORTH';
        $settings->save();
    }

    public function test_batch_encoder_generates_nhia_v8_6_structure(): void
    {
        $batch = $this->makeBatch(patient: ['middle_name' => null]);
        $encoder = app(NhisBatchXmlEncoder::class);
        $xml = $encoder->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringContainsString('<Batch>', $xml);
        $this->assertStringContainsString('<GeneralInformation>', $xml);
        $this->assertStringContainsString('<PatientData>', $xml);
        $this->assertStringContainsString('<Surname>Doe</Surname>', $xml);
        $this->assertStringContainsString('<OtherName>John</OtherName>', $xml);
        $this->assertStringContainsString('<ClaimIdentificationNumber>C-1004523</ClaimIdentificationNumber>', $xml);
        $this->assertStringContainsString('<MemberNumber>12345678</MemberNumber>', $xml);
        $this->assertStringContainsString('<CardSerialNumber>UWJPL120A0093</CardSerialNumber>', $xml);
        $this->assertStringContainsString('<ServiceType>OUT</ServiceType>', $xml);
        $this->assertStringContainsString('<AdmissionType>ACU</AdmissionType>', $xml);
        $this->assertStringContainsString('<TreatmentCode>CONS01</TreatmentCode>', $xml);
        $this->assertStringContainsString('<MedicineCode>DRUG02</MedicineCode>', $xml);
        $this->assertStringContainsString('<BatchAmount>75.50</BatchAmount>', $xml);
        $this->assertStringContainsString('<ServiceMonth>05</ServiceMonth>', $xml);
    }

    public function test_encoder_uses_structured_patient_names_and_middle_name(): void
    {
        $batch = $this->makeBatch(patient: [
            'first_name' => 'Ama',
            'middle_name' => 'Serwaa',
            'last_name' => 'Mensah',
        ]);

        $xml = app(NhisBatchXmlEncoder::class)->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringContainsString('<Surname>Mensah</Surname>', $xml);
        $this->assertStringContainsString('<OtherName>Ama Serwaa</OtherName>', $xml);
    }

    public function test_encoder_marks_infant_under_three_months_and_uses_mother_identity(): void
    {
        $batch = $this->makeBatch(
            patient: [
                'first_name' => 'Baby',
                'last_name' => 'Mensah',
                'date_of_birth' => '2026-04-01',
            ],
            policy: [
                'member_number' => '99999999',
                'metadata' => [
                    'card_serial_number' => 'BABYSERIAL001',
                    'mother_member_number' => '87654321',
                    'mother_card_serial_number' => 'MOTHERSERIAL1',
                ],
            ],
            claim: [
                'nhia_payload' => [
                    'service_type' => 'OUT',
                    'pharmacy_included' => 'NO',
                    'all_inclusive' => 'NO',
                    'outcome_type' => 'DIS',
                    'admission_type' => 'ACU',
                    'speciality_code' => 'PAED',
                    'admission_date' => '2026-05-14',
                    'outpatient_code' => 'CONS01',
                    'outpatient_tariff_amount' => '20.00',
                ],
            ],
        );

        $xml = app(NhisBatchXmlEncoder::class)->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringContainsString('<Infant>Yes</Infant>', $xml);
        $this->assertStringContainsString('<MemberNumber>87654321</MemberNumber>', $xml);
        $this->assertStringContainsString('<CardSerialNumber>MOTHERSERIAL1</CardSerialNumber>', $xml);
    }

    public function test_encoder_emits_ccc_when_present_and_defaults_speciality_to_opdc(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->default_speciality_code = null;
        $settings->save();

        $batch = $this->makeBatch(claim: [
            'nhia_payload' => [
                'service_type' => 'OUT',
                'pharmacy_included' => 'NO',
                'all_inclusive' => 'NO',
                'outcome_type' => 'DIS',
                'admission_type' => 'EME',
                'speciality_code' => 'INVALID',
                'claim_check_code' => '14587',
                'admission_date' => '2026-05-14',
                'outpatient_code' => 'CONS01',
                'outpatient_tariff_amount' => '20.00',
            ],
        ]);

        $xml = app(NhisBatchXmlEncoder::class)->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringContainsString('<ClaimCheckCode>14587</ClaimCheckCode>', $xml);
        $this->assertStringContainsString('<SpecialityCode>OPDC</SpecialityCode>', $xml);
        $this->assertStringContainsString('<AdmissionType>EME</AdmissionType>', $xml);
    }

    public function test_encoder_omits_empty_admission_date_so_no_empty_node_is_emitted(): void
    {
        $batch = $this->makeBatch(claim: [
            'nhia_payload' => [
                'service_type' => 'OUT',
                'pharmacy_included' => 'NO',
                'all_inclusive' => 'NO',
                'outcome_type' => 'DIS',
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'outpatient_code' => 'CONS01',
                'outpatient_tariff_amount' => '20.00',
            ],
        ]);

        $xml = app(NhisBatchXmlEncoder::class)->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringNotContainsString('<AdmissionDate>', $xml);
    }

    public function test_encoder_normalizes_treatment_type_case(): void
    {
        $batch = $this->makeBatch();
        $batch->claims->first()->lines()
            ->where('line_type', ClaimLineType::TREATMENT)
            ->first()
            ->update(['metadata' => ['treatment_type' => 'procedure', 'icd_code' => 'J06.9', 'tariff' => '45.50']]);

        $xml = app(NhisBatchXmlEncoder::class)->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        $this->assertStringContainsString('<Type>Procedure</Type>', $xml);
        $this->assertStringNotContainsString('<Type>procedure</Type>', $xml);
    }

    public function test_generated_xml_passes_nhia_xsd(): void
    {
        $batch = $this->makeBatch();
        $xml = app(NhisBatchXmlEncoder::class)->encode($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));
        $result = app(NhsXmlSchemaValidator::class)->validate($xml);

        $this->assertTrue($result->valid, implode("\n", $result->errors));
    }

    /**
     * @param  array<string, mixed>  $patient
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>  $claim
     */
    protected function makeBatch(array $patient = [], array $policy = [], array $claim = []): ClaimBatch
    {
        $payer = Payer::factory()->create([
            'code' => 'nhis',
            'type' => PayerType::NHIS,
        ]);

        $patientModel = PatientFactory::new()->create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-04-12',
        ], $patient));

        $policyModel = PatientPolicyFactory::new()->create(array_merge([
            'payer_id' => $payer->id,
            'patient_id' => $patientModel->id,
            'member_number' => '12345678',
            'metadata' => [
                'card_serial_number' => 'UWJPL120A0093',
            ],
        ], $policy));

        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-TEST-001',
            'batch_amount' => 75.50,
            'claims_count' => 1,
            'service_year' => 2026,
            'service_month' => 5,
        ]);

        $claimModel = InsuranceClaimFactory::new()->create(array_merge([
            'batch_id' => $batch->id,
            'payer_id' => $payer->id,
            'policy_id' => $policyModel->id,
            'patient_id' => $patientModel->id,
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
        ], $claim));

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claimModel->id,
            'line_type' => ClaimLineType::TREATMENT,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 45.50,
            'metadata' => [
                'treatment_type' => 'Diagnosis',
                'icd_code' => 'J06.9',
                'tariff' => '45.50',
                'performance_date' => '2026-05-14',
            ],
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claimModel->id,
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

        return $batch;
    }
}

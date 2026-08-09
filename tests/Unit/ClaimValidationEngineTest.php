<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Modules\Insurance\Database\Factories\ClaimBatchFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimLineFactory;
use Modules\Insurance\Database\Factories\MembersMasterFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Database\Factories\PayerFactory;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\ClaimBatchService;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Insurance\Validation\ClaimValidationEngine;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class ClaimValidationEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);

        $settings = app(InsuranceSettings::class);
        $settings->member_verification_mode = 'offline';
        $settings->save();
    }

    public function test_engine_returns_valid_report_for_conforming_claim(): void
    {
        [$batch, $claim] = $this->conformingClaim();
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertTrue($report->valid());
        $this->assertCount(0, $report->errors());
    }

    public function test_engine_flags_member_not_in_master_with_203(): void
    {
        [$batch] = $this->conformingClaim();

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertFalse($report->valid());
        $this->assertSame('203', $report->errors()->first()->code);
    }

    public function test_engine_flags_missing_gdrg_map_with_242(): void
    {
        [$batch, $claim] = $this->conformingClaim();
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $claim->update([
            'total_billed_amount' => 150.00,
            'nhia_payload' => array_merge($claim->nhia_payload, [
                'outpatient_code' => 'GDRG999',
                'outpatient_tariff_amount' => '100.00',
            ]),
        ]);
        $batch->update(['batch_amount' => 150.00]);

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertFalse($report->valid());
        $this->assertSame('242', $report->errors()->first()->code);
    }

    public function test_engine_flags_total_formula_mismatch_with_238(): void
    {
        [$batch, $claim] = $this->conformingClaim();
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $claim->update(['total_billed_amount' => 60.00]);
        $batch->update(['batch_amount' => 60.00]);

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertFalse($report->valid());
        $this->assertSame('238', $report->errors()->first()->code);
    }

    public function test_engine_flags_unknown_medicine_with_281(): void
    {
        [$batch, $claim] = $this->conformingClaim();
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $claim->lines()->create([
            'line_type' => ClaimLineType::MEDICINE,
            'external_item_code' => 'MED999',
            'description' => 'Unknown drug',
            'quantity' => 1,
            'billed_amount' => 10.00,
            'metadata' => ['dosage' => '1x1'],
        ]);
        $claim->update(['total_billed_amount' => 60.00]);
        $batch->update(['batch_amount' => 60.00]);

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertFalse($report->valid());
        $this->assertSame('281', $report->errors()->first()->code);
    }

    public function test_batch_validator_flags_amount_mismatch_with_109(): void
    {
        [$batch, $claim] = $this->conformingClaim();
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $batch->update(['batch_amount' => 999.00]);

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertFalse($report->valid());
        $this->assertTrue($report->errors()->contains(fn ($item) => $item->code === '109'));
    }

    public function test_batch_validator_flags_service_month_with_120(): void
    {
        [$batch, $claim] = $this->conformingClaim();
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $claim->update(['nhia_payload' => array_merge($claim->nhia_payload, [
            'admission_date' => '2026-01-15',
        ])]);
        $batch->update(['service_year' => 2026, 'service_month' => 7]);

        $report = app(ClaimValidationEngine::class)->validateBatch($batch);

        $this->assertFalse($report->valid());
        $this->assertTrue($report->errors()->contains(fn ($item) => $item->code === '120'));
    }

    public function test_export_blocks_invalid_batch_unless_forced(): void
    {
        Storage::fake('local');

        $payer = PayerFactory::new()->create([
            'code' => 'nhis',
            'type' => PayerType::NHIS,
        ]);

        $patient = PatientFactory::new()->create();
        $policy = PatientPolicyFactory::new()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '87654321',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ]);

        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-VALIDATE-001',
            'batch_amount' => 50.00,
            'claims_count' => 1,
            'status' => ClaimBatchStatus::VETTED,
        ]);

        $claim = InsuranceClaimFactory::new()->create([
            'batch_id' => $batch->id,
            'payer_id' => $payer->id,
            'policy_id' => $policy->id,
            'patient_id' => $patient->id,
            'claim_number' => 'C-VALIDATE-001',
            'status' => ClaimStatus::VALIDATED,
            'total_billed_amount' => 50.00,
            'nhia_payload' => [
                'service_type' => 'OUT',
                'pharmacy_included' => 'NO',
                'all_inclusive' => 'NO',
                'outcome_type' => 'DIS',
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'admission_date' => '2026-07-01',
                'outpatient_code' => 'CONS01',
                'outpatient_tariff_amount' => '50.00',
            ],
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claim->id,
            'line_type' => ClaimLineType::TREATMENT,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 50.00,
            'metadata' => [
                'treatment_type' => 'Diagnosis',
                'icd_code' => 'J06.9',
                'tariff' => '50.00',
                'performance_date' => '2026-07-01',
            ],
        ]);

        // Member not present in the master table -> 203, engine blocks export.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch validation failed');

        app(ClaimBatchService::class)->export($batch->fresh());
    }

    public function test_export_forced_writes_file_despite_validation_errors(): void
    {
        Storage::fake('local');

        $payer = Payer::query()->where('code', 'nhis')->first()
            ?? PayerFactory::new()->create(['code' => 'nhis', 'type' => PayerType::NHIS]);

        $patient = PatientFactory::new()->create();
        $policy = PatientPolicyFactory::new()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '87654321',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ]);

        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-FORCE-001',
            'batch_amount' => 50.00,
            'claims_count' => 1,
            'service_year' => 2026,
            'service_month' => 7,
            'status' => ClaimBatchStatus::VETTED,
        ]);

        $claim = InsuranceClaimFactory::new()->create([
            'batch_id' => $batch->id,
            'payer_id' => $payer->id,
            'policy_id' => $policy->id,
            'patient_id' => $patient->id,
            'claim_number' => 'C-FORCE-001',
            'status' => ClaimStatus::VALIDATED,
            'total_billed_amount' => 50.00,
            'nhia_payload' => [
                'service_type' => 'OUT',
                'pharmacy_included' => 'NO',
                'all_inclusive' => 'NO',
                'outcome_type' => 'DIS',
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'admission_date' => '2026-07-01',
                'outpatient_code' => 'CONS01',
                'outpatient_tariff_amount' => '50.00',
            ],
        ]);

        InsuranceClaimLineFactory::new()->create([
            'claim_id' => $claim->id,
            'line_type' => ClaimLineType::TREATMENT,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 50.00,
            'metadata' => [
                'treatment_type' => 'Diagnosis',
                'icd_code' => 'J06.9',
                'tariff' => '50.00',
                'performance_date' => '2026-07-01',
            ],
        ]);

        $exported = app(ClaimBatchService::class)->export($batch->fresh(), force: true);

        $this->assertSame('nhis-batch-NB-FORCE-001.xml', $exported->filename);
        Storage::disk('local')->assertExists($exported->path);

        $batch->refresh();
        $this->assertSame('203', $batch->metadata['validation_report']['errors'][0]['code'] ?? null);
        $this->assertTrue($batch->metadata['validation_forced']);
    }

    /**
     * @return array{0: ClaimBatch, 1: InsuranceClaim}
     */
    protected function conformingClaim(): array
    {
        $payer = Payer::query()->where('code', 'nhis')->first()
            ?? PayerFactory::new()->create(['code' => 'nhis', 'type' => PayerType::NHIS]);

        $patient = PatientFactory::new()->create();
        $policy = PatientPolicyFactory::new()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '87654321',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ]);

        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-ENGINE-'.uniqid(),
            'batch_amount' => 50.00,
            'claims_count' => 1,
            'service_year' => 2026,
            'service_month' => 7,
        ]);

        $claim = InsuranceClaimFactory::new()->create([
            'batch_id' => $batch->id,
            'payer_id' => $payer->id,
            'policy_id' => $policy->id,
            'patient_id' => $patient->id,
            'claim_number' => 'C-ENGINE-'.uniqid(),
            'status' => ClaimStatus::VALIDATED,
            'total_billed_amount' => 50.00,
            'nhia_payload' => [
                'service_type' => 'OUT',
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'admission_date' => '2026-07-01',
                'claim_check_code' => '12345',
            ],
        ]);

        $claim->lines()->create([
            'line_type' => ClaimLineType::TREATMENT,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 50.00,
            'metadata' => [
                'treatment_type' => 'Diagnosis',
                'icd_code' => 'J06.9',
                'tariff' => '50.00',
                'performance_date' => '2026-07-01',
            ],
        ]);

        return [$batch->fresh(), $claim->fresh()];
    }
}

<?php

namespace Modules\Insurance\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Enums\CoverageType;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\ClaimBatchService;
use Modules\Insurance\Services\ClaimGenerationService;
use Modules\Insurance\Support\ClaimBatchCriteria;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class ClaimGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing', 'Pharmacy', 'Insurance']);
    }

    /**
     * @return array{admitted_at: Carbon, discharged_at: Carbon}
     */
    private function withinCurrentMonthDates(): array
    {
        return [
            'admitted_at' => now()->startOfMonth(),
            'discharged_at' => now()->startOfMonth()->addDay(),
        ];
    }

    public function test_claim_generation_creates_batch_from_nhis_encounter(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $payer = Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            ['name' => 'NHIS', 'type' => PayerType::NHIS, 'is_active' => true]
        );

        PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => 'NHIS-123456',
            'is_active' => true,
            'is_primary' => true,
        ]);

        EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            ...$this->withinCurrentMonthDates(),
        ]);

        $batch = app(ClaimGenerationService::class)->generate(new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $branch->id,
            patientId: (string) $patient->id,
            year: (int) now()->year,
            month: (int) now()->month,
        ));

        $this->assertInstanceOf(ClaimBatch::class, $batch);
        $this->assertSame(ClaimBatchStatus::GENERATED, $batch->status);
        $this->assertSame(1, $batch->claims_count);
        $this->assertDatabaseHas('insurance_claims', [
            'batch_id' => $batch->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_claim_generation_carries_encounter_claim_check_code_into_payload(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $payer = Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            ['name' => 'NHIS', 'type' => PayerType::NHIS, 'is_active' => true]
        );

        PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => 'NHIS-777777',
            'is_active' => true,
            'is_primary' => true,
        ]);

        EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            'claim_check_code' => '4654351214657',
            ...$this->withinCurrentMonthDates(),
        ]);

        $batch = app(ClaimGenerationService::class)->generate(new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $branch->id,
            patientId: (string) $patient->id,
            year: (int) now()->year,
            month: (int) now()->month,
        ));

        $claim = $batch->claims()->first();

        $this->assertSame('4654351214657', data_get($claim->nhia_payload, 'claim_check_code'));
    }

    public function test_batch_export_marks_claims_submitted(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $payer = Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            ['name' => 'NHIS', 'type' => PayerType::NHIS, 'is_active' => true]
        );

        PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => 'NHIS-999999',
            'is_active' => true,
            'is_primary' => true,
        ]);

        EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            ...$this->withinCurrentMonthDates(),
        ]);

        $batch = app(ClaimGenerationService::class)->generate(new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $branch->id,
            patientId: (string) $patient->id,
            year: (int) now()->year,
            month: (int) now()->month,
        ));

        $claim = $batch->claims()->first();
        $claim->update([
            'nhia_payload' => array_merge($claim->nhia_payload ?? [], [
                'service_type' => 'OUT',
                'speciality_code' => 'ORTH',
                'admission_date' => now()->subDays(3)->toDateString(),
            ]),
        ]);

        if ($claim->lines()->count() === 0) {
            InsuranceClaimLine::query()->create([
                'claim_id' => $claim->id,
                'line_type' => ClaimLineType::TREATMENT,
                'external_item_code' => 'CONS01',
                'description' => 'Consultation',
                'quantity' => 1,
                'billed_amount' => '20.00',
                'metadata' => ['treatment_type' => 'Diagnosis', 'icd_code' => 'J06.9', 'tariff' => '20.00'],
            ]);
            $claim->update(['total_billed_amount' => '20.00']);
        } else {
            foreach ($claim->lines()->where('line_type', 'treatment')->get() as $line) {
                $line->update([
                    'external_item_code' => 'CONS01',
                    'metadata' => array_merge($line->metadata ?? [], ['icd_code' => 'J06.9']),
                ]);
            }
        }

        $exported = app(ClaimBatchService::class)->export($batch->fresh(), force: true);

        $this->assertTrue(Storage::disk('local')->exists($exported->path));
        $this->assertSame(ClaimBatchStatus::EXPORTED, $batch->fresh()->status);
        $this->assertSame('submitted', $claim->fresh()->status->value);
    }
}

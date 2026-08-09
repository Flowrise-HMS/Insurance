<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Enums\CoverageType;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\GdrgIcdMap;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Services\ClaimBatchService;
use Modules\Insurance\Services\ClaimGenerationService;
use Modules\Insurance\Settings\InsuranceSettings;
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

    public function test_claim_generation_prefers_local_icd10_code_over_who_icd11_code(): void
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
            'member_number' => 'NHIS-ICD10',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $encounter = EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            ...$this->withinCurrentMonthDates(),
        ]);

        $user = User::factory()->create(['branch_id' => $branch->id]);

        EncounterDiagnosis::query()->create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'icd_code' => '1A00',
            'icd10_code' => 'A00.0',
            'icd_entity_id' => '123',
            'description' => 'Cholera',
            'type' => DiagnosisType::Primary,
            'is_new_case' => false,
            'certainty' => DiagnosisCertainty::Provisional,
            'ordered_by' => $user->id,
            'is_active' => true,
        ]);

        $batch = app(ClaimGenerationService::class)->generate(new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $branch->id,
            patientId: (string) $patient->id,
            year: (int) now()->year,
            month: (int) now()->month,
        ));

        $claim = $batch->claims()->first();
        $line = $claim->lines()->where('line_type', ClaimLineType::TREATMENT)->first();

        $this->assertSame('A00.0', data_get($line->metadata, 'icd_code'));
    }

    public function test_claim_generation_defaults_admission_date_to_encounter_created_at_when_not_admitted(): void
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
            'member_number' => 'NHIS-FALLBACK',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $encounter = EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            'admitted_at' => null,
        ]);

        $batch = app(ClaimGenerationService::class)->generate(new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $branch->id,
            patientId: (string) $patient->id,
        ));

        $claim = $batch->claims()->first();

        $this->assertSame($encounter->created_at->toDateString(), data_get($claim->nhia_payload, 'admission_date'));
    }

    public function test_claim_generation_resolves_outpatient_gdrg_from_annex_c_and_tariff(): void
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
            'member_number' => 'NHIS-GDRG',
            'is_active' => true,
            'is_primary' => true,
        ]);

        GdrgIcdMap::query()->create([
            'icd10_code' => 'A00.0',
            'gdrg_code' => 'GDRG001',
            'service_type' => 'OUT',
            'is_active' => true,
        ]);

        TariffItem::query()->create([
            'payer_id' => $payer->id,
            'item_type' => 'service',
            'external_code' => 'GDRG001',
            'name' => 'Cholera G-DRG',
            'price' => 120.00,
            'effective_from' => now()->startOfMonth(),
            'effective_to' => now()->endOfMonth(),
            'is_active' => true,
        ]);

        $encounter = EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            ...$this->withinCurrentMonthDates(),
        ]);

        $user = User::factory()->create(['branch_id' => $branch->id]);

        EncounterDiagnosis::query()->create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'icd_code' => '1A00',
            'icd10_code' => 'A00.0',
            'icd_entity_id' => '123',
            'description' => 'Cholera',
            'type' => DiagnosisType::Primary,
            'is_new_case' => true,
            'certainty' => DiagnosisCertainty::Confirmed,
            'ordered_by' => $user->id,
            'is_active' => true,
        ]);

        $batch = app(ClaimGenerationService::class)->generate(new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $branch->id,
            patientId: (string) $patient->id,
            year: (int) now()->year,
            month: (int) now()->month,
        ));

        $claim = $batch->claims()->first();

        $this->assertSame('GDRG001', data_get($claim->nhia_payload, 'outpatient_code'));
        $this->assertSame('120.00', data_get($claim->nhia_payload, 'outpatient_tariff_amount'));
        $this->assertSame('120.00', (string) $claim->total_billed_amount);
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
            'metadata' => [
                'card_serial_number' => 'UWJPL120A0093',
            ],
        ]);

        EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'coverage_type' => CoverageType::NHIS,
            ...$this->withinCurrentMonthDates(),
        ]);

        $settings = app(InsuranceSettings::class);
        $settings->provider_accreditation_number = '4563';
        $settings->eclaim_authorization_number = '12345567890';
        $settings->default_speciality_code = 'ORTH';
        $settings->save();

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
                'pharmacy_included' => 'NO',
                'all_inclusive' => 'NO',
                'outcome_type' => 'DIS',
                'admission_type' => 'ACU',
                'speciality_code' => 'ORTH',
                'admission_date' => now()->subDays(3)->toDateString(),
                'outpatient_code' => 'CONS01',
                'outpatient_tariff_amount' => '20.00',
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
                'metadata' => ['treatment_type' => 'Diagnosis', 'icd_code' => 'J06.9', 'tariff' => '20.00', 'performance_date' => now()->subDays(3)->toDateString()],
            ]);
            $claim->update(['total_billed_amount' => '20.00']);
            $batch->update(['batch_amount' => '20.00', 'claims_count' => 1]);
        } else {
            foreach ($claim->lines()->where('line_type', 'treatment')->get() as $line) {
                $line->update([
                    'external_item_code' => 'CONS01',
                    'metadata' => array_merge($line->metadata ?? [], ['icd_code' => 'J06.9', 'performance_date' => now()->subDays(3)->toDateString()]),
                ]);
            }
        }

        $exported = app(ClaimBatchService::class)->export($batch->fresh(), force: true);

        $this->assertTrue(Storage::disk('local')->exists($exported->path));
        $this->assertSame(ClaimBatchStatus::EXPORTED, $batch->fresh()->status);
        $this->assertSame('submitted', $claim->fresh()->status->value);
    }
}

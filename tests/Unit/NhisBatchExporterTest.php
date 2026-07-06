<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Modules\Insurance\Database\Factories\ClaimBatchFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Database\Factories\InsuranceClaimLineFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Schemes\Nhis\NhisBatchExporter;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class NhisBatchExporterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_export_writes_xml_to_local_disk(): void
    {
        Storage::fake('local');

        $payer = Payer::factory()->create([
            'code' => 'nhis',
            'type' => PayerType::NHIS,
        ]);

        $patient = PatientFactory::new()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_of_birth' => '1985-01-01',
        ]);

        $policy = PatientPolicyFactory::new()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => '87654321',
        ]);

        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-EXPORT-001',
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
            'claim_number' => 'C-EXPORT-001',
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
            ],
        ]);

        $exported = app(NhisBatchExporter::class)->export(
            $batch->fresh(['claims.patient', 'claims.policy', 'claims.lines'])
        );

        $this->assertSame('nhis-batch-NB-EXPORT-001.xml', $exported->filename);
        $this->assertSame('insurance/exports/nhis-batch-NB-EXPORT-001.xml', $exported->path);

        Storage::disk('local')->assertExists($exported->path);
        $this->assertStringContainsString('<Batch>', Storage::disk('local')->get($exported->path));
        $this->assertFileDoesNotExist(storage_path('app/'.$exported->path));
    }
}

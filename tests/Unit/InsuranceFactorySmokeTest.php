<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceCatalogSync;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimFeedback;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\InsuranceClaimSubmission;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Tests\TestCase;

class InsuranceFactorySmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing', 'Insurance']);
    }

    public function test_payer_factory(): void
    {
        $payer = Payer::factory()->create();
        $this->assertInstanceOf(Payer::class, $payer);
        $this->assertTrue($payer->exists);
    }

    public function test_patient_policy_factory(): void
    {
        $policy = PatientPolicy::factory()->create();
        $this->assertInstanceOf(PatientPolicy::class, $policy);
        $this->assertTrue($policy->exists);
    }

    public function test_insurance_claim_factory(): void
    {
        $claim = InsuranceClaim::factory()->create();
        $this->assertInstanceOf(InsuranceClaim::class, $claim);
        $this->assertTrue($claim->exists);
    }

    public function test_insurance_claim_line_factory(): void
    {
        $line = InsuranceClaimLine::factory()->create();
        $this->assertInstanceOf(InsuranceClaimLine::class, $line);
        $this->assertTrue($line->exists);
    }

    public function test_insurance_claim_submission_factory(): void
    {
        $submission = InsuranceClaimSubmission::factory()->create();
        $this->assertInstanceOf(InsuranceClaimSubmission::class, $submission);
        $this->assertTrue($submission->exists);
    }

    public function test_insurance_claim_feedback_factory(): void
    {
        $feedback = InsuranceClaimFeedback::factory()->create();
        $this->assertInstanceOf(InsuranceClaimFeedback::class, $feedback);
        $this->assertTrue($feedback->exists);
    }

    public function test_insurance_catalog_sync_factory(): void
    {
        $sync = InsuranceCatalogSync::factory()->create();
        $this->assertInstanceOf(InsuranceCatalogSync::class, $sync);
        $this->assertTrue($sync->exists);
    }

    public function test_tariff_item_factory(): void
    {
        $item = TariffItem::factory()->create();
        $this->assertInstanceOf(TariffItem::class, $item);
        $this->assertTrue($item->exists);
    }

    public function test_claim_batch_factory(): void
    {
        $batch = ClaimBatch::factory()->create();
        $this->assertInstanceOf(ClaimBatch::class, $batch);
        $this->assertTrue($batch->exists);
    }
}

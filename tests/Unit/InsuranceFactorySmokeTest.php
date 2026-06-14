<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Patient', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Insurance', '--force' => true]);
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
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
        $claim = InsuranceClaim::factory()->create();
        $this->assertInstanceOf(InsuranceClaim::class, $claim);
        $this->assertTrue($claim->exists);
    }

    public function test_insurance_claim_line_factory(): void
    {
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
        $line = InsuranceClaimLine::factory()->create();
        $this->assertInstanceOf(InsuranceClaimLine::class, $line);
        $this->assertTrue($line->exists);
    }

    public function test_insurance_claim_submission_factory(): void
    {
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
        $submission = InsuranceClaimSubmission::factory()->create();
        $this->assertInstanceOf(InsuranceClaimSubmission::class, $submission);
        $this->assertTrue($submission->exists);
    }

    public function test_insurance_claim_feedback_factory(): void
    {
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
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
}

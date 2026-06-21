<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Jobs\PollInsuranceClaimFeedbackJob;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimSubmission;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\ClaimSubmissionService;
use Tests\TestCase;

class ClaimSubmissionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ClaimSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Billing', 'Insurance']);
        Queue::fake();

        $this->service = app(ClaimSubmissionService::class);
    }

    public function test_submit_creates_submission_and_marks_claim_submitted(): void
    {
        $payer = Payer::query()->firstOrCreate(
            ['code' => 'private-generic'],
            [
                'name' => 'Generic Private Insurer',
                'type' => PayerType::PRIVATE,
                'is_active' => true,
            ]
        );

        $claim = InsuranceClaim::factory()->create([
            'payer_id' => $payer->id,
            'status' => ClaimStatus::DRAFT,
        ]);

        $submission = $this->service->submit($claim);

        $this->assertInstanceOf(InsuranceClaimSubmission::class, $submission);
        $this->assertSame($claim->id, $submission->claim_id);
        $this->assertSame('private-generic', $submission->connector_code);
        $this->assertSame('queued', $submission->submission_status);
        $this->assertSame(ClaimStatus::SUBMITTED, $claim->fresh()->status);
        $this->assertNotNull($claim->fresh()->submitted_at);

        Queue::assertPushed(PollInsuranceClaimFeedbackJob::class);
    }
}

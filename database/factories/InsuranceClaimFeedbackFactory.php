<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimFeedback;
use Modules\Insurance\Models\InsuranceClaimSubmission;

class InsuranceClaimFeedbackFactory extends Factory
{
    protected $model = InsuranceClaimFeedback::class;

    public function definition(): array
    {
        return [
            'claim_id' => InsuranceClaim::factory(),
            'claim_submission_id' => InsuranceClaimSubmission::factory(),
            'feedback_hash' => md5(fake()->uuid()),
            'feedback_type' => 'adjudication',
            'decision_status' => ClaimDecisionStatus::APPROVED,
            'processed_at' => now(),
        ];
    }
}

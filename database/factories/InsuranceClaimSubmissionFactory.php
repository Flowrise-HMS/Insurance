<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimSubmission;

class InsuranceClaimSubmissionFactory extends Factory
{
    protected $model = InsuranceClaimSubmission::class;

    public function definition(): array
    {
        return [
            'claim_id' => InsuranceClaim::factory(),
            'connector_code' => fake()->randomElement(['NHIS', 'GHA', 'PRIVATE']),
            'idempotency_key' => fake()->uuid(),
            'attempt_count' => 0,
            'submitted_at' => now(),
        ];
    }
}

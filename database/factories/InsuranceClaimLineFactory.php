<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\InvoiceLine;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;

class InsuranceClaimLineFactory extends Factory
{
    protected $model = InsuranceClaimLine::class;

    public function definition(): array
    {
        $billedAmount = fake()->randomFloat(2, 10, 2000);

        return [
            'claim_id' => InsuranceClaim::factory(),
            'invoice_line_id' => InvoiceLine::factory(),
            'billed_amount' => $billedAmount,
            'approved_amount' => $billedAmount,
            'rejected_amount' => 0,
            'decision_status' => ClaimDecisionStatus::PENDING,
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }
}

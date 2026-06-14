<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\Invoice;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Models\Patient;

class InsuranceClaimFactory extends Factory
{
    protected $model = InsuranceClaim::class;

    public function definition(): array
    {
        return [
            'payer_id' => Payer::factory(),
            'policy_id' => PatientPolicy::factory(),
            'patient_id' => Patient::factory(),
            'invoice_id' => Invoice::factory(),
            'claim_number' => 'CLM-' . strtoupper(fake()->bothify('####??')),
            'status' => ClaimStatus::DRAFT,
            'total_billed_amount' => fake()->randomFloat(2, 100, 10000),
            'total_approved_amount' => 0,
            'total_rejected_amount' => 0,
            'currency' => 'GHS',
        ];
    }
}

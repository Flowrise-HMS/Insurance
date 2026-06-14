<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Models\Patient;

class PatientPolicyFactory extends Factory
{
    protected $model = PatientPolicy::class;

    public function definition(): array
    {
        return [
            'payer_id' => Payer::factory(),
            'patient_id' => Patient::factory(),
            'member_number' => (string) fake()->randomNumber(8),
            'plan_code' => strtoupper(fake()->bothify('PLAN###')),
            'effective_from' => now()->subMonths(3),
            'effective_to' => now()->addMonths(9),
            'is_primary' => true,
            'is_active' => true,
        ];
    }
}

<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Models\NhisMedicine;

class NhisMedicineFactory extends Factory
{
    protected $model = NhisMedicine::class;

    public function definition(): array
    {
        $level = fake()->randomElement(NhisPrescribingLevel::cases());

        return [
            'code' => strtoupper(fake()->bothify('MED###')),
            'name' => fake()->word(),
            'strength' => fake()->randomElement(['500mg', '250mg', '10mg/5ml']),
            'form' => fake()->randomElement(['Tablet', 'Syrup', 'Injection']),
            'prescribing_level_code' => $level->value,
            'prescribing_level' => $level->ordinal(),
            'unit_of_pricing' => fake()->randomElement(['Tablet', 'Ampoule', '1 Course', '100 mL']),
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYear(),
            'is_active' => true,
        ];
    }
}

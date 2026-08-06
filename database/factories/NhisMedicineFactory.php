<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\NhisMedicine;

class NhisMedicineFactory extends Factory
{
    protected $model = NhisMedicine::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('MED###')),
            'name' => fake()->word(),
            'strength' => fake()->randomElement(['500mg', '250mg', '10mg/5ml']),
            'form' => fake()->randomElement(['Tablet', 'Syrup', 'Injection']),
            'prescribing_level' => fake()->numberBetween(1, 3),
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYear(),
            'is_active' => true,
        ];
    }
}

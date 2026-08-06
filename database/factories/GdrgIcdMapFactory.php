<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\GdrgIcdMap;

class GdrgIcdMapFactory extends Factory
{
    protected $model = GdrgIcdMap::class;

    public function definition(): array
    {
        return [
            'icd10_code' => strtoupper(fake()->regexify('[A-Z][0-9]{2}(\.[0-9])?')),
            'gdrg_code' => strtoupper(fake()->bothify('GDRG###')),
            'description' => fake()->sentence(4),
            'mdc' => fake()->randomElement(['01', '05', '08']),
            'service_type' => fake()->randomElement(['OUT', 'INP']),
            'is_active' => true,
        ];
    }
}

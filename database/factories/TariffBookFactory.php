<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\TariffBook;

class TariffBookFactory extends Factory
{
    protected $model = TariffBook::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('BOOK###')),
            'name' => fake()->company(),
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYear(),
            'is_active' => true,
        ];
    }
}

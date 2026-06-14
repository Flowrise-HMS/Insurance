<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;

class TariffItemFactory extends Factory
{
    protected $model = TariffItem::class;

    public function definition(): array
    {
        return [
            'payer_id' => Payer::factory(),
            'item_type' => fake()->randomElement(['consultation', 'procedure', 'medication', 'lab_test']),
            'external_code' => strtoupper(fake()->bothify('TARIFF###')),
            'name' => fake()->words(3, true),
            'price' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'GHS',
            'source_version' => 'v1',
            'is_active' => true,
        ];
    }
}

<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;

class PayerFactory extends Factory
{
    protected $model = Payer::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('PAYER###')),
            'name' => fake()->company().' Insurance',
            'type' => PayerType::PRIVATE,
            'is_active' => true,
        ];
    }
}

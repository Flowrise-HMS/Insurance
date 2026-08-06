<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\ProviderCredentialing;

class ProviderCredentialingFactory extends Factory
{
    protected $model = ProviderCredentialing::class;

    public function definition(): array
    {
        return [
            'provider_name' => fake()->name(),
            'prescribing_level' => fake()->numberBetween(1, 3),
            'specialities' => ['OPDC'],
            'level_of_care' => 'Primary',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'is_active' => true,
        ];
    }
}

<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Models\ProviderCredentialing;

class ProviderCredentialingFactory extends Factory
{
    protected $model = ProviderCredentialing::class;

    public function definition(): array
    {
        $level = fake()->randomElement(NhisPrescribingLevel::cases());

        return [
            'provider_name' => fake()->name(),
            'prescribing_level_code' => $level->value,
            'prescribing_level' => $level->ordinal(),
            'specialities' => ['OPDC'],
            'level_of_care' => 'Primary',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'is_active' => true,
        ];
    }
}

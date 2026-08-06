<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\MembersMaster;

class MembersMasterFactory extends Factory
{
    protected $model = MembersMaster::class;

    public function definition(): array
    {
        return [
            'member_number' => fake()->numerify('#########'),
            'card_serial_number' => strtoupper(fake()->bothify('################')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['F', 'M']),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'is_active' => true,
        ];
    }
}

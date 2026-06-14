<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\InsuranceCatalogSync;
use Modules\Insurance\Models\Payer;

class InsuranceCatalogSyncFactory extends Factory
{
    protected $model = InsuranceCatalogSync::class;

    public function definition(): array
    {
        return [
            'payer_id' => Payer::factory(),
            'sync_type' => fake()->randomElement(['full', 'incremental']),
            'status' => 'pending',
            'started_at' => now(),
            'records_processed' => 0,
        ];
    }
}

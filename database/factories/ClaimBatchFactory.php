<?php

namespace Modules\Insurance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\Payer;

class ClaimBatchFactory extends Factory
{
    protected $model = ClaimBatch::class;

    public function definition(): array
    {
        return [
            'scheme_code' => 'nhis',
            'payer_id' => Payer::factory(),
            'branch_id' => Branch::factory(),
            'batch_number' => 'NB-'.fake()->numerify('########-####'),
            'filter_criteria' => ['all' => true],
            'service_year' => (int) now()->format('Y'),
            'service_month' => (int) now()->format('n'),
            'status' => ClaimBatchStatus::GENERATED,
            'claims_count' => 0,
            'batch_amount' => 0,
            'currency' => 'GHS',
            'master_table_versions' => [
                'XMLFormatVersion' => '1',
                'MedicineVersion' => '1',
                'GDRGVersion' => '1',
                'TariffVersion' => '1',
                'ICDVersion' => '1',
                'OpenHDDVersion' => '1',
            ],
        ];
    }
}

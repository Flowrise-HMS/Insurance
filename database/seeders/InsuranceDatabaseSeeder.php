<?php

namespace Modules\Insurance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;

class InsuranceDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            [
                'name' => 'National Health Insurance Scheme (NHIS)',
                'type' => PayerType::NHIS,
                'is_active' => true,
                'config' => [
                    'xml_version' => config('insurance.nhis.xml_version', '8.6'),
                ],
            ]
        );

        Payer::query()->firstOrCreate(
            ['code' => 'private-generic'],
            [
                'name' => 'Private Insurance (Generic)',
                'type' => PayerType::PRIVATE,
                'is_active' => true,
            ]
        );

        $this->call(NhisMedicinesList2025Seeder::class);
    }
}

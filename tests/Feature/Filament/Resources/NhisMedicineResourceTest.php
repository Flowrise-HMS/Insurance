<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource\Pages\CreateNhisMedicine;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource\Pages\EditNhisMedicine;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource\Pages\ListNhisMedicines;
use Modules\Insurance\Models\NhisMedicine;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => NhisMedicineResource::class,
    'subject' => 'NhisMedicine',
    'model' => NhisMedicine::class,
    'listPage' => ListNhisMedicines::class,
    'createPage' => CreateNhisMedicine::class,
    'editPage' => EditNhisMedicine::class,
    'searchColumn' => 'code',
    'sortColumn' => 'code',
    'filter' => [
        'name' => 'prescribing_level_code',
        'value' => NhisPrescribingLevel::A->value,
        'attribute' => 'prescribing_level_code',
    ],
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'uniqueField' => 'code',
    'makeRecords' => fn (TestCase $test, int $count) => NhisMedicine::factory()->count($count)->create([
        'prescribing_level_code' => NhisPrescribingLevel::A->value,
    ]),
    'createForm' => fn (): array => [
        'code' => strtoupper(fake()->unique()->bothify('MED###')),
        'name' => 'Paracetamol 500mg',
        'prescribing_level_code' => NhisPrescribingLevel::A->value,
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'name' => 'Updated NHIS medicine',
    ],
    'schemaState' => fn (mixed $test, NhisMedicine $record): array => [
        'code' => $record->code,
        'name' => $record->name,
    ],
    'requiredValidation' => [
        'code is required' => [['code' => null], ['code' => 'required']],
        'name is required' => [['name' => null], ['name' => 'required']],
        'prescribing level is required' => [['prescribing_level_code' => null], ['prescribing_level_code' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'code' => $payload['code'],
        'name' => $payload['name'],
    ],
]);

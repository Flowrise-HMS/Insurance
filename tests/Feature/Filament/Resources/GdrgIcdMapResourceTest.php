<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource\Pages\CreateGdrgIcdMap;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource\Pages\EditGdrgIcdMap;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource\Pages\ListGdrgIcdMaps;
use Modules\Insurance\Models\GdrgIcdMap;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => GdrgIcdMapResource::class,
    'subject' => 'GdrgIcdMap',
    'model' => GdrgIcdMap::class,
    'listPage' => ListGdrgIcdMaps::class,
    'createPage' => CreateGdrgIcdMap::class,
    'editPage' => EditGdrgIcdMap::class,
    'searchColumn' => 'icd10_code',
    'sortColumn' => 'icd10_code',
    'filter' => [
        'name' => 'service_type',
        'value' => 'OUT',
        'attribute' => 'service_type',
    ],
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'makeRecords' => fn (TestCase $test, int $count) => GdrgIcdMap::factory()->count($count)->create([
        'service_type' => 'OUT',
    ]),
    'createForm' => fn (): array => [
        'icd10_code' => 'A09.0',
        'gdrg_code' => 'GDRG'.fake()->unique()->numerify('###'),
        'description' => 'Infectious gastroenteritis',
        'service_type' => 'OUT',
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'description' => 'Updated G-DRG mapping',
    ],
    'schemaState' => fn (mixed $test, GdrgIcdMap $record): array => [
        'icd10_code' => $record->icd10_code,
        'gdrg_code' => $record->gdrg_code,
    ],
    'requiredValidation' => [
        'icd10 code is required' => [['icd10_code' => null], ['icd10_code' => 'required']],
        'gdrg code is required' => [['gdrg_code' => null], ['gdrg_code' => 'required']],
        'service type is required' => [['service_type' => null], ['service_type' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'icd10_code' => $payload['icd10_code'],
        'gdrg_code' => $payload['gdrg_code'],
    ],
]);

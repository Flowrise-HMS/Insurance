<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\Pages\CreateTariffBook;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\Pages\EditTariffBook;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\Pages\ListTariffBooks;
use Modules\Insurance\Models\TariffBook;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => TariffBookResource::class,
    'subject' => 'TariffBook',
    'model' => TariffBook::class,
    'listPage' => ListTariffBooks::class,
    'createPage' => CreateTariffBook::class,
    'editPage' => EditTariffBook::class,
    'searchColumn' => 'code',
    'sortColumn' => 'code',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'uniqueField' => 'code',
    'createForm' => fn (): array => [
        'code' => strtoupper(fake()->unique()->bothify('BOOK###')),
        'name' => fake()->unique()->words(3, true).' Tariff',
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'name' => 'Updated tariff book',
    ],
    'schemaState' => fn (mixed $test, TariffBook $record): array => [
        'code' => $record->code,
        'name' => $record->name,
    ],
    'requiredValidation' => [
        'code is required' => [['code' => null], ['code' => 'required']],
        'name is required' => [['name' => null], ['name' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'code' => $payload['code'],
        'name' => $payload['name'],
    ],
]);

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\CreatePayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\EditPayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\ListPayers;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\ViewPayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\PayerResource;
use Modules\Insurance\Models\Payer;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => PayerResource::class,
    'subject' => 'Payer',
    'model' => Payer::class,
    'listPage' => ListPayers::class,
    'createPage' => CreatePayer::class,
    'editPage' => EditPayer::class,
    'viewPage' => ViewPayer::class,
    'searchColumn' => 'name',
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'makeRecord' => fn (TestCase $test, array $attributes = []): Payer => Payer::factory()->create($attributes),
    'makeRecords' => fn (TestCase $test, int $count) => Payer::factory()->count($count)->create([
        'type' => PayerType::PRIVATE,
    ]),
    'createForm' => fn (): array => [
        'code' => strtoupper(fake()->unique()->bothify('PAY###')),
        'name' => fake()->unique()->company().' Insurance',
        'type' => PayerType::PRIVATE->value,
    ],
    'updateForm' => fn (): array => [
        'name' => 'Updated '.fake()->unique()->company(),
    ],
    'schemaState' => fn (mixed $test, Payer $record): array => [
        'code' => $record->code,
        'name' => $record->name,
        'type' => $record->type,
    ],
    'requiredValidation' => [
        'code is required' => [['code' => null], ['code' => 'required']],
        'name is required' => [['name' => null], ['name' => 'required']],
        'type is required' => [['type' => null], ['type' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'code' => $payload['code'],
        'name' => $payload['name'],
        'type' => $payload['type'],
    ],
]);

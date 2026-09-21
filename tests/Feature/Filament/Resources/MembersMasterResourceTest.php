<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource\Pages\CreateMemberMaster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource\Pages\EditMemberMaster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource\Pages\ListMemberMasters;
use Modules\Insurance\Models\MembersMaster;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => MembersMasterResource::class,
    'subject' => 'MembersMaster',
    'model' => MembersMaster::class,
    'listPage' => ListMemberMasters::class,
    'createPage' => CreateMemberMaster::class,
    'editPage' => EditMemberMaster::class,
    'searchColumn' => 'member_number',
    'sortColumn' => 'member_number',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'createForm' => fn (): array => [
        'member_number' => fake()->unique()->numerify('#########'),
        'card_serial_number' => strtoupper(fake()->unique()->bothify('################')),
        'first_name' => 'Ama',
        'last_name' => 'Member',
        'gender' => 'F',
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'first_name' => 'Updated',
        'last_name' => 'Member',
    ],
    'schemaState' => fn (mixed $test, MembersMaster $record): array => [
        'member_number' => $record->member_number,
        'card_serial_number' => $record->card_serial_number,
    ],
    'requiredValidation' => [
        'member number is required' => [['member_number' => null], ['member_number' => 'required']],
        'card serial is required' => [['card_serial_number' => null], ['card_serial_number' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'member_number' => $payload['member_number'],
        'first_name' => $payload['first_name'],
    ],
]);

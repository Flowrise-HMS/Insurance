<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource\Pages\CreateProviderCredentialing;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource\Pages\EditProviderCredentialing;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource\Pages\ListProviderCredentialings;
use Modules\Insurance\Models\ProviderCredentialing;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => ProviderCredentialingResource::class,
    'subject' => 'ProviderCredentialing',
    'model' => ProviderCredentialing::class,
    'listPage' => ListProviderCredentialings::class,
    'createPage' => CreateProviderCredentialing::class,
    'editPage' => EditProviderCredentialing::class,
    'searchColumn' => 'provider_name',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'createForm' => fn (): array => [
        'provider_name' => fake()->unique()->name(),
        'prescribing_level_code' => NhisPrescribingLevel::A->value,
        'accreditation_number' => fake()->unique()->bothify('ACC-####'),
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'provider_name' => 'Updated credentialed provider',
    ],
    'schemaState' => fn (mixed $test, ProviderCredentialing $record): array => [
        'provider_name' => $record->provider_name,
        'prescribing_level_code' => $record->prescribing_level_code,
    ],
    'requiredValidation' => [
        'prescribing level is required' => [['prescribing_level_code' => null], ['prescribing_level_code' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'provider_name' => $payload['provider_name'],
        'prescribing_level_code' => $payload['prescribing_level_code'],
    ],
]);

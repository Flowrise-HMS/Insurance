<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\InsuranceClaimResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Pages\EditInsuranceClaim;
use Modules\Insurance\Models\InsuranceClaim;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Billing', 'Insurance']);
});

FilamentResourceTestSuite::register([
    'resource' => InsuranceClaimResource::class,
    'subject' => 'InsuranceClaim',
    'model' => InsuranceClaim::class,
    'editPage' => EditInsuranceClaim::class,
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'makeRecord' => fn (TestCase $test, array $attributes = []): InsuranceClaim => InsuranceClaim::factory()->create($attributes),
]);

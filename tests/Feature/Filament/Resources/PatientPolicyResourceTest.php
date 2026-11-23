<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\CreatePatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\EditPatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\ListPatientPolicies;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\ViewPatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\PatientPolicyResource;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Models\Patient;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Billing', 'Insurance']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
    $this->payer = Payer::factory()->create(['type' => PayerType::PRIVATE, 'is_active' => true]);
    $this->patient = Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $this->branch->id]));
});

FilamentResourceTestSuite::register([
    'resource' => PatientPolicyResource::class,
    'subject' => 'PatientPolicy',
    'model' => PatientPolicy::class,
    'listPage' => ListPatientPolicies::class,
    'createPage' => CreatePatientPolicy::class,
    'editPage' => EditPatientPolicy::class,
    'viewPage' => ViewPatientPolicy::class,
    'searchColumn' => 'member_number',
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'payer_id' => $test->payer->id,
        'member_number' => 'MEM-'.fake()->unique()->numerify('######'),
        'plan_code' => 'GOLD',
        'is_primary' => true,
        'is_active' => true,
    ],
    'updateForm' => fn (TestCase $test, PatientPolicy $record): array => [
        'plan_code' => 'SILVER',
    ],
    'requiredValidation' => [
        [['member_number' => null], ['member_number' => 'required']],
        [['payer_id' => null], ['payer_id' => 'required']],
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): PatientPolicy => PatientPolicy::factory()->create([
        'patient_id' => $test->patient->id,
        'payer_id' => $test->payer->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => PatientPolicy::factory()->count($count)->create([
        'patient_id' => $test->patient->id,
        'payer_id' => $test->payer->id,
    ]),
]);

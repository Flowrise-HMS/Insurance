<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Core\Models\Branch;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\CreatePatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\ListPatientPolicies;
use Modules\Insurance\Filament\RelationManagers\PatientPoliciesRelationManager;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Billing', 'Insurance']);
    $this->branch = Branch::factory()->create();
    $this->payer = Payer::factory()->create(['type' => PayerType::PRIVATE, 'is_active' => true]);
    $this->patient = Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $this->branch->id]));
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create(['branch_id' => $this->branch->id]));
});

it('keeps a single primary policy per patient', function (): void {
    $old = PatientPolicy::factory()->create([
        'patient_id' => $this->patient->id,
        'payer_id' => $this->payer->id,
        'is_primary' => true,
    ]);

    Livewire::test(CreatePatientPolicy::class)
        ->fillForm([
            'patient_id' => $this->patient->id,
            'payer_id' => $this->payer->id,
            'member_number' => 'NEW-123',
            'is_primary' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($old->fresh()->is_primary)->toBeFalse()
        ->and(PatientPolicy::query()->where('member_number', 'NEW-123')->sole()->is_primary)->toBeTrue();
});

it('only lists policies of patients in the current branch', function (): void {
    $other = Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => Branch::factory()->create()->id]));
    $mine = PatientPolicy::factory()->create(['patient_id' => $this->patient->id, 'payer_id' => $this->payer->id]);
    $theirs = PatientPolicy::factory()->create(['patient_id' => $other->id, 'payer_id' => $this->payer->id]);
    $this->setCurrentBranch($this->branch);

    Livewire::test(ListPatientPolicies::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('verifies a member from the table', function (): void {
    $policy = PatientPolicy::factory()->create(['patient_id' => $this->patient->id, 'payer_id' => $this->payer->id]);
    $this->setCurrentBranch($this->branch);

    Livewire::test(ListPatientPolicies::class)
        ->callAction(TestAction::make('verifyMember')->table($policy))
        ->assertNotified();

    expect($policy->fresh()->metadata)->toHaveKey('verification_status');
});

it('adds a policy from the patient record', function (): void {
    Livewire::test(PatientPoliciesRelationManager::class, [
        'ownerRecord' => $this->patient,
        'pageClass' => ViewPatient::class,
    ])
        ->callAction(TestAction::make('create')->table(), [
            'payer_id' => $this->payer->id,
            'member_number' => 'RM-555',
            'is_primary' => true,
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    expect(PatientPolicy::query()->where('patient_id', $this->patient->id)->where('member_number', 'RM-555')->exists())->toBeTrue();
});

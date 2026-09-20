<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Insurance\Filament\RelationManagers\PatientPoliciesRelationManager;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientPoliciesRelationManagerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules();
        Gate::before(fn () => true);
        Livewire::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_lists_the_patient_policies_with_their_payer_under_strict_lazy_loading(): void
    {
        $patient = Patient::withoutEvents(fn () => Patient::factory()->create());
        $payer = Payer::factory()->create(['name' => 'Acme Health']);
        $policies = PatientPolicy::factory()->count(2)->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
        ]);

        Model::preventLazyLoading(true);

        Livewire::test(PatientPoliciesRelationManager::class, [
            'ownerRecord' => $patient,
            'pageClass' => ViewPatient::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords($policies)
            ->assertSee('Acme Health');
    }
}

<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatientVerificationBadgeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    private function admin(): User
    {
        Permission::findOrCreate('ViewAny Patient', 'web');

        return User::factory()->create()->givePermissionTo('ViewAny Patient');
    }

    public function test_patient_list_shows_verified_badge(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create());
        PatientPolicyFactory::new()->create([
            'patient_id' => $patient->id,
            'metadata' => [
                'verification_status' => 'verified',
                'verified_at' => '2026-07-01 10:00:00',
                'verification_source' => 'members_master',
            ],
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListPatients::class)
            ->assertOk()
            ->assertSee('Member Verified');
    }

    public function test_patient_list_shows_invalid_badge_with_error_code(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create());
        PatientPolicyFactory::new()->create([
            'patient_id' => $patient->id,
            'metadata' => [
                'verification_status' => 'invalid',
                'verification_error_code' => '203',
            ],
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListPatients::class)
            ->assertOk()
            ->assertSee('Member Invalid (203)');
    }

    public function test_patient_list_shows_no_policy_for_uninsured_patient(): void
    {
        Patient::withoutEvents(fn () => PatientFactory::new()->create());

        Livewire::actingAs($this->admin())
            ->test(ListPatients::class)
            ->assertOk()
            ->assertSee('No policy');
    }
}

<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatientProfileVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Appointment', 'Clinical', 'Insurance']);
    }

    private function admin(): User
    {
        Permission::findOrCreate('View PatientProfile', 'web');

        return User::factory()->create()->givePermissionTo('View PatientProfile');
    }

    public function test_patient_profile_shows_verification_badge(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create());
        PatientPolicyFactory::new()->create([
            'patient_id' => $patient->id,
            'member_number' => '87654321',
            'metadata' => [
                'verification_status' => 'invalid',
                'verification_error_code' => '204',
                'verified_at' => '2026-07-01 10:00:00',
                'verification_source' => 'members_master',
            ],
        ]);

        Livewire::actingAs($this->admin())
            ->test(PatientProfile::class, ['patientId' => $patient->id])
            ->assertOk()
            ->assertSee('Member Invalid (204)')
            ->assertSee('87654321');
    }

    public function test_patient_profile_shows_master_data_import_status(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create());
        PatientPolicyFactory::new()->create([
            'patient_id' => $patient->id,
            'metadata' => [
                'verification_status' => 'verified',
                'verification_source' => 'members_master',
            ],
        ]);

        Livewire::actingAs($this->admin())
            ->test(PatientProfile::class, ['patientId' => $patient->id])
            ->assertOk()
            ->assertSee('Member Verified')
            ->assertSee('Not imported');
    }

    public function test_patient_profile_shows_no_policy_for_uninsured_patient(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create());

        Livewire::actingAs($this->admin())
            ->test(PatientProfile::class, ['patientId' => $patient->id])
            ->assertOk()
            ->assertSee('No policy');
    }
}

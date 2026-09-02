<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\InsuranceClaimResource;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InsuranceClaimGlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Billing', 'Insurance']);

        Permission::findOrCreate('ViewAny InsuranceClaim', 'web');
        Permission::findOrCreate('Update InsuranceClaim', 'web');
        $this->actingAs(User::factory()->create()->givePermissionTo('ViewAny InsuranceClaim', 'Update InsuranceClaim'));
    }

    public function test_claims_are_found_by_claim_number_and_patient_name(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'title' => null,
            'first_name' => 'Zainab',
            'middle_name' => null,
            'last_name' => 'Fuseini',
        ]));

        InsuranceClaimFactory::new()->create([
            'patient_id' => $patient->id,
            'claim_number' => 'CLM-GS-0001',
        ]);

        $byNumber = InsuranceClaimResource::getGlobalSearchResults('CLM-GS-0001');
        $this->assertCount(1, $byNumber);
        $this->assertSame('CLM-GS-0001', $byNumber->first()->title);
        $this->assertSame('Zainab Fuseini', $byNumber->first()->details['Patient'] ?? null);

        $this->assertCount(1, InsuranceClaimResource::getGlobalSearchResults('Fuseini'));
        $this->assertCount(0, InsuranceClaimResource::getGlobalSearchResults('no-such-claim'));
    }
}

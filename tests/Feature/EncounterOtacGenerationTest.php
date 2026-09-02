<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Models\Branch;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EncounterOtacGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected Patient $patient;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Insurance']);

        Http::preventStrayRequests();
        Cache::forget('insurance_otac_access_token');

        $this->branch = Branch::factory()->default()->create();
        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        Permission::findOrCreate('View ClinicalWorkspace', 'web');
        $this->user = User::factory()->create()->givePermissionTo('View ClinicalWorkspace');

        $payer = Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            ['name' => 'NHIS', 'type' => PayerType::NHIS, 'is_active' => true]
        );

        PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $this->patient->id,
            'member_number' => '18014180',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = true;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'secret';
        $settings->save();
    }

    protected function tearDown(): void
    {
        Cache::forget('insurance_otac_access_token');
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    protected function successBody(): array
    {
        return [
            'attendanceData' => [
                'hin' => '0018208473',
                'ccc' => '57566',
                'memberName' => 'ABDUL HAKIM  YAHUZA',
                'gender' => 'MALE',
                'dob' => '2000-09-10T00:00:00',
                'startDate' => '2025-11-30T00:00:00',
                'endDate' => '2026-11-29T00:00:00',
                'authID' => '185e113a-35a6-f111-9e6c-e0071bf203b7',
                'newCCC' => true,
            ],
            'statusCode' => 0,
            'statusMessage' => 'Attendance Generated Successfully',
        ];
    }

    public function test_creating_an_nhis_encounter_auto_generates_the_claim_check_code(): void
    {
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response([
                'accessToken' => ['token' => 'jwt-token', 'expiresIn' => 7200],
            ]),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::response($this->successBody()),
        ]);

        Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('encounterFormData.coverage_type', CoverageType::NHIS->value)
            ->call('createEncounter')
            ->assertHasNoErrors()
            ->assertNotified('NHIS claim code generated')
            ->assertSet('encounterFormData.claim_check_code', '57566');

        $encounter = Encounter::where('patient_id', $this->patient->id)->first();

        $this->assertSame('57566', $encounter->claim_check_code);
        $this->assertSame('otac', data_get($encounter->patient->insurancePolicies()->first()->metadata, 'verification_source'));
    }

    public function test_failed_generation_notifies_and_still_saves_the_encounter(): void
    {
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response([
                'accessToken' => ['token' => 'jwt-token', 'expiresIn' => 7200],
            ]),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::response([
                'attendanceData' => null,
                'statusCode' => 1,
                'statusMessage' => 'ATTENDANCE LOG ERROR: Client has NO ACTIVE NHIS Membership. Please advise client to renew their NHIS membership and try again.',
            ]),
        ]);

        Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('encounterFormData.coverage_type', CoverageType::NHIS->value)
            ->call('createEncounter')
            ->assertHasNoErrors()
            ->assertNotified('NHIS claim code generation failed');

        $encounter = Encounter::where('patient_id', $this->patient->id)->first();

        $this->assertNotNull($encounter);
        $this->assertNull($encounter->claim_check_code);
    }

    public function test_retry_button_generates_the_code_for_an_open_encounter(): void
    {
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response([
                'accessToken' => ['token' => 'jwt-token', 'expiresIn' => 7200],
            ]),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::sequence()
                ->push([
                    'attendanceData' => null,
                    'statusCode' => 1,
                    'statusMessage' => 'ATTENDANCE LOG ERROR: Client has NO ACTIVE NHIS Membership.',
                ])
                ->push($this->successBody()),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('encounterFormData.coverage_type', CoverageType::NHIS->value)
            ->call('createEncounter')
            ->assertNotified('NHIS claim code generation failed');

        $this->assertTrue($component->instance()->canGenerateClaimCheckCode());

        $component
            ->call('generateClaimCheckCode')
            ->assertNotified('NHIS claim code generated')
            ->assertSet('encounterFormData.claim_check_code', '57566');

        $this->assertSame('57566', Encounter::where('patient_id', $this->patient->id)->first()->claim_check_code);
        $this->assertFalse($component->instance()->canGenerateClaimCheckCode());
    }

    public function test_manual_claim_check_code_skips_generation(): void
    {
        Livewire::actingAs($this->user)
            ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->set('encounterFormData.coverage_type', CoverageType::NHIS->value)
            ->set('encounterFormData.claim_check_code', '12345')
            ->call('createEncounter')
            ->assertHasNoErrors();

        $this->assertSame('12345', Encounter::where('patient_id', $this->patient->id)->first()->claim_check_code);
        Http::assertNothingSent();
    }
}

<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Models\Branch;
use Modules\Insurance\DTOs\OtacAttendanceResult;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\MembersMaster;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\MemberVerificationService;
use Modules\Insurance\Services\Otac\NhisAttendanceService;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class NhisAttendanceServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Insurance']);

        Http::preventStrayRequests();
        Cache::forget('insurance_otac_access_token');

        $this->branch = BranchFactory::new()->create();
        $this->patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id])
        );
    }

    protected function tearDown(): void
    {
        Cache::forget('insurance_otac_access_token');
        parent::tearDown();
    }

    protected function configureOtac(bool $enabled = true): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = $enabled;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'secret';
        $settings->save();
    }

    protected function createNhisPolicy(string $memberNumber = '18014180'): PatientPolicy
    {
        $payer = Payer::query()->firstOrCreate(
            ['code' => 'nhis'],
            ['name' => 'NHIS', 'type' => PayerType::NHIS, 'is_active' => true]
        );

        return PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $this->patient->id,
            'member_number' => $memberNumber,
            'is_active' => true,
            'is_primary' => true,
        ]);
    }

    protected function createNhisEncounter(array $attributes = []): Encounter
    {
        return EncounterFactory::new()->create(array_merge([
            'patient_id' => $this->patient->id,
            'branch_id' => $this->branch->id,
            'coverage_type' => CoverageType::NHIS,
            'claim_check_code' => null,
        ], $attributes));
    }

    protected function fakeSuccessfulGeneration(): void
    {
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response([
                'accessToken' => ['token' => 'jwt-token', 'expiresIn' => 7200],
            ]),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::response([
                'attendanceData' => [
                    'hin' => '0018208473',
                    'attendanceDate' => '2026-09-01T00:00:00',
                    'ccc' => '57566',
                    'memberPhone' => '0547189420',
                    'memberName' => 'ABDUL HAKIM  YAHUZA',
                    'gender' => 'MALE',
                    'dob' => '2000-09-10T00:00:00',
                    'startDate' => '2025-11-30T00:00:00',
                    'endDate' => '2026-11-29T00:00:00',
                    'authID' => '185e113a-35a6-f111-9e6c-e0071bf203b7',
                    'newCCC' => true,
                    'attendanceType' => 'NHIS',
                ],
                'statusCode' => 0,
                'statusMessage' => 'Attendance Generated Successfully',
            ]),
        ]);
    }

    public function test_generates_ccc_and_persists_member_details(): void
    {
        $this->configureOtac();
        $policy = $this->createNhisPolicy();
        $encounter = $this->createNhisEncounter();
        $this->fakeSuccessfulGeneration();

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_GENERATED, $result->status);
        $this->assertSame('57566', $result->ccc);

        $encounter->refresh();
        $this->assertSame('57566', $encounter->claim_check_code);
        $this->assertSame('0018208473', data_get($encounter->metadata, 'nhis_attendance.hin'));
        $this->assertSame('185e113a-35a6-f111-9e6c-e0071bf203b7', data_get($encounter->metadata, 'nhis_attendance.auth_id'));

        $policy->refresh();
        $this->assertSame('verified', data_get($policy->metadata, 'verification_status'));
        $this->assertSame('otac', data_get($policy->metadata, 'verification_source'));
        $this->assertSame('ABDUL HAKIM  YAHUZA', data_get($policy->metadata, 'otac_member.member_name'));
        $this->assertSame('2025-11-30', $policy->effective_from?->toDateString());
        $this->assertSame('2026-11-29', $policy->effective_to?->toDateString());

        $badge = app(MemberVerificationService::class)->badge($policy);
        $this->assertSame('verified', $badge['status']);
    }

    public function test_success_upserts_the_members_master_roster(): void
    {
        $this->configureOtac();
        $this->createNhisPolicy();
        $encounter = $this->createNhisEncounter();
        $this->fakeSuccessfulGeneration();

        app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $member = MembersMaster::query()->where('member_number', '18014180')->first();

        $this->assertNotNull($member);
        $this->assertSame('YAHUZA', $member->last_name);
        $this->assertSame('ABDUL HAKIM', $member->first_name);
        $this->assertTrue($member->is_active);
        $this->assertSame('otac', $member->source_file);
        $this->assertSame('2026-11-29', $member->valid_to?->toDateString());
    }

    public function test_success_updates_existing_roster_row_instead_of_duplicating(): void
    {
        $this->configureOtac();
        $this->createNhisPolicy();
        $encounter = $this->createNhisEncounter();
        $this->fakeSuccessfulGeneration();

        MembersMaster::query()->create([
            'member_number' => '18014180',
            'card_serial_number' => 'UWJPL120A0093',
            'is_active' => false,
        ]);

        app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $rows = MembersMaster::query()->where('member_number', '18014180')->get();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is_active);
        $this->assertSame('UWJPL120A0093', $rows->first()->card_serial_number);
    }

    public function test_nhia_rejection_leaves_encounter_untouched(): void
    {
        $this->configureOtac();
        $this->createNhisPolicy('90439432');
        $encounter = $this->createNhisEncounter();

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

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('NO ACTIVE NHIS Membership', (string) $result->message);

        $encounter->refresh();
        $this->assertNull($encounter->claim_check_code);
        $this->assertNull(data_get($encounter->metadata, 'nhis_attendance'));
    }

    public function test_skips_when_otac_is_disabled(): void
    {
        $this->configureOtac(enabled: false);
        $this->createNhisPolicy();
        $encounter = $this->createNhisEncounter();

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_SKIPPED, $result->status);
        Http::assertNothingSent();
    }

    public function test_skips_non_nhis_coverage(): void
    {
        $this->configureOtac();
        $this->createNhisPolicy();
        $encounter = $this->createNhisEncounter(['coverage_type' => CoverageType::NONE]);

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_SKIPPED, $result->status);
        Http::assertNothingSent();
    }

    public function test_skips_when_claim_check_code_already_present(): void
    {
        $this->configureOtac();
        $this->createNhisPolicy();
        $encounter = $this->createNhisEncounter(['claim_check_code' => '12345']);

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_SKIPPED, $result->status);
        Http::assertNothingSent();
    }

    public function test_skips_when_patient_has_no_membership_or_ghana_card(): void
    {
        $this->configureOtac();
        $encounter = $this->createNhisEncounter();

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_SKIPPED, $result->status);
        Http::assertNothingSent();
    }

    public function test_falls_back_to_ghana_card_when_no_nhis_policy(): void
    {
        $this->configureOtac();
        $encounter = $this->createNhisEncounter();

        PatientIdentifier::query()->create([
            'patient_id' => $this->patient->id,
            'type' => IdentifierType::NATIONAL_ID->value,
            'value' => 'GHA-728442965-9',
        ]);

        $this->fakeSuccessfulGeneration();

        $result = app(NhisAttendanceService::class)->generateForEncounter($encounter);

        $this->assertSame(OtacAttendanceResult::STATUS_GENERATED, $result->status);
        $this->assertSame('GHANACARD', data_get($encounter->refresh()->metadata, 'nhis_attendance.card_type'));
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/attendance/generate')
                ? $request['cardType'] === 'GHANACARD' && $request['cardNo'] === 'GHA-728442965-9'
                : true;
        });
        $this->assertSame(0, MembersMaster::query()->count());
    }
}

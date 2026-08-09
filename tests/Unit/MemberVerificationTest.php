<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\MembersMasterFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Models\MembersMaster;
use Modules\Insurance\Services\MemberVerificationService;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Insurance\Verification\OfflineMasterVerifier;
use Tests\TestCase;

class MemberVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);

        $settings = app(InsuranceSettings::class);
        $settings->member_verification_mode = 'offline';
        $settings->save();
    }

    public function test_verifies_member_present_in_master_table(): void
    {
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $result = app(OfflineMasterVerifier::class)->verifyNumbers('87654321', 'UWJPL120A0093');

        $this->assertTrue($result->verified());
        $this->assertSame('members_master', $result->source);
    }

    public function test_rejects_member_missing_from_master_table_with_203(): void
    {
        $result = app(OfflineMasterVerifier::class)->verifyNumbers('99999999', 'UWJPL120A0093');

        $this->assertFalse($result->verified());
        $this->assertSame('203', $result->errorCode);
    }

    public function test_rejects_card_serial_mismatch_with_204(): void
    {
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        $result = app(OfflineMasterVerifier::class)->verifyNumbers('87654321', 'UWJPL120A00BAD');

        $this->assertFalse($result->verified());
        $this->assertSame('204', $result->errorCode);
    }

    public function test_rejects_missing_identifiers_with_204(): void
    {
        $result = app(OfflineMasterVerifier::class)->verifyNumbers('', '');

        $this->assertFalse($result->verified());
        $this->assertSame('204', $result->errorCode);
    }

    public function test_rejects_expired_member_with_016(): void
    {
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
            'valid_from' => now()->subYears(2),
            'valid_to' => now()->subDay(),
        ]);

        $result = app(OfflineMasterVerifier::class)->verifyNumbers('87654321', 'UWJPL120A0093');

        $this->assertFalse($result->verified());
        $this->assertSame('016', $result->errorCode);
    }

    public function test_rejects_not_yet_active_member_with_016(): void
    {
        MembersMasterFactory::new()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
            'valid_from' => now()->addDay(),
            'valid_to' => now()->addYear(),
        ]);

        $result = app(OfflineMasterVerifier::class)->verifyNumbers('87654321', 'UWJPL120A0093');

        $this->assertFalse($result->verified());
        $this->assertSame('016', $result->errorCode);
    }

    public function test_disabled_mode_returns_unverified(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->member_verification_mode = 'disabled';
        $settings->save();

        $result = app(MemberVerificationService::class)->verifyNumbers('87654321', 'UWJPL120A0093');

        $this->assertSame('unverified', $result->status);
        $this->assertSame('disabled', $result->source);
    }

    public function test_service_persists_verification_on_policy_metadata(): void
    {
        $policy = PatientPolicyFactory::new()->create([
            'member_number' => '87654321',
            'metadata' => ['card_serial_number' => 'UWJPL120A0093'],
        ]);

        MembersMaster::query()->create([
            'member_number' => '87654321',
            'card_serial_number' => 'UWJPL120A0093',
        ]);

        app(MemberVerificationService::class)->verify($policy);

        $policy->refresh();

        $this->assertSame('verified', $policy->metadata['verification_status']);
        $this->assertSame('members_master', $policy->metadata['verification_source']);
    }
}

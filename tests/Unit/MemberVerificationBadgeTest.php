<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\MembersMasterFactory;
use Modules\Insurance\Database\Factories\PatientPolicyFactory;
use Modules\Insurance\Services\MemberVerificationService;
use Tests\TestCase;

class MemberVerificationBadgeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_badge_reflects_verified_status(): void
    {
        $policy = PatientPolicyFactory::new()->create([
            'metadata' => [
                'verification_status' => 'verified',
                'verification_error_code' => null,
                'verified_at' => '2026-07-01 10:00:00',
                'verification_source' => 'members_master',
            ],
        ]);

        $badge = app(MemberVerificationService::class)->badge($policy);

        $this->assertSame('verified', $badge['status']);
        $this->assertSame('Member Verified', $badge['label']);
        $this->assertSame('success', $badge['color']);
        $this->assertNull($badge['error_code']);
        $this->assertSame('members_master', $badge['source']);
    }

    public function test_badge_reflects_invalid_status_with_error_code(): void
    {
        $policy = PatientPolicyFactory::new()->create([
            'metadata' => [
                'verification_status' => 'invalid',
                'verification_error_code' => '203',
                'verified_at' => '2026-07-01 10:00:00',
                'verification_source' => 'members_master',
            ],
        ]);

        $badge = app(MemberVerificationService::class)->badge($policy);

        $this->assertSame('invalid', $badge['status']);
        $this->assertSame('Member Invalid (203)', $badge['label']);
        $this->assertSame('danger', $badge['color']);
        $this->assertSame('203', $badge['error_code']);
    }

    public function test_badge_reflects_unverified_when_verification_disabled(): void
    {
        $policy = PatientPolicyFactory::new()->create([
            'metadata' => [
                'verification_status' => 'unverified',
                'verification_source' => 'disabled',
            ],
        ]);

        $badge = app(MemberVerificationService::class)->badge($policy);

        $this->assertSame('unverified', $badge['status']);
        $this->assertSame('Verification Disabled', $badge['label']);
        $this->assertSame('gray', $badge['color']);
    }

    public function test_badge_is_not_checked_when_no_verification_recorded(): void
    {
        $policy = PatientPolicyFactory::new()->create();

        $badge = app(MemberVerificationService::class)->badge($policy);

        $this->assertSame('not_checked', $badge['status']);
        $this->assertSame('Not Verified', $badge['label']);
        $this->assertSame('gray', $badge['color']);
    }

    public function test_master_data_status_reports_imported_state(): void
    {
        $status = app(MemberVerificationService::class)->masterDataStatus();

        $this->assertFalse($status['imported']);
        $this->assertSame(0, $status['members']);

        MembersMasterFactory::new()->create();

        $status = app(MemberVerificationService::class)->masterDataStatus();

        $this->assertTrue($status['imported']);
        $this->assertSame(1, $status['members']);
    }
}

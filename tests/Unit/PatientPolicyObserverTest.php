<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class PatientPolicyObserverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    public function test_updates_legacy_nhis_identifier_when_nhis_policy_saved(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create([
            'code' => 'nhis',
            'type' => PayerType::NHIS,
        ]);

        $identifier = PatientIdentifier::factory()->create([
            'patient_id' => $patient->id,
            'type' => 'nhis',
            'value' => 'old-value',
            'issuer' => 'NIA',
        ]);

        $policy = PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'member_number' => 'NHIS123456',
            'is_active' => true,
        ]);

        $identifier->refresh();

        $this->assertEquals('NHIS123456', $identifier->value);
        $this->assertEquals('NHIA', $identifier->issuer);
    }

    public function test_does_not_create_new_identifier_when_none_exists(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create([
            'code' => 'nhis',
            'type' => PayerType::NHIS,
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'member_number' => 'NHIS123456',
            'is_active' => true,
        ]);

        $identifiers = PatientIdentifier::query()
            ->where('patient_id', $patient->id)
            ->get();

        $this->assertCount(0, $identifiers);
    }

    public function test_does_not_update_identifiers_for_non_nhis_payer(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create([
            'type' => PayerType::PRIVATE,
        ]);

        $identifier = PatientIdentifier::factory()->create([
            'patient_id' => $patient->id,
            'type' => 'nhis',
            'value' => 'should-stay',
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'member_number' => 'PRIV123',
            'is_active' => true,
        ]);

        $identifier->refresh();

        $this->assertEquals('should-stay', $identifier->value);
    }
}

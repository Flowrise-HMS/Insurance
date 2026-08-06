<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientInsuranceServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected PatientInsuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);

        $this->service = new PatientInsuranceService;
    }

    public function test_sync_creates_policy_on_first_save(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create();

        $policy = $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
            'insurance_card_serial_number' => 'UWJPL120A0093',
            'insurance_mother_member_number' => '87654321',
            'insurance_mother_card_serial_number' => 'MOTHERSERIAL1',
        ]);

        $this->assertInstanceOf(PatientPolicy::class, $policy);
        $this->assertTrue($policy->exists);
        $this->assertEquals($payer->id, $policy->payer_id);
        $this->assertEquals('123456789', $policy->member_number);
        $this->assertSame('UWJPL120A0093', data_get($policy->metadata, 'card_serial_number'));
        $this->assertSame('87654321', data_get($policy->metadata, 'mother_member_number'));
        $this->assertTrue($policy->is_primary);
        $this->assertTrue($policy->is_active);
    }

    public function test_sync_updates_existing_policy_no_duplicate(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create();

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
            'insurance_card_serial_number' => 'UWJPL120A0093',
        ]);

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '987654321',
            'insurance_card_serial_number' => 'UWJPL120A0094',
        ]);

        $policies = PatientPolicy::query()
            ->where('patient_id', $patient->id)
            ->where('payer_id', $payer->id)
            ->get();

        $this->assertCount(1, $policies);
        $this->assertEquals('987654321', $policies->first()->member_number);
        $this->assertSame('UWJPL120A0094', data_get($policies->first()->metadata, 'card_serial_number'));
    }

    public function test_form_data_from_policy_round_trips(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create();

        $input = [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
            'insurance_card_serial_number' => 'UWJPL120A0093',
            'insurance_mother_member_number' => '55555555',
            'insurance_mother_card_serial_number' => 'MOTHERSERIAL1',
        ];

        $policy = $this->service->syncFromFormData($patient->id, $input);

        $roundTripped = $this->service->formDataFromPolicy($policy);

        $this->assertEquals($payer->id, $roundTripped['insurance_payer_id']);
        $this->assertEquals('123456789', $roundTripped['insurance_member_number']);
        $this->assertSame('UWJPL120A0093', $roundTripped['insurance_card_serial_number']);
        $this->assertSame('55555555', $roundTripped['insurance_mother_member_number']);
        $this->assertSame('MOTHERSERIAL1', $roundTripped['insurance_mother_card_serial_number']);
    }

    public function test_sync_clears_card_serial_when_form_value_removed(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create();

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
            'insurance_card_serial_number' => 'UWJPL120A0093',
        ]);

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
            'insurance_card_serial_number' => '',
        ]);

        $policy = PatientPolicy::query()
            ->where('patient_id', $patient->id)
            ->where('payer_id', $payer->id)
            ->first();

        $this->assertNull(data_get($policy->metadata, 'card_serial_number'));
    }

    public function test_sync_preserves_temporary_card_number_when_not_in_form(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create();

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
            'insurance_temporary_card_number' => 'TMP999',
        ]);

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
        ]);

        $policy = PatientPolicy::query()
            ->where('patient_id', $patient->id)
            ->where('payer_id', $payer->id)
            ->first();

        $this->assertSame('TMP999', data_get($policy->metadata, 'temporary_card_number'));
    }

    public function test_sync_returns_null_when_disabled(): void
    {
        $patient = Patient::factory()->create();

        config()->set('insurance.enabled', false);

        $result = $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => Payer::factory()->create()->id,
            'insurance_member_number' => '123456789',
        ]);

        $this->assertNull($result);
    }

    public function test_sync_returns_null_when_no_payer_selected(): void
    {
        $patient = Patient::factory()->create();

        $result = $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => null,
        ]);

        $this->assertNull($result);
    }

    public function test_form_data_from_policy_returns_empty_for_null(): void
    {
        $result = $this->service->formDataFromPolicy(null);

        $this->assertSame([], $result);
    }
}

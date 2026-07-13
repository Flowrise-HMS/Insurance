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
        ]);

        $this->assertInstanceOf(PatientPolicy::class, $policy);
        $this->assertTrue($policy->exists);
        $this->assertEquals($payer->id, $policy->payer_id);
        $this->assertEquals('123456789', $policy->member_number);
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
        ]);

        $this->service->syncFromFormData($patient->id, [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '987654321',
        ]);

        $policies = PatientPolicy::query()
            ->where('patient_id', $patient->id)
            ->where('payer_id', $payer->id)
            ->get();

        $this->assertCount(1, $policies);
        $this->assertEquals('987654321', $policies->first()->member_number);
    }

    public function test_form_data_from_policy_round_trips(): void
    {
        $patient = Patient::factory()->create();
        $payer = Payer::factory()->create();

        $input = [
            'insurance_payer_id' => $payer->id,
            'insurance_member_number' => '123456789',
        ];

        $policy = $this->service->syncFromFormData($patient->id, $input);

        $roundTripped = $this->service->formDataFromPolicy($policy);

        $this->assertEquals($payer->id, $roundTripped['insurance_payer_id']);
        $this->assertEquals('123456789', $roundTripped['insurance_member_number']);
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

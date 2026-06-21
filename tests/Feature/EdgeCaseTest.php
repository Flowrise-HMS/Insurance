<?php

namespace Modules\Insurance\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Enums\RejectionClass;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Tests\TestCase;

class EdgeCaseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules();
    }

    // ─── ClaimStatus enum ───────────────────────────────────────────────────

    public function test_claim_status_values(): void
    {
        $values = ClaimStatus::values();
        $this->assertContains('draft', $values);
        $this->assertContains('validated', $values);
        $this->assertContains('queued', $values);
        $this->assertContains('submitted', $values);
        $this->assertContains('accepted', $values);
        $this->assertContains('partial', $values);
        $this->assertContains('rejected', $values);
        $this->assertContains('reconciled', $values);
        $this->assertCount(8, $values);
    }

    public function test_claim_status_labels(): void
    {
        $this->assertSame('Draft', ClaimStatus::DRAFT->getLabel());
        $this->assertSame('Accepted', ClaimStatus::ACCEPTED->getLabel());
        $this->assertSame('Reconciled', ClaimStatus::RECONCILED->getLabel());
    }

    // ─── PayerType enum ─────────────────────────────────────────────────────

    public function test_payer_type_values(): void
    {
        $values = PayerType::values();
        $this->assertContains('nhis', $values);
        $this->assertContains('private', $values);
        $this->assertCount(2, $values);
    }

    // ─── ClaimDecisionStatus enum ───────────────────────────────────────────

    public function test_claim_decision_status_values(): void
    {
        $values = ClaimDecisionStatus::values();
        $this->assertContains('approved', $values);
        $this->assertContains('partial', $values);
        $this->assertContains('rejected', $values);
        $this->assertContains('pending', $values);
    }

    // ─── RejectionClass enum ────────────────────────────────────────────────

    public function test_rejection_class_values(): void
    {
        $values = RejectionClass::values();
        $this->assertContains('transport_rejected', $values);
        $this->assertContains('schema_rejected', $values);
        $this->assertContains('business_rejected', $values);
        $this->assertContains('payer_rejected', $values);
    }

    // ─── Payer model ────────────────────────────────────────────────────────

    public function test_payer_has_uuid(): void
    {
        $payer = Payer::factory()->create();
        $this->assertNotNull($payer->id);
    }

    public function test_payer_casts_type_as_enum(): void
    {
        $payer = Payer::factory()->create(['type' => PayerType::NHIS]);
        $this->assertTrue($payer->type instanceof PayerType);
        $this->assertSame(PayerType::NHIS, $payer->type);
    }

    // ─── PatientPolicy model ────────────────────────────────────────────────

    public function test_policy_has_uuid(): void
    {
        $policy = PatientPolicy::factory()->create();
        $this->assertNotNull($policy->id);
    }

    public function test_policy_belongs_to_payer(): void
    {
        $policy = PatientPolicy::factory()->create();
        $this->assertNotNull($policy->payer);
    }

    public function test_policy_belongs_to_patient(): void
    {
        $policy = PatientPolicy::factory()->create();
        $this->assertNotNull($policy->patient);
    }

    // ─── InsuranceClaim model ───────────────────────────────────────────────

    public function test_claim_has_uuid(): void
    {
        $claim = InsuranceClaim::factory()->create();
        $this->assertNotNull($claim->id);
    }

    public function test_claim_casts_status_as_enum(): void
    {
        $claim = InsuranceClaim::factory()->create(['status' => ClaimStatus::DRAFT]);
        $this->assertTrue($claim->status instanceof ClaimStatus);
        $this->assertSame(ClaimStatus::DRAFT, $claim->status);
    }
}

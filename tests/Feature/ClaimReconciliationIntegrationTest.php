<?php

namespace Modules\Insurance\Tests\Feature;

use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\ClaimReconciliationService;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class ClaimReconciliationIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Patient', 'Clinical', 'Appointment', 'Billing', 'Insurance'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_reconciliation_updates_billing_patient_responsibility_on_rejection(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
        ]));

        $payer = Payer::query()->create([
            'code' => 'nhis',
            'name' => 'NHIS',
            'type' => PayerType::Nhis,
            'is_active' => true,
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'total' => 80,
            'amount_paid' => 0,
        ]));

        $invoiceLine = InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Medication',
            'quantity' => 1,
            'unit_price' => 80,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 80,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 0,
            'insurance_expected_amount' => 80,
        ]);

        $claim = InsuranceClaim::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'claim_number' => 'CLM-REJ-001',
            'status' => 'submitted',
            'total_billed_amount' => 80,
            'currency' => 'GHS',
        ]);

        InsuranceClaimLine::query()->create([
            'claim_id' => $claim->id,
            'invoice_line_id' => $invoiceLine->id,
            'description' => 'Medication',
            'quantity' => 1,
            'billed_amount' => 80,
            'approved_amount' => 0,
            'rejected_amount' => 0,
        ]);

        app(ClaimReconciliationService::class)->applyFeedback($claim, [
            'external_reference' => 'CLM-REJ-001',
            'decision_status' => 'rejected',
            'rejection_class' => 'payer_rejected',
            'rejection_code' => '301',
            'rejection_reason' => 'Not covered',
            'raw_payload' => '<Feedback/>',
            'normalized_payload' => ['status' => 'rejected'],
        ]);

        $invoiceLine->refresh();
        $this->assertSame('80.00', (string) $invoiceLine->patient_responsibility_amount);
        $this->assertSame(InvoiceLineStatus::Unpaid, $invoiceLine->line_status);
    }
}

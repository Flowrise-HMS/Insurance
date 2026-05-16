<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimFeedback;
use Modules\Insurance\Models\InsuranceClaimLine;

class ClaimReconciliationService
{
    public function __construct(
        protected InvoiceTotalsService $totalsService
    ) {}

    public function applyFeedback(InsuranceClaim $claim, array $feedback): InsuranceClaimFeedback
    {
        $started = microtime(true);

        $record = DB::transaction(function () use ($claim, $feedback) {
            $claim->loadMissing(['lines.invoiceLine']);

            $feedbackHash = hash('sha256', json_encode($feedback));
            $record = InsuranceClaimFeedback::query()->firstOrCreate(
                ['feedback_hash' => $feedbackHash],
                [
                    'claim_id' => $claim->id,
                    'claim_submission_id' => data_get($feedback, 'claim_submission_id'),
                    'external_reference' => data_get($feedback, 'external_reference'),
                    'feedback_type' => data_get($feedback, 'feedback_type', 'status'),
                    'decision_status' => data_get($feedback, 'decision_status', ClaimDecisionStatus::PENDING->value),
                    'rejection_class' => data_get($feedback, 'rejection_class'),
                    'rejection_code' => data_get($feedback, 'rejection_code'),
                    'rejection_reason' => data_get($feedback, 'rejection_reason'),
                    'raw_payload' => data_get($feedback, 'raw_payload'),
                    'normalized_payload' => data_get($feedback, 'normalized_payload', []),
                    'processed_at' => now(),
                ]
            );

            foreach ($claim->lines as $claimLine) {
                $this->applyClaimLineDecision($claimLine, $record->decision_status->value);
            }

            $claim->refresh();
            $claim->update([
                'status' => match ($record->decision_status) {
                    ClaimDecisionStatus::APPROVED => ClaimStatus::ACCEPTED,
                    ClaimDecisionStatus::PARTIAL => ClaimStatus::PARTIAL,
                    ClaimDecisionStatus::REJECTED => ClaimStatus::REJECTED,
                    default => ClaimStatus::SUBMITTED,
                },
                'reconciled_at' => now(),
            ]);

            return $record;
        });

        Log::info('insurance.claim.reconciled', [
            'claim_id' => $claim->id,
            'feedback_id' => $record->id,
            'decision_status' => $record->decision_status,
            'elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $record;
    }

    protected function applyClaimLineDecision(InsuranceClaimLine $claimLine, string $decisionStatus): void
    {
        $invoiceLine = $claimLine->invoiceLine;
        if (! $invoiceLine) {
            return;
        }

        if ($decisionStatus === ClaimDecisionStatus::APPROVED->value) {
            $claimLine->approved_amount = $claimLine->billed_amount;
            $claimLine->rejected_amount = '0.00';
            $invoiceLine->patient_responsibility_amount = '0.00';
        } elseif ($decisionStatus === ClaimDecisionStatus::PARTIAL->value) {
            $approved = (string) $claimLine->approved_amount;
            $claimLine->rejected_amount = bcsub((string) $claimLine->billed_amount, $approved, 2);
            $invoiceLine->patient_responsibility_amount = $claimLine->rejected_amount;
        } elseif ($decisionStatus === ClaimDecisionStatus::REJECTED->value) {
            $claimLine->approved_amount = '0.00';
            $claimLine->rejected_amount = (string) $claimLine->billed_amount;
            $invoiceLine->patient_responsibility_amount = (string) $claimLine->billed_amount;
        }

        $claimLine->decision_status = $decisionStatus;
        $claimLine->save();

        $invoiceLine->line_status = InvoiceLineStatus::Unpaid;
        $invoiceLine->save();

        if ($invoiceLine->invoice) {
            $this->totalsService->recalculate($invoiceLine->invoice->fresh(['lines']));
        }
    }
}

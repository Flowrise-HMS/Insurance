<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Jobs\PollInsuranceClaimFeedbackJob;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimSubmission;

class ClaimSubmissionService
{
    public function __construct(
        protected PayerConnectorRegistry $connectors
    ) {}

    public function submit(InsuranceClaim $claim): InsuranceClaimSubmission
    {
        $started = microtime(true);
        $claim->loadMissing('payer');
        $connectorCode = $claim->payer?->code ?? 'private-generic';
        $idempotencyKey = (string) Str::uuid();

        $submission = DB::transaction(function () use ($claim, $connectorCode, $idempotencyKey) {
            $connector = $this->connectors->forCode($connectorCode);
            $result = $connector->submitClaim($claim->fresh(['lines', 'policy', 'payer']), $idempotencyKey);

            $submission = InsuranceClaimSubmission::query()->create([
                'claim_id' => $claim->id,
                'connector_code' => $connectorCode,
                'submission_status' => (string) ($result['status'] ?? 'queued'),
                'idempotency_key' => $idempotencyKey,
                'external_reference' => $result['external_reference'] ?? null,
                'request_payload' => $result['request_payload'] ?? null,
                'response_payload' => $result['response_payload'] ?? null,
                'attempt_count' => 1,
                'submitted_at' => now(),
            ]);

            $claim->update([
                'status' => ClaimStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            PollInsuranceClaimFeedbackJob::dispatch((string) $claim->id, $submission->external_reference);

            return $submission;
        });

        Log::info('insurance.claim.submitted', [
            'claim_id' => $claim->id,
            'connector' => $connectorCode,
            'elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
            'submission_status' => $submission->submission_status,
        ]);

        return $submission;
    }
}

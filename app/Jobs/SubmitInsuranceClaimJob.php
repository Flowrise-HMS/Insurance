<?php

namespace Modules\Insurance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Services\ClaimSubmissionService;

class SubmitInsuranceClaimJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public string $claimId
    ) {
        $this->onQueue(config('insurance.queues.claims', 'insurance-claims'));
    }

    public function handle(ClaimSubmissionService $submissionService): void
    {
        $claim = InsuranceClaim::query()->find($this->claimId);
        if (! $claim) {
            return;
        }

        $submissionService->submit($claim);
    }
}

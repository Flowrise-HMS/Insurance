<?php

namespace Modules\Insurance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Services\ClaimReconciliationService;
use Modules\Insurance\Services\PayerConnectorRegistry;

class PollInsuranceClaimFeedbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 120, 300, 600, 1200];

    public function __construct(
        public string $claimId,
        public ?string $externalReference = null
    ) {
        $this->onQueue(config('insurance.queues.claims', 'insurance-claims'));
    }

    public function handle(PayerConnectorRegistry $registry, ClaimReconciliationService $reconciliations): void
    {
        $claim = InsuranceClaim::query()->with('payer')->find($this->claimId);
        if (! $claim || ! $claim->payer) {
            return;
        }

        $feedback = $registry
            ->forCode((string) $claim->payer->code)
            ->fetchClaimFeedback($claim, $this->externalReference);

        if (! $feedback) {
            return;
        }

        $reconciliations->applyFeedback($claim, $feedback);
    }
}

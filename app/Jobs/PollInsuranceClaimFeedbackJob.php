<?php

namespace Modules\Insurance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Insurance\Exceptions\ClaimFeedbackPendingException;
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

        try {
            $feedback = $registry
                ->forPayer($claim->payer)
                ->fetchClaimFeedback($claim, $this->externalReference);
        } catch (ClaimFeedbackPendingException) {
            // Not decided yet: poll again with the job's backoff until tries run out.
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
            }

            return;
        }

        if (! $feedback) {
            return;
        }

        $reconciliations->applyFeedback($claim, $feedback);
    }
}

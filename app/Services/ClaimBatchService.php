<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\DTOs\ExportedFile;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Schemes\InsuranceSchemeRegistry;
use Modules\Insurance\Schemes\Nhis\NhisClaimValidator;

class ClaimBatchService
{
    public function __construct(
        protected InsuranceSchemeRegistry $schemes,
        protected NhisClaimValidator $validator,
    ) {}

    public function vetClaim(InsuranceClaim $claim): InsuranceClaim
    {
        $result = $this->validator->validate($claim);

        if (! $result->valid) {
            throw new \InvalidArgumentException(implode(' ', $result->errors));
        }

        $claim->update([
            'status' => ClaimStatus::VALIDATED,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'metadata' => array_merge($claim->metadata ?? [], [
                'validation_warnings' => $result->warnings,
            ]),
        ]);

        if ($claim->batch_id) {
            $this->syncBatchReviewStatus($claim->batch);
        }

        return $claim->fresh();
    }

    public function vetAll(ClaimBatch $batch, bool $force = false): ClaimBatch
    {
        $batch->loadMissing('claims.lines');

        DB::transaction(function () use ($batch, $force) {
            foreach ($batch->claims as $claim) {
                if ($claim->status === ClaimStatus::VALIDATED) {
                    continue;
                }

                $result = $this->validator->validate($claim);

                if (! $result->valid && ! $force) {
                    throw new \InvalidArgumentException(
                        "Claim {$claim->claim_number}: ".implode(' ', $result->errors)
                    );
                }

                $claim->update([
                    'status' => ClaimStatus::VALIDATED,
                    'reviewed_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'metadata' => array_merge($claim->metadata ?? [], [
                        'validation_warnings' => $result->warnings,
                        'forced_vet' => $force && ! $result->valid,
                    ]),
                ]);
            }

            $batch->update(['status' => ClaimBatchStatus::VETTED]);
        });

        return $batch->fresh(['claims']);
    }

    public function export(ClaimBatch $batch, bool $force = false): ExportedFile
    {
        if ($batch->status !== ClaimBatchStatus::VETTED && $batch->status !== ClaimBatchStatus::EXPORTED) {
            $this->vetAll($batch, $force);
        }

        $scheme = $this->schemes->forCode($batch->scheme_code);
        $exported = $scheme->exportBatch($batch->fresh(['claims.patient', 'claims.policy', 'claims.lines']));

        DB::transaction(function () use ($batch, $exported) {
            $batch->claims()->update([
                'status' => ClaimStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            $batch->update([
                'status' => ClaimBatchStatus::EXPORTED,
                'exported_xml_path' => $exported->path,
                'exported_at' => now(),
            ]);
        });

        return $exported;
    }

    protected function syncBatchReviewStatus(ClaimBatch $batch): void
    {
        $batch->loadMissing('claims');

        $allValidated = $batch->claims->every(
            fn (InsuranceClaim $claim) => $claim->status === ClaimStatus::VALIDATED
                || $claim->status === ClaimStatus::SUBMITTED
        );

        if ($allValidated) {
            $batch->update(['status' => ClaimBatchStatus::VETTED]);

            return;
        }

        if ($batch->status === ClaimBatchStatus::GENERATED) {
            $batch->update(['status' => ClaimBatchStatus::UNDER_REVIEW]);
        }
    }
}

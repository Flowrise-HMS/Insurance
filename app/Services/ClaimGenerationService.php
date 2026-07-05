<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Schemes\InsuranceSchemeRegistry;
use Modules\Insurance\Schemes\Nhis\NhisClaimAssembler;
use Modules\Insurance\Support\ClaimBatchCriteria;

class ClaimGenerationService
{
    public function __construct(
        protected InsuranceSchemeRegistry $schemes,
        protected NhisClaimAssembler $nhisClaimAssembler,
    ) {}

    public function previewEligibleEncounters(ClaimBatchCriteria $criteria)
    {
        return $this->nhisClaimAssembler->previewEligibleEncounters($criteria);
    }

    public function generate(ClaimBatchCriteria $criteria): ClaimBatch
    {
        $scheme = $this->schemes->forCode($criteria->schemeCode);
        $payer = Payer::query()->where('code', $criteria->schemeCode)->firstOrFail();

        $batch = ClaimBatch::query()->create([
            'scheme_code' => $criteria->schemeCode,
            'payer_id' => $payer->id,
            'branch_id' => $criteria->branchId,
            'batch_number' => ClaimBatch::generateBatchNumber($criteria->branchId),
            'filter_criteria' => $criteria->toArray(),
            'service_year' => $criteria->year ?? (int) now()->format('Y'),
            'service_month' => $criteria->month ?? (int) now()->format('n'),
            'status' => ClaimBatchStatus::GENERATED,
            'master_table_versions' => app(\Modules\Core\Settings\InsuranceSettings::class)->master_table_versions,
            'created_by' => Auth::id(),
        ]);

        $result = $scheme->generateClaims($criteria, $batch);

        $batch->update([
            'claims_count' => $result->claims->count(),
            'batch_amount' => $result->claims->sum(fn ($claim) => (float) $claim->total_billed_amount),
        ]);

        return $batch->fresh(['claims.patient', 'claims.lines']);
    }
}

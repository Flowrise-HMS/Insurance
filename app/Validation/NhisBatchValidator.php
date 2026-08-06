<?php

namespace Modules\Insurance\Validation;

use Illuminate\Support\Carbon;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;

class NhisBatchValidator
{
    /**
     * @return array<int, ValidationItem>
     */
    public function validate(ClaimBatch $batch): array
    {
        $batch->loadMissing('claims');
        $items = [];

        $items = array_merge($items, $this->validateBatchAmount($batch));
        $items = array_merge($items, $this->validateClaimsCount($batch));
        $items = array_merge($items, $this->validateServicePeriod($batch));

        return $items;
    }

    /**
     * @return array<int, ValidationItem>
     */
    protected function validateBatchAmount(ClaimBatch $batch): array
    {
        $sum = $batch->claims->sum(fn (InsuranceClaim $claim) => (float) $claim->total_billed_amount);

        if (abs($sum - (float) $batch->batch_amount) > 0.005) {
            return [new ValidationItem(
                code: '109',
                message: "Batch amount {$batch->batch_amount} does not match the sum of claim amounts {$sum}.",
            )];
        }

        return [];
    }

    /**
     * @return array<int, ValidationItem>
     */
    protected function validateClaimsCount(ClaimBatch $batch): array
    {
        $count = $batch->claims->count();

        if ($count !== (int) $batch->claims_count) {
            return [new ValidationItem(
                code: '111',
                message: "Batch claims count {$batch->claims_count} does not match the actual count {$count}.",
            )];
        }

        return [];
    }

    /**
     * Error 120: at least 50% of claims must fall within the batch service month.
     *
     * @return array<int, ValidationItem>
     */
    protected function validateServicePeriod(ClaimBatch $batch): array
    {
        if (! $batch->service_year || ! $batch->service_month || $batch->claims->isEmpty()) {
            return [];
        }

        $matching = $batch->claims->filter(function (InsuranceClaim $claim) use ($batch) {
            $admissionDate = data_get($claim->nhia_payload, 'admission_date');

            if (! filled($admissionDate)) {
                return false;
            }

            try {
                $date = Carbon::parse($admissionDate);
            } catch (\Throwable) {
                return false;
            }

            return $date->year === (int) $batch->service_year
                && $date->month === (int) $batch->service_month;
        })->count();

        $ratio = $matching / $batch->claims->count();

        if ($ratio < 0.5) {
            return [new ValidationItem(
                code: '120',
                message: "Only {$matching} of {$batch->claims->count()} claims ({round($ratio * 100)}%) fall within the batch service month {$batch->service_year}-{$batch->service_month}.",
            )];
        }

        return [];
    }
}

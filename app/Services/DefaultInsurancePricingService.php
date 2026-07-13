<?php

namespace Modules\Insurance\Services;

use Carbon\Carbon;
use Modules\Core\Contracts\InsurancePricingResolver;
use Modules\Core\Models\Service;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\TariffItem;

class DefaultInsurancePricingService implements InsurancePricingResolver
{
    public function resolveForItem(
        string $patientId,
        string $itemType,
        ?string $externalCode,
        string $fallbackAmount,
        ?string $asOfDate = null
    ): array {
        $date = $asOfDate ? Carbon::parse($asOfDate)->toDateString() : now()->toDateString();

        $policy = PatientPolicy::query()
            ->where('patient_id', $patientId)
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('is_primary')
            ->latest('updated_at')
            ->first();

        if (! $policy || ! $externalCode) {
            return [
                'insurer_amount' => '0.00',
                'patient_amount' => $fallbackAmount,
                'tariff_price' => null,
                'source_version' => null,
                'policy_id' => $policy?->id,
                'payer_id' => $policy?->payer_id,
            ];
        }

        if ($itemType === 'service') {
            $service = Service::query()->find($externalCode);
            $externalCode = $service?->metadata['nhis_code'] ?? $externalCode;
        }

        $tariff = TariffItem::query()
            ->where('payer_id', $policy->payer_id)
            ->where('item_type', $itemType)
            ->where('external_code', $externalCode)
            ->where('is_active', true)
            ->first();

        if (! $tariff) {
            return [
                'insurer_amount' => '0.00',
                'patient_amount' => $fallbackAmount,
                'tariff_price' => null,
                'source_version' => null,
                'policy_id' => $policy->id,
                'payer_id' => $policy->payer_id,
            ];
        }

        $insurerAmount = (string) $tariff->price;
        $patientAmount = bccomp($fallbackAmount, $insurerAmount, 2) > 0
            ? bcsub($fallbackAmount, $insurerAmount, 2)
            : '0.00';

        return [
            'insurer_amount' => $insurerAmount,
            'patient_amount' => $patientAmount,
            'tariff_price' => $insurerAmount,
            'source_version' => $tariff->source_version,
            'policy_id' => $policy->id,
            'payer_id' => $policy->payer_id,
        ];
    }
}

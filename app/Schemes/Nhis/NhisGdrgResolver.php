<?php

namespace Modules\Insurance\Schemes\Nhis;

use Illuminate\Support\Carbon;
use Modules\Insurance\Models\GdrgIcdMap;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;

class NhisGdrgResolver
{
    /**
     * Resolve the NHIA G-DRG for an ICD-10 code via the Annex C map.
     */
    public function mapGdrgCode(string $icd10Code, string $serviceType = 'OUT'): ?string
    {
        return $this->mapForCode($icd10Code, $serviceType)?->gdrg_code;
    }

    /**
     * Find the active Annex C map entry (G-DRG or outpatient code) for a code.
     */
    public function mapForCode(string $code, string $serviceType = 'OUT'): ?GdrgIcdMap
    {
        return GdrgIcdMap::query()
            ->where(function ($query) use ($code) {
                $query->where('icd10_code', $code)
                    ->orWhere('gdrg_code', $code);
            })
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN service_type = ? THEN 0 ELSE 1 END', [$serviceType])
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Find the priced tariff item for a G-DRG code in the service month.
     */
    public function resolveTariff(Payer $payer, string $gdrgCode, ?string $serviceDate = null): ?TariffItem
    {
        $serviceDate = $serviceDate !== null ? Carbon::parse($serviceDate) : null;

        return TariffItem::query()
            ->where('payer_id', $payer->id)
            ->where('external_code', $gdrgCode)
            ->where('item_type', 'service')
            ->where('is_active', true)
            ->when($serviceDate, function ($query) use ($serviceDate) {
                $query->where(function ($inner) use ($serviceDate) {
                    $inner->whereNull('effective_from')->orWhere('effective_from', '<=', $serviceDate->toDateString());
                })->where(function ($inner) use ($serviceDate) {
                    $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $serviceDate->toDateString());
                });
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Resolve the full G-DRG pricing for an ICD-10 diagnosis.
     *
     * @return array{code: string, tariff: string, description: ?string}|null
     */
    public function resolve(Payer $payer, string $icd10Code, string $serviceType = 'OUT', ?string $serviceDate = null): ?array
    {
        $gdrgCode = $this->mapGdrgCode($icd10Code, $serviceType);

        if ($gdrgCode === null) {
            return null;
        }

        $tariff = $this->resolveTariff($payer, $gdrgCode, $serviceDate);

        return [
            'code' => $gdrgCode,
            'tariff' => (string) ($tariff?->price ?? '0.00'),
            'description' => $tariff?->name ?? $gdrgCode,
        ];
    }
}

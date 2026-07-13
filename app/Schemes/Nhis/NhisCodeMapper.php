<?php

namespace Modules\Insurance\Schemes\Nhis;

use Modules\Core\Models\Service;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Pharmacy\Models\Medication;

class NhisCodeMapper
{
    public function mapServiceCode(?Service $service, ?Payer $payer): ?string
    {
        if (! $service) {
            return null;
        }

        $fromMetadata = data_get($service->metadata, 'nhis_code')
            ?? data_get($service->metadata, 'nhia_code');

        if (filled($fromMetadata)) {
            return (string) $fromMetadata;
        }

        if ($payer) {
            $tariff = TariffItem::query()
                ->where('payer_id', $payer->id)
                ->where('item_type', 'service')
                ->where('name', $service->name)
                ->where('is_active', true)
                ->first();

            if ($tariff) {
                return $tariff->external_code;
            }
        }

        return $service->code ?: null;
    }

    public function mapMedicationCode(?Medication $medication, ?Payer $payer): ?string
    {
        if (! $medication) {
            return null;
        }

        $service = $medication->service;
        $fromMetadata = data_get($service?->metadata, 'nhis_code')
            ?? data_get($service?->metadata, 'nhia_code');

        if (filled($fromMetadata)) {
            return (string) $fromMetadata;
        }

        if ($payer) {
            $tariff = TariffItem::query()
                ->where('payer_id', $payer->id)
                ->where('item_type', 'medication')
                ->where(function ($q) use ($medication) {
                    $q->where('name', $medication->generic_name)
                        ->orWhere('name', $medication->brand_name);
                })
                ->where('is_active', true)
                ->first();

            if ($tariff) {
                return $tariff->external_code;
            }
        }

        return null;
    }

    public function mapServiceTariff(?Service $service, ?Payer $payer, string $fallback): string
    {
        if ($payer && $code = $this->mapServiceCode($service, $payer)) {
            $tariff = TariffItem::query()
                ->where('payer_id', $payer->id)
                ->where('item_type', 'service')
                ->where('external_code', $code)
                ->where('is_active', true)
                ->first();

            if ($tariff) {
                return (string) $tariff->price;
            }
        }

        return $fallback;
    }

    public function mapMedicationUnitPrice(?Medication $medication, ?Payer $payer, string $fallback): string
    {
        if ($payer && $code = $this->mapMedicationCode($medication, $payer)) {
            $tariff = TariffItem::query()
                ->where('payer_id', $payer->id)
                ->where('item_type', 'medication')
                ->where('external_code', $code)
                ->where('is_active', true)
                ->first();

            if ($tariff) {
                return (string) $tariff->price;
            }
        }

        return $fallback;
    }
}

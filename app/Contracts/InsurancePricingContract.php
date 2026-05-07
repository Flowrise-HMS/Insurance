<?php

namespace Modules\Insurance\Contracts;

interface InsurancePricingContract
{
    /**
     * @return array{
     *   insurer_amount:string,
     *   patient_amount:string,
     *   tariff_price:string|null,
     *   source_version:string|null,
     *   policy_id:string|null,
     *   payer_id:string|null
     * }
     */
    public function resolveForItem(
        string $patientId,
        string $itemType,
        ?string $externalCode,
        string $fallbackAmount,
        ?string $asOfDate = null
    ): array;
}

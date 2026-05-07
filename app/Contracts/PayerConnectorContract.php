<?php

namespace Modules\Insurance\Contracts;

use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\Payer;

interface PayerConnectorContract
{
    public function code(): string;

    /**
     * @return array{processed:int, watermark?:string}
     */
    public function syncCatalogs(Payer $payer, ?string $watermark = null): array;

    /**
     * @return array{
     *   external_reference:string|null,
     *   request_payload:string|null,
     *   response_payload:string|null,
     *   status:string
     * }
     */
    public function submitClaim(InsuranceClaim $claim, string $idempotencyKey): array;

    /**
     * @return array{
     *   external_reference:string|null,
     *   raw_payload:string|null,
     *   decision_status:string,
     *   rejection_class:string|null,
     *   rejection_code:string|null,
     *   rejection_reason:string|null,
     *   normalized_payload:array
     * }|null
     */
    public function fetchClaimFeedback(InsuranceClaim $claim, ?string $externalReference = null): ?array;
}

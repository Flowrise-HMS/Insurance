<?php

namespace Modules\Insurance\Services\Connectors\PrivateInsurer;

use Modules\Insurance\Contracts\PayerConnectorContract;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\Payer;

class GenericPrivateInsurerConnector implements PayerConnectorContract
{
    public function code(): string
    {
        return 'private-generic';
    }

    public function syncCatalogs(Payer $payer, ?string $watermark = null): array
    {
        return [
            'processed' => 0,
            'watermark' => $watermark,
        ];
    }

    public function submitClaim(InsuranceClaim $claim, string $idempotencyKey): array
    {
        return [
            'external_reference' => null,
            'request_payload' => null,
            'response_payload' => null,
            'status' => 'queued',
        ];
    }

    public function fetchClaimFeedback(InsuranceClaim $claim, ?string $externalReference = null): ?array
    {
        return null;
    }
}

<?php

namespace Modules\Insurance\Services;

use InvalidArgumentException;
use Modules\Insurance\Contracts\PayerConnectorContract;
use Modules\Insurance\Services\Connectors\PrivateInsurer\GenericPrivateInsurerConnector;

class PayerConnectorRegistry
{
    public function __construct(
        protected GenericPrivateInsurerConnector $genericPrivateConnector
    ) {}

    /**
     * NHIS has no HTTP connector by design: NHIS claims go exclusively through
     * the batch vetting/export pipeline (v8.6 XML uploaded to CLAIM-it).
     */
    public function forCode(string $code): PayerConnectorContract
    {
        return match ($code) {
            'private-generic' => $this->genericPrivateConnector,
            default => throw new InvalidArgumentException("Unsupported payer connector [{$code}]"),
        };
    }
}

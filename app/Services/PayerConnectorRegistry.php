<?php

namespace Modules\Insurance\Services;

use InvalidArgumentException;
use Modules\Insurance\Contracts\PayerConnectorContract;
use Modules\Insurance\Services\Connectors\Nhis\NhisConnector;
use Modules\Insurance\Services\Connectors\PrivateInsurer\GenericPrivateInsurerConnector;

class PayerConnectorRegistry
{
    public function __construct(
        protected NhisConnector $nhisConnector,
        protected GenericPrivateInsurerConnector $genericPrivateConnector
    ) {}

    public function forCode(string $code): PayerConnectorContract
    {
        return match ($code) {
            'nhis' => $this->nhisConnector,
            'private-generic' => $this->genericPrivateConnector,
            default => throw new InvalidArgumentException("Unsupported payer connector [{$code}]"),
        };
    }
}

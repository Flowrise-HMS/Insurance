<?php

namespace Modules\Insurance\Services;

use InvalidArgumentException;
use Modules\Insurance\Contracts\PayerConnectorContract;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\Connectors\PrivateInsurer\GenericPrivateInsurerConnector;
use Modules\Insurance\Services\Connectors\PrivateInsurer\HttpPrivateInsurerConnector;

/**
 * Resolves the claims connector for a payer.
 *
 * NHIS has no HTTP connector by design: NHIS claims go exclusively through
 * the batch vetting/export pipeline (v8.6 XML uploaded to CLAIM-it).
 */
class PayerConnectorRegistry
{
    /**
     * @var array<string, class-string<PayerConnectorContract>>
     */
    public const CONNECTORS = [
        'private-generic' => GenericPrivateInsurerConnector::class,
        HttpPrivateInsurerConnector::CODE => HttpPrivateInsurerConnector::class,
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'private-generic' => __('None (manual claims)'),
            HttpPrivateInsurerConnector::CODE => __('HTTP / REST API'),
        ];
    }

    public function forCode(string $code): PayerConnectorContract
    {
        $class = self::CONNECTORS[$code] ?? throw new InvalidArgumentException("Unsupported payer connector [{$code}]");

        return app($class);
    }

    /**
     * The payer's configured connector; private payers without one fall back
     * to the no-op generic connector.
     */
    public function forPayer(Payer $payer): PayerConnectorContract
    {
        return $this->forCode($this->codeFor($payer));
    }

    public function codeFor(Payer $payer): string
    {
        if ($payer->type === PayerType::NHIS) {
            throw new InvalidArgumentException('NHIS claims are exported as batch XML; there is no NHIS API connector.');
        }

        $code = (string) ($payer->connector_code ?: '');

        if ($code !== '' && isset(self::CONNECTORS[$code])) {
            return $code;
        }

        // Legacy payers whose payer code doubled as the connector code.
        return isset(self::CONNECTORS[$payer->code]) ? $payer->code : 'private-generic';
    }
}

<?php

namespace Modules\Insurance\Services\Connectors\Nhis;

use Illuminate\Support\Facades\Http;
use Modules\Insurance\Contracts\PayerConnectorContract;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\RejectionClass;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\Payer;

class NhisConnector implements PayerConnectorContract
{
    public function __construct(
        protected NhisXmlEncoder $encoder,
        protected NhisFeedbackParser $parser
    ) {}

    public function code(): string
    {
        return 'nhis';
    }

    public function syncCatalogs(Payer $payer, ?string $watermark = null): array
    {
        // Placeholder for NHIS catalog synchronization transport.
        return [
            'processed' => 0,
            'watermark' => $watermark,
        ];
    }

    public function submitClaim(InsuranceClaim $claim, string $idempotencyKey): array
    {
        $payload = $this->encoder->encodeClaim($claim);
        $endpoint = (string) data_get($claim->payer?->config, 'submission_endpoint', config('insurance.nhis.submission_endpoint'));
        $token = (string) data_get($claim->payer?->config, 'token', config('insurance.nhis.token'));
        $timeout = (int) config('insurance.nhis.timeout', 15);

        if ($endpoint === '') {
            return [
                'external_reference' => null,
                'request_payload' => $payload,
                'response_payload' => null,
                'status' => RejectionClass::TransportRejected->value,
            ];
        }

        $response = Http::timeout($timeout)
            ->accept('application/xml')
            ->contentType('application/xml')
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->when($token !== '', fn ($req) => $req->withToken($token))
            ->post($endpoint, $payload);

        return [
            'external_reference' => $claim->claim_number,
            'request_payload' => $payload,
            'response_payload' => $response->body(),
            'status' => $response->successful() ? ClaimDecisionStatus::Pending->value : RejectionClass::TransportRejected->value,
        ];
    }

    public function fetchClaimFeedback(InsuranceClaim $claim, ?string $externalReference = null): ?array
    {
        $endpoint = (string) data_get($claim->payer?->config, 'feedback_endpoint', config('insurance.nhis.feedback_endpoint'));
        $token = (string) data_get($claim->payer?->config, 'token', config('insurance.nhis.token'));
        $timeout = (int) config('insurance.nhis.timeout', 15);

        if ($endpoint === '') {
            return null;
        }

        $response = Http::timeout($timeout)
            ->accept('application/xml')
            ->when($token !== '', fn ($req) => $req->withToken($token))
            ->get($endpoint, [
                'claim_reference' => $externalReference ?? $claim->claim_number,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $parsed = $this->parser->parse($response->body());

        return array_merge($parsed, [
            'raw_payload' => $response->body(),
        ]);
    }
}

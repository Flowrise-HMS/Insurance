<?php

namespace Modules\Insurance\Services\Connectors\PrivateInsurer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Insurance\Contracts\PayerConnectorContract;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Exceptions\ClaimFeedbackPendingException;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\CatalogSyncService;

/**
 * Talks to a private insurer's JSON REST API, configured per payer in
 * `insurance_payers.connector_config` (encrypted):
 *
 * - base_url, timeout (seconds), auth_type none|bearer|basic|api_key with
 *   token / username + password / api_key_header + api_key;
 * - catalog_path (GET, ?since={watermark}), claim_path (POST), feedback_path
 *   (GET, "{reference}" is replaced), health_path (GET, for "Test connection");
 * - response field paths (dot notation, top-level or under `field_map`) with defaults: catalog_items_key
 *   "items", catalog_watermark_key "watermark", catalog_code_key "code",
 *   catalog_name_key "name", catalog_price_key "price", catalog_type_key
 *   "type", claim_reference_key "reference", claim_status_key "status",
 *   feedback_status_key "status", feedback_reason_key "reason",
 *   feedback_code_key "code";
 * - feedback_status_map: payer status => approved|partial|rejected|pending.
 */
class HttpPrivateInsurerConnector implements PayerConnectorContract
{
    public const CODE = 'http';

    public function code(): string
    {
        return self::CODE;
    }

    public function syncCatalogs(Payer $payer, ?string $watermark = null): array
    {
        $config = $this->config($payer);
        $path = $this->requirePath($config, 'catalog_path');

        $response = $this->request($config)
            ->get($path, array_filter(['since' => $watermark], fn ($value) => $value !== null))
            ->throw();

        $items = collect((array) data_get($response->json(), $config['catalog_items_key'] ?? 'items', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row): array => [
                'item_type' => (string) (data_get($row, $config['catalog_type_key'] ?? 'type') ?: 'service'),
                'external_code' => (string) data_get($row, $config['catalog_code_key'] ?? 'code'),
                'name' => (string) data_get($row, $config['catalog_name_key'] ?? 'name'),
                'price' => (string) (data_get($row, $config['catalog_price_key'] ?? 'price') ?? '0'),
                'currency' => (string) (data_get($row, 'currency') ?: ($config['currency'] ?? 'GHS')),
                'source_version' => $watermark,
            ])
            ->filter(fn (array $item): bool => $item['external_code'] !== '' && $item['name'] !== '')
            ->values()
            ->all();

        $processed = app(CatalogSyncService::class)->upsertItems($payer, $items);

        return [
            'processed' => $processed,
            'watermark' => (string) (data_get($response->json(), $config['catalog_watermark_key'] ?? 'watermark') ?? $watermark) ?: null,
        ];
    }

    public function submitClaim(InsuranceClaim $claim, string $idempotencyKey): array
    {
        $claim->loadMissing(['payer', 'policy', 'lines']);
        $config = $this->config($claim->payer);
        $path = $this->requirePath($config, 'claim_path');
        $payload = $this->claimPayload($claim);

        $response = $this->request($config)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($path, $payload)
            // A refused submission must not mark the claim as submitted.
            ->throw();

        return [
            'external_reference' => ($reference = data_get($response->json(), $config['claim_reference_key'] ?? 'reference')) !== null ? (string) $reference : null,
            'request_payload' => json_encode($payload),
            'response_payload' => $response->body(),
            'status' => (string) (data_get($response->json(), $config['claim_status_key'] ?? 'status') ?: 'submitted'),
        ];
    }

    public function fetchClaimFeedback(InsuranceClaim $claim, ?string $externalReference = null): ?array
    {
        $claim->loadMissing('payer');
        $config = $this->config($claim->payer);
        $reference = $externalReference ?? $claim->claim_number;

        if (blank($config['feedback_path'] ?? null) || blank($reference)) {
            return null;
        }

        $path = str_replace('{reference}', rawurlencode((string) $reference), (string) $config['feedback_path']);
        $response = $this->request($config)->get($path)->throw();
        $body = (array) $response->json();

        $decision = $this->decision((string) data_get($body, $config['feedback_status_key'] ?? 'status', ''), $config);

        if ($decision === ClaimDecisionStatus::PENDING) {
            throw new ClaimFeedbackPendingException("Claim {$reference} is still pending with the payer.");
        }

        return [
            'external_reference' => (string) $reference,
            'raw_payload' => $response->body(),
            'decision_status' => $decision->value,
            'rejection_class' => $decision === ClaimDecisionStatus::APPROVED ? null : 'payer',
            'rejection_code' => ($code = data_get($body, $config['feedback_code_key'] ?? 'code')) !== null ? (string) $code : null,
            'rejection_reason' => ($reason = data_get($body, $config['feedback_reason_key'] ?? 'reason')) !== null ? (string) $reason : null,
            'normalized_payload' => $body,
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, message: string}
     */
    public function testConnection(Payer $payer): array
    {
        try {
            $config = $this->config($payer);
            $response = $this->request($config)->get((string) ($config['health_path'] ?? '/'));

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? __('Connected (HTTP :status).', ['status' => $response->status()])
                    : __('The payer API answered HTTP :status.', ['status' => $response->status()]),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function claimPayload(InsuranceClaim $claim): array
    {
        return [
            'claim_number' => $claim->claim_number,
            'member_number' => $claim->policy?->member_number,
            'plan_code' => $claim->policy?->plan_code,
            'patient_reference' => $claim->patient_id,
            'encounter_reference' => $claim->encounter_id,
            'invoice_reference' => $claim->invoice_id,
            'currency' => $claim->currency ?? 'GHS',
            'total_billed_amount' => (string) $claim->total_billed_amount,
            'lines' => $claim->lines->map(fn (InsuranceClaimLine $line): array => [
                'line_reference' => $line->id,
                'type' => $line->line_type?->value,
                'code' => $line->external_item_code,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'billed_amount' => (string) $line->billed_amount,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(?Payer $payer): array
    {
        $config = (array) ($payer?->connector_config ?? []);
        // Field-path overrides entered in the payer form's "advanced" key/value list.
        $config = array_merge($config, array_filter((array) ($config['field_map'] ?? []), fn ($value) => filled($value)));

        if (blank($config['base_url'] ?? null)) {
            throw new InvalidArgumentException("Payer [{$payer?->code}] has no connector base URL configured.");
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function requirePath(array $config, string $key): string
    {
        if (blank($config[$key] ?? null)) {
            throw new InvalidArgumentException("The payer connector has no [{$key}] configured.");
        }

        return (string) $config[$key];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function request(array $config): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) $config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(max(1, (int) ($config['timeout'] ?? 20)))
            ->retry(2, 500, fn (\Throwable $e): bool => ! $e instanceof RequestException || $e->response->serverError(), throw: false);

        return match ($config['auth_type'] ?? 'none') {
            'bearer' => $request->withToken((string) ($config['token'] ?? '')),
            'basic' => $request->withBasicAuth((string) ($config['username'] ?? ''), (string) ($config['password'] ?? '')),
            'api_key' => $request->withHeaders([(string) ($config['api_key_header'] ?? 'X-API-Key') => (string) ($config['api_key'] ?? '')]),
            default => $request,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function decision(string $payerStatus, array $config): ClaimDecisionStatus
    {
        $map = array_change_key_case((array) ($config['feedback_status_map'] ?? []), CASE_LOWER);
        $normalized = strtolower(trim($payerStatus));
        $mapped = strtolower((string) Arr::get($map, $normalized, $normalized));

        return enum_try_from(ClaimDecisionStatus::class, match ($mapped) {
            'accepted', 'paid', 'approved' => 'approved',
            'partially_approved', 'partial' => 'partial',
            'denied', 'declined', 'rejected' => 'rejected',
            default => $mapped,
        }) ?? ClaimDecisionStatus::PENDING;
    }
}

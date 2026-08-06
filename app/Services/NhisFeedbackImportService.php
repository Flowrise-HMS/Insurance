<?php

namespace Modules\Insurance\Services;

use Modules\Insurance\DTOs\ImportResult;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\RejectionClass;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Services\Connectors\Nhis\NhisFeedbackParser;

class NhisFeedbackImportService
{
    public function __construct(
        protected ClaimReconciliationService $reconciliation,
        protected NhisFeedbackParser $parser,
    ) {}

    /**
     * Import an NHIA feedback XML file and apply each claim decision.
     *
     * @param  string  $xmlPath  Absolute path to the feedback XML file.
     */
    public function import(string $xmlPath): ImportResult
    {
        if (! is_file($xmlPath) || ! is_readable($xmlPath)) {
            throw new \InvalidArgumentException("Feedback file [{$xmlPath}] does not exist or is not readable.");
        }

        $xml = @simplexml_load_file($xmlPath);

        if ($xml === false) {
            throw new \InvalidArgumentException('Feedback file is not valid XML.');
        }

        $nodes = $this->extractClaimNodes($xml);

        if ($nodes === []) {
            return new ImportResult(
                created: 0,
                updated: 0,
                skipped: 0,
                errors: ['No claim feedback nodes found in the file.'],
            );
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($nodes as $node) {
            $parsed = $this->parseNode($node);
            $claim = $this->findClaim($parsed['external_reference']);

            if (! $claim) {
                $skipped++;
                $errors[] = "No claim found for identification number [{$parsed['external_reference']}]. Skipped.";

                continue;
            }

            if ($parsed['decision_status'] === ClaimDecisionStatus::PENDING->value) {
                $skipped++;
                $errors[] = "Feedback for claim [{$claim->claim_number}] has no decision. Skipped.";

                continue;
            }

            $parsed['claim_submission_id'] = $claim->submissions()
                ->latest('submitted_at')
                ->value('id');

            $feedbackHash = hash('sha256', json_encode($parsed));

            $existing = $claim->feedbacks()->where('feedback_hash', $feedbackHash)->exists();

            if ($existing) {
                $skipped++;

                continue;
            }

            $this->reconciliation->applyFeedback($claim, $parsed);

            $created++;
        }

        return new ImportResult(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * @return array<int, \SimpleXMLElement>
     */
    protected function extractClaimNodes(\SimpleXMLElement $root): array
    {
        if (isset($root->ClaimIdentificationNumber)) {
            return [$root];
        }

        $nodes = [];

        foreach ($root->children() as $child) {
            if (isset($child->ClaimIdentificationNumber)
                || isset($child->ClaimNumber)
                || isset($child->ExternalReference)) {
                $nodes[] = $child;
            }
        }

        return $nodes;
    }

    /**
     * @return array{
     *  external_reference: string|null,
     *  decision_status: string,
     *  rejection_class: string|null,
     *  rejection_code: string|null,
     *  rejection_reason: string|null,
     *  raw_payload: string,
     *  normalized_payload: array<string, string>
     * }
     */
    protected function parseNode(\SimpleXMLElement $node): array
    {
        $externalReference = trim((string) (
            $node->ClaimIdentificationNumber
            ?? $node->ClaimNumber
            ?? $node->ExternalReference
            ?? ''
        ));

        $status = strtolower((string) ($node->ClaimStatus ?? 'pending'));
        $errorCode = trim((string) ($node->ErrorCode ?? ''));
        $errorMessage = trim((string) ($node->ErrorDescription ?? ''));

        $decision = match ($status) {
            'approved', 'accepted' => ClaimDecisionStatus::APPROVED,
            'partial' => ClaimDecisionStatus::PARTIAL,
            'rejected', 'denied' => ClaimDecisionStatus::REJECTED,
            default => ClaimDecisionStatus::PENDING,
        };

        $rejectionClass = null;
        if ($decision === ClaimDecisionStatus::REJECTED || $decision === ClaimDecisionStatus::PARTIAL) {
            $rejectionClass = $this->mapErrorCodeToRejectionClass($errorCode)?->value;
        }

        return [
            'external_reference' => $externalReference !== '' ? $externalReference : null,
            'decision_status' => $decision->value,
            'rejection_class' => $rejectionClass,
            'rejection_code' => $errorCode !== '' ? $errorCode : null,
            'rejection_reason' => $errorMessage !== '' ? $errorMessage : null,
            'raw_payload' => $node->asXML() ?: '',
            'normalized_payload' => [
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ],
        ];
    }

    protected function findClaim(?string $externalReference): ?InsuranceClaim
    {
        if (! $externalReference) {
            return null;
        }

        return InsuranceClaim::query()
            ->where('claim_number', $externalReference)
            ->orWhereHas('submissions', fn ($query) => $query->where('external_reference', $externalReference))
            ->first();
    }

    protected function mapErrorCodeToRejectionClass(?string $errorCode): ?RejectionClass
    {
        if (! $errorCode) {
            return RejectionClass::PAYER_REJECTED;
        }

        if (in_array($errorCode, ['HTTP_TIMEOUT', 'TEMP_UNAVAILABLE'], true)) {
            return RejectionClass::TRANSPORT_REJECTED;
        }

        if (str_starts_with($errorCode, '2')) {
            return RejectionClass::SCHEMA_REJECTED;
        }

        if (str_starts_with($errorCode, '3')) {
            return RejectionClass::BUSINESS_REJECTED;
        }

        return RejectionClass::PAYER_REJECTED;
    }
}

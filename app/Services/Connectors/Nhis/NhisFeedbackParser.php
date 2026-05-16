<?php

namespace Modules\Insurance\Services\Connectors\Nhis;

use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\RejectionClass;

class NhisFeedbackParser
{
    /**
     * @return array{
     *  external_reference:string|null,
     *  decision_status:string,
     *  rejection_class:string|null,
     *  rejection_code:string|null,
     *  rejection_reason:string|null,
     *  normalized_payload:array
     * }
     */
    public function parse(string $feedbackXml): array
    {
        if (trim($feedbackXml) === '') {
            return [
                'external_reference' => null,
                'decision_status' => ClaimDecisionStatus::PENDING->value,
                'rejection_class' => RejectionClass::TRANSPORT_REJECTED->value,
                'rejection_code' => 'EMPTY_FEEDBACK',
                'rejection_reason' => 'Empty feedback payload received from payer.',
                'normalized_payload' => [],
            ];
        }

        $xml = @simplexml_load_string($feedbackXml);
        if ($xml === false) {
            return [
                'external_reference' => null,
                'decision_status' => ClaimDecisionStatus::REJECTED->value,
                'rejection_class' => RejectionClass::SCHEMA_REJECTED->value,
                'rejection_code' => 'INVALID_XML',
                'rejection_reason' => 'Feedback payload is not valid XML.',
                'normalized_payload' => [],
            ];
        }

        $externalReference = (string) ($xml->ClaimIdentificationNumber ?? '');
        $status = strtolower((string) ($xml->ClaimStatus ?? 'pending'));
        $errorCode = (string) ($xml->ErrorCode ?? '');
        $errorMessage = (string) ($xml->ErrorDescription ?? '');

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
            'normalized_payload' => [
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ],
        ];
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

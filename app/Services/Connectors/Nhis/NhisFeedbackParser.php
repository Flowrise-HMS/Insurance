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
                'decision_status' => ClaimDecisionStatus::Pending->value,
                'rejection_class' => RejectionClass::TransportRejected->value,
                'rejection_code' => 'EMPTY_FEEDBACK',
                'rejection_reason' => 'Empty feedback payload received from payer.',
                'normalized_payload' => [],
            ];
        }

        $xml = @simplexml_load_string($feedbackXml);
        if ($xml === false) {
            return [
                'external_reference' => null,
                'decision_status' => ClaimDecisionStatus::Rejected->value,
                'rejection_class' => RejectionClass::SchemaRejected->value,
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
            'approved', 'accepted' => ClaimDecisionStatus::Approved,
            'partial' => ClaimDecisionStatus::Partial,
            'rejected', 'denied' => ClaimDecisionStatus::Rejected,
            default => ClaimDecisionStatus::Pending,
        };

        $rejectionClass = null;
        if ($decision === ClaimDecisionStatus::Rejected || $decision === ClaimDecisionStatus::Partial) {
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
            return RejectionClass::PayerRejected;
        }

        if (in_array($errorCode, ['HTTP_TIMEOUT', 'TEMP_UNAVAILABLE'], true)) {
            return RejectionClass::TransportRejected;
        }

        if (str_starts_with($errorCode, '2')) {
            return RejectionClass::SchemaRejected;
        }

        if (str_starts_with($errorCode, '3')) {
            return RejectionClass::BusinessRejected;
        }

        return RejectionClass::PayerRejected;
    }
}

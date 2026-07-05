<?php

namespace Modules\Insurance\Schemes\Nhis;

use Modules\Core\Settings\InsuranceSettings;
use Modules\Insurance\DTOs\ValidationResult;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Models\InsuranceClaim;

class NhisClaimValidator
{
    public function __construct(
        protected InsuranceSettings $settings,
    ) {}

    public function validate(InsuranceClaim $claim): ValidationResult
    {
        $claim->loadMissing(['patient', 'policy', 'lines', 'encounter']);

        $errors = [];
        $warnings = [];

        if (! $claim->policy?->member_number) {
            $errors[] = 'NHIS member number is required.';
        }

        $cardSerial = data_get($claim->nhia_payload, 'card_serial_number')
            ?? data_get($claim->policy?->metadata, 'card_serial_number');

        if (! filled($cardSerial)) {
            $warnings[] = 'Card serial number is missing (required by NHIA for most claims).';
        }

        $serviceType = data_get($claim->nhia_payload, 'service_type');
        if (! filled($serviceType)) {
            $errors[] = 'Service type (OUT/INP/DIA/CAP) is required.';
        }

        if ($this->settings->require_claim_check_code && ! filled(data_get($claim->nhia_payload, 'claim_check_code'))) {
            $errors[] = 'Claim check code is required.';
        }

        $treatments = $claim->lines->where('line_type', ClaimLineType::TREATMENT);
        $medicines = $claim->lines->where('line_type', ClaimLineType::MEDICINE);

        if ($treatments->isEmpty() && $medicines->isEmpty()) {
            $errors[] = 'Claim must include at least one treatment or medicine line.';
        }

        foreach ($treatments as $line) {
            if (! filled($line->external_item_code)) {
                $errors[] = "Treatment line [{$line->description}] is missing NHIA treatment code.";
            }
            if (! filled(data_get($line->metadata, 'icd_code'))) {
                $warnings[] = "Treatment line [{$line->description}] is missing ICD code.";
            }
        }

        foreach ($medicines as $line) {
            if (! filled($line->external_item_code)) {
                $errors[] = "Medicine line [{$line->description}] is missing NHIA medicine code.";
            }
        }

        $lineTotal = $claim->lines->sum(fn ($line) => (float) $line->billed_amount);
        $claimTotal = (float) $claim->total_billed_amount;

        if (abs($lineTotal - $claimTotal) > 0.01) {
            $errors[] = 'Total cost does not match sum of claim lines.';
        }

        if ($serviceType === 'INP' && ! filled(data_get($claim->nhia_payload, 'discharge_date'))) {
            $errors[] = 'Discharge date is required for inpatient claims.';
        }

        return $errors === []
            ? ValidationResult::pass($warnings)
            : ValidationResult::fail($errors, $warnings);
    }
}

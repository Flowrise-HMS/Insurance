<?php

namespace Modules\Insurance\Schemes\Nhis;

use Carbon\Carbon;
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
        $codedErrors = [];

        $isInfant = $this->isInfantClaim($claim);

        if ($isInfant) {
            $memberNumber = (string) data_get($claim->policy?->metadata, 'mother_member_number', '');
            $cardSerial = (string) data_get($claim->policy?->metadata, 'mother_card_serial_number', '');
        } else {
            $memberNumber = (string) ($claim->policy?->member_number ?? '');
            $cardSerial = (string) data_get($claim->policy?->metadata, 'card_serial_number', '');
        }

        if ($memberNumber === '') {
            $this->pushError(
                $errors,
                $codedErrors,
                '203',
                $isInfant
                    ? 'Mother member number is required for an infant claim.'
                    : 'NHIS member number is required.'
            );
        } elseif (strlen($memberNumber) < 8) {
            $this->pushError($errors, $codedErrors, '203', 'Member number must be at least 8 characters.');
        }

        if ($cardSerial === '') {
            $this->pushError(
                $errors,
                $codedErrors,
                '204',
                $isInfant
                    ? 'Mother card serial number is required for an infant claim.'
                    : 'Card serial number is required.'
            );
        } elseif (! preg_match('/^[A-Za-z0-9]{13}$/', $cardSerial)) {
            $this->pushError($errors, $codedErrors, '204', 'Card serial number must be exactly 13 alphanumeric characters.');
        }

        $serviceType = data_get($claim->nhia_payload, 'service_type');
        if (! filled($serviceType)) {
            $this->pushError($errors, $codedErrors, null, 'Service type (OUT/INP/DIA/CAP) is required.');
        }

        $claimCheckCode = (string) data_get($claim->nhia_payload, 'claim_check_code', '');
        if ($this->settings->require_claim_check_code && $claimCheckCode === '') {
            $this->pushError($errors, $codedErrors, '237', 'Claim check code is required.');
        } elseif ($claimCheckCode !== '' && ! in_array(strlen($claimCheckCode), [5, 13], true)) {
            $this->pushError($errors, $codedErrors, '237', 'Claim check code must be 5 or 13 characters.');
        }

        $admissionType = strtoupper((string) data_get($claim->nhia_payload, 'admission_type', ''));
        if ($admissionType === '' || ! NhisSpecialityCodes::isValidAdmissionType($admissionType)) {
            $this->pushError($errors, $codedErrors, '212', 'Admission type must be CRO, EME, or ACU.');
        }

        $speciality = strtoupper(trim((string) data_get(
            $claim->nhia_payload,
            'speciality_code',
            $this->settings->default_speciality_code ?? ''
        )));
        if ($speciality === '' || ! NhisSpecialityCodes::isValid($speciality)) {
            $this->pushError($errors, $codedErrors, '271', 'Speciality code is missing or not in the NHIA allow-list.');
        }

        if (! filled(data_get($claim->nhia_payload, 'admission_date'))) {
            $this->pushError($errors, $codedErrors, '214', 'Admission date is required.');
        }

        $treatments = $claim->lines->where('line_type', ClaimLineType::TREATMENT);
        $medicines = $claim->lines->where('line_type', ClaimLineType::MEDICINE);

        if ($treatments->isEmpty() && $medicines->isEmpty()) {
            $this->pushError($errors, $codedErrors, null, 'Claim must include at least one treatment or medicine line.');
        }

        foreach ($treatments as $line) {
            if (! filled($line->external_item_code)) {
                $this->pushError($errors, $codedErrors, null, "Treatment line [{$line->description}] is missing NHIA treatment code.");
            }
            if (! filled(data_get($line->metadata, 'icd_code'))) {
                $warnings[] = "Treatment line [{$line->description}] is missing ICD code.";
            }

            $treatmentType = strtoupper((string) data_get($line->metadata, 'treatment_type', ''));
            if (in_array($treatmentType, ['PROCEDURE', 'INVESTIGATION'], true)
                && ! filled(data_get($line->metadata, 'performance_date'))
                && ! filled(data_get($claim->nhia_payload, 'admission_date'))) {
                $this->pushError($errors, $codedErrors, null, "Treatment line [{$line->description}] requires a performance date.");
            }
        }

        foreach ($medicines as $line) {
            if (! filled($line->external_item_code)) {
                $this->pushError($errors, $codedErrors, null, "Medicine line [{$line->description}] is missing NHIA medicine code.");
            }
        }

        if ($serviceType === 'INP' && ! filled(data_get($claim->nhia_payload, 'discharge_date'))) {
            $this->pushError($errors, $codedErrors, null, 'Discharge date is required for inpatient claims.');
        }

        return $errors === []
            ? ValidationResult::pass($warnings)
            : ValidationResult::fail($errors, $warnings, $codedErrors);
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, array{code: ?string, message: string}>  $codedErrors
     */
    protected function pushError(array &$errors, array &$codedErrors, ?string $code, string $message): void
    {
        $errors[] = $code === null ? $message : sprintf('[%s] %s', $code, $message);
        $codedErrors[] = ['code' => $code, 'message' => $message];
    }

    protected function isInfantClaim(InsuranceClaim $claim): bool
    {
        $dob = $claim->patient?->date_of_birth;
        if (! $dob) {
            return false;
        }

        try {
            $reference = filled(data_get($claim->nhia_payload, 'admission_date'))
                ? Carbon::parse((string) data_get($claim->nhia_payload, 'admission_date'))->startOfDay()
                : now()->startOfDay();

            return Carbon::parse($dob)->startOfDay()->greaterThan($reference->copy()->subMonths(3));
        } catch (\Throwable) {
            return false;
        }
    }
}

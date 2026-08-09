<?php

namespace Modules\Insurance\Schemes\Nhis;

use Carbon\Carbon;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Patient\Models\Patient;

class NhisBatchXmlEncoder
{
    public function __construct(
        protected InsuranceSettings $settings,
    ) {}

    public function encode(ClaimBatch $batch): string
    {
        $batch->loadMissing(['claims.patient', 'claims.policy', 'claims.lines']);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Batch/>');

        $general = $xml->addChild('GeneralInformation');
        $versions = $general->addChild('VersionInformation');
        $masterVersions = $batch->master_table_versions ?? $this->settings->master_table_versions;

        foreach ([
            'XMLFormatVersion',
            'MedicineVersion',
            'GDRGVersion',
            'TariffVersion',
            'ICDVersion',
            'OpenHDDVersion',
        ] as $key) {
            $versions->addChild($key, htmlspecialchars((string) ($masterVersions[$key] ?? '1')));
        }

        $batchInfo = $general->addChild('BatchInformation');
        $batchInfo->addChild('BatchNumber', htmlspecialchars((string) $batch->batch_number));
        $batchInfo->addChild('BatchAmount', $this->formatDecimal((string) $batch->batch_amount));
        $batchInfo->addChild('BatchCurrency', 'GHC');
        $batchInfo->addChild('ClaimsCount', (string) $batch->claims_count);
        $batchInfo->addChild('CreationDate', now()->format('d/m/Y'));
        $batchInfo->addChild('ServiceYear', (string) ($batch->service_year ?? now()->year));
        $batchInfo->addChild('ServiceMonth', sprintf('%02d', (int) ($batch->service_month ?? now()->month)));

        $provider = $general->addChild('ProviderInformation');
        $provider->addChild('ProviderAccreditationNumber', htmlspecialchars((string) ($this->settings->provider_accreditation_number ?? '')));
        $provider->addChild('eClaimAuthorizationNumber', htmlspecialchars((string) ($this->settings->eclaim_authorization_number ?? '')));

        $patientsNode = $xml->addChild('Patients');

        $claimsByPatient = $batch->claims->groupBy('patient_id');

        foreach ($claimsByPatient as $claims) {
            /** @var InsuranceClaim $firstClaim */
            $firstClaim = $claims->first();
            $patient = $firstClaim->patient;
            $policy = $firstClaim->policy;
            $referenceDate = $this->resolveReferenceDate($firstClaim, $batch);
            $isInfant = $this->isInfant($patient?->date_of_birth, $referenceDate);
            $names = $this->patientNames($patient);
            [$memberNumber, $cardSerial] = $this->resolveMemberIdentity($policy, $isInfant);

            $patientData = $patientsNode->addChild('PatientData');
            $patientData->addChild('Surname', htmlspecialchars($names['surname']));
            $patientData->addChild('OtherName', htmlspecialchars($names['other']));
            $patientData->addChild('DateOfBirth', $this->formatDate($patient?->date_of_birth));
            $patientData->addChild('Infant', $isInfant ? 'Yes' : 'No');
            $patientData->addChild('MemberNumber', htmlspecialchars($memberNumber));

            $temporaryCard = (string) data_get($policy?->metadata, 'temporary_card_number', '');
            if ($temporaryCard !== '') {
                $patientData->addChild('TemporaryCardNumber', htmlspecialchars($temporaryCard));
            }

            $patientData->addChild('HospitalRecordNumber', htmlspecialchars((string) ($patient?->mrn ?? '')));
            $patientData->addChild('CardSerialNumber', htmlspecialchars($cardSerial));
            $patientData->addChild('Gender', htmlspecialchars(strtoupper(substr((string) ($patient?->gender?->value ?? 'M'), 0, 1))));

            $claimsNode = $patientData->addChild('Claims');

            foreach ($claims as $claim) {
                $this->appendClaimNode($claimsNode, $claim);
            }
        }

        return $xml->asXML() ?: '';
    }

    protected function appendClaimNode(\SimpleXMLElement $claimsNode, InsuranceClaim $claim): void
    {
        $payload = $claim->nhia_payload ?? [];
        $claimNode = $claimsNode->addChild('Claim');

        $claimNode->addChild('ClaimIdentificationNumber', htmlspecialchars((string) $claim->claim_number));

        $claimCheckCode = (string) data_get($payload, 'claim_check_code', '');
        if ($claimCheckCode !== '') {
            $claimNode->addChild('ClaimCheckCode', htmlspecialchars($claimCheckCode));
        }

        $claimNode->addChild('ServiceType', htmlspecialchars((string) data_get($payload, 'service_type', 'OUT')));
        $claimNode->addChild('PharmacyIncluded', $this->formatYesNo(data_get($payload, 'pharmacy_included', 'NO')));
        $claimNode->addChild('AllInclusive', $this->formatYesNo(data_get($payload, 'all_inclusive', 'NO')));
        $claimNode->addChild('OutcomeType', htmlspecialchars((string) data_get($payload, 'outcome_type', 'DIS')));

        if (data_get($payload, 'service_type') === 'INP' && filled(data_get($payload, 'duration_length'))) {
            $claimNode->addChild('DurationLength', (string) data_get($payload, 'duration_length'));
        }

        $admissionType = strtoupper((string) data_get($payload, 'admission_type', 'ACU'));
        if (! NhisSpecialityCodes::isValidAdmissionType($admissionType)) {
            $admissionType = 'ACU';
        }
        $claimNode->addChild('AdmissionType', htmlspecialchars($admissionType));
        $claimNode->addChild('SpecialityCode', htmlspecialchars($this->resolveSpecialityCode($payload)));

        if (filled(data_get($payload, 'admission_date'))) {
            $claimNode->addChild('AdmissionDate', $this->formatDateValue(data_get($payload, 'admission_date')));
        }

        if (filled(data_get($payload, 'discharge_date'))) {
            $claimNode->addChild('DischargeDate', $this->formatDateValue(data_get($payload, 'discharge_date')));
        }

        $claimNode->addChild('OutPatientTariffAmount', $this->formatDecimal((string) data_get($payload, 'outpatient_tariff_amount', '0')));
        $claimNode->addChild('OutPatientCode', htmlspecialchars((string) data_get($payload, 'outpatient_code', '')));
        $claimNode->addChild('TotalCost', $this->formatDecimal((string) $claim->total_billed_amount));

        $treatments = $claim->lines->where('line_type', ClaimLineType::TREATMENT);
        $medicines = $claim->lines->where('line_type', ClaimLineType::MEDICINE);

        $claimNode->addChild('TreatmentsCount', (string) $treatments->count());
        $claimNode->addChild('MedicinesCount', (string) $medicines->count());

        if (filled(data_get($payload, 'referral_no'))) {
            $claimNode->addChild('ReferralNo', htmlspecialchars((string) data_get($payload, 'referral_no')));
        }

        if ($treatments->isNotEmpty()) {
            $treatmentsNode = $claimNode->addChild('Treatments');
            foreach ($treatments as $line) {
                $treatment = $treatmentsNode->addChild('Treatment');
                $treatmentType = $this->normalizeTreatmentType((string) data_get($line->metadata, 'treatment_type', 'Diagnosis'));
                $performanceDate = data_get($line->metadata, 'performance_date')
                    ?? data_get($payload, 'admission_date');

                if (filled($performanceDate)) {
                    $treatment->addChild('Date', $this->formatDateValue($performanceDate));
                }

                $treatment->addChild('Type', htmlspecialchars($treatmentType));
                $treatment->addChild('TreatmentCode', htmlspecialchars((string) ($line->external_item_code ?? '')));
                $treatment->addChild('ICDCode', htmlspecialchars((string) data_get($line->metadata, 'icd_code', '')));
                $treatment->addChild('Tariff', $this->formatDecimal((string) data_get($line->metadata, 'tariff', $line->billed_amount)));
            }
        }

        if ($medicines->isNotEmpty()) {
            $medicinesNode = $claimNode->addChild('Medicines');
            foreach ($medicines as $line) {
                $medicine = $medicinesNode->addChild('Medicine');
                $unitPrice = data_get($line->metadata, 'unit_price', bcdiv((string) $line->billed_amount, (string) max(1, $line->quantity), 2));
                $medicine->addChild('MedicineCode', htmlspecialchars((string) ($line->external_item_code ?? '')));
                $medicine->addChild('Quantity', $this->formatDecimal((string) $line->quantity));
                $medicine->addChild('UnitPrice', $this->formatDecimal((string) $unitPrice));
                $medicine->addChild('MedicineTotal', $this->formatDecimal((string) $line->billed_amount));
                $medicine->addChild('MedicineDate', $this->formatDateValue(data_get($line->metadata, 'medicine_date')));
            }
        }
    }

    /**
     * @return array{surname: string, other: string}
     */
    protected function patientNames(?Patient $patient): array
    {
        $surname = trim((string) ($patient?->last_name ?? ''));
        $other = trim(implode(' ', array_filter([
            trim((string) ($patient?->first_name ?? '')),
            trim((string) ($patient?->middle_name ?? '')),
        ])));

        if ($surname === '' && $other === '') {
            return ['surname' => 'UNKNOWN', 'other' => ''];
        }

        if ($surname === '') {
            return ['surname' => $other, 'other' => ''];
        }

        return ['surname' => $surname, 'other' => $other];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveMemberIdentity(mixed $policy, bool $isInfant): array
    {
        if ($isInfant) {
            $motherMember = (string) data_get($policy?->metadata, 'mother_member_number', '');
            $motherSerial = (string) data_get($policy?->metadata, 'mother_card_serial_number', '');

            if ($motherMember !== '' || $motherSerial !== '') {
                return [$motherMember, $motherSerial];
            }
        }

        return [
            (string) ($policy?->member_number ?? ''),
            (string) data_get($policy?->metadata, 'card_serial_number', ''),
        ];
    }

    protected function resolveSpecialityCode(array $payload): string
    {
        $code = strtoupper(trim((string) data_get(
            $payload,
            'speciality_code',
            $this->settings->default_speciality_code ?? 'OPDC'
        )));

        if ($code === '' || ! NhisSpecialityCodes::isValid($code)) {
            return 'OPDC';
        }

        return $code;
    }

    protected function resolveReferenceDate(InsuranceClaim $claim, ClaimBatch $batch): Carbon
    {
        $admissionDate = data_get($claim->nhia_payload, 'admission_date');

        if (filled($admissionDate)) {
            try {
                return Carbon::parse((string) $admissionDate)->startOfDay();
            } catch (\Throwable) {
                // Fall through to batch month end.
            }
        }

        $year = (int) ($batch->service_year ?? now()->year);
        $month = (int) ($batch->service_month ?? now()->month);

        return Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
    }

    protected function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        return $this->formatDateValue($value);
    }

    protected function formatDateValue(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function formatDecimal(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function normalizeTreatmentType(string $type): string
    {
        return ucfirst(strtolower(trim($type)));
    }

    protected function formatYesNo(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['YES', 'Y', '1', 'TRUE'], true) ? 'YES' : 'NO';
    }

    protected function isInfant(mixed $dateOfBirth, Carbon $referenceDate): bool
    {
        if (! $dateOfBirth) {
            return false;
        }

        try {
            return Carbon::parse($dateOfBirth)->startOfDay()
                ->greaterThan($referenceDate->copy()->startOfDay()->subMonths(3));
        } catch (\Throwable) {
            return false;
        }
    }
}

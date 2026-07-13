<?php

namespace Modules\Insurance\Schemes\Nhis;

use Carbon\Carbon;
use Modules\Core\Settings\InsuranceSettings;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;

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
        $batchInfo->addChild('ServiceMonth', (string) ($batch->service_month ?? now()->month));
        $batchInfo->addChild('IDPayer', '');

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

            $patientData = $patientsNode->addChild('PatientData');
            $names = $this->splitName($patient?->full_name ?? 'Unknown Patient');

            $patientData->addChild('Surname', htmlspecialchars($names['surname']));
            $patientData->addChild('OtherName', htmlspecialchars($names['other']));
            $patientData->addChild('DateOfBirth', $this->formatDate($patient?->date_of_birth));
            $patientData->addChild('Infant', $this->isInfant($patient?->date_of_birth) ? 'Yes' : 'No');
            $patientData->addChild('MemberNumber', htmlspecialchars((string) ($policy?->member_number ?? '')));
            $patientData->addChild('TemporaryCardNumber', htmlspecialchars((string) data_get($policy?->metadata, 'temporary_card_number', '')));
            $patientData->addChild('HospitalRecordNumber', htmlspecialchars((string) ($patient?->mrn ?? '')));
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
        $claimNode->addChild('ClaimCheckCode', htmlspecialchars((string) data_get($payload, 'claim_check_code', '')));
        $claimNode->addChild('ServiceType', htmlspecialchars((string) data_get($payload, 'service_type', 'OUT')));
        $claimNode->addChild('PharmacyIncluded', data_get($payload, 'pharmacy_included', 'NO'));
        $claimNode->addChild('AllInclusive', data_get($payload, 'all_inclusive', 'NO'));
        $claimNode->addChild('OutcomeType', htmlspecialchars((string) data_get($payload, 'outcome_type', 'DIS')));

        if (data_get($payload, 'service_type') === 'INP' && filled(data_get($payload, 'duration_length'))) {
            $claimNode->addChild('DurationLength', (string) data_get($payload, 'duration_length'));
        }

        $claimNode->addChild('AdmissionType', htmlspecialchars((string) data_get($payload, 'admission_type', 'ACU')));
        $claimNode->addChild('SpecialityCode', htmlspecialchars((string) data_get($payload, 'speciality_code', $this->settings->default_speciality_code ?? '')));
        $claimNode->addChild('AdmissionDate', $this->formatDateValue(data_get($payload, 'admission_date')));

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
        $claimNode->addChild('ReferralNo', htmlspecialchars((string) data_get($payload, 'referral_no', '')));

        if ($treatments->isNotEmpty()) {
            $treatmentsNode = $claimNode->addChild('Treatments');
            foreach ($treatments as $line) {
                $treatment = $treatmentsNode->addChild('Treatment');
                $treatment->addChild('Type', htmlspecialchars((string) data_get($line->metadata, 'treatment_type', 'Diagnosis')));
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
                $medicine->addChild('Quantity', (string) $line->quantity);
                $medicine->addChild('UnitPrice', $this->formatDecimal((string) $unitPrice));
                $medicine->addChild('MedicineTotal', $this->formatDecimal((string) $line->billed_amount));
                $medicine->addChild('MedicineDate', $this->formatDateValue(data_get($line->metadata, 'medicine_date')));
            }
        }
    }

    /**
     * @return array{surname: string, other: string}
     */
    protected function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) === 0) {
            return ['surname' => 'UNKNOWN', 'other' => ''];
        }

        if (count($parts) === 1) {
            return ['surname' => $parts[0], 'other' => ''];
        }

        return [
            'surname' => array_pop($parts),
            'other' => implode(' ', $parts),
        ];
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

    protected function isInfant(mixed $dateOfBirth): bool
    {
        if (! $dateOfBirth) {
            return false;
        }

        try {
            return Carbon::parse($dateOfBirth)->age < 1;
        } catch (\Throwable) {
            return false;
        }
    }
}

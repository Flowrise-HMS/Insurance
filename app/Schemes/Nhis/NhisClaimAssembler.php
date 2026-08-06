<?php

namespace Modules\Insurance\Schemes\Nhis;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Billing\Models\Invoice;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Settings\InsuranceSettings;
use Modules\Core\Support\Currency;
use Modules\Insurance\DTOs\ClaimGenerationResult;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Support\ClaimBatchCriteria;
use Modules\Pharmacy\Models\Dispense;

class NhisClaimAssembler
{
    public function __construct(
        protected NhisCodeMapper $codeMapper,
        protected NhisGdrgResolver $gdrgResolver,
        protected InsuranceSettings $settings,
    ) {}

    /**
     * @return Collection<int, Encounter>
     */
    public function previewEligibleEncounters(ClaimBatchCriteria $criteria): Collection
    {
        return $this->buildEncounterQuery($criteria)->get();
    }

    public function generateClaimsForBatch(ClaimBatchCriteria $criteria, ClaimBatch $batch): ClaimGenerationResult
    {
        $encounters = $this->buildEncounterQuery($criteria)->get();
        $payer = Payer::query()->where('code', $criteria->schemeCode)->firstOrFail();
        $claims = collect();
        $warnings = [];

        foreach ($encounters as $encounter) {
            [$claim, $claimWarnings] = $this->buildClaimFromEncounter($encounter, $batch, $payer);
            $claims->push($claim);
            $warnings = array_merge($warnings, $claimWarnings);
        }

        return new ClaimGenerationResult(
            claims: $claims,
            patientsCount: $claims->pluck('patient_id')->unique()->count(),
            warnings: $warnings,
        );
    }

    /**
     * @return array{0: InsuranceClaim, 1: array<int, string>}
     */
    public function buildClaimFromEncounter(Encounter $encounter, ClaimBatch $batch, Payer $payer): array
    {
        $encounter->loadMissing(['patient', 'department']);
        $warnings = [];

        $policy = PatientPolicy::query()
            ->where('patient_id', $encounter->patient_id)
            ->where('payer_id', $payer->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->first();

        if (! $policy) {
            $warnings[] = "Encounter {$encounter->encounter_number}: no active NHIS policy.";
        }

        $invoice = Invoice::query()
            ->where('encounter_id', $encounter->id)
            ->latest('issued_at')
            ->first();

        $nhiaPayload = [
            'service_type' => $this->mapServiceType($encounter),
            'pharmacy_included' => 'NO',
            'all_inclusive' => 'NO',
            'outcome_type' => $this->mapOutcomeType($encounter->discharge_disposition),
            'admission_type' => $encounter->type === EncounterType::EMERGENCY ? 'EME' : 'ACU',
            'speciality_code' => data_get($encounter->department?->metadata, 'nhis_speciality_code')
                ?: ($this->settings->default_speciality_code ?: 'OPDC'),
            'admission_date' => ($encounter->admitted_at ?? $encounter->created_at)?->toDateString(),
            'discharge_date' => $encounter->discharged_at?->toDateString(),
            'referral_no' => data_get($encounter->metadata, 'referral_no'),
            'claim_check_code' => $encounter->claim_check_code,
            'duration_length' => $this->calculateDurationDays($encounter),
        ];

        $currency = strtoupper(substr((string) ($invoice?->currency ?? Currency::defaultCode()), 0, 3));

        $claim = InsuranceClaim::query()->create([
            'payer_id' => $payer->id,
            'batch_id' => $batch->id,
            'policy_id' => $policy?->id,
            'patient_id' => $encounter->patient_id,
            'invoice_id' => $invoice?->id,
            'encounter_id' => $encounter->id,
            'claim_number' => $this->generateClaimNumber($batch),
            'status' => ClaimStatus::DRAFT,
            'currency' => $currency,
            'nhia_payload' => $nhiaPayload,
        ]);

        $total = '0.00';
        $primaryOutpatientCode = null;
        $primaryOutpatientTariff = '0.00';

        $diagnoses = EncounterDiagnosis::query()
            ->where('encounter_id', $encounter->id)
            ->where('is_active', true)
            ->with('diagnosisCode')
            ->orderByRaw("CASE WHEN type = 'primary' THEN 0 ELSE 1 END")
            ->orderByDesc('is_new_case')
            ->get();

        $primaryIcd = $diagnoses->first()?->icd10_code
            ?? $diagnoses->first()?->icd_code
            ?? $diagnoses->first()?->diagnosisCode?->code;

        if ($primaryIcd) {
            $gdrg = $this->gdrgResolver->resolve(
                $payer,
                $primaryIcd,
                'OUT',
                $nhiaPayload['admission_date']
            );

            if ($gdrg !== null) {
                $primaryOutpatientCode = $gdrg['code'];
                $primaryOutpatientTariff = $gdrg['tariff'];
                $total = bcadd($total, $gdrg['tariff'], 2);
            }
        }

        $requestItems = RequestItem::query()
            ->whereHas('serviceRequest', fn ($q) => $q->where('encounter_id', $encounter->id))
            ->with(['service', 'serviceRequest'])
            ->get();

        foreach ($requestItems as $item) {
            $code = $this->codeMapper->mapServiceCode($item->service, $payer);
            $tariff = $this->codeMapper->mapServiceTariff($item->service, $payer, (string) $item->total_price);

            if (! $code) {
                $warnings[] = "Encounter {$encounter->encounter_number}: service [{$item->service?->name}] has no NHIS code.";
            }

            InsuranceClaimLine::query()->create([
                'claim_id' => $claim->id,
                'line_type' => ClaimLineType::TREATMENT,
                'external_item_code' => $code,
                'description' => $item->service?->name ?? 'Service',
                'quantity' => max(1, (int) $item->quantity),
                'billed_amount' => $tariff,
                'metadata' => [
                    'treatment_type' => 'Procedure',
                    'icd_code' => $primaryIcd,
                    'tariff' => $tariff,
                ],
            ]);

            $total = bcadd($total, $tariff, 2);
            $primaryOutpatientCode ??= $code;
            if ($primaryOutpatientCode === $code) {
                $primaryOutpatientTariff = $tariff;
            }
        }

        if ($diagnoses->isNotEmpty() && $requestItems->isEmpty()) {
            foreach ($diagnoses as $diagnosis) {
                InsuranceClaimLine::query()->create([
                    'claim_id' => $claim->id,
                    'line_type' => ClaimLineType::TREATMENT,
                    'external_item_code' => data_get($diagnosis->diagnosisCode?->metadata, 'nhis_code'),
                    'description' => $diagnosis->description ?? 'Diagnosis',
                    'quantity' => 1,
                    'billed_amount' => '0.00',
                    'metadata' => [
                        'treatment_type' => 'Diagnosis',
                        'icd_code' => $diagnosis->icd10_code ?? $diagnosis->icd_code ?? $diagnosis->diagnosisCode?->code,
                        'tariff' => '0.00',
                    ],
                ]);
            }
        }

        $dispenses = Dispense::query()
            ->whereHas('requestItem.serviceRequest', fn ($q) => $q->where('encounter_id', $encounter->id))
            ->with(['medication.service', 'requestItem'])
            ->get();

        if ($dispenses->isNotEmpty()) {
            $nhiaPayload['pharmacy_included'] = 'YES';
        }

        foreach ($dispenses as $dispense) {
            $medication = $dispense->medication;
            $code = $this->codeMapper->mapMedicationCode($medication, $payer);
            $unitPrice = $this->codeMapper->mapMedicationUnitPrice(
                $medication,
                $payer,
                bcdiv((string) ($dispense->requestItem?->unit_price ?? 0), '1', 2)
            );
            $lineTotal = bcmul($unitPrice, (string) max(1, $dispense->quantity), 2);

            if (! $code) {
                $warnings[] = "Encounter {$encounter->encounter_number}: medication [{$medication?->generic_name}] has no NHIS code.";
            }

            InsuranceClaimLine::query()->create([
                'claim_id' => $claim->id,
                'line_type' => ClaimLineType::MEDICINE,
                'external_item_code' => $code,
                'description' => $medication?->generic_name ?? 'Medication',
                'quantity' => max(1, (int) $dispense->quantity),
                'billed_amount' => $lineTotal,
                'metadata' => [
                    'unit_price' => $unitPrice,
                    'medicine_date' => ($dispense->dispensed_at ?? now())->toDateString(),
                ],
            ]);

            $total = bcadd($total, $lineTotal, 2);
        }

        if ($invoice) {
            $invoice->loadMissing('lines');

            foreach ($invoice->lines as $line) {
                if ((float) $line->insurance_expected_amount <= 0) {
                    continue;
                }

                if (InsuranceClaimLine::query()->where('claim_id', $claim->id)->where('invoice_line_id', $line->id)->exists()) {
                    continue;
                }

                InsuranceClaimLine::query()->create([
                    'claim_id' => $claim->id,
                    'invoice_line_id' => $line->id,
                    'line_type' => ClaimLineType::OTHER,
                    'external_item_code' => data_get($line->metadata, 'nhis_code'),
                    'description' => $line->description,
                    'quantity' => max(1, (int) $line->quantity),
                    'billed_amount' => (string) $line->insurance_expected_amount,
                    'metadata' => ['icd_code' => $primaryIcd],
                ]);

                $total = bcadd($total, (string) $line->insurance_expected_amount, 2);
            }
        }

        $claim->update([
            'total_billed_amount' => $total,
            'nhia_payload' => array_merge($nhiaPayload, [
                'outpatient_code' => $primaryOutpatientCode,
                'outpatient_tariff_amount' => $primaryOutpatientTariff,
            ]),
        ]);

        return [$claim->fresh(['lines', 'patient', 'policy']), $warnings];
    }

    protected function buildEncounterQuery(ClaimBatchCriteria $criteria)
    {
        $medicationEncounterIds = null;

        if ($criteria->medicationId) {
            $medicationEncounterIds = Dispense::query()
                ->where('medication_id', $criteria->medicationId)
                ->whereHas('requestItem.serviceRequest')
                ->with('requestItem.serviceRequest')
                ->get()
                ->pluck('requestItem.serviceRequest.encounter_id')
                ->filter()
                ->unique()
                ->values();
        }

        return Encounter::query()
            ->where('branch_id', $criteria->branchId)
            ->whereNotNull('patient_id')
            ->whereIn('status', [
                EncounterStatus::FINISHED->value,
                EncounterStatus::IN_PROGRESS->value,
            ])
            ->where(function ($query) {
                $query->where('coverage_type', CoverageType::NHIS)
                    ->orWhereHas('patient.insurancePolicies', function ($policyQuery) {
                        $policyQuery->where('is_active', true)
                            ->whereHas('payer', fn ($payer) => $payer->where('code', 'nhis'));
                    });
            })
            ->when($criteria->patientId, fn ($q) => $q->where('patient_id', $criteria->patientId))
            ->when($criteria->year, fn ($q) => $q->whereYear('admitted_at', $criteria->year))
            ->when($criteria->month, fn ($q) => $q->whereMonth('admitted_at', $criteria->month))
            ->when($criteria->serviceId, function ($q) use ($criteria) {
                $q->whereHas('serviceRequests.items', fn ($item) => $item->where('service_id', $criteria->serviceId));
            })
            ->when($medicationEncounterIds !== null, fn ($q) => $q->whereIn('id', $medicationEncounterIds))
            ->whereNotExists(function ($query) {
                $claim = new InsuranceClaim;

                $query->from($claim->getTable())
                    ->whereColumn($claim->qualifyColumn('encounter_id'), (new Encounter)->qualifyColumn('id'))
                    ->whereNotIn($claim->qualifyColumn('status'), [ClaimStatus::REJECTED->value]);
            });
    }

    protected function mapServiceType(Encounter $encounter): string
    {
        return match ($encounter->type) {
            EncounterType::INPATIENT => 'INP',
            EncounterType::EMERGENCY, EncounterType::OUTPATIENT, EncounterType::VIRTUAL, EncounterType::HOME_VISIT => 'OUT',
            default => 'OUT',
        };
    }

    protected function mapOutcomeType(?DischargeDisposition $disposition): string
    {
        return match ($disposition) {
            DischargeDisposition::COMPLETED => 'DIS',
            DischargeDisposition::TRANSFERRED, DischargeDisposition::REFERRED => 'TFR',
            DischargeDisposition::AGAINST_ADVICE => 'DAA',
            DischargeDisposition::DECEASED => 'DIE',
            default => 'DIS',
        };
    }

    protected function calculateDurationDays(Encounter $encounter): ?int
    {
        if (! $encounter->admitted_at || ! $encounter->discharged_at) {
            return null;
        }

        return max(1, (int) $encounter->admitted_at->diffInDays($encounter->discharged_at));
    }

    protected function generateClaimNumber(ClaimBatch $batch): string
    {
        return strtoupper(sprintf('CLM-%s-%s', $batch->batch_number, Str::random(6)));
    }
}

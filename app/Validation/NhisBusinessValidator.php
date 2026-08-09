<?php

namespace Modules\Insurance\Validation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\NhisMedicine;
use Modules\Insurance\Models\ProviderCredentialing;
use Modules\Insurance\Models\TariffBook;
use Modules\Insurance\Schemes\Nhis\NhisGdrgResolver;
use Modules\Insurance\Services\MemberVerificationService;
use Modules\Insurance\Settings\InsuranceSettings;

class NhisBusinessValidator
{
    public function __construct(
        protected InsuranceSettings $settings,
        protected MemberVerificationService $verification,
        protected NhisGdrgResolver $gdrgResolver,
    ) {}

    /**
     * @return array<int, ValidationItem>
     */
    public function validate(InsuranceClaim $claim): array
    {
        $claim->loadMissing(['patient', 'policy', 'lines', 'batch']);

        $items = [];

        $items = array_merge($items, $this->validateMember($claim));
        $items = array_merge($items, $this->validateTotalFormula($claim));
        $items = array_merge($items, $this->validateMasterVersion($claim));
        $items = array_merge($items, $this->validateMedicines($claim));
        $items = array_merge($items, $this->validateOutpatientGdrg($claim));
        $items = array_merge($items, $this->validatePrescribingLevel($claim));

        return $items;
    }

    /**
     * @return array<int, ValidationItem>
     */
    protected function validateMember(InsuranceClaim $claim): array
    {
        if ($this->settings->member_verification_mode === 'disabled') {
            return [];
        }

        [$memberNumber, $cardSerial] = $this->verificationIdentifiers($claim);
        $reference = (string) data_get($claim->nhia_payload, 'admission_date');

        $result = $this->verification->verifyNumbers($memberNumber, $cardSerial, $reference !== '' ? $reference : null);

        if ($result->verified()) {
            return [];
        }

        return [new ValidationItem(
            code: (string) ($result->errorCode ?? '016'),
            message: $result->errorCode === '203'
                ? "Member number {$memberNumber} is not found in the NHIS members master table."
                : ($result->errorCode === '016'
                    ? 'Member is inactive or the card validity period has lapsed.'
                    : "Member number {$memberNumber} does not match card serial number {$cardSerial}."),
            claimNumber: $claim->claim_number,
        )];
    }

    /**
     * Error 238: total cost must equal the outpatient G-DRG tariff plus the
     * itemized treatment and medicine totals.
     *
     * @return array<int, ValidationItem>
     */
    protected function validateTotalFormula(InsuranceClaim $claim): array
    {
        $outpatient = round((float) data_get($claim->nhia_payload, 'outpatient_tariff_amount', 0), 2);
        $treatment = round((float) $claim->lines
            ->where('line_type', ClaimLineType::TREATMENT)
            ->sum(fn ($line) => (float) $line->billed_amount), 2);
        $medicine = round((float) $claim->lines
            ->where('line_type', ClaimLineType::MEDICINE)
            ->sum(fn ($line) => (float) $line->billed_amount), 2);

        $expected = round($outpatient + $treatment + $medicine, 2);
        $actual = round((float) $claim->total_billed_amount, 2);

        if (abs($expected - $actual) > 0.01) {
            return [new ValidationItem(
                code: '238',
                message: "Total cost {$actual} does not equal outpatient tariff {$outpatient} plus treatment {$treatment} and medicine {$medicine} totals ({$expected}).",
                claimNumber: $claim->claim_number,
            )];
        }

        return [];
    }

    /**
     * Error 232: the tariff book linked to the batch's branch must be effective on the service date.
     *
     * @return array<int, ValidationItem>
     */
    protected function validateMasterVersion(InsuranceClaim $claim): array
    {
        $batch = $claim->batch;

        if (! $batch?->branch_id) {
            return [];
        }

        $bookId = DB::table('branch_tariff_book')
            ->where('branch_id', $batch->branch_id)
            ->value('tariff_book_id');

        if (! $bookId) {
            return [];
        }

        $tariffBook = TariffBook::find($bookId);

        if (! $tariffBook) {
            return [];
        }

        $reference = Carbon::parse((string) data_get($claim->nhia_payload, 'admission_date', now()));

        if ($tariffBook->effective_from && $tariffBook->effective_from->isAfter($reference)) {
            return [new ValidationItem(
                code: '232',
                message: "Tariff book [{$tariffBook->name}] is not yet effective for the service date.",
                claimNumber: $claim->claim_number,
            )];
        }

        if ($tariffBook->effective_to && $tariffBook->effective_to->isBefore($reference)) {
            return [new ValidationItem(
                code: '232',
                message: "Tariff book [{$tariffBook->name}] has expired for the service date.",
                claimNumber: $claim->claim_number,
            )];
        }

        return [];
    }

    /**
     * Error 281: each dispensed medicine must be effective on the dispensing date.
     *
     * @return array<int, ValidationItem>
     */
    protected function validateMedicines(InsuranceClaim $claim): array
    {
        $items = [];

        $reference = Carbon::parse((string) data_get($claim->nhia_payload, 'admission_date', now()));

        foreach ($claim->lines->where('line_type', ClaimLineType::MEDICINE) as $line) {
            if (! filled($line->external_item_code)) {
                continue;
            }

            $medicine = NhisMedicine::query()
                ->where('code', $line->external_item_code)
                ->first();

            if (! $medicine) {
                $items[] = new ValidationItem(
                    code: '281',
                    message: "Medicine [{$line->external_item_code}] is not found in the NHIA medicine catalog.",
                    claimNumber: $claim->claim_number,
                );

                continue;
            }

            if ($medicine->effective_from && $medicine->effective_from->isAfter($reference)) {
                $items[] = new ValidationItem(
                    code: '281',
                    message: "Medicine [{$medicine->code}] is not effective on the dispensing date.",
                    claimNumber: $claim->claim_number,
                );
            }

            if ($medicine->effective_to && $medicine->effective_to->isBefore($reference)) {
                $items[] = new ValidationItem(
                    code: '281',
                    message: "Medicine [{$medicine->code}] expired before the dispensing date.",
                    claimNumber: $claim->claim_number,
                );
            }
        }

        return $items;
    }

    /**
     * Errors 242/243/275/276: outpatient G-DRG must be mapped in Annex C and the
     * resolved tariff must be effective on the service date.
     *
     * @return array<int, ValidationItem>
     */
    protected function validateOutpatientGdrg(InsuranceClaim $claim): array
    {
        $outpatientCode = (string) data_get($claim->nhia_payload, 'outpatient_code', '');
        $serviceType = (string) data_get($claim->nhia_payload, 'service_type', 'OUT');
        $reference = (string) data_get($claim->nhia_payload, 'admission_date');

        if (! filled($outpatientCode)) {
            return [];
        }

        $map = $this->gdrgResolver->mapForCode($outpatientCode, $serviceType);

        if (! $map) {
            return [new ValidationItem(
                code: '242',
                message: "Outpatient code {$outpatientCode} is not mapped in the Annex C G-DRG catalog.",
                claimNumber: $claim->claim_number,
            )];
        }

        if (! $claim->payer) {
            return [];
        }

        $tariff = $this->gdrgResolver->resolveTariff(
            $claim->payer,
            $map->gdrg_code,
            $reference !== '' ? $reference : null,
        );

        if (! $tariff) {
            return [new ValidationItem(
                code: '243',
                message: "No effective tariff found for G-DRG [{$map->gdrg_code}] on the service date.",
                claimNumber: $claim->claim_number,
            )];
        }

        $serviceDate = Carbon::parse($reference !== '' ? $reference : now());

        if ($tariff->effective_from && $tariff->effective_from->isAfter($serviceDate)) {
            return [new ValidationItem(
                code: '275',
                message: "G-DRG [{$map->gdrg_code}] is not effective on the service date.",
                claimNumber: $claim->claim_number,
            )];
        }

        if ($tariff->effective_to && $tariff->effective_to->isBefore($serviceDate)) {
            return [new ValidationItem(
                code: '276',
                message: "G-DRG [{$map->gdrg_code}] expired before the service date.",
                claimNumber: $claim->claim_number,
            )];
        }

        return [];
    }

    /**
     * Error 231: the diagnosing prescriber's credentialing level must cover the
     * prescribed medicine's prescribing level.
     *
     * @return array<int, ValidationItem>
     */
    protected function validatePrescribingLevel(InsuranceClaim $claim): array
    {
        if (! $this->settings->enable_prescribing_level_warning) {
            return [];
        }

        $items = [];

        $prescriber = $this->prescriberId($claim);

        if (! $prescriber) {
            return [];
        }

        $credentialing = ProviderCredentialing::query()
            ->where('staff_id', $prescriber)
            ->where('is_active', true)
            ->first();

        $highestMedicine = $claim->lines
            ->where('line_type', ClaimLineType::MEDICINE)
            ->map(function ($line) {
                return NhisMedicine::query()
                    ->where('code', $line->external_item_code)
                    ->first();
            })
            ->filter()
            ->sortByDesc(fn (NhisMedicine $medicine): int => $medicine->prescribing_level)
            ->first();

        if (! $highestMedicine) {
            return [];
        }

        $prescribingLevel = $credentialing?->prescribing_level ?? $this->settings->prescribing_level ?? 1;
        $prescriberCode = $credentialing?->prescribing_level_code
            ?? NhisPrescribingLevel::tryFromOrdinal((int) $prescribingLevel)?->value
            ?? (string) $prescribingLevel;
        $medicineCode = $highestMedicine->prescribing_level_code
            ?? NhisPrescribingLevel::tryFromOrdinal((int) $highestMedicine->prescribing_level)?->value
            ?? (string) $highestMedicine->prescribing_level;

        if ($highestMedicine->prescribing_level > $prescribingLevel) {
            $items[] = new ValidationItem(
                code: '231',
                message: "Prescriber is credentialled to level {$prescriberCode} but a medicine requires level {$medicineCode}.",
                severity: 'warning',
                claimNumber: $claim->claim_number,
            );
        }

        return $items;
    }

    protected function prescriberId(InsuranceClaim $claim): ?int
    {
        $encounter = $claim->encounter;

        if (! $encounter) {
            return null;
        }

        $participant = $encounter->participants()
            ->whereIn('role', ['doctor', 'consultant', 'prescriber'])
            ->orderBy('joined_at')
            ->first();

        return $participant?->user_id ?? $encounter->admitted_by ?? $encounter->created_by;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function verificationIdentifiers(InsuranceClaim $claim): array
    {
        if ($this->isInfantClaim($claim)) {
            return [
                (string) data_get($claim->policy?->metadata, 'mother_member_number', ''),
                (string) data_get($claim->policy?->metadata, 'mother_card_serial_number', ''),
            ];
        }

        return [
            (string) ($claim->policy?->member_number ?? ''),
            (string) data_get($claim->policy?->metadata, 'card_serial_number', ''),
        ];
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

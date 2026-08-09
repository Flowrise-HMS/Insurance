<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Carbon;
use Modules\Insurance\Contracts\MemberVerifier;
use Modules\Insurance\Models\MembersMaster;
use Modules\Insurance\Models\NhisMedicine;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Insurance\Verification\MemberVerification;
use Modules\Insurance\Verification\OfflineMasterVerifier;

class MemberVerificationService
{
    public function __construct(
        protected InsuranceSettings $settings,
    ) {}

    public function verify(PatientPolicy $policy, ?string $referenceDate = null): MemberVerification
    {
        $result = $this->verifyNumbers(
            trim((string) $policy->member_number),
            trim((string) data_get($policy->metadata, 'card_serial_number')),
            $referenceDate,
        );

        $this->persist($policy, $result);

        return $result;
    }

    public function verifyNumbers(string $memberNumber, string $cardSerial, ?string $referenceDate = null): MemberVerification
    {
        if ($this->settings->member_verification_mode === 'disabled') {
            return new MemberVerification(
                status: 'unverified',
                checkedAt: now(),
                source: 'disabled',
            );
        }

        $reference = $referenceDate !== null ? Carbon::parse($referenceDate) : null;

        return $this->resolver()->verifyNumbers($memberNumber, $cardSerial, $reference);
    }

    protected function resolver(): MemberVerifier
    {
        return app(OfflineMasterVerifier::class);
    }

    protected function persist(PatientPolicy $policy, MemberVerification $result): void
    {
        $policy->update([
            'metadata' => array_merge($policy->metadata ?? [], [
                'verification_status' => $result->status,
                'verification_error_code' => $result->errorCode,
                'verified_at' => $result->checkedAt?->toDateTimeString(),
                'verification_source' => $result->source,
            ]),
        ]);
    }

    /**
     * Summarise the latest persisted verification result for display in admin views.
     *
     * @return array{status: string, label: string, color: string, error_code: string|null, checked_at: string|null, source: string|null}
     */
    public function badge(PatientPolicy $policy): array
    {
        $metadata = $policy->metadata ?? [];
        $status = $metadata['verification_status'] ?? null;
        $errorCode = $metadata['verification_error_code'] ?? null;

        return match ($status) {
            'verified' => [
                'status' => 'verified',
                'label' => 'Member Verified',
                'color' => 'success',
                'error_code' => null,
                'checked_at' => $metadata['verified_at'] ?? null,
                'source' => $metadata['verification_source'] ?? null,
            ],
            'invalid' => [
                'status' => 'invalid',
                'label' => $errorCode !== null ? "Member Invalid ({$errorCode})" : 'Member Invalid',
                'color' => 'danger',
                'error_code' => $errorCode,
                'checked_at' => $metadata['verified_at'] ?? null,
                'source' => $metadata['verification_source'] ?? null,
            ],
            'unverified' => [
                'status' => 'unverified',
                'label' => 'Verification Disabled',
                'color' => 'gray',
                'error_code' => null,
                'checked_at' => $metadata['verified_at'] ?? null,
                'source' => $metadata['verification_source'] ?? null,
            ],
            default => [
                'status' => 'not_checked',
                'label' => 'Not Verified',
                'color' => 'gray',
                'error_code' => null,
                'checked_at' => null,
                'source' => null,
            ],
        };
    }

    /**
     * Report whether NHIA master data has been imported, for the "import status" indicator.
     *
     * @return array{imported: bool, members: int, medicines: int, tariff_items: int}
     */
    public function masterDataStatus(): array
    {
        $members = MembersMaster::query()->count();
        $medicines = NhisMedicine::query()->count();
        $tariffItems = TariffItem::query()->count();

        return [
            'imported' => $members > 0 || $medicines > 0 || $tariffItems > 0,
            'members' => $members,
            'medicines' => $medicines,
            'tariff_items' => $tariffItems,
        ];
    }
}

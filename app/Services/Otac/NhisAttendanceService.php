<?php

namespace Modules\Insurance\Services\Otac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Enums\CoverageType;
use Modules\Insurance\DTOs\OtacAttendanceResult;
use Modules\Insurance\Models\MembersMaster;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Models\PatientIdentifier;

/**
 * Generates NHIS claim check codes for encounters via the NHIA OTAC API and
 * persists the returned member details onto the patient's NHIS policy.
 */
class NhisAttendanceService
{
    public function __construct(private OtacClient $client) {}

    public function isEnabled(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * The encounter is typed as Model to keep Insurance free of a hard
     * Clinical dependency; it must expose coverage_type, claim_check_code,
     * patient_id, and an array metadata cast.
     */
    public function generateForEncounter(Model $encounter): OtacAttendanceResult
    {
        if (! $this->isEnabled()) {
            return OtacAttendanceResult::skipped('NHIA OTAC is not enabled or not configured.');
        }

        $coverage = $encounter->coverage_type instanceof CoverageType
            ? $encounter->coverage_type->value
            : (string) $encounter->coverage_type;

        if ($coverage !== CoverageType::NHIS->value) {
            return OtacAttendanceResult::skipped('Encounter is not covered by NHIS.');
        }

        if (filled($encounter->claim_check_code)) {
            return OtacAttendanceResult::skipped('Encounter already has a claim check code.');
        }

        if (blank($encounter->patient_id)) {
            return OtacAttendanceResult::skipped('Encounter has no registered patient.');
        }

        $policy = PatientPolicy::query()
            ->where('patient_id', $encounter->patient_id)
            ->where('is_active', true)
            ->whereHas('payer', fn ($payer) => $payer->where('code', 'nhis'))
            ->orderByDesc('is_primary')
            ->first();

        [$cardType, $cardNo] = $this->resolveCard($policy, (string) $encounter->patient_id);

        if ($cardNo === null) {
            return OtacAttendanceResult::skipped('No NHIS membership or Ghana Card on file for this patient.');
        }

        $response = $this->client->generateAttendance($cardType, $cardNo);
        $attendanceData = $response['attendanceData'] ?? null;
        $ccc = is_array($attendanceData) ? (string) ($attendanceData['ccc'] ?? '') : '';

        if ((int) ($response['statusCode'] ?? -1) !== 0 || $ccc === '') {
            $message = (string) ($response['statusMessage'] ?? 'Unknown OTAC error.');

            Log::info('nhis.otac.attendance_failed', [
                'encounter_id' => $encounter->getKey(),
                'card_type' => $cardType,
                'status_code' => $response['statusCode'] ?? null,
                'status_message' => $message,
            ]);

            return OtacAttendanceResult::failed($message);
        }

        DB::transaction(function () use ($encounter, $policy, $cardType, $ccc, $attendanceData): void {
            $encounter->update([
                'claim_check_code' => $ccc,
                'metadata' => array_merge($encounter->metadata ?? [], [
                    'nhis_attendance' => [
                        'ccc' => $ccc,
                        'auth_id' => $attendanceData['authID'] ?? null,
                        'hin' => $attendanceData['hin'] ?? null,
                        'attendance_date' => $attendanceData['attendanceDate'] ?? null,
                        'new_ccc' => $attendanceData['newCCC'] ?? null,
                        'card_type' => $cardType,
                        'generated_at' => now()->toDateTimeString(),
                    ],
                ]),
            ]);

            if ($policy !== null && $cardType === 'NHISCARD') {
                $this->persistMemberDetails($policy, $attendanceData);
                $this->syncMembersMaster(trim((string) $policy->member_number), $attendanceData);
            }
        });

        Log::info('nhis.otac.attendance_generated', [
            'encounter_id' => $encounter->getKey(),
            'card_type' => $cardType,
            'new_ccc' => $attendanceData['newCCC'] ?? null,
        ]);

        return OtacAttendanceResult::generated($ccc, $attendanceData);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveCard(?PatientPolicy $policy, string $patientId): array
    {
        if ($policy !== null && filled($policy->member_number)) {
            return ['NHISCARD', trim((string) $policy->member_number)];
        }

        $ghanaCard = PatientIdentifier::query()
            ->where('patient_id', $patientId)
            ->where('type', IdentifierType::NATIONAL_ID->value)
            ->get()
            ->first(fn (PatientIdentifier $identifier) => preg_match('/^GHA-\d{9}-\d$/', trim((string) $identifier->value)) === 1);

        if ($ghanaCard !== null) {
            return ['GHANACARD', trim((string) $ghanaCard->value)];
        }

        return ['NHISCARD', null];
    }

    /**
     * Store the NHIA-confirmed member details using the same metadata keys
     * MemberVerificationService::badge() reads, so the existing verification
     * badges light up from an OTAC result.
     *
     * @param  array<string, mixed>  $attendanceData
     */
    private function persistMemberDetails(PatientPolicy $policy, array $attendanceData): void
    {
        $startDate = $this->parseDate($attendanceData['startDate'] ?? null);
        $endDate = $this->parseDate($attendanceData['endDate'] ?? null);

        $policy->update(array_filter([
            'effective_from' => $startDate,
            'effective_to' => $endDate,
        ], fn ($value) => $value !== null) + [
            'metadata' => array_merge($policy->metadata ?? [], [
                'verification_status' => 'verified',
                'verification_error_code' => null,
                'verified_at' => now()->toDateTimeString(),
                'verification_source' => 'otac',
                'otac_member' => [
                    'member_name' => $attendanceData['memberName'] ?? null,
                    'gender' => $attendanceData['gender'] ?? null,
                    'dob' => $attendanceData['dob'] ?? null,
                    'member_phone' => $attendanceData['memberPhone'] ?? null,
                    'hin' => $attendanceData['hin'] ?? null,
                    'membership_start' => $attendanceData['startDate'] ?? null,
                    'membership_end' => $attendanceData['endDate'] ?? null,
                ],
            ]),
        ]);
    }

    /**
     * Refresh the offline members_master roster from an NHIA-confirmed
     * attendance so offline verification agrees with the live result. OTAC
     * responses carry no card serial, so OTAC-sourced rows use an empty one;
     * an existing imported row for the member is updated instead.
     *
     * @param  array<string, mixed>  $attendanceData
     */
    private function syncMembersMaster(string $memberNumber, array $attendanceData): void
    {
        if ($memberNumber === '') {
            return;
        }

        [$firstName, $lastName] = $this->splitMemberName((string) ($attendanceData['memberName'] ?? ''));

        $attributes = array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $this->parseDate($attendanceData['dob'] ?? null),
            'gender' => $attendanceData['gender'] ?? null,
            'valid_from' => $this->parseDate($attendanceData['startDate'] ?? null),
            'valid_to' => $this->parseDate($attendanceData['endDate'] ?? null),
        ], fn ($value) => $value !== null) + [
            'is_active' => true,
            'source_file' => 'otac',
            'imported_at' => now(),
        ];

        $existing = MembersMaster::query()->where('member_number', $memberNumber)->first();

        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        MembersMaster::query()->create($attributes + [
            'member_number' => $memberNumber,
            'card_serial_number' => '',
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitMemberName(string $memberName): array
    {
        $parts = preg_split('/\s+/', trim($memberName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return [null, null];
        }

        $lastName = array_pop($parts);

        return [$parts === [] ? null : implode(' ', $parts), $lastName];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

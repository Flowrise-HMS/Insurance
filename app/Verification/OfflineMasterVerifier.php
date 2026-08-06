<?php

namespace Modules\Insurance\Verification;

use Carbon\CarbonInterface;
use Modules\Insurance\Contracts\MemberVerifier;
use Modules\Insurance\Models\MembersMaster;
use Modules\Insurance\Models\PatientPolicy;

class OfflineMasterVerifier implements MemberVerifier
{
    public function verify(PatientPolicy $policy, ?CarbonInterface $referenceDate = null): MemberVerification
    {
        $memberNumber = trim((string) $policy->member_number);
        $cardSerial = trim((string) data_get($policy->metadata, 'card_serial_number'));

        return $this->verifyNumbers($memberNumber, $cardSerial, $referenceDate);
    }

    public function verifyNumbers(
        string $memberNumber,
        string $cardSerial,
        ?CarbonInterface $referenceDate = null,
    ): MemberVerification {
        if ($memberNumber === '' || $cardSerial === '') {
            return $this->result('invalid', '204', $referenceDate);
        }

        $master = MembersMaster::query()
            ->where('member_number', $memberNumber)
            ->where('card_serial_number', $cardSerial)
            ->first();

        if (! $master) {
            $memberExists = MembersMaster::query()
                ->where('member_number', $memberNumber)
                ->where('is_active', true)
                ->exists();

            return $this->result('invalid', $memberExists ? '204' : '203', $referenceDate);
        }

        if (! $master->is_active) {
            return $this->result('invalid', '016', $referenceDate);
        }

        $date = ($referenceDate ?? now())->toDateString();

        if ($master->valid_from && $master->valid_from->toDateString() > $date) {
            return $this->result('invalid', '016', $referenceDate);
        }

        if ($master->valid_to && $master->valid_to->toDateString() < $date) {
            return $this->result('invalid', '016', $referenceDate);
        }

        return new MemberVerification(
            status: 'verified',
            checkedAt: now(),
            source: 'members_master',
        );
    }

    protected function result(
        string $status,
        ?string $errorCode,
        ?CarbonInterface $referenceDate,
    ): MemberVerification {
        return new MemberVerification(
            status: $status,
            errorCode: $errorCode,
            checkedAt: now(),
            source: 'members_master',
        );
    }
}

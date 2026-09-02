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
        return $this->verifyNumbers(trim((string) $policy->member_number), $referenceDate);
    }

    public function verifyNumbers(
        string $memberNumber,
        ?CarbonInterface $referenceDate = null,
    ): MemberVerification {
        if ($memberNumber === '') {
            return $this->result('invalid', '203', $referenceDate);
        }

        $master = MembersMaster::query()
            ->where('member_number', $memberNumber)
            ->orderByDesc('is_active')
            ->orderByDesc('valid_to')
            ->first();

        if (! $master) {
            return $this->result('invalid', '203', $referenceDate);
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

<?php

namespace Modules\Insurance\Contracts;

use Carbon\CarbonInterface;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Verification\MemberVerification;

interface MemberVerifier
{
    public function verify(PatientPolicy $policy, ?CarbonInterface $referenceDate = null): MemberVerification;
}

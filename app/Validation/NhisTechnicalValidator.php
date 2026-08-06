<?php

namespace Modules\Insurance\Validation;

use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Schemes\Nhis\NhisClaimValidator;

class NhisTechnicalValidator
{
    public function __construct(
        protected NhisClaimValidator $claimValidator,
    ) {}

    /**
     * @return array<int, ValidationItem>
     */
    public function validate(InsuranceClaim $claim): array
    {
        $result = $this->claimValidator->validate($claim);
        $items = [];

        foreach ($result->codedErrors as $error) {
            $items[] = new ValidationItem(
                code: $error['code'] ?? 'TECH',
                message: $error['message'],
                severity: 'error',
                claimNumber: $claim->claim_number,
            );
        }

        foreach ($result->warnings as $warning) {
            $items[] = new ValidationItem(
                code: 'WARN',
                message: $warning,
                severity: 'warning',
                claimNumber: $claim->claim_number,
            );
        }

        return $items;
    }
}

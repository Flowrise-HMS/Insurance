<?php

namespace Modules\Insurance\Validation;

use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;

class ClaimValidationEngine
{
    public function __construct(
        protected NhisBatchValidator $batchValidator,
        protected NhisTechnicalValidator $technicalValidator,
        protected NhisBusinessValidator $businessValidator,
    ) {}

    public function validateClaim(InsuranceClaim $claim): ValidationReport
    {
        $items = [];

        $items = array_merge($items, $this->technicalValidator->validate($claim));
        $items = array_merge($items, $this->businessValidator->validate($claim));

        return new ValidationReport($items);
    }

    public function validateBatch(ClaimBatch $batch): ValidationReport
    {
        $batch->loadMissing('claims');

        $items = $this->batchValidator->validate($batch);

        foreach ($batch->claims as $claim) {
            $items = array_merge($items, $this->technicalValidator->validate($claim));
            $items = array_merge($items, $this->businessValidator->validate($claim));
        }

        return new ValidationReport($items);
    }
}

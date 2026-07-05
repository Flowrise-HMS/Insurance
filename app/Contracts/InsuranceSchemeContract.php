<?php

namespace Modules\Insurance\Contracts;

use Modules\Insurance\DTOs\ClaimGenerationResult;
use Modules\Insurance\DTOs\ExportedFile;
use Modules\Insurance\DTOs\ValidationResult;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Support\ClaimBatchCriteria;

interface InsuranceSchemeContract
{
    public function code(): string;

    public function isEnabled(): bool;

    public function canUserManagePayer(): bool;

    public function generateClaims(ClaimBatchCriteria $criteria, ClaimBatch $batch): ClaimGenerationResult;

    /**
     * @return array<int, mixed>
     */
    public function buildClaimFormSchema(InsuranceClaim $claim): array;

    public function validateClaim(InsuranceClaim $claim): ValidationResult;

    public function exportBatch(ClaimBatch $batch): ExportedFile;
}

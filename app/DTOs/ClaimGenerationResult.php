<?php

namespace Modules\Insurance\DTOs;

use Illuminate\Support\Collection;
use Modules\Insurance\Models\InsuranceClaim;

final readonly class ClaimGenerationResult
{
    /**
     * @param  Collection<int, InsuranceClaim>  $claims
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public Collection $claims,
        public int $patientsCount,
        public array $warnings = [],
    ) {}
}

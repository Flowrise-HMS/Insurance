<?php

namespace Modules\Insurance\Support;

final readonly class ClaimBatchCriteria
{
    public function __construct(
        public string $schemeCode,
        public string $branchId,
        public ?string $patientId = null,
        public ?int $year = null,
        public ?int $month = null,
        public ?string $serviceId = null,
        public ?string $medicationId = null,
        public bool $all = false,
    ) {}

    public function toArray(): array
    {
        return [
            'scheme_code' => $this->schemeCode,
            'branch_id' => $this->branchId,
            'patient_id' => $this->patientId,
            'year' => $this->year,
            'month' => $this->month,
            'service_id' => $this->serviceId,
            'medication_id' => $this->medicationId,
            'all' => $this->all,
        ];
    }
}

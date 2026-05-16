<?php

namespace Modules\Insurance\Services;

use Modules\Insurance\Models\PatientPolicy;

class PatientInsuranceService
{
    /**
     * Create a patient policy from form data.
     */
    public function createPolicyFromData(string $patientId, array $data): ?PatientPolicy
    {
        if (! config('insurance.enabled', true) || empty($data['insurance_payer_id'])) {
            return null;
        }

        return PatientPolicy::query()->create([
            'patient_id' => $patientId,
            'payer_id' => $data['insurance_payer_id'],
            'member_number' => $data['insurance_member_number'],
            'effective_from' => $data['insurance_effective_from'] ?? null,
            'effective_to' => $data['insurance_effective_to'] ?? null,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }
}

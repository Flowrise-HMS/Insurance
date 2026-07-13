<?php

namespace Modules\Insurance\Services;

use Modules\Insurance\Models\PatientPolicy;

class PatientInsuranceService
{
    /**
     * Upsert the patient's primary active policy from form data.
     */
    public function syncFromFormData(string $patientId, array $data): ?PatientPolicy
    {
        if (! config('insurance.enabled', true) || empty($data['insurance_payer_id'])) {
            return null;
        }

        $policy = PatientPolicy::query()
            ->where('patient_id', $patientId)
            ->where('payer_id', $data['insurance_payer_id'])
            ->where('is_active', true)
            ->first();

        $attributes = [
            'patient_id' => $patientId,
            'payer_id' => $data['insurance_payer_id'],
            'member_number' => $data['insurance_member_number'] ?? null,
            'effective_from' => $data['insurance_effective_from'] ?? null,
            'effective_to' => $data['insurance_effective_to'] ?? null,
            'is_primary' => true,
            'is_active' => true,
        ];

        if ($policy) {
            $policy->update($attributes);

            return $policy->fresh();
        }

        return PatientPolicy::query()->create($attributes);
    }

    /**
     * Hydrate form data array from a policy record for edit form pre-fill.
     *
     * @return array<string, mixed>
     */
    public function formDataFromPolicy(?PatientPolicy $policy): array
    {
        if (! $policy) {
            return [];
        }

        return [
            'insurance_payer_id' => $policy->payer_id,
            'insurance_member_number' => $policy->member_number,
            'insurance_effective_from' => $policy->effective_from,
            'insurance_effective_to' => $policy->effective_to,
        ];
    }

    /**
     * Remove insurance_* keys before patient model fill.
     *
     * @return array<string, mixed>
     */
    public function stripInsuranceFields(array $data): array
    {
        return array_filter($data, fn (string $key) => ! str_starts_with($key, 'insurance_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Create a patient policy from form data (deprecated, use syncFromFormData instead).
     *
     * @deprecated Use syncFromFormData instead.
     */
    public function createPolicyFromData(string $patientId, array $data): ?PatientPolicy
    {
        return $this->syncFromFormData($patientId, $data);
    }
}

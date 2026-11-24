<?php

namespace Modules\Insurance\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Services\MemberVerificationService;
use Modules\Patient\Models\Patient;

/**
 * @group Insurance
 *
 * Patients' insurance policies (branch isolation through the patient). Making a
 * policy primary demotes the patient's other policies.
 */
class PatientPolicyController extends ResourceApiController
{
    protected string $branchScope = 'via:patient';

    public function __construct(protected MemberVerificationService $verification) {}

    public static function model(): string
    {
        return PatientPolicy::class;
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'payer_id', 'member_number', 'plan_code', 'effective_from', 'effective_to', 'is_primary', 'is_active',
            'metadata.verification_status', 'metadata.verified_at', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'payer_id' => 'payer_id', 'is_active' => 'is_active', 'member_number' => 'member_number'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'payer_id' => ['required', 'uuid', 'exists:insurance_payers,id'],
            'member_number' => ['required', 'string', 'max:100'],
            'plan_code' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return collect(parent::updateRules($record))->except('patient_id')->all();
    }

    protected function createRecord(array $data): Model
    {
        Patient::query()->findOrFail($data['patient_id']);
        $policy = PatientPolicy::query()->create($data + ['is_primary' => true, 'is_active' => true]);
        $policy->demoteOtherPrimaries();

        return $policy;
    }

    protected function updateRecord(Model $record, array $data): Model
    {
        $record->update($data);
        $record->demoteOtherPrimaries();

        return $record;
    }

    /**
     * Verify the member number (offline members master).
     */
    public function verify(string $id): JsonResponse
    {
        return $this->act($id, 'update', function (PatientPolicy $policy): PatientPolicy {
            $this->verification->verify($policy);

            return $policy->refresh();
        });
    }
}

<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Insurance\Enums\ClaimStatus;

class InsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $claimId = $this->route('claim')?->id;

        return [
            'payer_id' => ['required', 'uuid', 'exists:insurance_payers,id'],
            'policy_id' => ['nullable', 'uuid', 'exists:insurance_patient_policies,id'],
            'patient_id' => ['required', 'uuid', 'exists:patients,id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:invoices,id'],
            'claim_number' => ['nullable', 'string', 'max:100', Rule::unique('insurance_claims', 'claim_number')->ignore($claimId)],
            'status' => ['nullable', Rule::enum(ClaimStatus::class)],
            'total_billed_amount' => ['nullable', 'numeric', 'min:0'],
            'total_approved_amount' => ['nullable', 'numeric', 'min:0'],
            'total_rejected_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'submitted_at' => ['nullable', 'date'],
            'reconciled_at' => ['nullable', 'date', 'after_or_equal:submitted_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'payer_id.required' => 'Payer is required.',
            'patient_id.required' => 'Patient is required.',
            'reconciled_at.after_or_equal' => 'Reconciliation date cannot be before submission date.',
        ];
    }
}

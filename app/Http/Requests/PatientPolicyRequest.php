<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payer_id' => ['required', 'uuid', 'exists:insurance_payers,id'],
            'patient_id' => ['required', 'uuid', 'exists:patients,id'],
            'member_number' => ['required', 'string', 'max:100'],
            'plan_code' => ['nullable', 'string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'payer_id.required' => 'Payer is required.',
            'patient_id.required' => 'Patient is required.',
            'member_number.required' => 'Member number is required.',
            'effective_to.after' => 'Policy end date must be after start date.',
        ];
    }
}

<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Insurance\Enums\PayerType;

class PayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $payerId = $this->route('payer')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('insurance_payers', 'code')->ignore($payerId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PayerType::class)],
            'is_active' => ['nullable', 'boolean'],
            'config' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Payer code is required.',
            'code.unique' => 'This payer code is already in use.',
            'name.required' => 'Payer name is required.',
            'type.required' => 'Payer type is required.',
        ];
    }
}

<?php

namespace Modules\Insurance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Jobs\SubmitInsuranceClaimJob;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;

class ClaimSubmissionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'payer_id' => ['required', 'uuid'],
            'policy_id' => ['nullable', 'uuid'],
            'patient_id' => ['required', 'uuid'],
            'invoice_id' => ['nullable', 'uuid'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.external_item_code' => ['nullable', 'string', 'max:128'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.billed_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $claim = InsuranceClaim::query()->create([
            'payer_id' => $data['payer_id'],
            'policy_id' => $data['policy_id'] ?? null,
            'patient_id' => $data['patient_id'],
            'invoice_id' => $data['invoice_id'] ?? null,
            'claim_number' => strtoupper('CLM-'.Str::random(12)),
            'status' => ClaimStatus::VALIDATED,
            'total_billed_amount' => collect($data['lines'])->sum('billed_amount'),
            'currency' => strtoupper((string) ($data['currency'] ?? 'GHS')),
        ]);

        foreach ($data['lines'] as $line) {
            InsuranceClaimLine::query()->create([
                'claim_id' => $claim->id,
                'invoice_line_id' => $line['invoice_line_id'] ?? null,
                'external_item_code' => $line['external_item_code'] ?? null,
                'description' => $line['description'],
                'quantity' => (int) $line['quantity'],
                'billed_amount' => (string) $line['billed_amount'],
            ]);
        }

        $claim->update([
            'status' => ClaimStatus::QUEUED,
        ]);
        SubmitInsuranceClaimJob::dispatch((string) $claim->id);

        return response()->json([
            'claim_id' => $claim->id,
            'claim_number' => $claim->claim_number,
            'status' => ClaimStatus::QUEUED->value,
        ], 201);
    }
}

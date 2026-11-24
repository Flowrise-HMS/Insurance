<?php

namespace Modules\Insurance\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Billing\Models\Invoice;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Core\Http\Resources\FieldListTransformer;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\ClaimSubmissionService;
use Modules\Patient\Models\Patient;

/**
 * @group Insurance
 *
 * Insurance claims. NHIS claims are generated and exported in claim batches in
 * the panel; private-payer claims can be created here and submitted through the
 * payer's connector.
 */
class InsuranceClaimController extends ResourceApiController
{
    protected string $branchScope = 'via:patient';

    protected array $with = ['lines'];

    public function __construct(protected ClaimSubmissionService $submissions) {}

    public static function model(): string
    {
        return InsuranceClaim::class;
    }

    public static function label(): string
    {
        return 'claim';
    }

    public function fields(): array
    {
        return ['id', 'claim_number', 'payer_id', 'batch_id', 'policy_id', 'patient_id', 'invoice_id', 'encounter_id', 'status',
            'total_billed_amount', 'total_approved_amount', 'total_rejected_amount', 'currency', 'submitted_at', 'reviewed_at',
            'reconciled_at', 'created_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'payer_id' => 'payer_id', 'status' => 'status', 'batch_id' => 'batch_id'];
    }

    public function searchable(): array
    {
        return ['claim_number'];
    }

    public function storeRules(): array
    {
        return [
            'payer_id' => ['required', 'uuid', 'exists:insurance_payers,id'],
            'patient_id' => ['required', 'uuid'],
            'policy_id' => ['nullable', 'uuid', 'exists:insurance_patient_policies,id'],
            'invoice_id' => ['nullable', 'uuid'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.external_item_code' => ['nullable', 'string', 'max:128'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.billed_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $payer = Payer::query()->findOrFail($data['payer_id']);

        if ($payer->type === PayerType::NHIS) {
            throw new \DomainException('NHIS claims are generated and exported in claim batches, not created over the API.');
        }

        $patient = Patient::query()->findOrFail($data['patient_id']);
        $this->assertBelongsTogether($data, $payer, $patient);

        return DB::transaction(function () use ($data, $payer, $patient): InsuranceClaim {
            $claim = InsuranceClaim::query()->create([
                'payer_id' => $payer->id,
                'policy_id' => $data['policy_id'] ?? null,
                'patient_id' => $patient->id,
                'invoice_id' => $data['invoice_id'] ?? null,
                'claim_number' => strtoupper('CLM-'.Str::random(12)),
                'status' => ClaimStatus::VALIDATED,
                'total_billed_amount' => collect($data['lines'])->sum('billed_amount'),
                'currency' => strtoupper((string) ($data['currency'] ?? 'GHS')),
            ]);

            foreach ($data['lines'] as $line) {
                $claim->lines()->create([
                    'invoice_line_id' => $line['invoice_line_id'] ?? null,
                    'external_item_code' => $line['external_item_code'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'billed_amount' => $line['billed_amount'],
                ]);
            }

            return $claim;
        });
    }

    /**
     * The policy, invoice and invoice lines must belong to the claimed patient (and payer).
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertBelongsTogether(array $data, Payer $payer, Patient $patient): void
    {
        if (isset($data['policy_id']) && ! PatientPolicy::query()->whereKey($data['policy_id'])->where('patient_id', $patient->id)->where('payer_id', $payer->id)->exists()) {
            throw new \DomainException('The policy does not belong to this patient and payer.');
        }

        $invoice = isset($data['invoice_id']) ? Invoice::query()->whereKey($data['invoice_id'])->where('patient_id', $patient->id)->first() : null;

        if (isset($data['invoice_id']) && $invoice === null) {
            throw new \DomainException('The invoice does not belong to this patient.');
        }

        $lineIds = collect($data['lines'])->pluck('invoice_line_id')->filter()->unique();

        if ($lineIds->isNotEmpty() && ($invoice === null || $invoice->lines()->whereKey($lineIds->all())->count() !== $lineIds->count())) {
            throw new \DomainException('The invoice lines do not belong to the claimed invoice.');
        }
    }

    protected function present(Model $record, Request $request): array
    {
        return [
            ...parent::present($record, $request),
            'lines' => $record->lines->map(fn (InsuranceClaimLine $line): array => FieldListTransformer::using($line, [
                'id', 'invoice_line_id', 'line_type', 'external_item_code', 'description', 'quantity', 'billed_amount',
                'approved_amount', 'rejected_amount', 'decision_status', 'rejection_code', 'rejection_reason',
            ])->toArray($request))->values()->all(),
        ];
    }

    /**
     * Submit a private-payer claim through the payer's connector.
     */
    public function submit(string $id): JsonResponse
    {
        return $this->act($id, 'update', function (InsuranceClaim $claim): InsuranceClaim {
            $claim->loadMissing('payer');

            if ($claim->payer?->type === PayerType::NHIS) {
                throw new \DomainException('NHIS claims are submitted by batch XML export.');
            }

            $this->requireStatus($claim, [ClaimStatus::DRAFT, ClaimStatus::VALIDATED, ClaimStatus::REJECTED], 'Only draft, validated or rejected claims can be submitted.');
            $this->submissions->submit($claim);

            return $claim->refresh();
        });
    }
}

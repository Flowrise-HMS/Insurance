<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Models\InvoiceLine;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\ClaimLineType;
use Modules\Insurance\Enums\RejectionClass;

class InsuranceClaimLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_claim_lines';

    protected $keyType = 'string';

    protected $fillable = [
        'claim_id',
        'invoice_line_id',
        'line_type',
        'external_item_code',
        'description',
        'quantity',
        'billed_amount',
        'approved_amount',
        'rejected_amount',
        'decision_status',
        'rejection_class',
        'rejection_code',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'line_type' => ClaimLineType::class,
        'quantity' => 'integer',
        'billed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'rejected_amount' => 'decimal:2',
        'decision_status' => ClaimDecisionStatus::class,
        'rejection_class' => RejectionClass::class,
        'metadata' => 'array',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'invoice_line_id');
    }
}

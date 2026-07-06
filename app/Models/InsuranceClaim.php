<?php

namespace Modules\Insurance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Models\Invoice;
use Modules\Clinical\Models\Encounter;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Patient\Models\Patient;

class InsuranceClaim extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_claims';

    protected $keyType = 'string';

    protected $fillable = [
        'payer_id',
        'batch_id',
        'policy_id',
        'patient_id',
        'invoice_id',
        'encounter_id',
        'claim_number',
        'status',
        'total_billed_amount',
        'total_approved_amount',
        'total_rejected_amount',
        'currency',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'reconciled_at',
        'metadata',
        'nhia_payload',
    ];

    protected $casts = [
        'status' => ClaimStatus::class,
        'total_billed_amount' => 'decimal:2',
        'total_approved_amount' => 'decimal:2',
        'total_rejected_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'metadata' => 'array',
        'nhia_payload' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ClaimBatch::class, 'batch_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PatientPolicy::class, 'policy_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InsuranceClaimLine::class, 'claim_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(InsuranceClaimSubmission::class, 'claim_id');
    }
}

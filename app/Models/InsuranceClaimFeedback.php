<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Enums\ClaimDecisionStatus;
use Modules\Insurance\Enums\RejectionClass;

class InsuranceClaimFeedback extends Model
{
    use HasUuids;

    protected $table = 'insurance_claim_feedbacks';

    protected $keyType = 'string';

    protected $fillable = [
        'claim_id',
        'claim_submission_id',
        'external_reference',
        'feedback_hash',
        'feedback_type',
        'decision_status',
        'rejection_class',
        'rejection_code',
        'rejection_reason',
        'raw_payload',
        'normalized_payload',
        'processed_at',
    ];

    protected $casts = [
        'decision_status' => ClaimDecisionStatus::class,
        'rejection_class' => RejectionClass::class,
        'normalized_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaimSubmission::class, 'claim_submission_id');
    }
}

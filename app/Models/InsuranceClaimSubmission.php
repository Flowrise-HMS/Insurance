<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceClaimSubmission extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_claim_submissions';

    protected $keyType = 'string';

    protected $fillable = [
        'claim_id',
        'connector_code',
        'submission_status',
        'idempotency_key',
        'external_reference',
        'request_payload',
        'response_payload',
        'attempt_count',
        'submitted_at',
        'next_retry_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'submitted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(InsuranceClaimFeedback::class, 'claim_submission_id');
    }
}

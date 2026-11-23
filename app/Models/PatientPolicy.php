<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Observers\PatientPolicyObserver;
use Modules\Patient\Models\Patient;

/**
 * @property string $id
 * @property string $payer_id
 * @property string $patient_id
 * @property string|null $member_number
 * @property string|null $plan_code
 * @property \Illuminate\Support\Carbon|null $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property bool $is_primary
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property-read Payer|null $payer
 * @property-read Patient|null $patient
 */
#[ObservedBy(PatientPolicyObserver::class)]
class PatientPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_patient_policies';

    protected $keyType = 'string';

    protected $fillable = [
        'payer_id',
        'patient_id',
        'member_number',
        'plan_code',
        'effective_from',
        'effective_to',
        'is_primary',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Keep a single primary policy per patient: demote the patient's other
     * policies when this one is primary.
     */
    public function demoteOtherPrimaries(): void
    {
        if (! $this->is_primary) {
            return;
        }

        static::query()
            ->where('patient_id', $this->patient_id)
            ->whereKeyNot($this->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}

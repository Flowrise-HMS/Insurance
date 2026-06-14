<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patient\Models\Patient;

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

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}

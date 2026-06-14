<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Enums\PayerType;

class Payer extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_payers';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'config',
    ];

    protected $casts = [
        'type' => PayerType::class,
        'is_active' => 'boolean',
        'config' => 'encrypted:array',
    ];

    public function policies(): HasMany
    {
        return $this->hasMany(PatientPolicy::class, 'payer_id');
    }
}

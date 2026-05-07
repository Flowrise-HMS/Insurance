<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffItem extends Model
{
    use HasUuids;

    protected $table = 'insurance_tariff_items';

    protected $keyType = 'string';

    protected $fillable = [
        'payer_id',
        'item_type',
        'external_code',
        'name',
        'price',
        'currency',
        'source_version',
        'source_updated_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'source_updated_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }
}

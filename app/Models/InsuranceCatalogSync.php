<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCatalogSync extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_catalog_syncs';

    protected $keyType = 'string';

    protected $fillable = [
        'payer_id',
        'sync_type',
        'status',
        'started_at',
        'ended_at',
        'watermark',
        'records_processed',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'records_processed' => 'integer',
        'metadata' => 'array',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }
}

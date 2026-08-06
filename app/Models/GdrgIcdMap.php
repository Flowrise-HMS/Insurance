<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Database\Factories\GdrgIcdMapFactory;

class GdrgIcdMap extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_gdrg_icd_maps';

    protected $keyType = 'string';

    protected $fillable = [
        'icd10_code',
        'gdrg_code',
        'description',
        'mdc',
        'service_type',
        'notes',
        'is_active',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'imported_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return GdrgIcdMapFactory::new();
    }
}

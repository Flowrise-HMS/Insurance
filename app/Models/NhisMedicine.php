<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Database\Factories\NhisMedicineFactory;
use Modules\Insurance\Enums\NhisPrescribingLevel;

class NhisMedicine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nhis_medicines';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'strength',
        'form',
        'prescribing_level_code',
        'prescribing_level',
        'unit_of_pricing',
        'effective_from',
        'effective_to',
        'is_active',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'prescribing_level' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'imported_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (NhisMedicine $medicine): void {
            if (filled($medicine->prescribing_level_code)) {
                $level = NhisPrescribingLevel::tryFromCode($medicine->prescribing_level_code);
                if ($level) {
                    $medicine->prescribing_level = $level->ordinal();
                }
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return NhisMedicineFactory::new();
    }
}

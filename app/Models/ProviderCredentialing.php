<?php

namespace Modules\Insurance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Database\Factories\ProviderCredentialingFactory;
use Modules\Insurance\Enums\NhisPrescribingLevel;

class ProviderCredentialing extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'provider_credentialing';

    protected $keyType = 'string';

    protected $fillable = [
        'staff_id',
        'provider_name',
        'prescribing_level_code',
        'prescribing_level',
        'specialities',
        'accreditation_number',
        'level_of_care',
        'valid_from',
        'valid_to',
        'is_active',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'prescribing_level' => 'integer',
        'specialities' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'imported_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProviderCredentialing $credentialing): void {
            if (filled($credentialing->prescribing_level_code)) {
                $level = NhisPrescribingLevel::tryFromCode($credentialing->prescribing_level_code);
                if ($level) {
                    $credentialing->prescribing_level = $level->ordinal();
                }
            }
        });
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    protected static function newFactory(): Factory
    {
        return ProviderCredentialingFactory::new();
    }
}

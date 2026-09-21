<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Enums\PayerType;
use RuntimeException;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property PayerType|null $type
 * @property bool $is_active
 * @property array<string, mixed>|null $config
 */
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

    protected static function booted(): void
    {
        // The NHIS scheme payer is seeded and referenced by coverage, member
        // verification and claim generation; it must never be deleted.
        static::deleting(function (Payer $payer): void {
            if ($payer->isSystem()) {
                throw new RuntimeException('The NHIS payer is system-managed and cannot be deleted.');
            }
        });
    }

    public function isSystem(): bool
    {
        return $this->type === PayerType::NHIS;
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PatientPolicy::class, 'payer_id');
    }
}

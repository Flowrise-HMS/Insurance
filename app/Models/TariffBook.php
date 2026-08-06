<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;
use Modules\Insurance\Database\Factories\TariffBookFactory;

class TariffBook extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tariff_books';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_tariff_book');
    }

    public function tariffItems(): HasMany
    {
        return $this->hasMany(TariffItem::class, 'tariff_book_id');
    }

    protected static function newFactory(): Factory
    {
        return TariffBookFactory::new();
    }
}

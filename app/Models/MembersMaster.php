<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Database\Factories\MembersMasterFactory;

class MembersMaster extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'members_master';

    protected $keyType = 'string';

    protected $fillable = [
        'member_number',
        'card_serial_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'valid_from',
        'valid_to',
        'is_active',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'imported_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return MembersMasterFactory::new();
    }
}

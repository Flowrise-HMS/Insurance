<?php

namespace Modules\Insurance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;
use Modules\Insurance\Database\Factories\ClaimBatchFactory;
use Modules\Insurance\Enums\ClaimBatchStatus;

class ClaimBatch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'insurance_claim_batches';

    protected $keyType = 'string';

    protected $fillable = [
        'scheme_code',
        'payer_id',
        'branch_id',
        'batch_number',
        'filter_criteria',
        'service_year',
        'service_month',
        'status',
        'claims_count',
        'batch_amount',
        'currency',
        'master_table_versions',
        'exported_xml_path',
        'exported_at',
        'created_by',
    ];

    protected $casts = [
        'status' => ClaimBatchStatus::class,
        'filter_criteria' => 'array',
        'service_year' => 'integer',
        'service_month' => 'integer',
        'claims_count' => 'integer',
        'batch_amount' => 'decimal:2',
        'master_table_versions' => 'array',
        'exported_at' => 'datetime',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'payer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'batch_id');
    }

    protected static function newFactory(): Factory
    {
        return ClaimBatchFactory::new();
    }

    public static function generateBatchNumber(string $branchId): string
    {
        $date = now()->format('Ymd');
        $sequence = static::query()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('NB-%s-%04d', $date, $sequence);
    }
}

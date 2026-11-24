<?php

namespace Modules\Insurance\Http\Controllers\Api;

use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Insurance\Models\ClaimBatch;

/**
 * @group Insurance
 *
 * NHIS claim batches (read-only; generated, vetted and exported in the panel).
 */
class ClaimBatchController extends ResourceApiController
{
    public static function model(): string
    {
        return ClaimBatch::class;
    }

    public function fields(): array
    {
        return ['id', 'batch_number', 'scheme_code', 'payer_id', 'service_year', 'service_month', 'status', 'claims_count',
            'batch_amount', 'currency', 'exported_at', 'created_at'];
    }

    public function filters(): array
    {
        return ['status' => 'status', 'service_year' => 'service_year', 'service_month' => 'service_month', 'payer_id' => 'payer_id'];
    }
}

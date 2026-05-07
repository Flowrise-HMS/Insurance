<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Insurance\Models\InsuranceCatalogSync;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;

class CatalogSyncService
{
    public function __construct(
        protected PayerConnectorRegistry $connectors
    ) {}

    /**
     * @param  array<int, array{item_type:string, external_code:string, name:string, price:string, currency?:string, source_version?:string}>  $items
     */
    public function upsertItems(Payer $payer, array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            TariffItem::query()->updateOrCreate(
                [
                    'payer_id' => $payer->id,
                    'item_type' => $item['item_type'],
                    'external_code' => $item['external_code'],
                ],
                [
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'currency' => $item['currency'] ?? 'GHS',
                    'source_version' => $item['source_version'] ?? null,
                    'source_updated_at' => now(),
                    'is_active' => true,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function sync(Payer $payer, string $syncType = 'medication', ?string $watermark = null): InsuranceCatalogSync
    {
        $started = microtime(true);

        $sync = DB::transaction(function () use ($payer, $syncType, $watermark) {
            $sync = InsuranceCatalogSync::query()->create([
                'payer_id' => $payer->id,
                'sync_type' => $syncType,
                'status' => 'running',
                'started_at' => now(),
                'watermark' => $watermark,
            ]);

            try {
                $result = $this->connectors->forCode($payer->code)->syncCatalogs($payer, $watermark);
                $sync->update([
                    'status' => 'success',
                    'ended_at' => now(),
                    'records_processed' => (int) ($result['processed'] ?? 0),
                    'watermark' => $result['watermark'] ?? $watermark,
                ]);
            } catch (\Throwable $e) {
                $sync->update([
                    'status' => 'failed',
                    'ended_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
                throw $e;
            }

            return $sync->fresh();
        });

        Log::info('insurance.catalog.sync', [
            'payer_id' => $payer->id,
            'sync_type' => $syncType,
            'status' => $sync->status,
            'records_processed' => $sync->records_processed,
            'elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $sync;
    }
}

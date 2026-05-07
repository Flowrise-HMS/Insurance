<?php

namespace Modules\Insurance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\CatalogSyncService;

class CatalogSyncController extends Controller
{
    public function store(Request $request, CatalogSyncService $syncService)
    {
        $data = $request->validate([
            'payer_code' => ['required', 'string', 'max:64'],
            'sync_type' => ['nullable', 'string', 'max:32'],
            'watermark' => ['nullable', 'string', 'max:255'],
        ]);

        $payer = Payer::query()->where('code', $data['payer_code'])->firstOrFail();
        $sync = $syncService->sync($payer, (string) ($data['sync_type'] ?? 'medication'), $data['watermark'] ?? null);

        return response()->json([
            'sync_id' => $sync->id,
            'status' => $sync->status,
            'records_processed' => $sync->records_processed,
            'watermark' => $sync->watermark,
        ], 202);
    }
}

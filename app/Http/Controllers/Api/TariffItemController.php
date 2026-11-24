<?php

namespace Modules\Insurance\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Insurance\Models\TariffItem;

/**
 * @group Insurance
 *
 * Payer tariff items (read-only; imported from NHIA files or synced from payer APIs).
 */
class TariffItemController extends ResourceApiController
{
    protected string $branchScope = 'none';

    protected string $defaultSort = 'name';

    public static function model(): string
    {
        return TariffItem::class;
    }

    /**
     * Tariff items follow their tariff book's permissions, as in the panel.
     */
    protected function authorizeResource(string $ability, ?Model $record = null): void
    {
        $ability === 'viewAny' || $record?->tariffBook === null
            ? $this->requirePermission('ViewAny TariffBook')
            : $this->authorizeApi('view', $record->tariffBook);
    }

    public function fields(): array
    {
        return ['id', 'payer_id', 'tariff_book_id', 'item_type', 'external_code', 'name', 'price', 'currency', 'effective_from',
            'effective_to', 'admission_type', 'is_active', 'source_version', 'source_updated_at'];
    }

    public function filters(): array
    {
        return ['payer_id' => 'payer_id', 'tariff_book_id' => 'tariff_book_id', 'item_type' => 'item_type', 'external_code' => 'external_code', 'is_active' => 'is_active'];
    }

    public function searchable(): array
    {
        return ['name', 'external_code'];
    }

    public function sortable(): array
    {
        return ['name', 'external_code', 'price'];
    }
}

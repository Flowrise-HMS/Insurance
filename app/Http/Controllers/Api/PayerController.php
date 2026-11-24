<?php

namespace Modules\Insurance\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\PayerConnectorRegistry;

/**
 * @group Insurance
 *
 * Insurance payers (facility-wide). Connector credentials are managed in the
 * panel and never returned. The seeded NHIS payer cannot be deleted.
 */
class PayerController extends ResourceApiController
{
    protected string $branchScope = 'none';

    protected string $defaultSort = 'name';

    public static function model(): string
    {
        return Payer::class;
    }

    public function fields(): array
    {
        return ['id', 'code', 'name', 'type', 'is_active', 'connector_code', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['type' => 'type', 'is_active' => 'is_active'];
    }

    public function searchable(): array
    {
        return ['code', 'name'];
    }

    public function sortable(): array
    {
        return ['name', 'code', 'created_at'];
    }

    public function storeRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('insurance_payers', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([PayerType::PRIVATE->value])],
            'is_active' => ['nullable', 'boolean'],
            'connector_code' => ['nullable', Rule::in(array_keys(PayerConnectorRegistry::CONNECTORS))],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function deleteRecord(Model $record): void
    {
        if ($record->isSystem()) {
            throw new \DomainException('The NHIS payer is system-managed and cannot be deleted.');
        }

        $record->delete();
    }
}

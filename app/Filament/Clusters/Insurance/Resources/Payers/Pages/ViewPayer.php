<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Actions\PayerConnectorActions;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\PayerResource;

class ViewPayer extends ViewRecord
{
    protected static string $resource = PayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PayerConnectorActions::testConnection(),
            PayerConnectorActions::syncCatalog(),
            EditAction::make(),
        ];
    }
}

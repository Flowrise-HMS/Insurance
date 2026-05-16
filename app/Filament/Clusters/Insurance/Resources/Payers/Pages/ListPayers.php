<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\PayerResource;

class ListPayers extends ListRecords
{
    protected static string $resource = PayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

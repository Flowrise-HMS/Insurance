<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\PayerResource;

class EditPayer extends EditRecord
{
    protected static string $resource = PayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

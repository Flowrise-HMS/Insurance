<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\PayerResource;

class CreatePayer extends CreateRecord
{
    protected static string $resource = PayerResource::class;
}

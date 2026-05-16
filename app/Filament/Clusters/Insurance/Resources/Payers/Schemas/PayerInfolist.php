<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PayerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')->placeholder('-'),
                TextEntry::make('name')->placeholder('-'),
                TextEntry::make('type')->placeholder('-'),
                IconEntry::make('is_active'),
            ]);
    }
}

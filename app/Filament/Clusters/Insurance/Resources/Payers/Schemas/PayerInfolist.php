<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Insurance\Services\PayerConnectorRegistry;

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
                TextEntry::make('connector_code')
                    ->label(__('Claims connector'))
                    ->formatStateUsing(fn (?string $state): string => PayerConnectorRegistry::options()[$state ?? 'private-generic'] ?? (string) $state)
                    ->placeholder(__('None (manual claims)')),
                TextEntry::make('connector_config.base_url')
                    ->label(__('API base URL'))
                    ->placeholder('—'),
            ]);
    }
}

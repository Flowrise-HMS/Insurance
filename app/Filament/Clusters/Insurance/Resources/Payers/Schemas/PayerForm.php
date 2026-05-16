<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Insurance\Enums\PayerType;

class PayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required(),
                TextInput::make('name')->required(),
                Select::make('type')
                    ->options(PayerType::class)
                    ->required(),
                KeyValue::make('config'),
            ]);
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;

class PayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required(),
                TextInput::make('name')->required(),
                // A second NHIS payer cannot be created; the seeded NHIS record
                // keeps its type listed (and locked) so it can still be edited.
                Select::make('type')
                    ->options(fn (?Payer $record): array => collect(PayerType::cases())
                        ->reject(fn (PayerType $type) => $type === PayerType::NHIS && ! $record?->isSystem())
                        ->mapWithKeys(fn (PayerType $type) => [$type->value => $type->getLabel()])
                        ->all())
                    ->disabled(fn (?Payer $record): bool => (bool) $record?->isSystem())
                    ->dehydrated(fn (?Payer $record): bool => ! $record?->isSystem())
                    ->required(),
                KeyValue::make('config'),
            ]);
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Insurance\Models\Payer;

class TariffItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'tariffItems';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('external_code')->required(),
                TextInput::make('name')->required(),
                TextInput::make('price')->numeric()->required(),
                TextInput::make('currency')->default('GHS'),
                Select::make('item_type')
                    ->options(['service' => 'Service', 'medicine' => 'Medicine', 'procedure' => 'Procedure'])
                    ->default('service'),
                Select::make('payer_id')
                    ->relationship('payer', 'name')
                    ->options(fn () => Payer::query()->pluck('name', 'id'))
                    ->required(),
                Select::make('admission_type')->options(['OUT' => 'Outpatient', 'INP' => 'Inpatient'])->nullable(),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_to'),
                Toggle::make('is_active')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('price')->money('GHS'),
                TextColumn::make('item_type'),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

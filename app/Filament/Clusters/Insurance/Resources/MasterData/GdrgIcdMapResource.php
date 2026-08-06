<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource\Pages\CreateGdrgIcdMap;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource\Pages\EditGdrgIcdMap;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource\Pages\ListGdrgIcdMaps;
use Modules\Insurance\Models\GdrgIcdMap;

class GdrgIcdMapResource extends Resource
{
    protected static ?string $model = GdrgIcdMap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::INFRASTRUCTURE;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('icd10_code')->required(),
                TextInput::make('gdrg_code')->required(),
                TextInput::make('description'),
                TextInput::make('mdc'),
                Select::make('service_type')->options(['OUT' => 'Outpatient', 'INP' => 'Inpatient'])->default('OUT')->required(),
                Textarea::make('notes'),
                Toggle::make('is_active')->default(true),
                TextInput::make('source_file'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icd10_code')->searchable()->sortable(),
                TextColumn::make('gdrg_code')->searchable()->sortable(),
                TextColumn::make('description')->searchable()->limit(50),
                TextColumn::make('mdc'),
                TextColumn::make('service_type'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('service_type')->options(['OUT' => 'Outpatient', 'INP' => 'Inpatient']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGdrgIcdMaps::route('/'),
            'create' => CreateGdrgIcdMap::route('/create'),
            'edit' => EditGdrgIcdMap::route('/{record}/edit'),
        ];
    }
}

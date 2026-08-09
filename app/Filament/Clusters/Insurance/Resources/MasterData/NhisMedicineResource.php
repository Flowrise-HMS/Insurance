<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource\Pages\CreateNhisMedicine;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource\Pages\EditNhisMedicine;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource\Pages\ListNhisMedicines;
use Modules\Insurance\Models\NhisMedicine;

class NhisMedicineResource extends Resource
{
    protected static ?string $model = NhisMedicine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::INFRASTRUCTURE;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->required()->unique(ignoreRecord: true),
                TextInput::make('name')->required(),
                TextInput::make('strength'),
                TextInput::make('form'),
                Select::make('prescribing_level_code')
                    ->label('Prescribing level')
                    ->options(NhisPrescribingLevel::options())
                    ->required()
                    ->default(NhisPrescribingLevel::A->value),
                TextInput::make('unit_of_pricing'),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_to'),
                Toggle::make('is_active')->default(true),
                TextInput::make('source_file'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('strength'),
                TextColumn::make('form'),
                TextColumn::make('prescribing_level_code')->label('Level')->sortable(),
                TextColumn::make('unit_of_pricing')->toggleable(),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('prescribing_level_code')
                    ->label('Prescribing level')
                    ->options(NhisPrescribingLevel::options()),
                SelectFilter::make('is_active')->options(['1' => 'Active', '0' => 'Inactive']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNhisMedicines::route('/'),
            'create' => CreateNhisMedicine::route('/create'),
            'edit' => EditNhisMedicine::route('/{record}/edit'),
        ];
    }
}

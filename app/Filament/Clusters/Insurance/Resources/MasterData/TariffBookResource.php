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
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Core\Models\Branch;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\Pages\CreateTariffBook;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\Pages\EditTariffBook;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\Pages\ListTariffBooks;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\RelationManagers\TariffItemsRelationManager;
use Modules\Insurance\Models\TariffBook;

class TariffBookResource extends Resource
{
    protected static ?string $model = TariffBook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::INFRASTRUCTURE;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->required()->unique(ignoreRecord: true),
                TextInput::make('name')->required(),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_to'),
                Select::make('branches')
                    ->relationship('branches', 'name')
                    ->multiple()
                    ->preload()
                    ->options(fn () => Branch::query()->pluck('name', 'id')),
                Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date(),
                TextColumn::make('tariffItems_count')->counts('tariffItems')->label('Tariff items'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TariffItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTariffBooks::route('/'),
            'create' => CreateTariffBook::route('/create'),
            'edit' => EditTariffBook::route('/{record}/edit'),
        ];
    }
}

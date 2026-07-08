<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\CreatePayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\EditPayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\ListPayers;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\ViewPayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas\PayerForm;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas\PayerInfolist;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Tables\PayersTable;
use Modules\Insurance\Models\Payer;

class PayerResource extends Resource
{
    protected static ?string $model = Payer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return PayerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayers::route('/'),
            'create' => CreatePayer::route('/create'),
            'view' => ViewPayer::route('/{record}'),
            'edit' => EditPayer::route('/{record}/edit'),
        ];
    }
}

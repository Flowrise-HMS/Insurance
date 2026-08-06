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
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource\Pages\CreateMemberMaster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource\Pages\EditMemberMaster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource\Pages\ListMemberMasters;
use Modules\Insurance\Models\MembersMaster;

class MembersMasterResource extends Resource
{
    protected static ?string $model = MembersMaster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::INFRASTRUCTURE;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('member_number')->required(),
                TextInput::make('card_serial_number')->required(),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                DatePicker::make('date_of_birth'),
                Select::make('gender')->options(['F' => 'Female', 'M' => 'Male']),
                DatePicker::make('valid_from'),
                DatePicker::make('valid_to'),
                Toggle::make('is_active')->default(true),
                TextInput::make('source_file'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_number')->searchable()->sortable(),
                TextColumn::make('card_serial_number')->searchable(),
                TextColumn::make('last_name')->searchable(),
                TextColumn::make('first_name')->searchable(),
                TextColumn::make('gender'),
                TextColumn::make('valid_from')->date(),
                TextColumn::make('valid_to')->date(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
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
            'index' => ListMemberMasters::route('/'),
            'create' => CreateMemberMaster::route('/create'),
            'edit' => EditMemberMaster::route('/{record}/edit'),
        ];
    }
}

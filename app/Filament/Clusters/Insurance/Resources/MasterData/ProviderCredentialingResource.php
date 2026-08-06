<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData;

use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource\Pages\CreateProviderCredentialing;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource\Pages\EditProviderCredentialing;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource\Pages\ListProviderCredentialings;
use Modules\Insurance\Models\ProviderCredentialing;

class ProviderCredentialingResource extends Resource
{
    protected static ?string $model = ProviderCredentialing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::INFRASTRUCTURE;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('staff_id')
                    ->relationship('staff', 'name')
                    ->options(fn () => User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('provider_name'),
                TextInput::make('prescribing_level')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3)
                    ->default(1),
                TagsInput::make('specialities'),
                TextInput::make('accreditation_number'),
                TextInput::make('level_of_care'),
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
                TextColumn::make('staff.name')->searchable()->placeholder('—'),
                TextColumn::make('provider_name')->searchable(),
                TextColumn::make('prescribing_level'),
                TextColumn::make('accreditation_number')->searchable(),
                TextColumn::make('level_of_care'),
                TextColumn::make('valid_from')->date(),
                TextColumn::make('valid_to')->date(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('prescribing_level')->options([1, 2, 3]),
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
            'index' => ListProviderCredentialings::route('/'),
            'create' => CreateProviderCredentialing::route('/create'),
            'edit' => EditProviderCredentialing::route('/{record}/edit'),
        ];
    }
}

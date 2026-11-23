<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Insurance\Models\Payer;
use Modules\Patient\Models\Patient;

/**
 * Policy form shared by the Patient Policies resource and the patient record's
 * Insurance Policies relation manager (where the patient is implied).
 */
class PatientPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Policy'))
                    ->columns(2)
                    ->schema(static::fields()),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            Select::make('patient_id')
                ->label(__('Patient'))
                ->relationship('patient', 'last_name')
                ->getOptionLabelFromRecordUsing(fn (Patient $record): string => trim($record->full_name.($record->mrn ? " ({$record->mrn})" : '')))
                ->searchable(['first_name', 'last_name', 'mrn'])
                ->required()
                ->hidden(fn ($livewire): bool => $livewire instanceof RelationManager)
                ->columnSpanFull(),
            Select::make('payer_id')
                ->label(__('Payer'))
                ->options(fn (): array => Payer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            TextInput::make('member_number')
                ->label(__('Member number'))
                ->required()
                ->maxLength(100),
            TextInput::make('plan_code')
                ->label(__('Plan'))
                ->maxLength(100),
            DatePicker::make('effective_from')
                ->label(__('Effective from')),
            DatePicker::make('effective_to')
                ->label(__('Effective to'))
                ->afterOrEqual('effective_from'),
            Toggle::make('is_primary')
                ->label(__('Primary policy'))
                ->helperText(__('Making this policy primary demotes the patient\'s other policies.'))
                ->default(true),
            Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true),
        ];
    }
}

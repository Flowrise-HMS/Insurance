<?php

namespace Modules\Insurance\Filament\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Modules\Insurance\Models\Payer;

class PatientInsuranceSchema
{
    public static function getFields(): array
    {
        if (! config('insurance.enabled', true)) {
            return [];
        }

        return [
            Fieldset::make('Insurance')->schema([
                Select::make('insurance_payer_id')
                    ->label('Insurance Payer')
                    ->options(fn () => Payer::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live(),

                TextInput::make('insurance_member_number')
                    ->label('Member Number')
                    ->required(fn ($get) => filled($get('insurance_payer_id')))
                    ->minLength(8)
                    ->placeholder('e.g. 123456139')
                    ->helperText('NHIA member number (minimum 8 characters).'),

                TextInput::make('insurance_card_serial_number')
                    ->label('Card Serial Number')
                    ->length(13)
                    ->required(fn ($get) => filled($get('insurance_payer_id')))
                    ->placeholder('e.g. UWJPL120A0093')
                    ->helperText('Exactly 13 alphanumeric characters from the NHIS card.'),

                TextInput::make('insurance_mother_member_number')
                    ->label('Mother Member Number')
                    ->minLength(8)
                    ->helperText('Required for infants under 3 months without their own NHIS membership.')
                    ->visible(fn ($get) => filled($get('insurance_payer_id'))),

                TextInput::make('insurance_mother_card_serial_number')
                    ->label('Mother Card Serial Number')
                    ->length(13)
                    ->helperText('Mother card serial when claiming for an infant under 3 months.')
                    ->visible(fn ($get) => filled($get('insurance_payer_id'))),

                DatePicker::make('insurance_effective_from')
                    ->label('Effective From')
                    ->native(false)
                    ->visible(false)
                    ->displayFormat('d M Y'),

                DatePicker::make('insurance_effective_to')
                    ->label('Effective To')
                    ->native(false)
                    ->visible(false)
                    ->displayFormat('d M Y')
                    ->after('insurance_effective_from'),
            ])
                ->columns(2)
                ->visible(fn () => config('insurance.enabled', true)),
        ];
    }
}

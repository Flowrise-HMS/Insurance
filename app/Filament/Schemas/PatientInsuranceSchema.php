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
                    ->placeholder('e.g. NHIS-123456'),

                DatePicker::make('insurance_effective_from')
                    ->label('Effective From')
                    ->native(false)
                    ->displayFormat('d M Y'),

                DatePicker::make('insurance_effective_to')
                    ->label('Effective To')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->after('insurance_effective_from'),
            ])
                ->columns(2)
                ->visible(fn () => config('insurance.enabled', true)),
        ];
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Schemes\InsuranceSchemeRegistry;

class InsuranceClaimForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function baseComponents(): array
    {
        return [
            Placeholder::make('patient_summary')
                ->label('Patient')
                ->content(function (?InsuranceClaim $record) {
                    if (! $record) {
                        return '';
                    }
                    $record->loadMissing('patient');

                    return ($record->patient?->full_name ?? 'Unknown').' (MRN: '.($record->patient?->mrn ?? 'N/A').')';
                }),
            Placeholder::make('validation_summary')
                ->label('Validation')
                ->content(function (?InsuranceClaim $record) {
                    if (! $record) {
                        return '';
                    }

                    $registry = app(InsuranceSchemeRegistry::class);
                    $scheme = $registry->forCode($record->batch?->scheme_code ?? 'nhis');
                    $validation = $scheme->validateClaim($record->loadMissing(['patient', 'policy', 'lines']));

                    $messages = array_merge($validation->errors, $validation->warnings);

                    return $messages === [] ? 'No validation issues.' : implode("\n", $messages);
                }),
        ];
    }

    public static function configure(Schema $schema, ?InsuranceClaim $claim = null): Schema
    {
        $components = self::baseComponents();

        if ($claim) {
            $registry = app(InsuranceSchemeRegistry::class);
            $scheme = $registry->forCode($claim->batch?->scheme_code ?? 'nhis');
            $components = array_merge($components, $scheme->buildClaimFormSchema($claim));
        }

        return $schema->components($components);
    }
}

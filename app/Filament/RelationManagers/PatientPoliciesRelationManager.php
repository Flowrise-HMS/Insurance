<?php

namespace Modules\Insurance\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of a patient's insurance policies on the patient record.
 * Policies are maintained through the patient form's Insurance Information
 * fields (and the NHIS members master), not edited here.
 */
class PatientPoliciesRelationManager extends RelationManager
{
    protected static string $relationship = 'insurancePolicies';

    protected static ?string $title = 'Insurance Policies';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Insurance Policies');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('payer'))
            ->columns([
                TextColumn::make('payer.name')
                    ->label(__('Payer')),
                TextColumn::make('member_number')
                    ->label(__('Member number'))
                    ->searchable(),
                TextColumn::make('plan_code')
                    ->label(__('Plan'))
                    ->placeholder('—'),
                TextColumn::make('effective_from')
                    ->label(__('Effective from'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('effective_to')
                    ->label(__('Effective to'))
                    ->date()
                    ->placeholder('—'),
                IconColumn::make('is_primary')
                    ->label(__('Primary'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No insurance policies'));
    }
}

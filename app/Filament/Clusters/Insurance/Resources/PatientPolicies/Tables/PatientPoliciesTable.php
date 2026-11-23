<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Services\MemberVerificationService;

/**
 * Policy columns shared by the resource and the patient relation manager.
 */
class PatientPoliciesTable
{
    /**
     * @return array<int, mixed>
     */
    public static function columns(bool $withPatient = true): array
    {
        return array_values(array_filter([
            $withPatient
                ? TextColumn::make('patient.last_name')
                    ->label(__('Patient'))
                    ->state(fn (PatientPolicy $record): string => (string) ($record->patient?->full_name ?? '—'))
                    ->description(fn (PatientPolicy $record): ?string => $record->patient?->mrn)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('patient', fn (Builder $patient) => $patient
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('mrn', 'like', "%{$search}%")))
                : null,
            TextColumn::make('payer.name')
                ->label(__('Payer'))
                ->sortable(),
            TextColumn::make('member_number')
                ->label(__('Member number'))
                ->searchable(),
            TextColumn::make('plan_code')
                ->label(__('Plan'))
                ->placeholder('—'),
            TextColumn::make('effective_from')
                ->label(__('Effective from'))
                ->date()
                ->placeholder('—')
                ->sortable(),
            TextColumn::make('effective_to')
                ->label(__('Effective to'))
                ->date()
                ->placeholder('—')
                ->sortable(),
            TextColumn::make('verification')
                ->label(__('Verification'))
                ->badge()
                ->state(fn (PatientPolicy $record): string => app(MemberVerificationService::class)->badge($record)['label'])
                ->color(fn (PatientPolicy $record): string => app(MemberVerificationService::class)->badge($record)['color']),
            IconColumn::make('is_primary')
                ->label(__('Primary'))
                ->boolean(),
            IconColumn::make('is_active')
                ->label(__('Active'))
                ->boolean(),
        ]));
    }

    /**
     * @return array<int, mixed>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('payer_id')
                ->label(__('Payer'))
                ->relationship('payer', 'name'),
            TernaryFilter::make('is_active')
                ->label(__('Active')),
            Filter::make('expired')
                ->label(__('Expired'))
                ->query(fn (Builder $query): Builder => $query->whereNotNull('effective_to')->whereDate('effective_to', '<', now())),
            Filter::make('current')
                ->label(__('In force today'))
                ->query(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()))
                    ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()))),
        ];
    }
}

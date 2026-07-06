<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Support\Currency;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\InsuranceClaimResource;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Services\ClaimBatchService;

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    protected static ?string $title = 'Claims in Batch';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claim_number')->searchable(),
                TextColumn::make('patient.first_name')->label('Patient'),
                TextColumn::make('encounter.admitted_at')->label('Visit')->dateTime(),
                CurrencyColumn::make('total_billed_amount')
                    ->currency(fn (InsuranceClaim $record): string => (string) ($record->currency ?? $record->batch?->currency ?? Currency::defaultCode())),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn ($record) => InsuranceClaimResource::getUrl('edit', ['record' => $record])),
                Action::make('markReady')
                    ->label('Mark Ready')
                    ->requiresConfirmation()
                    ->visible(fn (InsuranceClaim $record): bool => $record->status->canMarkReady())
                    ->action(function ($record, ClaimBatchService $service) {
                        $service->vetClaim($record);
                    }),
            ]);
    }
}

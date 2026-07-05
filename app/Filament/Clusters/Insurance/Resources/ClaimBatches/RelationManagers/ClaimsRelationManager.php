<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\InsuranceClaimResource;
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
                TextColumn::make('total_billed_amount')->money('GHS'),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn ($record) => InsuranceClaimResource::getUrl('edit', ['record' => $record])),
                Action::make('markReady')
                    ->label('Mark Ready')
                    ->requiresConfirmation()
                    ->action(function ($record, ClaimBatchService $service) {
                        $service->vetClaim($record);
                    }),
            ]);
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Tables;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Modules\Insurance\Enums\ClaimBatchStatus;
use Modules\Insurance\Services\ClaimBatchService;

class ClaimBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')->searchable()->sortable(),
                TextColumn::make('service_year')->label('Year'),
                TextColumn::make('service_month')->label('Month'),
                TextColumn::make('claims_count')->label('Claims'),
                TextColumn::make('batch_amount')->money('GHS'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                Action::make('export')
                    ->label('Export XML')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn ($record) => in_array($record->status, [ClaimBatchStatus::VETTED, ClaimBatchStatus::EXPORTED, ClaimBatchStatus::GENERATED, ClaimBatchStatus::UNDER_REVIEW], true))
                    ->requiresConfirmation()
                    ->action(function ($record, ClaimBatchService $batchService) {
                        $exported = $batchService->export($record, force: false);

                        return response()->download(
                            Storage::disk('local')->path($exported->path),
                            $exported->filename
                        );
                    }),
                Action::make('download')
                    ->label('Download XML')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn ($record) => filled($record->exported_xml_path))
                    ->action(fn ($record) => response()->download(
                        Storage::disk('local')->path($record->exported_xml_path),
                        basename($record->exported_xml_path)
                    )),
            ]);
    }
}

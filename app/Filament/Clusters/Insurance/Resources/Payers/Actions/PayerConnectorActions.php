<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\CatalogSyncService;
use Modules\Insurance\Services\Connectors\PrivateInsurer\HttpPrivateInsurerConnector;
use Throwable;

class PayerConnectorActions
{
    public static function testConnection(): Action
    {
        return Action::make('testConnection')
            ->label(__('Test connection'))
            ->icon(Heroicon::OutlinedSignal)
            ->visible(fn (Payer $record): bool => $record->connector_code === HttpPrivateInsurerConnector::CODE && Gate::allows('update', $record))
            ->action(function (Payer $record): void {
                $result = app(HttpPrivateInsurerConnector::class)->testConnection($record);

                Notification::make()
                    ->title($result['ok'] ? __('Payer API reachable') : __('Connection failed'))
                    ->body($result['message'])
                    ->color($result['ok'] ? 'success' : 'danger')
                    ->send();
            });
    }

    public static function syncCatalog(): Action
    {
        return Action::make('syncCatalog')
            ->label(__('Sync tariff catalog'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription(__('Downloads the payer\'s tariff items through its API and updates the tariff list.'))
            ->visible(fn (Payer $record): bool => $record->connector_code === HttpPrivateInsurerConnector::CODE && Gate::allows('update', $record))
            ->action(function (Payer $record): void {
                try {
                    $last = $record->catalogSyncs()->where('status', 'success')->latest('ended_at')->value('watermark');
                    $sync = app(CatalogSyncService::class)->sync($record, 'tariff', $last);

                    Notification::make()
                        ->title(__('Catalog synchronised'))
                        ->body(__(':count items processed.', ['count' => $sync->records_processed]))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('Catalog sync failed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}

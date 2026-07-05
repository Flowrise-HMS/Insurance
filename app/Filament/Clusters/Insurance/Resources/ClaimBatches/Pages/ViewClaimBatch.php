<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Services\ClaimBatchService;

class ViewClaimBatch extends ViewRecord
{
    protected static string $resource = ClaimBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vetAll')
                ->label('Submit All / Vet')
                ->requiresConfirmation()
                ->action(function (ClaimBatchService $service) {
                    $service->vetAll($this->record, force: false);
                    $this->refreshFormData(['status', 'claims_count']);
                }),
            Action::make('export')
                ->label('Export XML')
                ->requiresConfirmation()
                ->action(function (ClaimBatchService $service) {
                    $exported = $service->export($this->record);

                    return response()->download(
                        storage_path('app/'.$exported->path),
                        $exported->filename
                    );
                }),
        ];
    }
}

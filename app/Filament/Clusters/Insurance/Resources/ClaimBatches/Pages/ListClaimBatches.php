<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Support\SuperAdminExportAction;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Filament\Exports\ClaimBatchExporter;

class ListClaimBatches extends ListRecords
{
    protected static string $resource = ClaimBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuperAdminExportAction::make(ClaimBatchExporter::class),
            Action::make('generate')
                ->label('Generate Claims')
                ->url(GenerateClaims::getUrl())
                ->icon('heroicon-o-plus'),
        ];
    }
}

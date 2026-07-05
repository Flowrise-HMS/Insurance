<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\InsuranceClaimResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Schemas\InsuranceClaimForm;
use Modules\Insurance\Services\ClaimBatchService;

class EditInsuranceClaim extends EditRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    public function form(Schema $schema): Schema
    {
        return InsuranceClaimForm::configure($schema, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReady')
                ->label('Mark Ready')
                ->action(function (ClaimBatchService $service) {
                    $service->vetClaim($this->record);
                    $this->refreshFormData(['status', 'reviewed_at']);
                }),
            Action::make('backToBatch')
                ->label('Back to Batch')
                ->url(fn () => $this->record->batch_id
                    ? ClaimBatchResource::getUrl('view', ['record' => $this->record->batch_id])
                    : ClaimBatchResource::getUrl('index')),
        ];
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\InsuranceClaimResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Schemas\InsuranceClaimForm;
use Modules\Insurance\Services\ClaimBatchService;

class EditInsuranceClaim extends EditRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    public function form(Schema $schema): Schema
    {
        return InsuranceClaimForm::configure($schema, $this->getRecord())
            ->disabled(fn (): bool => ! $this->isClaimEditable());
    }

    protected function isClaimEditable(): bool
    {
        return $this->record->status->canMarkReady();
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        if (! $this->isClaimEditable()) {
            return [
                $this->getCancelFormAction(),
            ];
        }

        return parent::getFormActions();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->record->refresh();

        if (! $this->record->status->canMarkReady()) {
            Notification::make()
                ->title('This claim can no longer be edited after validation.')
                ->warning()
                ->send();

            throw new Halt;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReady')
                ->label('Mark Ready')
                ->visible(fn (): bool => $this->isClaimEditable())
                ->requiresConfirmation()
                ->action(function (ClaimBatchService $service) {
                    $service->vetClaim($this->record);
                    $this->record->refresh();
                    $this->fillForm();
                }),
            Action::make('backToBatch')
                ->label('Back to Batch')
                ->url(fn () => $this->record->batch_id
                    ? ClaimBatchResource::getUrl('view', ['record' => $this->record->batch_id])
                    : ClaimBatchResource::getUrl('index')),
        ];
    }
}

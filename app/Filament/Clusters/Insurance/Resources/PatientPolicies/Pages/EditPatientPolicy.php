<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Actions\VerifyMemberAction;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\PatientPolicyResource;
use Modules\Insurance\Models\PatientPolicy;

class EditPatientPolicy extends EditRecord
{
    protected static string $resource = PatientPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            VerifyMemberAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var PatientPolicy $policy */
        $policy = $this->getRecord();
        $policy->demoteOtherPrimaries();
    }
}

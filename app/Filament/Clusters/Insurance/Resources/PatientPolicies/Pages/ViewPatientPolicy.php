<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Actions\VerifyMemberAction;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\PatientPolicyResource;

class ViewPatientPolicy extends ViewRecord
{
    protected static string $resource = PatientPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VerifyMemberAction::make(),
            EditAction::make(),
        ];
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\PatientPolicyResource;

class ListPatientPolicies extends ListRecords
{
    protected static string $resource = PatientPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\PatientPolicyResource;
use Modules\Insurance\Models\PatientPolicy;

class CreatePatientPolicy extends CreateRecord
{
    protected static string $resource = PatientPolicyResource::class;

    protected function afterCreate(): void
    {
        /** @var PatientPolicy $policy */
        $policy = $this->getRecord();
        $policy->demoteOtherPrimaries();
    }
}

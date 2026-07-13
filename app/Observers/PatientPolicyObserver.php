<?php

namespace Modules\Insurance\Observers;

use Modules\Insurance\Models\PatientPolicy;
use Modules\Patient\Models\PatientIdentifier;

class PatientPolicyObserver
{
    /**
     * Sync legacy patient identifiers when an NHIS policy is saved.
     */
    public function saved(PatientPolicy $policy): void
    {
        if (! $this->isNhisPayer($policy)) {
            return;
        }

        $this->updateNhisIdentifier($policy);
    }

    protected function isNhisPayer(PatientPolicy $policy): bool
    {
        $payer = $policy->payer;

        return $payer && ($payer->code === 'nhis' || $payer->type->value === 'nhis');
    }

    protected function updateNhisIdentifier(PatientPolicy $policy): void
    {
        $identifier = PatientIdentifier::query()
            ->where('patient_id', $policy->patient_id)
            ->whereIn('type', ['nhis', 'nhis_card'])
            ->first();

        if (! $identifier) {
            return;
        }

        $identifier->update([
            'value' => $policy->member_number,
            'issuer' => 'NHIA',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\PatientPolicy;

class PatientPolicyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny PatientPolicy');
    }

    public function view(AuthUser $authUser, PatientPolicy $patientPolicy): bool
    {
        return $authUser->can('View PatientPolicy');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create PatientPolicy');
    }

    public function update(AuthUser $authUser, PatientPolicy $patientPolicy): bool
    {
        return $authUser->can('Update PatientPolicy');
    }

    public function delete(AuthUser $authUser, PatientPolicy $patientPolicy): bool
    {
        return $authUser->can('Delete PatientPolicy');
    }

    public function restore(AuthUser $authUser, PatientPolicy $patientPolicy): bool
    {
        return $authUser->can('Restore PatientPolicy');
    }

    public function forceDelete(AuthUser $authUser, PatientPolicy $patientPolicy): bool
    {
        return $authUser->can('ForceDelete PatientPolicy');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny PatientPolicy');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny PatientPolicy');
    }

    public function replicate(AuthUser $authUser, PatientPolicy $patientPolicy): bool
    {
        return $authUser->can('Replicate PatientPolicy');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder PatientPolicy');
    }
}

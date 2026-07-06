<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\InsuranceClaim;

class InsuranceClaimPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny InsuranceClaim');
    }

    public function view(AuthUser $authUser, InsuranceClaim $insuranceClaim): bool
    {
        return $authUser->can('View InsuranceClaim');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create InsuranceClaim');
    }

    public function update(AuthUser $authUser, InsuranceClaim $insuranceClaim): bool
    {
        return $authUser->can('Update InsuranceClaim');
    }

    public function delete(AuthUser $authUser, InsuranceClaim $insuranceClaim): bool
    {
        return $authUser->can('Delete InsuranceClaim');
    }

    public function restore(AuthUser $authUser, InsuranceClaim $insuranceClaim): bool
    {
        return $authUser->can('Restore InsuranceClaim');
    }

    public function forceDelete(AuthUser $authUser, InsuranceClaim $insuranceClaim): bool
    {
        return $authUser->can('ForceDelete InsuranceClaim');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny InsuranceClaim');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny InsuranceClaim');
    }

    public function replicate(AuthUser $authUser, InsuranceClaim $insuranceClaim): bool
    {
        return $authUser->can('Replicate InsuranceClaim');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder InsuranceClaim');
    }
}

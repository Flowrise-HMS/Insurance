<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\NhisMedicine;

class NhisMedicinePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny NhisMedicine');
    }

    public function view(AuthUser $authUser, NhisMedicine $nhisMedicine): bool
    {
        return $authUser->can('View NhisMedicine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create NhisMedicine');
    }

    public function update(AuthUser $authUser, NhisMedicine $nhisMedicine): bool
    {
        return $authUser->can('Update NhisMedicine');
    }

    public function delete(AuthUser $authUser, NhisMedicine $nhisMedicine): bool
    {
        return $authUser->can('Delete NhisMedicine');
    }

    public function restore(AuthUser $authUser, NhisMedicine $nhisMedicine): bool
    {
        return $authUser->can('Restore NhisMedicine');
    }

    public function forceDelete(AuthUser $authUser, NhisMedicine $nhisMedicine): bool
    {
        return $authUser->can('ForceDelete NhisMedicine');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny NhisMedicine');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny NhisMedicine');
    }

    public function replicate(AuthUser $authUser, NhisMedicine $nhisMedicine): bool
    {
        return $authUser->can('Replicate NhisMedicine');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder NhisMedicine');
    }
}

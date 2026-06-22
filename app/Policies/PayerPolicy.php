<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\Payer;

class PayerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny Payer');
    }

    public function view(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('View Payer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create Payer');
    }

    public function update(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Update Payer');
    }

    public function delete(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Delete Payer');
    }

    public function restore(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Restore Payer');
    }

    public function forceDelete(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('ForceDelete Payer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny Payer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny Payer');
    }

    public function replicate(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Replicate Payer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder Payer');
    }
}

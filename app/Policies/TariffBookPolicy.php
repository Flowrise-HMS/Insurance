<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\TariffBook;

class TariffBookPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny TariffBook');
    }

    public function view(AuthUser $authUser, TariffBook $tariffBook): bool
    {
        return $authUser->can('View TariffBook');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create TariffBook');
    }

    public function update(AuthUser $authUser, TariffBook $tariffBook): bool
    {
        return $authUser->can('Update TariffBook');
    }

    public function delete(AuthUser $authUser, TariffBook $tariffBook): bool
    {
        return $authUser->can('Delete TariffBook');
    }

    public function restore(AuthUser $authUser, TariffBook $tariffBook): bool
    {
        return $authUser->can('Restore TariffBook');
    }

    public function forceDelete(AuthUser $authUser, TariffBook $tariffBook): bool
    {
        return $authUser->can('ForceDelete TariffBook');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny TariffBook');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny TariffBook');
    }

    public function replicate(AuthUser $authUser, TariffBook $tariffBook): bool
    {
        return $authUser->can('Replicate TariffBook');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder TariffBook');
    }
}

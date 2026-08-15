<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\GdrgIcdMap;

class GdrgIcdMapPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny GdrgIcdMap');
    }

    public function view(AuthUser $authUser, GdrgIcdMap $gdrgIcdMap): bool
    {
        return $authUser->can('View GdrgIcdMap');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create GdrgIcdMap');
    }

    public function update(AuthUser $authUser, GdrgIcdMap $gdrgIcdMap): bool
    {
        return $authUser->can('Update GdrgIcdMap');
    }

    public function delete(AuthUser $authUser, GdrgIcdMap $gdrgIcdMap): bool
    {
        return $authUser->can('Delete GdrgIcdMap');
    }

    public function restore(AuthUser $authUser, GdrgIcdMap $gdrgIcdMap): bool
    {
        return $authUser->can('Restore GdrgIcdMap');
    }

    public function forceDelete(AuthUser $authUser, GdrgIcdMap $gdrgIcdMap): bool
    {
        return $authUser->can('ForceDelete GdrgIcdMap');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny GdrgIcdMap');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny GdrgIcdMap');
    }

    public function replicate(AuthUser $authUser, GdrgIcdMap $gdrgIcdMap): bool
    {
        return $authUser->can('Replicate GdrgIcdMap');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder GdrgIcdMap');
    }
}

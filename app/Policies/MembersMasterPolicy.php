<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\MembersMaster;

class MembersMasterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny MembersMaster');
    }

    public function view(AuthUser $authUser, MembersMaster $membersMaster): bool
    {
        return $authUser->can('View MembersMaster');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create MembersMaster');
    }

    public function update(AuthUser $authUser, MembersMaster $membersMaster): bool
    {
        return $authUser->can('Update MembersMaster');
    }

    public function delete(AuthUser $authUser, MembersMaster $membersMaster): bool
    {
        return $authUser->can('Delete MembersMaster');
    }

    public function restore(AuthUser $authUser, MembersMaster $membersMaster): bool
    {
        return $authUser->can('Restore MembersMaster');
    }

    public function forceDelete(AuthUser $authUser, MembersMaster $membersMaster): bool
    {
        return $authUser->can('ForceDelete MembersMaster');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny MembersMaster');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny MembersMaster');
    }

    public function replicate(AuthUser $authUser, MembersMaster $membersMaster): bool
    {
        return $authUser->can('Replicate MembersMaster');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder MembersMaster');
    }
}

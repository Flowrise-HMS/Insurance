<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\ClaimBatch;

class ClaimBatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny ClaimBatch');
    }

    public function view(AuthUser $authUser, ClaimBatch $claimBatch): bool
    {
        return $authUser->can('View ClaimBatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create ClaimBatch');
    }

    public function update(AuthUser $authUser, ClaimBatch $claimBatch): bool
    {
        return $authUser->can('Update ClaimBatch');
    }

    public function delete(AuthUser $authUser, ClaimBatch $claimBatch): bool
    {
        return $authUser->can('Delete ClaimBatch');
    }

    public function restore(AuthUser $authUser, ClaimBatch $claimBatch): bool
    {
        return $authUser->can('Restore ClaimBatch');
    }

    public function forceDelete(AuthUser $authUser, ClaimBatch $claimBatch): bool
    {
        return $authUser->can('ForceDelete ClaimBatch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny ClaimBatch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny ClaimBatch');
    }

    public function replicate(AuthUser $authUser, ClaimBatch $claimBatch): bool
    {
        return $authUser->can('Replicate ClaimBatch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder ClaimBatch');
    }
}

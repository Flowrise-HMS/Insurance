<?php

declare(strict_types=1);

namespace Modules\Insurance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Insurance\Models\ProviderCredentialing;

class ProviderCredentialingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny ProviderCredentialing');
    }

    public function view(AuthUser $authUser, ProviderCredentialing $providerCredentialing): bool
    {
        return $authUser->can('View ProviderCredentialing');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create ProviderCredentialing');
    }

    public function update(AuthUser $authUser, ProviderCredentialing $providerCredentialing): bool
    {
        return $authUser->can('Update ProviderCredentialing');
    }

    public function delete(AuthUser $authUser, ProviderCredentialing $providerCredentialing): bool
    {
        return $authUser->can('Delete ProviderCredentialing');
    }

    public function restore(AuthUser $authUser, ProviderCredentialing $providerCredentialing): bool
    {
        return $authUser->can('Restore ProviderCredentialing');
    }

    public function forceDelete(AuthUser $authUser, ProviderCredentialing $providerCredentialing): bool
    {
        return $authUser->can('ForceDelete ProviderCredentialing');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny ProviderCredentialing');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny ProviderCredentialing');
    }

    public function replicate(AuthUser $authUser, ProviderCredentialing $providerCredentialing): bool
    {
        return $authUser->can('Replicate ProviderCredentialing');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder ProviderCredentialing');
    }
}

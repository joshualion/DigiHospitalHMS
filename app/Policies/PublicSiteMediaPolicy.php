<?php

namespace App\Policies;

use App\Models\PublicSiteMedia;
use App\Models\User;

class PublicSiteMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('website.manage_media') || $user->hasRole('superadmin');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PublicSiteMedia $media): bool
    {
        return $this->viewAny($user) && $media->usage_count === 0 && ($user->hasRole('superadmin') || $user->hospitalId() === $media->hospital_id);
    }
}

<?php

namespace App\Policies;

use App\Models\PublicSiteItem;
use App\Models\User;

class PublicSiteItemPolicy
{
    public function update(User $user, PublicSiteItem $item): bool
    {
        return ($user->can('website.edit') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $item->hospital_id);
    }

    public function publish(User $user, PublicSiteItem $item): bool
    {
        return ($user->can('website.publish') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $item->hospital_id);
    }

    public function unpublish(User $user, PublicSiteItem $item): bool
    {
        return ($user->can('website.unpublish') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $item->hospital_id);
    }
}

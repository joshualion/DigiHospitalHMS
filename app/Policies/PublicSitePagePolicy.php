<?php

namespace App\Policies;

use App\Models\PublicSitePage;
use App\Models\User;

class PublicSitePagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('website.view') || $user->hasRole('superadmin');
    }

    public function view(User $user, PublicSitePage $page): bool
    {
        return $this->viewAny($user) && ($user->hasRole('superadmin') || $user->hospitalId() === $page->hospital_id);
    }

    public function update(User $user, PublicSitePage $page): bool
    {
        return ($user->can('website.edit') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $page->hospital_id);
    }

    public function publish(User $user, PublicSitePage $page): bool
    {
        return ($user->can('website.publish') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $page->hospital_id);
    }

    public function unpublish(User $user, PublicSitePage $page): bool
    {
        return ($user->can('website.unpublish') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $page->hospital_id);
    }

    public function preview(User $user, PublicSitePage $page): bool
    {
        return $this->view($user, $page);
    }
}

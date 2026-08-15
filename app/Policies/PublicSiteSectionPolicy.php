<?php

namespace App\Policies;

use App\Models\PublicSiteSection;
use App\Models\User;

class PublicSiteSectionPolicy
{
    public function update(User $user, PublicSiteSection $section): bool
    {
        return ($user->can('website.edit') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $section->page->hospital_id);
    }

    public function publish(User $user, PublicSiteSection $section): bool
    {
        return ($user->can('website.publish') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $section->page->hospital_id);
    }
}

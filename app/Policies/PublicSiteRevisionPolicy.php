<?php

namespace App\Policies;

use App\Models\PublicSiteRevision;
use App\Models\User;

class PublicSiteRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('website.view_revisions') || $user->hasRole('superadmin');
    }

    public function restore(User $user, PublicSiteRevision $revision): bool
    {
        return ($user->can('website.restore_revision') || $user->hasRole('superadmin')) && ($user->hasRole('superadmin') || $user->hospitalId() === $revision->hospital_id);
    }
}

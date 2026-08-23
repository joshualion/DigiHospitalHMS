<?php

namespace App\Policies;

use App\Models\RadiologyStudy;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class RadiologyStudyPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'radiology.catalogue.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'radiology.catalogue.manage');
    }

    public function update(User $user, RadiologyStudy $study): bool
    {
        return $this->allowed($user, 'radiology.catalogue.manage', $study);
    }
}

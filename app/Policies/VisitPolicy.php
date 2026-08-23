<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class VisitPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'encounters.view');
    }

    public function view(User $user, Visit $visit): bool
    {
        return $this->allowed($user, 'encounters.view', $visit);
    }

    public function startEncounter(User $user, Visit $visit): bool
    {
        return $this->allowed($user, 'encounters.manage', $visit);
    }
}

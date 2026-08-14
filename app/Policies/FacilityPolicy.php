<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class FacilityPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'facilities.view');
    }

    public function view(User $user, Facility $facility): bool
    {
        return $this->allowed($user, 'facilities.view', $facility);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'facilities.create');
    }

    public function update(User $user, Facility $facility): bool
    {
        return $this->allowed($user, 'facilities.update', $facility);
    }

    public function activate(User $user, Facility $facility): bool
    {
        return $this->allowed($user, 'facilities.activate', $facility);
    }
}

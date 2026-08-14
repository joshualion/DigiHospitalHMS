<?php

namespace App\Policies;

use App\Models\StaffProfile;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class StaffProfilePolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'staff.view');
    }

    public function view(User $user, StaffProfile $staffProfile): bool
    {
        return $this->allowed($user, 'staff.view', $staffProfile);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'staff.invite');
    }

    public function update(User $user, StaffProfile $staffProfile): bool
    {
        return $this->allowed($user, 'staff.update', $staffProfile);
    }

    public function suspend(User $user, StaffProfile $staffProfile): bool
    {
        return $this->allowed($user, 'staff.suspend', $staffProfile);
    }

    public function assignFacilities(User $user, StaffProfile $staffProfile): bool
    {
        return $this->allowed($user, 'staff.assign-facilities', $staffProfile);
    }
}

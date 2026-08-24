<?php

namespace App\Policies;

use App\Models\BloodDonor;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class BloodDonorPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.view');
    }

    public function view(User $user, BloodDonor $donor): bool
    {
        return $this->allowed($user, 'blood-bank.view', $donor);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.donors.manage');
    }

    public function update(User $user, BloodDonor $donor): bool
    {
        return $this->allowed($user, 'blood-bank.donors.manage', $donor);
    }

    public function screen(User $user, BloodDonor $donor): bool
    {
        return $this->allowed($user, 'blood-bank.screening.manage', $donor);
    }
}

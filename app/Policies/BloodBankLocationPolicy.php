<?php

namespace App\Policies;

use App\Models\BloodBankLocation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class BloodBankLocationPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.catalogue.manage');
    }

    public function update(User $user, BloodBankLocation $location): bool
    {
        return $this->allowed($user, 'blood-bank.catalogue.manage', $location);
    }
}

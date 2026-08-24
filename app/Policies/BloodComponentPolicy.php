<?php

namespace App\Policies;

use App\Models\BloodComponent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class BloodComponentPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.view');
    }

    public function view(User $user, BloodComponent $component): bool
    {
        return $this->allowed($user, 'blood-bank.view', $component);
    }

    public function release(User $user, BloodComponent $component): bool
    {
        return $this->allowed($user, 'blood-bank.components.release', $component);
    }

    public function manage(User $user, BloodComponent $component): bool
    {
        return $this->allowed($user, 'blood-bank.components.manage', $component);
    }

    public function amend(User $user, BloodComponent $component): bool
    {
        return $this->allowed($user, 'blood-bank.amend', $component);
    }
}

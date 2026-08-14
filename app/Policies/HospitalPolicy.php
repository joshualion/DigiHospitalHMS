<?php

namespace App\Policies;

use App\Models\Hospital;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class HospitalPolicy
{
    use AuthorizesFoundationAccess;

    public function view(User $user, Hospital $hospital): bool
    {
        return $this->allowed($user, 'hospital.view', $hospital);
    }

    public function update(User $user, Hospital $hospital): bool
    {
        return $this->allowed($user, 'hospital.update', $hospital);
    }
}

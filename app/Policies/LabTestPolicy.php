<?php

namespace App\Policies;

use App\Models\LabTest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class LabTestPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'lab.catalogue.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'lab.catalogue.manage');
    }

    public function update(User $user, LabTest $test): bool
    {
        return $this->allowed($user, 'lab.catalogue.manage', $test);
    }
}

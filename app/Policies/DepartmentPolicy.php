<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class DepartmentPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->allowed($user, 'departments.view', $department);
    }

    public function manage(User $user, ?Department $department = null): bool
    {
        return $this->allowed($user, 'departments.manage', $department);
    }
}

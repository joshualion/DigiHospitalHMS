<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class UserPolicy
{
    use AuthorizesFoundationAccess;

    public function assignRoles(User $actor, User $subject): bool
    {
        if (! $this->allowed($actor, 'roles.assign')) {
            return false;
        }

        return $actor->hasRole('superadmin') || $actor->hospitalId() === $subject->hospitalId();
    }

    public function suspend(User $actor, User $subject): bool
    {
        if (! $this->allowed($actor, 'staff.suspend')) {
            return false;
        }

        return $actor->id !== $subject->id;
    }
}

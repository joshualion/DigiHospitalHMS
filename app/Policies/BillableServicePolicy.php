<?php

namespace App\Policies;

use App\Models\BillableService;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class BillableServicePolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'billing.catalogue.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'billing.catalogue.manage');
    }

    public function update(User $user, BillableService $service): bool
    {
        return $this->allowed($user, 'billing.catalogue.manage', $service);
    }
}

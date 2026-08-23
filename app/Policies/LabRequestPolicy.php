<?php

namespace App\Policies;

use App\Models\LabRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class LabRequestPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'lab.requests.view');
    }

    public function view(User $user, LabRequest $request): bool
    {
        return $this->allowed($user, 'lab.requests.view', $request);
    }

    public function order(User $user): bool
    {
        return $this->allowed($user, 'lab.requests.order');
    }

    public function collect(User $user, LabRequest $request): bool
    {
        return $this->allowed($user, 'lab.specimens.manage', $request);
    }

    public function result(User $user, LabRequest $request): bool
    {
        return $this->allowed($user, 'lab.results.enter', $request);
    }

    public function verify(User $user, LabRequest $request): bool
    {
        return $this->allowed($user, 'lab.results.verify', $request);
    }

    public function approve(User $user, LabRequest $request): bool
    {
        return $this->allowed($user, 'lab.results.approve', $request);
    }

    public function amend(User $user, LabRequest $request): bool
    {
        return $this->allowed($user, 'lab.results.amend', $request);
    }
}

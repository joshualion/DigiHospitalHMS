<?php

namespace App\Policies;

use App\Models\BloodRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class BloodRequestPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.requests.view');
    }

    public function view(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.requests.view', $request);
    }

    public function order(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.requests.order');
    }

    public function manage(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.requests.manage', $request);
    }

    public function collect(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.specimens.manage', $request);
    }

    public function test(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.compatibility.enter', $request);
    }

    public function authorizeTest(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.compatibility.authorize', $request);
    }

    public function reserve(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.reservations.manage', $request);
    }

    public function issue(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.issues.manage', $request);
    }

    public function emergencyRelease(User $user, BloodRequest $request): bool
    {
        return $this->allowed($user, 'blood-bank.emergency-release.authorize', $request);
    }
}

<?php

namespace App\Policies;

use App\Models\RadiologyRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class RadiologyRequestPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'radiology.requests.view');
    }

    public function view(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.requests.view', $request);
    }

    public function order(User $user): bool
    {
        return $this->allowed($user, 'radiology.requests.order');
    }

    public function schedule(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.schedule.manage', $request);
    }

    public function perform(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.perform', $request);
    }

    public function report(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.reports.write', $request);
    }

    public function verify(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.reports.verify', $request);
    }

    public function approve(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.reports.approve', $request);
    }

    public function amend(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.reports.amend', $request);
    }

    public function attachments(User $user, RadiologyRequest $request): bool
    {
        return $this->allowed($user, 'radiology.attachments.manage', $request);
    }
}

<?php

namespace App\Policies;

use App\Models\BloodDonation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class BloodDonationPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.view');
    }

    public function view(User $user, BloodDonation $donation): bool
    {
        return $this->allowed($user, 'blood-bank.view', $donation);
    }

    public function collect(User $user): bool
    {
        return $this->allowed($user, 'blood-bank.collections.manage');
    }

    public function test(User $user, BloodDonation $donation): bool
    {
        return $this->allowed($user, 'blood-bank.testing.manage', $donation);
    }

    public function verify(User $user, BloodDonation $donation): bool
    {
        return $this->allowed($user, 'blood-bank.testing.verify', $donation);
    }

    public function process(User $user, BloodDonation $donation): bool
    {
        return $this->allowed($user, 'blood-bank.components.manage', $donation);
    }
}

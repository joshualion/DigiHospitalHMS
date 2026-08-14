<?php

namespace App\Policies;

use App\Models\NumberSequence;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class NumberSequencePolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'numbering.manage');
    }

    public function update(User $user, NumberSequence $sequence): bool
    {
        return $this->allowed($user, 'numbering.manage', $sequence);
    }
}

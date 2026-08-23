<?php

namespace App\Policies;

use App\Models\RefundRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class RefundRequestPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'payments.view');
    }

    public function approve(User $user, RefundRequest $refund): bool
    {
        return $this->allowed($user, 'refunds.approve', $refund) && $user->id !== $refund->requested_by;
    }

    public function process(User $user, RefundRequest $refund): bool
    {
        return $this->allowed($user, 'refunds.process', $refund);
    }
}

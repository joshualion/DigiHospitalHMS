<?php

namespace App\Policies;

use App\Models\CashierShift;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class CashierShiftPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'payments.view');
    }

    public function view(User $user, CashierShift $shift): bool
    {
        return $this->allowed($user, 'payments.view', $shift);
    }

    public function open(User $user): bool
    {
        return $this->allowed($user, 'cashier-shifts.open');
    }

    public function close(User $user, CashierShift $shift): bool
    {
        return $this->allowed($user, 'cashier-shifts.close', $shift) && ($user->id === $shift->cashier_id || $user->can('cashier-shifts.review') || $user->hasRole('superadmin'));
    }

    public function review(User $user, CashierShift $shift): bool
    {
        return $this->allowed($user, 'cashier-shifts.review', $shift) && $user->id !== $shift->cashier_id;
    }
}

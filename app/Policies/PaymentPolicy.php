<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class PaymentPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->allowed($user, 'payments.view', $payment);
    }

    public function post(User $user): bool
    {
        return $this->allowed($user, 'payments.post');
    }

    public function allocate(User $user, Payment $payment): bool
    {
        return $this->allowed($user, 'payments.post', $payment);
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $this->allowed($user, 'payments.reverse', $payment);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->allowed($user, 'refunds.request', $payment);
    }
}

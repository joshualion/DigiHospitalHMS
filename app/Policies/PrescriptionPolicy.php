<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class PrescriptionPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'prescriptions.view');
    }

    public function view(User $user, Prescription $prescription): bool
    {
        return $this->allowed($user, 'prescriptions.view', $prescription);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'prescriptions.create');
    }

    public function sign(User $user, Prescription $prescription): bool
    {
        return $this->allowed($user, 'prescriptions.sign', $prescription);
    }

    public function review(User $user, Prescription $prescription): bool
    {
        return $this->allowed($user, 'prescriptions.review', $prescription);
    }

    public function dispense(User $user, Prescription $prescription): bool
    {
        return $this->allowed($user, 'prescriptions.dispense', $prescription);
    }

    public function reverse(User $user, Prescription $prescription): bool
    {
        return $this->allowed($user, 'prescriptions.reverse', $prescription);
    }
}

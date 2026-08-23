<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class PatientPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->allowed($user, 'patients.view', $patient);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'patients.register');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->allowed($user, 'patients.update', $patient);
    }

    public function changeStatus(User $user, Patient $patient): bool
    {
        return $this->allowed($user, 'patients.archive', $patient);
    }

    public function recordClinicalIdentity(User $user, Patient $patient): bool
    {
        return $this->allowed($user, 'patients.record-alerts', $patient);
    }
}

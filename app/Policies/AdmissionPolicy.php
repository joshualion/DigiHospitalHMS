<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class AdmissionPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'admissions.view');
    }

    public function view(User $user, Admission $admission): bool
    {
        return $this->allowed($user, 'admissions.view', $admission);
    }

    public function request(User $user, ?Admission $admission = null): bool
    {
        return $this->allowed($user, 'admissions.request', $admission);
    }

    public function approve(User $user, ?Admission $admission = null): bool
    {
        return $this->allowed($user, 'admissions.approve', $admission);
    }

    public function manage(User $user, ?Admission $admission = null): bool
    {
        return $this->allowed($user, 'admissions.manage', $admission);
    }

    public function discharge(User $user, Admission $admission): bool
    {
        return $this->allowed($user, 'admissions.discharge', $admission);
    }

    public function overrideDischarge(User $user, Admission $admission): bool
    {
        return $this->allowed($user, 'admissions.discharge.override', $admission);
    }
}

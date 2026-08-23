<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class AppointmentPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->allowed($user, 'appointments.view', $appointment);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'appointments.book');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->allowed($user, 'appointments.manage', $appointment);
    }
}

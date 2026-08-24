<?php

namespace App\Policies;

use App\Models\EmarSchedule;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class EmarSchedulePolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'emar.view');
    }

    public function view(User $user, EmarSchedule $schedule): bool
    {
        return $this->allowed($user, 'emar.view', $schedule);
    }

    public function administer(User $user, EmarSchedule $schedule): bool
    {
        return $this->allowed($user, 'emar.administer', $schedule);
    }

    public function amend(User $user, EmarSchedule $schedule): bool
    {
        return $this->allowed($user, 'emar.amend', $schedule);
    }
}

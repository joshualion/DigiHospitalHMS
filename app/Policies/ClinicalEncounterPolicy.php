<?php

namespace App\Policies;

use App\Models\ClinicalEncounter;
use App\Models\User;
use App\Models\Visit;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class ClinicalEncounterPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'encounters.view');
    }

    public function view(User $user, ClinicalEncounter $encounter): bool
    {
        return $this->allowed($user, 'encounters.view', $encounter);
    }

    public function create(User $user, ?Visit $visit = null): bool
    {
        return $this->allowed($user, 'encounters.manage', $visit);
    }

    public function manage(User $user, ClinicalEncounter $encounter): bool
    {
        return $this->allowed($user, 'encounters.manage', $encounter);
    }

    public function recordVitals(User $user, ClinicalEncounter $encounter): bool
    {
        return $this->allowed($user, 'vitals.record', $encounter);
    }

    public function sign(User $user, ClinicalEncounter $encounter): bool
    {
        return $this->allowed($user, 'encounters.sign', $encounter);
    }
}

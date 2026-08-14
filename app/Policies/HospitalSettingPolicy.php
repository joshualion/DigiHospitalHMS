<?php

namespace App\Policies;

use App\Models\HospitalSetting;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class HospitalSettingPolicy
{
    use AuthorizesFoundationAccess;

    public function view(User $user, HospitalSetting $setting): bool
    {
        return $this->allowed($user, 'settings.manage', $setting);
    }

    public function update(User $user, HospitalSetting $setting): bool
    {
        return $this->allowed($user, 'settings.manage', $setting);
    }
}

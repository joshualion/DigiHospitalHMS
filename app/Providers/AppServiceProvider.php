<?php

namespace App\Providers;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\StaffProfile;
use App\Models\User;
use App\Policies\AuditEventPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\FacilityPolicy;
use App\Policies\HospitalPolicy;
use App\Policies\HospitalSettingPolicy;
use App\Policies\NumberSequencePolicy;
use App\Policies\StaffProfilePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Facility::class, FacilityPolicy::class);
        Gate::policy(Hospital::class, HospitalPolicy::class);
        Gate::policy(HospitalSetting::class, HospitalSettingPolicy::class);
        Gate::policy(NumberSequence::class, NumberSequencePolicy::class);
        Gate::policy(StaffProfile::class, StaffProfilePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}

<?php

use App\Models\AppointmentType;
use App\Models\ClinicianSchedule;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

$email = getenv('PHASE2B_ADMIN_EMAIL') ?: 'phase2b-smoke@example.test';
$password = getenv('PHASE2B_ADMIN_PASSWORD') ?: 'Phase2BSmoke!';

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 2B Smoke Hospital'],
    [
        'display_name' => 'Phase 2B Smoke Hospital',
        'country' => 'Nigeria',
        'timezone' => 'Africa/Lagos',
        'default_currency' => 'NGN',
    ],
);

$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P2B'],
    [
        'name' => 'Phase 2B Clinic',
        'facility_type' => 'clinic',
        'city' => 'Lagos',
        'country' => 'Nigeria',
        'timezone' => 'Africa/Lagos',
        'is_primary' => true,
        'status' => 'active',
    ],
);

HospitalSetting::query()->updateOrCreate(
    ['hospital_id' => $hospital->id],
    ['default_facility_id' => $facility->id],
);

$department = Department::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P2B-OPD'],
    [
        'facility_id' => $facility->id,
        'name' => 'Phase 2B Outpatients',
        'category' => 'clinical',
        'status' => 'active',
        'display_order' => 1,
    ],
);

$user = User::query()->updateOrCreate(
    ['email' => $email],
    [
        'firstname' => 'Phase2B',
        'lastname' => 'Admin',
        'password' => Hash::make($password),
        'access_level' => 'admin',
        'status' => 'active',
    ],
);
$user->forceFill(['email_verified_at' => now()])->save();
$user->syncRoles(['hospital-admin']);
$user->givePermissionTo([
    'hospital.view',
    'patients.view',
    'patients.register',
    'appointments.view',
    'appointments.book',
    'appointments.manage',
    'appointment-requests.review',
    'queues.view',
    'queues.manage',
    'queues.prioritize',
]);

$staff = StaffProfile::query()->updateOrCreate(
    ['user_id' => $user->id],
    [
        'hospital_id' => $hospital->id,
        'staff_number' => 'P2B-ADMIN',
        'job_title' => 'Phase 2B Clinician',
        'staff_category' => 'clinical',
        'employment_status' => 'active',
        'is_active' => true,
    ],
);

AppointmentType::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P2B-CONSULT'],
    ['name' => 'Phase 2B Consultation', 'duration_minutes' => 30, 'is_active' => true],
);

ClinicianSchedule::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'staff_profile_id' => $staff->id, 'day_of_week' => 1],
    [
        'department_id' => $department->id,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'breaks' => [['starts_at' => '13:00', 'ends_at' => '14:00']],
        'is_active' => true,
    ],
);

NumberSequence::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'key' => 'patient_number'],
    ['label' => 'Patient', 'prefix' => 'P2B', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active'],
);

$patient = Patient::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'hospital_number' => 'P2B-SMOKE'],
    [
        'registration_facility_id' => $facility->id,
        'registered_by' => $user->id,
        'first_name' => 'Phase2B',
        'last_name' => 'Patient',
        'date_of_birth' => '1990-01-01',
        'sex' => 'female',
        'status' => 'active',
    ],
);
$patient->phone = '08020000000';
$patient->save();

echo "Phase 2B smoke setup ready for {$email}\n";

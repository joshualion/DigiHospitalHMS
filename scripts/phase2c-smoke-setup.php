<?php

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
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

$email = getenv('PHASE2C_ADMIN_EMAIL') ?: 'phase2c-smoke@example.test';
$password = getenv('PHASE2C_ADMIN_PASSWORD') ?: 'Phase2CSmoke!';
$unique = time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 2C Smoke Hospital'],
    ['display_name' => 'Phase 2C Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);

$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P2C'],
    ['name' => 'Phase 2C Clinic', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);

HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id]);

$department = Department::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P2C-OPD'],
    ['facility_id' => $facility->id, 'name' => 'Phase 2C Outpatients', 'category' => 'clinical', 'status' => 'active', 'display_order' => 1],
);

$user = User::query()->updateOrCreate(
    ['email' => $email],
    ['firstname' => 'Phase2C', 'lastname' => 'Clinician', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active'],
);
$user->forceFill(['email_verified_at' => now()])->save();
$user->syncRoles(['doctor']);
$user->givePermissionTo(['hospital.view', 'patients.view', 'appointments.view', 'appointments.manage', 'queues.view', 'queues.manage', 'encounters.view', 'encounters.manage', 'encounters.sign', 'vitals.record']);

$staff = StaffProfile::query()->updateOrCreate(
    ['user_id' => $user->id],
    ['hospital_id' => $hospital->id, 'staff_number' => 'P2C-DOC', 'job_title' => 'Phase 2C Clinician', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true],
);

$type = AppointmentType::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P2C-CONSULT'],
    ['name' => 'Phase 2C Consultation', 'duration_minutes' => 30, 'is_active' => true],
);

$patient = Patient::query()->create([
    'hospital_id' => $hospital->id,
    'registration_facility_id' => $facility->id,
    'registered_by' => $user->id,
    'hospital_number' => "P2C-{$unique}",
    'first_name' => 'Phase2C',
    'last_name' => 'Patient',
    'date_of_birth' => '1990-01-01',
    'sex' => 'female',
    'status' => 'active',
]);
$patient->phone = '08040000000';
$patient->save();

$appointment = Appointment::query()->create([
    'hospital_id' => $hospital->id,
    'facility_id' => $facility->id,
    'department_id' => $department->id,
    'patient_id' => $patient->id,
    'clinician_id' => $staff->id,
    'appointment_type_id' => $type->id,
    'starts_at' => now(),
    'ends_at' => now()->addMinutes(30),
    'status' => 'checked_in',
    'source' => 'staff',
    'booked_by' => $user->id,
]);

$visit = Visit::query()->create([
    'hospital_id' => $hospital->id,
    'facility_id' => $facility->id,
    'department_id' => $department->id,
    'patient_id' => $patient->id,
    'clinician_id' => $staff->id,
    'appointment_id' => $appointment->id,
    'source' => 'appointment',
    'status' => 'checked_in',
    'checked_in_by' => $user->id,
    'checked_in_at' => now(),
]);

QueueEntry::query()->create([
    'hospital_id' => $hospital->id,
    'facility_id' => $facility->id,
    'department_id' => $department->id,
    'visit_id' => $visit->id,
    'patient_id' => $patient->id,
    'clinician_id' => $staff->id,
    'queue_date' => now()->toDateString(),
    'queue_number' => $unique,
    'priority' => 3,
    'status' => 'called',
    'created_by' => $user->id,
]);

echo "Phase 2C smoke setup ready for {$email} visit {$visit->id}\n";

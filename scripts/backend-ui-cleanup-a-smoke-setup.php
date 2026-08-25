<?php

use App\Models\Admission;
use App\Models\AppointmentType;
use App\Models\Bed;
use App\Models\BedClass;
use App\Models\ClinicianSchedule;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Ward;
use App\Services\AdmissionWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

$password = getenv('CLEANUP_A_PASSWORD') ?: 'CleanupASmoke!';
$suffix = (string) now()->timestamp;
$hospital = Hospital::query()->firstOrCreate(['legal_name' => 'Cleanup A Smoke Hospital'], ['display_name' => 'Cleanup A Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNA'], ['name' => 'Cleanup A Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['patient_number', 'Patient', 'PAT'], ['admission_number', 'Admission', 'ADM']] as [$key, $label, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$permissions = ['hospital.view', 'facilities.view', 'facilities.create', 'facilities.update', 'facilities.activate', 'departments.view', 'departments.manage', 'staff.view', 'staff.invite', 'staff.update', 'staff.suspend', 'patients.view', 'patients.register', 'patients.update', 'patients.archive', 'patients.record-alerts', 'appointments.view', 'appointments.book', 'appointments.manage', 'appointment-requests.review', 'queues.view', 'queues.manage', 'admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'admissions.discharge', 'admissions.discharge.override'];
$admin = User::query()->updateOrCreate(['email' => 'cleanup-a-admin@example.test'], ['firstname' => 'Cleanup', 'lastname' => 'Admin', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
$admin->forceFill(['email_verified_at' => now()])->save();
$admin->syncRoles(['admin']);
$admin->givePermissionTo($permissions);
$staff = StaffProfile::query()->updateOrCreate(['user_id' => $admin->id], ['hospital_id' => $hospital->id, 'staff_number' => 'CLNA-ADMIN', 'job_title' => 'Administrator', 'staff_category' => 'administrative', 'employment_status' => 'active', 'is_active' => true]);

$department = Department::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNA-DEP'], ['facility_id' => $facility->id, 'name' => 'Cleanup A Department', 'category' => 'clinical', 'status' => 'active']);
$type = AppointmentType::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNA-CONSULT'], ['name' => 'Cleanup A Consult', 'duration_minutes' => 30, 'is_active' => true]);
ClinicianSchedule::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'staff_profile_id' => $staff->id, 'day_of_week' => 1], ['department_id' => $department->id, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true]);

$patient = Patient::query()->firstOrCreate(['hospital_id' => $hospital->id, 'hospital_number' => 'CLNA-PAT-'.$suffix], ['registration_facility_id' => $facility->id, 'registered_by' => $admin->id, 'status' => 'active', 'first_name' => 'Cleanup', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female']);
$class = BedClass::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNA-CLASS'], ['name' => 'Cleanup A Class', 'is_active' => true]);
$ward = Ward::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNA-WARD'], ['facility_id' => $facility->id, 'department_id' => $department->id, 'name' => 'Cleanup A Ward', 'status' => 'active']);
Bed::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNA-BED'], ['facility_id' => $facility->id, 'ward_id' => $ward->id, 'bed_class_id' => $class->id, 'label' => 'Cleanup Bed 1', 'state' => 'available']);

if (! Admission::where('hospital_id', $hospital->id)->where('patient_id', $patient->id)->exists()) {
    app(AdmissionWorkflowService::class)->request(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'attending_clinician_id' => $staff->id, 'department_id' => $department->id, 'reason' => 'Cleanup A smoke request'], $admin);
}

File::ensureDirectoryExists(storage_path('app/backend-ui-cleanup-a'));
File::put(storage_path('app/backend-ui-cleanup-a/context.json'), json_encode(['email' => $admin->email, 'password' => $password], JSON_PRETTY_PRINT));
echo "Backend UI Cleanup A smoke setup ready.\n";
